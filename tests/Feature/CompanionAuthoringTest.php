<?php
namespace Tests\Feature;

use App\Models\{Story, User, StoryCharacter};
use App\Services\{CompanionChanges, CompanionEditorActions};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanionAuthoringTest extends TestCase
{
    use RefreshDatabase;
    private Story $story;
    private $page;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create(); $this->actingAs($user);
        $this->story = Story::create(['user_id'=>$user->id,'title'=>'House','premise'=>'Explore']);
        $this->page = $this->story->pages()->create(['position'=>1,'title'=>'Room','visual_document'=>['visuals'=>[]]]);
    }

    private function proposal(array $changes)
    {
        $this->story->refresh();
        $proposal = ['changes'=>$changes];
        $proposal['_review'] = app(CompanionChanges::class)->prepare($this->story,$proposal);
        return $this->story->messages()->create(['role'=>'assistant','content'=>'Review these changes','revision'=>$this->story->revision,'proposal'=>$proposal]);
    }

    private function apply($message, array $selected = [0])
    {
        return $this->postJson(route('companion.changes',[$this->story,$message]),['selected'=>$selected]);
    }

    public function test_character_creation_is_reviewed_then_saves_profile_and_views(): void
    {
        $asset=$this->story->assets()->create(['name'=>'Visitor','path'=>'visitor.png','mime'=>'image/png','bytes'=>1,'role'=>'scene']);
        $message=$this->proposal([['scope'=>'create_character','fields'=>['name'=>'The Visitor','description'=>'Brown coat','views'=>[['label'=>'Rear','asset_id'=>$asset->id]]]]]);
        $this->assertDatabaseCount('story_characters',0);
        $this->apply($message)->assertOk();
        $character=StoryCharacter::firstOrFail();
        $this->assertSame($asset->id,$character->views[0]['asset_id']);
        $this->assertSame('character',$asset->fresh()->role);
        $edit=$this->proposal([['scope'=>'character','character_id'=>$character->id,'fields'=>['description'=>'Brown coat and red scarf']]]);
        $this->apply($edit)->assertOk();
        $this->assertSame('The Visitor',$character->fresh()->name);
        $this->assertCount(1,$character->fresh()->views);
        $stale=$this->proposal([['scope'=>'character','character_id'=>$character->id,'fields'=>['name'=>'Visitor']]]);
        $character->refresh()->increment('revision');
        $this->apply($stale)->assertUnprocessable();
    }

    public function test_new_scene_and_new_flag_can_be_applied_as_one_review(): void
    {
        $message=$this->proposal([
            ['scope'=>'story','fields'=>['game_flags'=>[['key'=>'door_open','label'=>'Door open','initial'=>false]]]],
            ['scope'=>'create_visual','page_id'=>$this->page->id,'fields'=>['name'=>'Porch','kind'=>'scene','hotspots'=>[
                ['label'=>'Door','description'=>'Pull the handle','action'=>'examine','x'=>.1,'y'=>.1,'width'=>.2,'height'=>.3,'flag_effects'=>[['key'=>'door_open','value'=>true]],'click_behavior'=>'text','action_preset'=>'custom_single','action_label'=>'Pull']
            ]]],
        ]);
        $this->apply($message,[1])->assertUnprocessable(); // Cannot apply dependent event without its flag.
        $this->assertSame([],$this->page->fresh()->visual_document['visuals']);
        $this->apply($message,[0,1])->assertOk();
        $area=$this->page->fresh()->visual_document['visuals'][0]['hotspots'][0];
        $this->assertSame('Pull',$area['action_label']);
        $this->assertSame('door_open',$area['flag_effects'][0]['key']);
    }

    public function test_editor_bridge_uses_manual_scene_validation_and_rejects_replay(): void
    {
        $visual=['id'=>(string)Str::uuid(),'name'=>'Hall','kind'=>'scene','asset_id'=>null,'hotspots'=>[]];
        $message=$this->proposal([['scope'=>'editor_action','fields'=>['operation'=>'scene.save','targets'=>['page'=>$this->page->id],'input'=>['document'=>['visuals'=>[$visual]]]]]]);
        $this->apply($message)->assertOk();
        $this->assertSame('Hall',$this->page->fresh()->visual_document['visuals'][0]['name']);
        $this->apply($message)->assertUnprocessable();
        try {
            $this->proposal([['scope'=>'editor_action','fields'=>['operation'=>'scene.save','targets'=>['page'=>$this->page->id],'input'=>['document'=>['visuals'=>[['name'=>'Missing identity']]]]]]]);
            $this->fail('Expected invalid document rejection');
        } catch (\Illuminate\Validation\ValidationException $e) {$this->assertNotEmpty($e->errors());}
        $this->assertSame('Hall',$this->page->fresh()->visual_document['visuals'][0]['name']);
    }

    public function test_editor_bridge_rejects_foreign_targets_and_unlisted_commands(): void
    {
        $other=Story::create(['user_id'=>$this->story->user_id,'title'=>'Other','premise'=>'Other']);
        $page=$other->pages()->create(['position'=>1,'title'=>'Other']);
        foreach ([['operation'=>'scene.save','targets'=>['page'=>$page->id],'input'=>[]],['operation'=>'account.delete','targets'=>[],'input'=>[]]] as $fields) {
            try {app(CompanionEditorActions::class)->prepare($this->story,$fields);$this->fail('Expected rejection');}
            catch (\Illuminate\Validation\ValidationException $e) {$this->assertNotEmpty($e->errors());}
        }
    }

    public function test_stale_manual_target_is_not_overwritten(): void
    {
        $message=$this->proposal([['scope'=>'editor_action','fields'=>['operation'=>'scene.save','targets'=>['page'=>$this->page->id],'input'=>['document'=>['visuals'=>[]]]]]]);
        $this->page->increment('visual_revision');
        $this->apply($message)->assertUnprocessable();
    }

    public function test_reference_video_command_saves_draft_without_starting_generation(): void
    {
        $asset=$this->story->assets()->create(['name'=>'Room','path'=>'room.png','mime'=>'image/png','bytes'=>1,'role'=>'scene']);
        $message=$this->proposal([['scope'=>'video','fields'=>['name'=>'Approach chest','prompt'=>'The person in <Picture 1> approaches the chest.','engine'=>'minimax_h3_reference','parameters'=>[],'first_asset_id'=>$asset->id,'reference_asset_ids'=>[$asset->id]]]]);
        $this->apply($message)->assertOk();
        $this->assertDatabaseHas('video_generations',['story_id'=>$this->story->id,'status'=>'draft','engine'=>'minimax_h3_reference']);
    }

    public function test_manual_commands_prepare_character_view_edit_image_and_reorder_pages(): void
    {
        $character=app(\App\Services\CharacterProfiles::class)->save($this->story,['name'=>'Visitor','views'=>[],'revision'=>0]);
        $message=$this->proposal([['scope'=>'editor_action','fields'=>['operation'=>'character.view','targets'=>['character'=>$character->id],'input'=>['page_id'=>$this->page->id,'label'=>'Rear']]]]);
        $this->apply($message)->assertOk();
        $view=$character->fresh()->views[0];
        $this->assertSame('Rear',$view['label']);
        $image=\App\Models\ImageGeneration::findOrFail($view['image_id']);
        $this->assertSame('draft',$image->status);
        $message=$this->proposal([['scope'=>'editor_action','fields'=>['operation'=>'image.draft','targets'=>['page'=>$this->page->id],'input'=>['draft_id'=>$image->id,'name'=>'Rear view','prompt'=>'Rear full body with brown coat','references'=>[],'settings'=>[]]]]]);
        $this->apply($message)->assertOk();
        $this->assertSame('Rear full body with brown coat',$image->fresh()->payload['prompt']);
        $second=$this->story->pages()->create(['position'=>2,'title'=>'Hall']);
        $message=$this->proposal([['scope'=>'editor_action','fields'=>['operation'=>'page.structure','targets'=>[],'input'=>['action'=>'up','page_id'=>$second->id]]]]);
        $this->apply($message)->assertOk();
        $this->assertSame(1,$second->fresh()->position);
    }

    public function test_every_advertised_editor_command_has_a_public_handler(): void
    {
        $commands=(new \ReflectionClass(CompanionEditorActions::class))->getConstant('COMMANDS');
        foreach($commands as $name=>[$controller,$method]) {
            $handler=new \ReflectionMethod('App\\Http\\Controllers\\'.$controller,$method);
            $this->assertTrue($handler->isPublic(),$name);
        }
    }
}
