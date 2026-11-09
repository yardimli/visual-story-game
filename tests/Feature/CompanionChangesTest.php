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
 public function test_create_page_inserts_reviewed_scene_and_can_be_skipped(): void {
  [$u,$s,$p,$second,$m]=$this->fixture();
  $proposal=['changes'=>[['scope'=>'create_page','after_page_id'=>$p->id,'fields'=>['title'=>'Back at the house','narrative'=>'The stone lies empty.','visuals'=>[['name'=>'Empty stone','kind'=>'scene','asset_id'=>null,'prompt'=>'An open empty stone','hotspots'=>[['label'=>'Approach door','action'=>'next_page','x'=>.5,'y'=>.2,'width'=>.2,'height'=>.5]]]]]],['scope'=>'page','page_id'=>$p->id,'fields'=>['interaction_plan'=>'Return via the new page']]]];
  $proposal['_review']=app(CompanionChanges::class)->prepare($s,$proposal);$m->update(['proposal'=>$proposal]);
  $this->assertDatabaseCount('story_pages',2);
  $this->postJson(route('companion.changes',[$s,$m]),['selected'=>[0]])->assertOk();
  $this->assertSame(['Porch','Back at the house','Hall'],$s->pages()->pluck('title')->all());
  $new=$s->pages()->where('position',2)->first();$this->assertSame('next_page',$new->visual_document['visuals'][0]['hotspots'][0]['action']);
  $this->assertTrue(Str::isUuid($new->visual_document['visuals'][0]['id']));
  $this->assertSame(3,$s->pages()->count());$this->assertNull($p->fresh()->interaction_plan);
  $this->assertCount(2,$s->revisions()->first()->snapshot['pages']);
  $this->postJson(route('companion.changes',[$s,$m]),['selected'=>[0]])->assertUnprocessable();
 }
 public function test_prepared_image_is_a_draft_without_paid_call_or_visual_mutation(): void {
  [$u,$s,$p,$second,$m]=$this->fixture();$before=$p->visual_document;
  $proposal=['changes'=>[['scope'=>'image','page_id'=>$p->id,'fields'=>['name'=>'Empty stone','prompt'=>'An open stone with no key','video_prompt'=>'A gentle breeze']]]];
  $proposal['_review']=app(CompanionChanges::class)->prepare($s,$proposal);$m->update(['proposal'=>$proposal]);
  $this->postJson(route('companion.changes',[$s,$m]),['selected'=>[0]])->assertOk();
  $this->assertDatabaseHas('image_generations',['status'=>'draft','name'=>'Empty stone','llm_request_id'=>null]);
  $this->assertDatabaseCount('llm_requests',0);$this->assertSame($before,$p->fresh()->visual_document);
 }
 public function test_creation_rejects_foreign_page(): void {
  [$u,$s,$p]=$this->fixture();$other=Story::create(['user_id'=>$u->id,'title'=>'Other','premise'=>'Other']);$foreign=$other->pages()->create(['position'=>1,'title'=>'Other']);
  $this->expectException(\Illuminate\Validation\ValidationException::class);
  app(CompanionChanges::class)->prepare($s,['changes'=>[['scope'=>'create_page','after_page_id'=>$foreign->id,'fields'=>['title'=>'Invalid']]]]);
 }
 public function test_html_page_proposals_preserve_quill_formatting_and_strip_unsafe_content(): void {
  [$u,$s,$p,$second,$m]=$this->fixture();
  $html='<h2 onclick="alert(1)">The stone</h2><p>A <strong>hidden</strong> key.</p><ul><li>Read the note</li><li>Find the key</li></ul><script>alert(1)</script><img src="x" onerror="alert(1)">';
  $proposal=['changes'=>[['scope'=>'page','page_id'=>$p->id,'fields'=>['narrative'=>$html,'interaction_plan'=>'<ol><li>Inspect</li><li>Leave</li></ol>']]]];
  $proposal['_review']=app(CompanionChanges::class)->prepare($s,$proposal);$m->update(['proposal'=>$proposal]);
  $this->assertStringNotContainsString('<',$proposal['_review']['items'][0]['after']);
  $this->postJson(route('companion.changes',[$s,$m]),['selected'=>[0,1]])->assertOk();
  $page=$p->fresh();$this->assertStringContainsString('The stone',$page->narrative);$this->assertStringNotContainsString('alert',$page->narrative);
  $ops=collect($page->narrative_delta['ops']);$this->assertTrue($ops->contains(fn($op)=>($op['attributes']['header']??null)===2));
  $this->assertTrue($ops->contains(fn($op)=>($op['attributes']['bold']??false)===true));
  $this->assertCount(2,$ops->filter(fn($op)=>($op['attributes']['list']??null)==='bullet'));
  $this->assertCount(2,collect($page->interaction_plan_delta['ops'])->filter(fn($op)=>($op['attributes']['list']??null)==='ordered'));
 }
 public function test_new_page_html_is_converted_and_plain_text_remains_compatible(): void {
  [$u,$s,$p,$second,$m]=$this->fixture();
  $proposal=['changes'=>[['scope'=>'create_page','after_page_id'=>$p->id,'fields'=>['title'=>'New','narrative'=>'<h3>Inside</h3><p><em>Silence.</em></p>','interaction_plan'=>'Plain plan']]]];
  $proposal['_review']=app(CompanionChanges::class)->prepare($s,$proposal);$m->update(['proposal'=>$proposal]);
  $this->postJson(route('companion.changes',[$s,$m]),['selected'=>[0]])->assertOk();
  $page=$s->pages()->where('position',2)->first();$this->assertNotNull($page->narrative_delta);$this->assertSame('Plain plan',$page->interaction_plan);$this->assertNull($page->interaction_plan_delta);
 }
}
