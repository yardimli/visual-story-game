<?php
namespace Tests\Feature;
use Tests\TestCase;use App\Models\{User,Story,ImageGeneration};use App\Services\{SceneDocument,CompanionChanges};use Illuminate\Foundation\Testing\RefreshDatabase;use Illuminate\Support\Str;
class StoryStateTest extends TestCase {
 use RefreshDatabase;
 private function fixture():array{$u=User::factory()->create();$this->actingAs($u);$s=Story::create(['user_id'=>$u->id,'title'=>'State','premise'=>'Stone']);$p=$s->pages()->create(['position'=>1,'title'=>'Garden']);return [$u,$s->fresh(),$p->fresh()];}
 public function test_only_owned_ungenerated_drafts_can_be_permanently_deleted():void{
  [$u,$s,$p]=$this->fixture();
  $id=$this->postJson(route('images.draft',[$s,$p]),['name'=>'Unused','prompt'=>'A room'])->assertOk()->json('id');
  $s->update(['image_order'=>[$id]]);
  $this->actingAs(User::factory()->create())->deleteJson(route('images.draft.delete',[$s,$id]))->assertNotFound();
  $this->actingAs($u);
  foreach(['queued','running','completed','failed'] as $status){ImageGeneration::whereKey($id)->update(['status'=>$status]);$this->deleteJson(route('images.draft.delete',[$s,$id]))->assertUnprocessable();}
  ImageGeneration::whereKey($id)->update(['status'=>'draft']);
  $this->deleteJson(route('images.draft.delete',[$s,$id]))->assertOk();
  $this->assertDatabaseMissing('image_generations',['id'=>$id]);$this->assertSame([],$s->fresh()->image_order);
 }
 public function test_flags_validate_links_and_block_stale_or_foreign_changes():void{
  [$u,$s,$p]=$this->fixture();$flags=[['key'=>'stone_opened','label'=>'Stone open','initial'=>false]];
  $this->putJson(route('flags.update',$s),['revision'=>1,'flags'=>$flags])->assertOk();
  $scene=['id'=>(string)Str::uuid(),'kind'=>'scene','name'=>'Stone','asset_id'=>null,'hotspots'=>[['id'=>(string)Str::uuid(),'label'=>'Open','action'=>'examine','description'=>'Open','x'=>0,'y'=>0,'width'=>.2,'height'=>.2,'overlay'=>false,'cursor'=>'help','availability_enabled'=>false,'flag_effects'=>[['key'=>'stone_opened','value'=>true]],'flag_conditions'=>[['key'=>'stone_opened','value'=>false]]]]];
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>1,'document'=>['visuals'=>[$scene]]])->assertOk();
  $this->assertSame('help',$p->fresh()->visual_document['visuals'][0]['hotspots'][0]['cursor']);
  $this->assertFalse($p->fresh()->visual_document['visuals'][0]['hotspots'][0]['availability_enabled']);
  $this->assertTrue($p->fresh()->visual_document['visuals'][0]['hotspots'][0]['flag_effects'][0]['value']);
  $this->putJson(route('flags.update',$s),['revision'=>2,'flags'=>[]])->assertUnprocessable();
  $this->putJson(route('flags.update',$s),['revision'=>1,'flags'=>$flags])->assertUnprocessable();
  $scene['hotspots'][0]['flag_effects'][0]['key']='unknown';$this->putJson(route('visuals.update',[$s,$p]),['revision'=>2,'document'=>['visuals'=>[$scene]]])->assertUnprocessable();
  $this->actingAs(User::factory()->create())->getJson(route('flags.show',$s))->assertNotFound();
 }
 public function test_companion_can_create_flags_and_scene_conditions_together():void{
  [$u,$s,$p]=$this->fixture();$scene=['id'=>(string)Str::uuid(),'kind'=>'scene','name'=>'Stone','asset_id'=>null,'hotspots'=>[]];$p->update(['visual_document'=>['visuals'=>[$scene]]]);
  $proposal=['changes'=>[['scope'=>'story','fields'=>['game_flags'=>[['key'=>'opened','label'=>'Opened','initial'=>false]]]],['scope'=>'visual','page_id'=>$p->id,'visual_id'=>$scene['id'],'fields'=>['skip_rules'=>[['id'=>(string)Str::uuid(),'object_ids'=>[],'flag_conditions'=>[['key'=>'opened','value'=>true]],'target_page_id'=>$p->id,'target_scene_id'=>$scene['id']]]]]]];
  $proposal['_review']=app(CompanionChanges::class)->prepare($s,$proposal);$m=$s->messages()->create(['user_id'=>$u->id,'role'=>'assistant','content'=>'Review','proposal'=>$proposal]);
  $this->postJson(route('companion.changes',[$s,$m]),['selected'=>[0,1]])->assertOk();$this->assertSame('opened',$s->fresh()->game_flags[0]['key']);
 }
 public function test_all_page_images_and_presentation_order_are_story_scoped():void{
  [$u,$s,$p]=$this->fixture();$second=$s->pages()->create(['position'=>2,'title'=>'Porch']);
  $jobs=[];foreach([$p,$second] as $page)$jobs[]=ImageGeneration::create(['user_id'=>$u->id,'story_id'=>$s->id,'story_page_id'=>$page->id,'visual_id'=>(string)Str::uuid(),'kind'=>'scene','name'=>'Image','status'=>'draft','request_key'=>(string)Str::uuid(),'payload'=>['prompt'=>'Test'],'references'=>[],'pricing'=>[]]);
  $this->getJson(route('visuals.show',[$s,$p,'all_images'=>1]))->assertOk()->assertJsonCount(2,'jobs');
  $this->getJson(route('visuals.show',[$s,$p]))->assertJsonCount(2,'jobs');
  $this->postJson(route('images.draft',[$s,$p]),['draft_id'=>$jobs[1]->id,'name'=>'Shared image','prompt'=>'Updated from another page'])->assertOk();
  $this->assertSame('Shared image',$jobs[1]->fresh()->name);
  $this->putJson(route('images.order',$s),['order'=>[$jobs[0]->id,$jobs[1]->id]])->assertOk();$this->assertSame([$jobs[0]->id,$jobs[1]->id],$s->fresh()->image_order);
  $this->putJson(route('images.order',$s),['order'=>[999]])->assertUnprocessable();$this->assertNull($p->fresh()->visual_document);
 }
}
