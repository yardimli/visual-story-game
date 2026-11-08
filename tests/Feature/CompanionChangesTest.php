<?php
namespace Tests\Feature;
use App\Models\{User,Story};
use App\Services\CompanionChanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class CompanionChangesTest extends TestCase {
 use RefreshDatabase;
 private function fixture(): array {
  $u=User::factory()->create();$this->actingAs($u);$s=Story::create(['user_id'=>$u->id,'title'=>'House','premise'=>'Original']);
  $v=(string)Str::uuid();$h=(string)Str::uuid();
  $p=$s->pages()->create(['position'=>1,'title'=>'Porch','visual_document'=>['visuals'=>[['id'=>$v,'name'=>'Porch','kind'=>'scene','asset_id'=>null,'prompt'=>'Original prompt','hotspots'=>[['id'=>$h,'label'=>'Envelope','description'=>'Original description','action'=>'inspect','object_id'=>null,'target_id'=>null,'overlay'=>false,'x'=>.1,'y'=>.1,'width'=>.2,'height'=>.2]]]]]]);
  $second=$s->pages()->create(['position'=>2,'title'=>'Hall','narrative'=>'Original narrative']);
  $proposal=['changes'=>[
   ['scope'=>'story','fields'=>['premise'=>'Changed premise']],
   ['scope'=>'page','page_id'=>$second->id,'fields'=>['narrative'=>'New narrative']],
   ['scope'=>'hotspot','page_id'=>$p->id,'visual_id'=>$v,'hotspot_id'=>$h,'fields'=>['description'=>'Read the envelope']],
   ['scope'=>'object','page_id'=>$p->id,'visual_id'=>$v,'hotspot_id'=>$h,'fields'=>['name'=>'Envelope letter','prompt'=>'Readable letter'],'open_image_generation'=>true],
  ]];
  $s->refresh();$proposal['_review']=app(CompanionChanges::class)->prepare($s,$proposal);
  $m=$s->messages()->create(['role'=>'assistant','content'=>'Review changes','revision'=>$s->revision,'proposal'=>$proposal]);
  return [$u,$s,$p,$second,$m];
 }
 public function test_companion_can_propose_inventory_responses_and_named_choices(): void {
  [$u,$s,$p,$second,$m]=$this->fixture();$doc=$p->visual_document;$v=$doc['visuals'][0];$id=(string)Str::uuid();
  $doc['visuals'][]=['id'=>$id,'name'=>'Envelope','kind'=>'object','asset_id'=>null,'prompt'=>'','hotspots'=>[]];$p->update(['visual_document'=>$doc]);
  $fields=['action'=>'inventory','interaction_text'=>'A brass lock.','fallback_text'=>'Nothing happens.','inventory_rules'=>[['object_id'=>$id,'response'=>'You lack the lock-picking skill.','target_id'=>null,'consume'=>false]],'choices'=>[['label'=>'Walk inside','response'=>'You enter.','target_id'=>$v['id']]]];
  $proposal=['changes'=>[['scope'=>'hotspot','page_id'=>$p->id,'visual_id'=>$v['id'],'hotspot_id'=>$v['hotspots'][0]['id'],'fields'=>$fields]]];
  $proposal['_review']=app(CompanionChanges::class)->prepare($s,$proposal);$m->update(['proposal'=>$proposal]);
  $this->assertCount(5,$proposal['_review']['items']);
  $this->postJson(route('companion.changes',[$s,$m]),['selected'=>[0,1,2,3,4]])->assertOk();
  $area=$p->fresh()->visual_document['visuals'][0]['hotspots'][0];$this->assertSame($fields['inventory_rules'],$area['inventory_rules']);$this->assertSame($fields['choices'],$area['choices']);
 }
 public function test_selected_changes_apply_across_pages_and_skipped_changes_stay_untouched(): void {
  [$u,$s,$p,$second,$m]=$this->fixture();
  $this->postJson(route('companion.changes',[$s,$m]),['selected'=>[1,2,3,4]])->assertOk()->assertJsonPath('revision',2);
  $this->assertSame('Original',$s->fresh()->premise);$this->assertSame('New narrative',$second->fresh()->narrative);
  $doc=$p->fresh()->visual_document;$this->assertSame('Read the envelope',$doc['visuals'][0]['hotspots'][0]['description']);
  $this->assertSame($doc['visuals'][1]['id'],$doc['visuals'][0]['hotspots'][0]['object_id']);$this->assertSame('Readable letter',$doc['visuals'][1]['prompt']);
  $this->assertDatabaseCount('image_generations',0);$this->assertDatabaseCount('story_revisions',1);
  $this->postJson(route('companion.changes',[$s,$m]),['selected'=>[0]])->assertUnprocessable();
 }
 public function test_navigation_dependency_stale_visuals_and_ownership_are_enforced(): void {
  [$u,$s,$p,$second,$m]=$this->fixture();
  $this->postJson(route('companion.changes',[$s,$m]),['selected'=>[4]])->assertUnprocessable();
  $p->increment('visual_revision');
  $this->postJson(route('companion.changes',[$s,$m]),['selected'=>[0,2]])->assertUnprocessable();
  $this->assertSame('Original',$s->fresh()->premise);$this->assertSame(1,$s->fresh()->revision);
  $this->actingAs(User::factory()->create())->postJson(route('companion.changes',[$s,$m]),['selected'=>[0]])->assertNotFound();
 }
 public function test_invalid_geometry_rolls_back_all_selected_changes(): void {
  [$u,$s,$p,$second,$m]=$this->fixture();$v=$p->visual_document['visuals'][0];
  $proposal=['changes'=>[['scope'=>'story','fields'=>['premise'=>'Changed']],['scope'=>'hotspot','page_id'=>$p->id,'visual_id'=>$v['id'],'hotspot_id'=>$v['hotspots'][0]['id'],'fields'=>['width'=>1]]]];
  $s->refresh();$proposal['_review']=app(CompanionChanges::class)->prepare($s,$proposal);$m->update(['proposal'=>$proposal]);
  $this->postJson(route('companion.changes',[$s,$m]),['selected'=>[0,1]])->assertUnprocessable();
  $this->assertSame('Original',$s->fresh()->premise);$this->assertDatabaseCount('story_revisions',0);
 }
}
