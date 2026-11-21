<?php
namespace Tests\Feature;
use App\Models\{User,Story,ChatMessage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Storage,DB};
use Tests\TestCase;
class StudioTest extends TestCase {
    use RefreshDatabase;
    private function story(User $u): Story {
        $story=Story::create(['user_id'=>$u->id,'title'=>'Workshop','premise'=>'Repair a radio.']);
        $story->pages()->create(['position'=>1,'title'=>'Arrival']);
        return $story;
    }
    public function test_editor_tabs_and_dashboard_history_are_scoped_to_owner(): void {
        $u=User::factory()->create();$s=$this->story($u);$p=$s->pages()->first();$this->actingAs($u);
        $this->get(route('stories.edit',$s))->assertOk()->assertSeeInOrder(['Page plan','Scenes','Image','Video','Audio','Inventory','Story bible'])->assertSee('Insert blank page after')->assertDontSee('A shared visual language')->assertDontSee("switchTab('assets')", false)->assertDontSee('Completion condition')->assertDontSee('storyboard-panel')->assertDontSee('Linear progression');
        $this->get(route('images.studio',[$s,$p]))->assertOk()->assertSeeInOrder(['Page plan','Scenes','aria-current="page"','Image','Video','Audio','Inventory','Story bible'],false);
        $this->get(route('dashboard',['history'=>$s->id]))->assertOk()->assertSee('History · Workshop')->assertSee('Your first save will create a revision.');
        $this->actingAs(User::factory()->create())->get(route('dashboard',['history'=>$s->id]))->assertNotFound();
    }
    public function test_inline_reference_upload_returns_library_thumbnails_without_generating(): void {
        Storage::fake('local');$u=User::factory()->create();$s=$this->story($u);
        $response=$this->actingAs($u)->postJson(route('assets.store',$s),['images'=>[UploadedFile::fake()->image('note.png'),UploadedFile::fake()->image('stone.png')],'role'=>'reference']);
        $response->assertCreated()->assertJsonCount(2,'assets')->assertJsonPath('assets.0.name','note.png');
        $assets=$s->assets()->get();$this->assertCount(2,$assets);
        foreach($assets as $asset){Storage::disk('local')->assertExists($asset->path);$this->get(route('assets.show',$asset))->assertOk();}
        $this->assertDatabaseCount('image_generations',0);
        $this->actingAs(User::factory()->create())->postJson(route('assets.store',$s),['images'=>[UploadedFile::fake()->image('other.png')],'role'=>'reference'])->assertNotFound();
    }
    public function test_new_stories_need_only_title_and_start_with_one_blank_page(): void {
        $this->actingAs(User::factory()->create());
        foreach ([['title'=>'New story'], ['title'=>'Another','page_count'=>50,'premise'=>'Ignored','genre'=>'Ignored']] as $input) {
            $this->post(route('stories.store'),$input)->assertRedirect();
            $story=Story::latest('id')->first();
            $this->assertSame('', $story->premise);
            $this->assertSame('', $story->genre);
            $this->assertSame(1,$story->pages()->count());
            $this->assertEmpty($story->pages()->first()->narrative);
            $this->putJson(route('stories.update',$story),['revision'=>$story->revision,'title'=>'Renamed','premise'=>'','genre'=>'','language'=>'English'])->assertOk();
        }
        $this->post(route('stories.store'),['title'=>''])->assertSessionHasErrors('title');
    }
    public function test_story_cover_is_scoped_to_project_and_shown_on_dashboard(): void {
        $u=User::factory()->create(); $s=$this->story($u);
        $asset=$s->assets()->create(['name'=>'Cover','path'=>'cover.png','mime'=>'image/png','bytes'=>10,'role'=>'generated']);
        $data=['revision'=>0,'title'=>$s->title,'premise'=>$s->premise,'genre'=>'','language'=>'English','cover_asset_id'=>$asset->id];
        $data['revision']=$s->fresh()->revision;
        $this->actingAs($u)->putJson(route('stories.update',$s),$data)->assertOk();
        $this->assertSame($asset->id,$s->fresh()->cover_asset_id);
        $this->get(route('dashboard'))->assertSee(route('assets.show',$asset));
        $other=$this->story($u);
        $data['revision']=$other->fresh()->revision;
        $this->putJson(route('stories.update',$other),$data)->assertUnprocessable()->assertJsonValidationErrors('cover_asset_id');
        $data['revision']=$s->fresh()->revision; $data['cover_asset_id']=null;
        $this->putJson(route('stories.update',$s),$data)->assertOk();
        $this->assertNull($s->fresh()->cover_asset_id);
    }
    public function test_editor_and_settings_render(): void {
        $u=User::factory()->create();$s=$this->story($u);
        $this->actingAs($u)->get(route('dashboard'))->assertOk()->assertSee('Workshop');
        $this->get(route('stories.edit',$s))->assertOk()->assertSee('Creative companion');
        $this->get(route('ai.edit'))->assertOk()->assertSee('AI & usage',false);
    }
    public function test_other_users_cannot_read_or_edit_a_story(): void {
        $s=$this->story(User::factory()->create());
        $this->actingAs(User::factory()->create())->get(route('stories.edit',$s))->assertNotFound();
        $this->put(route('stories.update',$s),[])->assertNotFound();
        $this->post(route('chat.store',$s),[])->assertNotFound();
    }
    public function test_page_save_versions_and_rejects_stale_writes(): void {
        $u=User::factory()->create();$s=$this->story($u);
        $this->actingAs($u)->put(route('pages.update',[$s,$s->pages->first()]),['revision'=>1,'title'=>'Changed'])->assertRedirect();
        $this->assertSame(2,$s->fresh()->revision);
        $this->assertSame('Arrival',$s->revisions->first()->snapshot['pages'][0]['title']);
        $this->put(route('pages.update',[$s,$s->pages->first()]),['revision'=>1,'title'=>'Stale'])->assertSessionHasErrors('revision');
    }
    public function test_editor_has_no_full_plan_approval_gate(): void {
        $u=User::factory()->create();$s=$this->story($u);
        $this->actingAs($u)->get(route('stories.edit',$s))->assertOk()->assertDontSee('Approve full plan');
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Plan approved');
        $this->post('/stories/'.$s->id.'/approve',['revision'=>1])->assertNotFound();
    }
    public function test_key_is_encrypted_hidden_and_not_flashed_on_validation_error(): void {
        $u=User::factory()->create();$key='sk-or-v1-personal-secret';
        $this->actingAs($u)->put(route('ai.update'),['openrouter_key'=>$key,'current_password'=>'password'])->assertRedirect();
        $this->assertSame($key,$u->fresh()->openrouter_key);
        $this->assertNotSame($key,DB::table('users')->where('id',$u->id)->value('openrouter_key'));
        $this->assertArrayNotHasKey('openrouter_key',$u->fresh()->toArray());
        $this->put(route('ai.update'),['openrouter_key'=>$key,'current_password'=>'wrong'])->assertSessionHasErrors('current_password')->assertSessionMissing('_old_input.openrouter_key');
        $this->delete(route('ai.destroy'),['current_password'=>'password'])->assertRedirect();
        $this->assertNull($u->fresh()->openrouter_key);
    }
    public function test_uploaded_references_are_private(): void {
        Storage::fake('local');$u=User::factory()->create();$s=$this->story($u);
        $this->actingAs($u)->post(route('assets.store',$s),['images'=>[UploadedFile::fake()->image('reference.png')],'role'=>'character'])->assertRedirect();
        $asset=$s->assets()->first();Storage::disk('local')->assertExists($asset->path);
        $this->get(route('assets.show',$asset))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('assets.show',$asset))->assertNotFound();
    }
    public function test_assistant_proposal_is_reviewed_and_rejects_stale_apply(): void {
        $u=User::factory()->create();$s=$this->story($u);
        $m=$s->messages()->create(['role'=>'assistant','content'=>'Draft','revision'=>1,'proposal'=>['bible'=>'New bible']]);
        $this->assertNull($s->bible);
        $this->actingAs($u)->post(route('stories.apply',[$s,$m]))->assertRedirect();
        $this->assertSame('New bible',$s->fresh()->bible);
        $this->post(route('stories.apply',[$s,$m]))->assertSessionHasErrors('revision');
        $rev=$s->revisions()->first();
        $this->post(route('stories.restore',[$s,$rev]),['revision'=>2])->assertRedirect();
        $this->assertNull($s->fresh()->bible);
    }
    public function test_proposal_cannot_move_a_page_into_another_story(): void {
        $u=User::factory()->create();$s=$this->story($u);$other=$this->story(User::factory()->create());
        $fields=array_fill_keys(['narrative','scene_prompt','video_prompt','interaction_plan','completion'],'Draft');
        $m=$s->messages()->create(['role'=>'assistant','content'=>'Draft','revision'=>1,'proposal'=>['pages'=>[array_merge($fields,['position'=>1,'title'=>'Moved','story_id'=>$other->id])]]]);
        $this->actingAs($u)->post(route('stories.apply',[$s,$m]))->assertSessionHasErrors('pages.0');
        $this->assertSame($s->id,$s->pages->first()->fresh()->story_id);
    }
    public function test_page_route_cannot_modify_another_story_page(): void {
        $u=User::factory()->create();$s=$this->story($u);$other=$this->story(User::factory()->create());
        $this->actingAs($u)->put(route('pages.update',[$s,$other->pages->first()]),['revision'=>1,'title'=>'Wrong'])->assertNotFound();
    }
    public function test_email_control_characters_are_rejected(): void {
        $this->post('/register',['name'=>'Test','email'=>"test@example.test\r\nBcc: other@example.test",'password'=>'password','password_confirmation'=>'password'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users',0);
    }
}
