<?php
namespace Tests\Feature;
use App\Models\{User,Story,Asset,ImageGeneration};
use App\Jobs\GenerateSceneImage;
use App\Services\OpenRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache,Http,Queue,Storage};
use Illuminate\Support\Str;
use Tests\TestCase;
class SceneAuthoringTest extends TestCase {
 use RefreshDatabase;
 private function setupScene(): array {
  $u=User::factory()->create();$u->forceFill(['openrouter_key'=>'test-personal'])->save();$this->actingAs($u);
  $s=Story::create(['user_id'=>$u->id,'title'=>'Silent House','premise'=>'Find the key.']);$p=$s->pages()->create(['position'=>1,'title'=>'Porch']);
  $scene=['id'=>(string)Str::uuid(),'name'=>'Porch without note','kind'=>'scene','asset_id'=>null,'prompt'=>'A porch without a note','hotspots'=>[]];
  $object=['id'=>(string)Str::uuid(),'name'=>'Note','kind'=>'object','asset_id'=>null,'prompt'=>'A readable note','hotspots'=>[]];
  $h=['id'=>(string)Str::uuid(),'label'=>'Read note','description'=>'The rose guards the key.','x'=>0.1,'y'=>0.2,'width'=>0.2,'height'=>0.2,'action'=>'inspect','target_id'=>null,'object_id'=>$object['id'],'overlay'=>true];
  $scene['hotspots'][]=$h;$doc=['visuals'=>[$scene,$object]];$p->update(['visual_document'=>$doc]);
  config(['story.openrouter_providers'=>['openai']]);
  Cache::put('openrouter.image-models',[['id'=>'openai/image','name'=>'Image','architecture'=>['output_modalities'=>['image']]]],60);
  Cache::put('openrouter.image-endpoints.'.hash('sha256','openai/image'),[['provider_tag'=>'openai','supported_parameters'=>['input_references'=>['type'=>'range','max'=>2],'quality'=>['type'=>'enum','values'=>['high']]],'pricing'=>[['billable'=>'output_image','unit'=>'image','cost_usd'=>0.05]]]],60);
  return [$u,$s,$p->fresh(),$doc];
 }
 public function test_next_page_action_and_ordered_preview_pages(): void {
  [$u,$s,$p,$doc]=$this->setupScene();
  $doc['visuals'][0]['hotspots'][0]['action']='next_page';
  $validated=app(\App\Services\SceneDocument::class)->validate($s,$doc);
  $this->assertSame('next_page',$validated['visuals'][0]['hotspots'][0]['action']);
  $next=$s->pages()->create(['position'=>3,'title'=>'Inside','visual_document'=>['visuals'=>[]]]);
  $removed=$s->pages()->create(['position'=>2,'title'=>'Removed']);$removed->delete();
  $this->getJson(route('visuals.show',[$s,$p]))->assertOk()->assertJsonPath('page_id',$p->id)->assertJsonCount(2,'preview_pages')->assertJsonPath('preview_pages.1.id',$next->id);
 }
 public function test_asset_names_archiving_and_usage_links_are_owned_and_preserve_assignments(): void {
  [$u,$s,$p,$doc]=$this->setupScene();
  $a=$s->assets()->create(['name'=>'Original','path'=>'test.png','mime'=>'image/png','bytes'=>1]);
  $doc['visuals'][0]['asset_id']=$a->id;$doc['visuals'][1]['asset_id']=$a->id;$p->update(['visual_document'=>$doc]);
  $other=$s->pages()->create(['position'=>2,'title'=>'Next','visual_document'=>['visuals'=>[$doc['visuals'][0]]]]);
  $this->patchJson(route('assets.update',$a),['name'=>'Envelope artwork','archived'=>true])->assertOk();
  $response=$this->getJson(route('visuals.show',[$s,$p]))->assertOk()->assertJsonPath('assets.0.name','Envelope artwork')->assertJsonPath('assets.0.archived',true)->assertJsonCount(3,'assets.0.uses');
  $this->assertStringContainsString('tab=visuals',$response->json('assets.0.uses.0.url'));
  $this->assertEquals($a->id,$p->fresh()->visual_document['visuals'][0]['asset_id']);
  $this->assertSame('Envelope artwork',Asset::withGenerationNames(collect([$a->fresh()]),$s->id)->first()->name);
  $this->patchJson(route('assets.update',$a),['name'=>''])->assertUnprocessable();
  $this->patchJson(route('assets.update',$a),['archived'=>false])->assertOk()->assertJsonPath('archived',false);
  $this->actingAs(User::factory()->create())->patchJson(route('assets.update',$a),['name'=>'Not mine','archived'=>true])->assertNotFound();
  $this->assertSame('Envelope artwork',$a->fresh()->name);
 }
 public function test_inventory_rules_and_choices_validate_targets_and_round_trip(): void {
  [$u,$s,$p,$doc]=$this->setupScene();$object=$doc['visuals'][1]['id'];$scene=$doc['visuals'][0]['id'];
  $h=&$doc['visuals'][0]['hotspots'][0];$h['action']='inventory';$h['interaction_text']='A lock.';$h['fallback_text']='Nothing happens.';
  $h['inventory_rules']=[['object_id'=>$object,'response'=>'The envelope will not fit.','target_id'=>null,'consume'=>false]];
  $h['choices']=[['label'=>'Walk inside','response'=>'You enter.','target_id'=>$scene]];
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>1,'document'=>$doc])->assertOk();
  $this->getJson(route('visuals.show',[$s,$p]))->assertJsonPath('document.visuals.0.hotspots.0.inventory_rules.0.response','The envelope will not fit.');
  $h['inventory_rules'][0]['target_id']=$object;
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>2,'document'=>$doc])->assertUnprocessable();
  $h['inventory_rules'][0]['target_id']=null;$h['inventory_rules'][0]['object_id']=$scene;
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>2,'document'=>$doc])->assertUnprocessable();
  $h['inventory_rules'][0]['object_id']=$object;$h['inventory_rules'][]=$h['inventory_rules'][0];
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>2,'document'=>$doc])->assertUnprocessable();
  array_pop($h['inventory_rules']);$h['choices'][0]['target_id']=(string)Str::uuid();
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>2,'document'=>$doc])->assertUnprocessable();
  $h['choices'][0]['target_id']=$scene;$h['action']='choices';
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>2,'document'=>$doc])->assertOk();
 }
 public function test_retry_reuses_failed_snapshot_with_new_request_and_leaves_current_visual_untouched(): void {
  Queue::fake();[$u,$s,$p]=$this->setupScene();
  $this->postJson(route('images.generate',[$s,$p]),array_merge($this->payload($p),['name'=>'Original artwork']))->assertAccepted();
  $old=ImageGeneration::first();$old->update(['status'=>'failed','error'=>'Request expired.']);
  $doc=$p->visual_document;$doc['visuals'][0]['prompt']='A completely different current prompt';$p->update(['visual_document'=>$doc,'visual_revision'=>2]);
  $key=(string)Str::uuid();
  $response=$this->postJson(route('images.retry',[$s,$p,$old]),['request_key'=>$key])->assertAccepted();
  $new=ImageGeneration::findOrFail($response->json('id'));
  $this->assertNotSame($old->id,$new->id);$this->assertSame($old->payload,$new->payload);$this->assertSame($old->references,$new->references);$this->assertSame('failed',$old->fresh()->status);
  $this->assertSame($doc,$p->fresh()->visual_document);$this->assertSame(240,(new GenerateSceneImage($new->id))->timeout);
  $this->postJson(route('images.retry',[$s,$p,$old]),['request_key'=>$key])->assertUnprocessable();
  $this->postJson(route('images.retry',[$s,$p,$new]),['request_key'=>(string)Str::uuid()])->assertUnprocessable();
  $this->actingAs(User::factory()->create())->postJson(route('images.retry',[$s,$p,$old]),['request_key'=>(string)Str::uuid()])->assertNotFound();
  Queue::assertPushed(GenerateSceneImage::class,2);
 }
 private function payload($p): array {return ['revision'=>$p->visual_revision,'visual_id'=>$p->visual_document['visuals'][0]['id'],'request_key'=>(string)Str::uuid(),'model'=>'openai/image','references'=>[],'settings'=>['quality'=>'high']];}
 public function test_object_actions_save_reversible_links_and_reject_scene_targets(): void {
  [$u,$s,$p,$doc]=$this->setupScene();
  $doc['visuals'][0]['description']='A silent porch.';$doc['visuals'][1]['description']='A sealed envelope.';
  $closed=$doc['visuals'][1]['id'];$open=(string)Str::uuid();
  $doc['visuals'][1]['actions']=[['id'=>(string)Str::uuid(),'label'=>'Open','target_id'=>$open]];
  $doc['visuals'][]=['id'=>$open,'name'=>'Opened envelope','kind'=>'object','asset_id'=>null,'prompt'=>'Open envelope','hotspots'=>[],'actions'=>[['id'=>(string)Str::uuid(),'label'=>'Close','target_id'=>$closed]]];
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>1,'document'=>$doc])->assertOk();
  $this->getJson(route('visuals.show',[$s,$p]))->assertJsonPath('document.visuals.0.description','A silent porch.')->assertJsonPath('document.visuals.1.description','A sealed envelope.')->assertJsonPath('document.visuals.1.actions.0.target_id',$open)->assertJsonPath('document.visuals.2.actions.0.target_id',$closed);
  $doc['visuals'][1]['actions'][0]['target_id']=$doc['visuals'][0]['id'];
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>2,'document'=>$doc])->assertUnprocessable();
  $doc['visuals'][1]['actions'][0]['target_id']=(string)Str::uuid();
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>2,'document'=>$doc])->assertUnprocessable();
  $doc['visuals'][1]['actions'][0]['target_id']=null;
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>2,'document'=>$doc])->assertOk();
 }
 public function test_catalog_requests_do_not_consume_generation_quota_and_generation_stays_limited(): void {
  Queue::fake();[$u,$s,$p]=$this->setupScene();
  for($i=0;$i<8;$i++) {
   $this->getJson(route('ai.models',['modality'=>'image']))->assertOk();
   $this->getJson(route('ai.image-endpoints',['model'=>'openai/image']))->assertOk();
  }
  for($i=0;$i<6;$i++) $this->postJson(route('images.generate',[$s,$p]),$this->payload($p))->assertAccepted();
  $this->postJson(route('images.generate',[$s,$p]),$this->payload($p))->assertStatus(429)->assertHeader('Retry-After');
  $this->getJson(route('ai.models',['modality'=>'image']))->assertOk();
  $this->assertSame(6,ImageGeneration::count());
  foreach(['openrouter.image-models','openrouter.image-endpoints.'.hash('sha256','openai/image')] as $key) Cache::put($key,Cache::get($key),120);
  $this->travel(61)->seconds();
  $this->postJson(route('images.generate',[$s,$p]),$this->payload($p))->assertAccepted();
 }
 public function test_generation_workspace_is_private_and_returns_original_prompt_snapshot(): void {
  Queue::fake();[$u,$s,$p]=$this->setupScene();
  $this->get(route('images.studio',[$s,$p]))->assertOk()->assertSee('Copy prompt')->assertDontSee('Current visual')->assertDontSee('selectGenerationVisual()')->assertSee('Include as reference')->assertDontSee('Edit this image');
  $this->postJson(route('images.generate',[$s,$p]),$this->payload($p))->assertAccepted();
  $doc=$p->visual_document;$doc['visuals'][0]['prompt']='Changed later';$p->update(['visual_document'=>$doc]);
  $this->getJson(route('visuals.show',[$s,$p]))->assertJsonPath('jobs.0.prompt','A porch without a note')->assertJsonPath('jobs.0.model','openai/image');
  $this->actingAs(User::factory()->create())->get(route('images.studio',[$s,$p]))->assertNotFound();
 }
 public function test_completed_generation_can_move_only_to_an_owned_page_visual(): void {
  Queue::fake();[$u,$s,$p,$doc]=$this->setupScene();
  $this->postJson(route('images.generate',[$s,$p]),$this->payload($p))->assertAccepted();
  $job=ImageGeneration::first();$job->update(['status'=>'completed']);$payload=$job->payload;
  $this->patchJson(route('images.move',[$s,$p,$job]),['visual_id'=>$doc['visuals'][1]['id'],'revision'=>1])->assertOk();
  $this->assertSame($doc['visuals'][1]['id'],$job->fresh()->visual_id);$this->assertSame($payload,$job->fresh()->payload);
  $this->assertSame($doc,$p->fresh()->visual_document);
  $this->patchJson(route('images.move',[$s,$p,$job]),['visual_id'=>(string)Str::uuid(),'revision'=>1])->assertUnprocessable();
  $this->patchJson(route('images.move',[$s,$p,$job]),['visual_id'=>$doc['visuals'][0]['id'],'revision'=>9])->assertUnprocessable();
  $job->update(['status'=>'running']);
  $this->patchJson(route('images.move',[$s,$p,$job]),['visual_id'=>$doc['visuals'][0]['id'],'revision'=>1])->assertUnprocessable();
  $this->actingAs(User::factory()->create())->patchJson(route('images.move',[$s,$p,$job]),['visual_id'=>$doc['visuals'][0]['id'],'revision'=>1])->assertNotFound();
 }
 public function test_image_picker_names_follow_moved_generations_including_older_moves(): void {
  Queue::fake();[$u,$s,$p,$doc]=$this->setupScene();
  $this->postJson(route('images.generate',[$s,$p]),$this->payload($p))->assertAccepted();
  $job=ImageGeneration::first();
  $asset=$s->assets()->create(['name'=>'Old scene name','path'=>'test.png','mime'=>'image/png','bytes'=>10,'role'=>'scene']);
  $job->update(['status'=>'completed','asset_ids'=>[$asset->id],'name'=>'Previously moved']);
  $this->getJson(route('visuals.show',[$s,$p]))->assertJsonPath('assets.0.name','Previously moved · render '.$job->id.'-1');
  $this->get(route('stories.edit',[$s,'page'=>$p->id]))->assertOk()->assertSee('Previously moved');
  $this->patchJson(route('images.move',[$s,$p,$job]),['visual_id'=>$doc['visuals'][1]['id'],'revision'=>1])->assertOk();
  $this->assertSame('Note · render '.$job->id.'-1',$asset->fresh()->name);
  $this->assertSame('object',$asset->fresh()->role);
  $this->getJson(route('visuals.show',[$s,$p]))->assertJsonPath('assets.0.name','Note · render '.$job->id.'-1')->assertJsonPath('jobs.0.name','Note');
 }
 public function test_unfinished_inspect_draft_saves_and_can_open_generation(): void {
  [$u,$s,$p,$doc]=$this->setupScene();$doc['visuals'][0]['hotspots'][0]['object_id']=null;
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>1,'document'=>$doc])->assertOk();
  $this->get(route('images.studio',[$s,$p]))->assertOk();
  $this->assertNull($p->fresh()->visual_document['visuals'][0]['hotspots'][0]['object_id']);
 }
 public function test_visual_document_autosaves_and_rejects_conflicts_and_out_of_bounds(): void {
  [$u,$s,$p,$doc]=$this->setupScene();
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>1,'document'=>$doc])->assertOk()->assertJsonPath('revision',2);
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>1,'document'=>$doc])->assertUnprocessable()->assertJsonValidationErrors('revision');
  $doc['visuals'][0]['hotspots'][0]['x']=0.95;
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>2,'document'=>$doc])->assertUnprocessable();
 }
 public function test_private_ownership_and_cross_story_asset_rejection(): void {
  [$u,$s,$p,$doc]=$this->setupScene();$other=Story::create(['user_id'=>$u->id,'title'=>'Other','premise'=>'Other']);
  $a=$other->assets()->create(['name'=>'Foreign','path'=>'other.png','mime'=>'image/png','bytes'=>1,'role'=>'scene']);
  $doc['visuals'][0]['asset_id']=$a->id;
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>1,'document'=>$doc])->assertUnprocessable();
  $this->actingAs(User::factory()->create())->getJson(route('visuals.show',[$s,$p]))->assertNotFound();
  $this->postJson(route('images.generate',[$s,$p]),$this->payload($p))->assertNotFound();
 }
 public function test_invalid_hotspot_target_is_rejected(): void {
  [$u,$s,$p,$doc]=$this->setupScene();$doc['visuals'][0]['hotspots'][0]['object_id']=(string)Str::uuid();
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>1,'document'=>$doc])->assertUnprocessable();
 }
 public function test_generation_queues_once_without_changing_selected_images_or_text_credit(): void {
  Queue::fake();[$u,$s,$p]=$this->setupScene();$payload=$this->payload($p);
  $this->postJson(route('images.generate',[$s,$p]),$payload)->assertAccepted();
  $this->postJson(route('images.generate',[$s,$p]),$payload)->assertUnprocessable();
  Queue::assertPushed(GenerateSceneImage::class,1);
  $this->assertDatabaseCount('image_generations',1);$this->assertSame(0,$u->fresh()->llm_spent);
  $this->assertNull($p->fresh()->visual_document['visuals'][0]['asset_id']);
 }
 public function test_exhausted_credit_requires_personal_key_and_capabilities_are_validated(): void {
  Queue::fake();[$u,$s,$p]=$this->setupScene();$payload=$this->payload($p);$payload['settings']['quality']='unsupported';
  $this->postJson(route('images.generate',[$s,$p]),$payload)->assertUnprocessable();
  $u->forceFill(['openrouter_key'=>null,'llm_spent'=>1000000])->save();$this->actingAs($u);
  $this->postJson(route('images.generate',[$s,$p]),$this->payload($p))->assertUnprocessable()->assertJsonValidationErrors('key');
  Queue::assertNothingPushed();
 }
 public function test_worker_stores_version_and_never_submits_twice(): void {
  Storage::fake('local');Queue::fake();[$u,$s,$p]=$this->setupScene();
  $this->postJson(route('images.generate',[$s,$p]),array_merge($this->payload($p),['name'=>'Rainy Doorstep']))->assertAccepted();
  $job=ImageGeneration::first();
  $png='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX5kAAAAASUVORK5CYII=';
  Http::fake(['*/images'=>Http::response(['data'=>[['b64_json'=>$png]],'usage'=>['cost'=>0]])]);
  (new GenerateSceneImage($job->id))->handle(app(OpenRouter::class));
  (new GenerateSceneImage($job->id))->handle(app(OpenRouter::class));
  Http::assertSentCount(1);$this->assertSame('completed',$job->fresh()->status);
  $asset=Asset::findOrFail($job->fresh()->asset_ids[0]);$this->assertSame('Rainy Doorstep',$asset->custom_name);Http::assertSent(fn($r)=>!isset($r['_asset_name']));Storage::disk('local')->assertExists($asset->path);
  $this->assertNull($p->fresh()->visual_document['visuals'][0]['asset_id']);
 }
 public function test_failed_provider_is_visible_and_not_retried(): void {
  Queue::fake();[$u,$s,$p]=$this->setupScene();$this->postJson(route('images.generate',[$s,$p]),$this->payload($p))->assertAccepted();
  $job=ImageGeneration::first();Http::fake(['*/images'=>Http::response(['error'=>'Failure'],500)]);
  (new GenerateSceneImage($job->id))->handle(app(OpenRouter::class));
  $this->assertSame('failed',$job->fresh()->status);$this->assertDatabaseCount('assets',0);Http::assertSentCount(1);
 }
 public function test_rejected_image_request_shows_redacted_reason_and_releases_reservation(): void {
  Queue::fake();[$u,$s,$p]=$this->setupScene();$u->forceFill(['openrouter_key'=>null])->save();$this->actingAs($u);
  $this->postJson(route('images.generate',[$s,$p]),$this->payload($p))->assertAccepted();
  $job=ImageGeneration::first();$payload=$job->payload;$payload['provider']=['require_parameters'=>true];$job->update(['payload'=>$payload]);
  Http::fake(['*/images'=>Http::response(['error'=>['message'=>'Unsupported field; key '.config('story.openrouter_key')]],400)]);
  (new GenerateSceneImage($job->id))->handle(app(OpenRouter::class));
  $this->assertStringContainsString('HTTP 400: Unsupported field',$job->fresh()->error);
  $this->assertStringNotContainsString(config('story.openrouter_key'),$job->fresh()->error);
  $this->assertSame(0,$u->fresh()->llm_reserved);
  Http::assertSent(fn($r)=>!isset($r['provider']));Http::assertSentCount(1);
 }
 public function test_reference_images_are_private_validated_and_sent_as_data_urls(): void {
  Storage::fake('local');Queue::fake();[$u,$s,$p]=$this->setupScene();
  $other=Story::create(['user_id'=>$u->id,'title'=>'Other','premise'=>'Other']);
  $foreign=$other->assets()->create(['name'=>'Foreign','path'=>'foreign.png','mime'=>'image/png','bytes'=>3,'role'=>'scene']);
  $payload=$this->payload($p);$payload['references']=[$foreign->id];
  $this->postJson(route('images.generate',[$s,$p]),$payload)->assertNotFound();
  $asset=$s->assets()->create(['name'=>'Reference','path'=>'reference.png','mime'=>'image/png','bytes'=>3,'role'=>'scene']);
  Storage::disk('local')->put('reference.png','abc');$payload['references']=[$asset->id];
  $this->postJson(route('images.generate',[$s,$p]),$payload)->assertAccepted();
  Http::fake(['*/images'=>Http::response(['data'=>[['b64_json'=>base64_encode('<svg/>')]]])]);
  $job=ImageGeneration::first();(new GenerateSceneImage($job->id))->handle(app(OpenRouter::class));
  Http::assertSent(fn($r)=>$r['input_references'][0]['image_url']['url']==='data:image/png;base64,YWJj' && !isset($r['provider']));
  $this->assertSame('failed',$job->fresh()->status);$this->assertCount(1,$s->assets()->get());
 }
 public function test_trial_images_share_chat_balance_and_settle_catalog_price_even_when_byok_cost_is_zero(): void {
  Storage::fake('local');Queue::fake();[$u,$s,$p]=$this->setupScene();
  $u->forceFill(['openrouter_key'=>null,'llm_spent'=>900000])->save();$this->actingAs($u);
  $payload=$this->payload($p);$this->postJson(route('images.generate',[$s,$p]),$payload)->assertAccepted();
  $this->assertSame(52500,$u->fresh()->llm_reserved);
  $this->postJson(route('images.generate',[$s,$p]),$this->payload($p))->assertUnprocessable();
  $this->postJson(route('images.generate',[$s,$p]),$payload)->assertUnprocessable();
  $job=ImageGeneration::first();
  // Adding a personal key after enqueue must not change the captured funding.
  $u->forceFill(['openrouter_key'=>'added-later'])->save();
  $png='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX5kAAAAASUVORK5CYII=';
  Http::fake(['*/images'=>Http::response(['data'=>[['b64_json'=>$png]],'usage'=>['cost'=>0]])]);
  (new GenerateSceneImage($job->id))->handle(app(OpenRouter::class));
  (new GenerateSceneImage($job->id))->handle(app(OpenRouter::class));
  $this->assertSame('completed',$job->fresh()->status);
  $this->assertSame(950000,$u->fresh()->llm_spent);$this->assertSame(0,$u->fresh()->llm_reserved);
  Http::assertSentCount(1);Http::assertSent(fn($r)=>$r->hasHeader('Authorization','Bearer '.config('story.openrouter_key')));
 }
 public function test_unsubmitted_failure_releases_credit_but_provider_failure_retains_it(): void {
  Queue::fake();[$u,$s,$p]=$this->setupScene();$u->forceFill(['openrouter_key'=>null])->save();$this->actingAs($u);
  $this->postJson(route('images.generate',[$s,$p]),$this->payload($p))->assertAccepted();$job=ImageGeneration::first();
  Http::fake(['*/images'=>Http::response([],500)]);(new GenerateSceneImage($job->id))->handle(app(OpenRouter::class));
  $this->assertSame(52500,$u->fresh()->llm_reserved);$this->assertSame('uncertain',\App\Models\LlmRequest::find($job->llm_request_id)->status);
  $this->actingAs($u->fresh())->postJson(route('images.generate',[$s,$p]),$this->payload($p))->assertAccepted();$next=ImageGeneration::latest('id')->first();
  $p->delete();(new GenerateSceneImage($next->id))->handle(app(OpenRouter::class));
  $this->assertSame(52500,$u->fresh()->llm_reserved);Http::assertSentCount(1);
 }
 public function test_personal_job_never_falls_back_to_platform_after_key_removal(): void {
  Queue::fake();[$u,$s,$p]=$this->setupScene();$this->postJson(route('images.generate',[$s,$p]),$this->payload($p))->assertAccepted();
  $job=ImageGeneration::first();$u->forceFill(['openrouter_key'=>null])->save();Http::fake();
  (new GenerateSceneImage($job->id))->handle(app(OpenRouter::class));
  Http::assertNothingSent();$this->assertSame('failed',$job->fresh()->status);$this->assertSame(0,$u->fresh()->llm_spent);
 }
}
