<?php
namespace Tests\Feature;
use Tests\TestCase;use App\Models\{User,Story,ImageGeneration};use App\Services\{SceneDocument,CompanionChanges};use Illuminate\Foundation\Testing\RefreshDatabase;use Illuminate\Support\Str;
class StoryStateTest extends TestCase {
 use RefreshDatabase;
 private function fixture():array{$u=User::factory()->create();$this->actingAs($u);$s=Story::create(['user_id'=>$u->id,'title'=>'State','premise'=>'Stone']);$p=$s->pages()->create(['position'=>1,'title'=>'Garden']);return [$u,$s->fresh(),$p->fresh()];}
 public function test_flag_deletion_reviews_and_removes_nested_uses_atomically():void {
  [$u,$s,$p]=$this->fixture();$s->update(['game_flags'=>[['key'=>'opened','label'=>'Opened','initial'=>false],['key'=>'keep','label'=>'Keep','initial'=>false]]]);
  $condition=[['key'=>'opened','value'=>true],['key'=>'keep','value'=>false]];
  $doc=['visuals'=>[['id'=>(string)Str::uuid(),'kind'=>'scene','name'=>'Stone','hotspots'=>[['label'=>'Lid','flag_conditions'=>$condition,'flag_effects'=>$condition,'state_flag'=>'opened','action_requirements'=>['flag_conditions'=>$condition],'inventory_rules'=>[['flag_effects'=>$condition]]]],'skip_rules'=>[['flag_conditions'=>[['key'=>'opened','value'=>true]],'object_ids'=>[]]]]]];
  $p->update(['visual_document'=>$doc]);$other=$s->pages()->create(['position'=>2,'title'=>'Hall','visual_document'=>['visuals'=>[]]])->fresh();$revision=$p->visual_revision;
  $usage=$this->getJson(route('flags.uses',[$s,'opened']))->assertOk()->assertJsonCount(6,'uses')->json();$this->assertStringContainsString('Garden',$usage['uses'][0]['location']);$this->assertStringContainsString('Lid',$usage['uses'][0]['location']);
  $this->deleteJson(route('flags.delete',[$s,'opened']),['revision'=>$usage['revision'],'fingerprint'=>'wrong'])->assertUnprocessable();$this->assertCount(2,$s->fresh()->game_flags);
  $this->actingAs(User::factory()->create())->deleteJson(route('flags.delete',[$s,'opened']),$usage)->assertNotFound();$this->actingAs($u);
  $this->deleteJson(route('flags.delete',[$s,'opened']),$usage)->assertOk();$this->assertSame(['keep'],array_column($s->fresh()->game_flags,'key'));$saved=$p->fresh()->visual_document['visuals'][0];$this->assertSame([],$saved['skip_rules']);$h=$saved['hotspots'][0];$this->assertNull($h['state_flag']);
  foreach([$h['flag_conditions'],$h['flag_effects'],$h['action_requirements']['flag_conditions'],$h['inventory_rules'][0]['flag_effects']] as $rows)$this->assertSame([['key'=>'keep','value'=>false]],$rows);
  $this->assertSame($revision+1,$p->fresh()->visual_revision);$this->assertSame($other->visual_revision,$other->fresh()->visual_revision);
 }
 public function test_flag_deletion_rejects_new_uses_after_review():void {
  [$u,$s,$p]=$this->fixture();$s->update(['game_flags'=>[['key'=>'opened','label'=>'Opened','initial'=>false]]]);$usage=$this->getJson(route('flags.uses',[$s,'opened']))->assertOk()->json();$p->update(['visual_revision'=>$p->visual_revision+1]);
  $this->deleteJson(route('flags.delete',[$s,'opened']),$usage)->assertUnprocessable();$this->assertCount(1,$s->fresh()->game_flags);
 }
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
 public function test_cursor_tooltips_save_validate_and_are_available_to_preview():void {
  [$u,$s,$p]=$this->fixture();$payload=['revision'=>$s->revision,'title'=>$s->title,'premise'=>$s->premise,'genre'=>'Mystery','language'=>'English','cursor_tooltips'=>['grab'=>'Take item','help'=>'Examine','pointer'=>'']];
  $this->putJson(route('stories.update',$s),$payload)->assertOk()->assertJsonPath('cursor_tooltips.grab','Take item');
  $this->assertSame('Examine',$s->fresh()->cursor_tooltips['help']);$this->getJson(route('visuals.show',[$s,$p]))->assertOk()->assertJsonPath('cursor_tooltips.grab','Take item');
  $payload['revision']=$s->fresh()->revision;$payload['cursor_tooltips']=['grab'=>str_repeat('x',201)];$this->putJson(route('stories.update',$s),$payload)->assertUnprocessable();
  $payload['cursor_tooltips']=['unknown'=>'Bad cursor'];$this->putJson(route('stories.update',$s),$payload)->assertUnprocessable();
  $this->actingAs(User::factory()->create())->putJson(route('stories.update',$s),$payload)->assertNotFound();
 }
}
