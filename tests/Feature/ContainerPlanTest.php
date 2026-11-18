<?php
namespace Tests\Feature;
use App\Models\{User,Story,Asset,SceneExtraction,ContainerPlan};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Queue};
use Illuminate\Support\Str;
use Tests\TestCase;
class ContainerPlanTest extends TestCase {
 use RefreshDatabase;
 private function fixture():array {
  Storage::fake('local');Queue::fake();$u=User::factory()->create();$this->actingAs($u);$s=Story::create(['user_id'=>$u->id,'title'=>'House','premise'=>'Test']);
  $im=imagecreatetruecolor(100,100);ob_start();imagepng($im);$png=ob_get_clean();imagedestroy($im);Storage::disk('local')->put('scene.png',$png);$a=Asset::create(['story_id'=>$s->id,'name'=>'Scene','path'=>'scene.png','mime'=>'image/png','bytes'=>strlen($png),'role'=>'scene']);
  $item=['id'=>(string)Str::uuid(),'kind'=>'object','name'=>'Key','asset_id'=>$a->id,'description'=>'Brass key','hotspots'=>[]];$coin=['id'=>(string)Str::uuid(),'kind'=>'object','name'=>'Coin','description'=>'Silver coin','asset_id'=>null,'hotspots'=>[]];$s->update(['inventory_document'=>['visuals'=>[$item,$coin]]]);
  $h=['id'=>(string)Str::uuid(),'label'=>'Stone','action'=>'examine','description'=>'A hinged stone','overlay'=>false,'x'=>.1,'y'=>.1,'width'=>.5,'height'=>.5];$v=['id'=>(string)Str::uuid(),'kind'=>'scene','name'=>'Garden','asset_id'=>$a->id,'hotspots'=>[$h]];$p=$s->pages()->create(['title'=>'Garden','position'=>1,'visual_document'=>['visuals'=>[$v]]]);
  return [$s->fresh(),$p->fresh(),['visual_id'=>$v['id'],'area_id'=>$h['id'],'name'=>'Stone contents','prompt'=>'Open the stone','contents'=>[['object_id'=>$item['id'],'name'=>'Key'],['object_id'=>$coin['id'],'name'=>'Coin']]],$a];
 }
 public function test_extract_existing_switch_keeps_unlock_rule_and_six_collectibles():void {
  [$s,$p,$data,$asset]=$this->fixture();$doc=$p->visual_document;$parent=&$doc['visuals'][0]['hotspots'][0];
  $parent=array_merge($parent,['action'=>'state_v2','state_flag'=>'chest_open','state_asset_id'=>$asset->id,'overlay_asset_id'=>$asset->id,'overlay'=>true,'availability_enabled'=>true,'flag_conditions'=>[['key'=>'unlocked','value'=>true]]]);
  $lock=['id'=>(string)Str::uuid(),'label'=>'Lock','description'=>'Locked','action'=>'inventory','overlay'=>false,'x'=>.2,'y'=>.2,'width'=>.1,'height'=>.1,'disabled_when'=>[['key'=>'unlocked','value'=>true]],'inventory_rules'=>[['object_id'=>$s->inventory_document['visuals'][0]['id'],'consume'=>true,'response'=>'Unlocked','flag_effects'=>[['key'=>'unlocked','value'=>true],['key'=>'chest_open','value'=>true]]]]];$doc['visuals'][0]['hotspots'][]=$lock;$p->update(['visual_document'=>$doc]);
  $items=$s->inventory_document['visuals'];for($i=0;$i<4;$i++)$items[]=['id'=>(string)Str::uuid(),'kind'=>'object','name'=>'Item '.$i,'asset_id'=>$asset->id,'hotspots'=>[]];
  $s->update(['inventory_document'=>['visuals'=>$items],'game_flags'=>[['key'=>'unlocked','label'=>'Unlocked','initial'=>false],['key'=>'chest_open','label'=>'Open','initial'=>false]]]);
  $data['reuse_changed_state']=true;unset($data['prompt']);$data['contents']=array_map(fn($item)=>['object_id'=>$item['id']],$items);
  $plan=$this->postJson(route('containers.index',[$s,$p]),$data)->assertCreated()->json();$this->assertSame($asset->id,$plan['batch']['outputs'][1]['accepted_asset_id']);$this->assertDatabaseCount('image_generations',0);
  $marks=array_map(fn($c)=>['area_id'=>$c['area_id'],'bounds'=>['x'=>.1,'y'=>.2,'width'=>.2,'height'=>.2]],$plan['contents']);
  $this->postJson(route('containers.prepare',[$s,$p,$plan['id']]),['preview_asset_id'=>$asset->id,'contents'=>$marks])->assertOk();
  $batch=SceneExtraction::find($plan['extraction_id']);$outputs=$batch->outputs;foreach($outputs as &$o)$o['accepted_asset_id']??=$asset->id;unset($o);$batch->update(['outputs'=>$outputs]);
  $this->postJson(route('containers.activate',[$s,$p,$plan['id']]),['revision'=>$p->fresh()->visual_revision,'inventory_revision'=>$s->fresh()->inventory_revision])->assertOk();
  $areas=$p->fresh()->visual_document['visuals'][0]['hotspots'];$this->assertCount(8,$areas);$this->assertSame('chest_open',$areas[0]['state_flag']);$this->assertSame($lock+['target_id'=>null],$areas[1]);$uses=app(\App\Services\StoryFlags::class)->inspectDocument($p->fresh()->visual_document,'unlocked','Page',true);$this->assertSame([],$uses['document']['visuals'][0]['hotspots'][1]['disabled_when']);$this->assertSame([['key'=>'unlocked','value'=>true]],$areas[0]['flag_conditions']);
  $this->deleteJson(route('containers.delete',[$s,$p]),['visual_id'=>$data['visual_id'],'area_id'=>$data['area_id'],'revision'=>$p->fresh()->visual_revision])->assertOk();$restored=$p->fresh()->visual_document['visuals'][0]['hotspots'][0];$this->assertSame('state_v2',$restored['action']);$this->assertSame('chest_open',$restored['state_flag']);$this->assertSame($asset->id,$restored['state_asset_id']);
 }
 public function test_staged_container_reuses_inventory_and_activates_relative_children_without_replacing_scene():void {
  [$s,$p,$data,$asset]=$this->fixture();$original=$p->visual_document;
  $plan=$this->postJson(route('containers.index',[$s,$p]),$data)->assertCreated()->json();$assets=$this->getJson(route('visuals.show',[$s,$p]))->assertOk()->json('assets');$references=collect($assets)->firstWhere('id',$asset->id)['plan_references'];$this->assertCount(1,$references);$this->assertSame('Container reference · Stone contents',$references[0]['label']);$this->assertDatabaseCount('image_generations',0);$this->assertSame($original,$p->fresh()->visual_document);$this->assertCount(2,$s->fresh()->inventory_document['visuals']);
  $batch=SceneExtraction::findOrFail($plan['extraction_id']);$outputs=$batch->outputs;$outputs[1]['accepted_asset_id']=$asset->id;$batch->update(['outputs'=>$outputs]);
  $marks=array_map(fn($c)=>['area_id'=>$c['area_id'],'bounds'=>['x'=>.1,'y'=>.2,'width'=>.2,'height'=>.3]],$plan['contents']);
  $prepared=$this->postJson(route('containers.prepare',[$s,$p,$plan['id']]),['preview_asset_id'=>$asset->id,'contents'=>$marks])->assertOk()->json();$this->assertCount(7,$prepared['batch']['outputs']);
  $reused=collect($prepared['batch']['outputs'])->first(fn($o)=>$o['type']==='inventory'&&$o['area_id']===$marks[0]['area_id']);$this->assertSame($asset->id,$reused['accepted_asset_id']);
  $activate=route('containers.activate',[$s,$p,$plan['id']]);$revision=['revision'=>$p->visual_revision,'inventory_revision'=>$s->inventory_revision];$this->postJson($activate,$revision)->assertUnprocessable();
  $batch->refresh();$outputs=$batch->outputs;foreach($outputs as &$o)$o['accepted_asset_id']=$o['accepted_asset_id']??$asset->id;unset($o);$batch->update(['outputs'=>$outputs]);
  $this->postJson($activate,$revision)->assertOk();$doc=$p->fresh()->visual_document;$this->assertSame($asset->id,$doc['visuals'][0]['asset_id']);$areas=$doc['visuals'][0]['hotspots'];$this->assertCount(3,$areas);$this->assertTrue($areas[0]['is_container']);$this->assertSame('state_v2',$areas[0]['action']);$this->assertSame($areas[0]['id'],$areas[1]['container_parent_id']);$this->assertEqualsWithDelta(.15,$areas[1]['x'],.00001);$this->assertSame('pickup_v2',$areas[1]['action']);$this->assertCount(2,$s->fresh()->inventory_document['visuals']);$this->assertDatabaseCount('chat_messages',1);$this->assertSame('active',ContainerPlan::find($plan['id'])->stage);
  $second=$this->postJson(route('containers.index',[$s,$p]),$data)->assertCreated()->json();$this->assertNotSame($plan['id'],$second['id']);$this->assertSame('active',ContainerPlan::find($plan['id'])->stage);$this->assertDatabaseCount('image_generations',0);
  $delete=['visual_id'=>$data['visual_id'],'area_id'=>$data['area_id'],'revision'=>$p->fresh()->visual_revision];$assetCount=Asset::count();$inventoryBefore=$s->fresh()->inventory_document;
  $this->deleteJson(route('containers.delete',[$s,$p]),array_merge($delete,['revision'=>0]))->assertUnprocessable();$this->assertDatabaseCount('container_plans',2);
  $this->deleteJson(route('containers.delete',[$s,$p]),$delete)->assertOk();$areas=$p->fresh()->visual_document['visuals'][0]['hotspots'];
  $this->assertCount(1,$areas);$this->assertSame($data['area_id'],$areas[0]['id']);$this->assertSame('examine',$areas[0]['action']);$this->assertFalse($areas[0]['overlay']);
  foreach(['is_container','container_plan_id','state_flag','state_asset_id','overlay_bounds'] as $key)$this->assertArrayNotHasKey($key,$areas[0]);
  $this->assertDatabaseCount('container_plans',0);$this->assertDatabaseCount('scene_extractions',0);$this->assertSame($assetCount,Asset::count());$this->assertSame($inventoryBefore,$s->fresh()->inventory_document);$this->assertSame([],$s->fresh()->game_flags);

 }
 public function test_requires_drawn_container_and_explicit_preview_review():void {
  [$s,$p,$data,$asset]=$this->fixture();$bad=$data;$bad['area_id']=(string)Str::uuid();$this->postJson(route('containers.index',[$s,$p]),$bad)->assertUnprocessable();$this->assertDatabaseCount('container_plans',0);
  $plan=$this->postJson(route('containers.index',[$s,$p]),$data)->assertCreated()->json();$this->postJson(route('containers.prepare',[$s,$p,$plan['id']]),['preview_asset_id'=>$asset->id,'contents'=>array_map(fn($c)=>['area_id'=>$c['area_id'],'bounds'=>['x'=>0,'y'=>0,'width'=>.2,'height'=>.2]],$plan['contents'])])->assertUnprocessable();
  $this->actingAs(User::factory()->create())->getJson(route('containers.index',[$s,$p]))->assertNotFound();
 }
 public function test_companion_prepares_one_reviewable_plan_without_starting_generation():void {
  [$s,$p,$data,$asset]=$this->fixture();$fields=$data;unset($fields['visual_id'],$fields['area_id']);$proposal=['changes'=>[['scope'=>'create_container_plan','page_id'=>$p->id,'visual_id'=>$data['visual_id'],'area_id'=>$data['area_id'],'fields'=>$fields]]];
  $proposal['_review']=app(\App\Services\CompanionChanges::class)->prepare($s,$proposal);$this->assertCount(1,$proposal['_review']['items']);$this->assertDatabaseCount('container_plans',0);
  $m=$s->messages()->create(['role'=>'assistant','content'=>'Container plan','revision'=>$s->revision,'proposal'=>$proposal]);
  $this->postJson(route('companion.changes',[$s,$m]),['selected'=>[0]])->assertOk();$this->assertDatabaseCount('container_plans',1);$this->assertDatabaseCount('image_generations',0);$this->assertCount(1,$p->fresh()->visual_document['visuals'][0]['hotspots']);
 }

 public function test_deleting_container_removes_its_plans_and_extractions_but_keeps_shared_artwork():void {
  [$s,$p,$data,$asset]=$this->fixture();$plan=$this->postJson(route('containers.index',[$s,$p]),$data)->assertCreated()->json();
  $item=$s->inventory_document['visuals'][0];$this->assertNotEmpty(app(\App\Services\InventoryLibrary::class)->uses($s)[$item['id']]);
  $doc=$p->visual_document;$doc['visuals'][0]['hotspots']=[];
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>$p->visual_revision,'document'=>$doc])->assertOk();
  $this->assertDatabaseMissing('container_plans',['id'=>$plan['id']]);$this->assertDatabaseMissing('scene_extractions',['id'=>$plan['extraction_id']]);$this->assertDatabaseHas('assets',['id'=>$asset->id]);$this->assertArrayNotHasKey($item['id'],app(\App\Services\InventoryLibrary::class)->uses($s));
 }
 public function test_delete_draft_container_is_owned_and_preserves_original_area():void {
  [$s,$p,$data,$asset]=$this->fixture();$doc=$p->visual_document;$this->postJson(route('containers.index',[$s,$p]),$data)->assertCreated();$user=auth()->user();
  $this->actingAs(User::factory()->create())->deleteJson(route('containers.delete',[$s,$p]),['revision'=>$p->visual_revision,'visual_id'=>$data['visual_id'],'area_id'=>$data['area_id']])->assertNotFound();
  $this->actingAs($user)->deleteJson(route('containers.delete',[$s,$p]),['revision'=>$p->visual_revision,'visual_id'=>$data['visual_id'],'area_id'=>$data['area_id']])->assertOk();$this->assertSame($doc,$p->fresh()->visual_document);$this->assertDatabaseCount('container_plans',0);$this->assertDatabaseHas('assets',['id'=>$asset->id]);
 }

 public function test_padding_preserves_click_bounds_and_clamps_crop_to_scene():void {
  [$s,$p,$data,$asset]=$this->fixture();$data['padding']=['top'=>500,'right'=>20,'bottom'=>5,'left'=>5];$data['equal_padding']=false;
  $plan=$this->postJson(route('containers.index',[$s,$p]),$data)->assertCreated()->json();
  $this->assertEquals(['x'=>.1,'y'=>.1,'width'=>.5,'height'=>.5],$plan['bounds']);
  $this->assertEquals(['x'=>.05,'y'=>0,'width'=>.75,'height'=>.65],$plan['crop_bounds']);
  $crop=Asset::find($plan['source_asset_id']);$this->assertSame([75,65],array_slice(getimagesizefromstring(Storage::disk('local')->get($crop->path)),0,2));
  $batch=SceneExtraction::find($plan['extraction_id']);$outputs=$batch->outputs;$outputs[1]['accepted_asset_id']=$asset->id;$batch->update(['outputs'=>$outputs]);
  $marks=array_map(fn($c)=>['area_id'=>$c['area_id'],'bounds'=>['x'=>0,'y'=>0,'width'=>.2,'height'=>.2]],$plan['contents']);$marks=array_reverse($marks);
  $this->postJson(route('containers.prepare',[$s,$p,$plan['id']]),['preview_asset_id'=>$asset->id,'contents'=>$marks])->assertOk();
  $outputs=$batch->fresh()->outputs;foreach($outputs as &$o)$o['accepted_asset_id']=$asset->id;unset($o);$batch->update(['outputs'=>$outputs]);
  $this->postJson(route('containers.activate',[$s,$p,$plan['id']]),['revision'=>$p->visual_revision,'inventory_revision'=>$s->inventory_revision])->assertOk();
  $areas=$p->fresh()->visual_document['visuals'][0]['hotspots'];$this->assertEquals(.1,$areas[0]['x']);$this->assertEquals(.5,$areas[0]['width']);$this->assertEquals($plan['crop_bounds'],$areas[0]['overlay_bounds']);$this->assertSame($marks[0]['area_id'],$areas[1]['id']);$this->assertSame($marks[1]['area_id'],$areas[2]['id']);$this->assertEquals(.05,$areas[1]['x']);$this->assertEquals(0,$areas[1]['y']);
  $data['padding']['top']=501;$this->postJson(route('containers.index',[$s,$p]),$data)->assertUnprocessable();
  unset($data['padding']);unset($data['contents'][0]['object_id']);$this->postJson(route('containers.index',[$s,$p]),$data)->assertUnprocessable();
 }
 public function test_prompt_drafts_are_logged_but_only_final_generation_enters_chat():void {
  [$s,$p,$data,$asset]=$this->fixture();config(['story.openrouter_key'=>'test','story.openrouter_providers'=>['openai']]);
  \Illuminate\Support\Facades\Cache::put('openrouter.models',[['id'=>'openai/test','context_length'=>100000,'architecture'=>['output_modalities'=>['text']],'pricing'=>['prompt'=>.000002,'completion'=>.00001]]],60);
  \Illuminate\Support\Facades\Http::fake(['*/chat/completions'=>\Illuminate\Support\Facades\Http::response(['id'=>'draft','choices'=>[['message'=>['content'=>'Open the lid with both items inside.']]],'usage'=>['prompt_tokens'=>100,'completion_tokens'=>10]])]);
  for($i=0;$i<2;$i++)$this->postJson(route('containers.prompt',[$s,$p]),$data+['user_prompt'=>'Open the lid','model'=>'openai/test','request_key'=>(string)Str::uuid()])->assertOk()->assertJsonPath('prompt','Open the lid with both items inside.');
  $this->assertDatabaseCount('llm_requests',2);$this->assertDatabaseCount('chat_messages',0);$this->assertDatabaseCount('image_generations',0);
  $this->assertSame('container_prompt',\App\Models\LlmRequest::first()->request_content['purpose']);
  auth()->user()->forceFill(['openrouter_key'=>'personal-test'])->save();
  \Illuminate\Support\Facades\Cache::put('openrouter.image-models',[['id'=>'openai/image','name'=>'Image','architecture'=>['output_modalities'=>['image']]]],60);
  \Illuminate\Support\Facades\Cache::put('openrouter.image-endpoints.'.hash('sha256','openai/image'),[['provider_name'=>'OpenAI','provider_tag'=>'openai','supported_parameters'=>['input_references'=>['max'=>4]],'pricing'=>[['billable'=>'output_image','unit'=>'image','cost_usd'=>.05]]]],60);
  $plan=$this->postJson(route('containers.index',[$s,$p]),$data)->assertCreated()->json();$o=$plan['batch']['outputs'][1];$url=route('extractions.output',[$s,$p,$plan['extraction_id'],$o['id']]);
  $payload=['action'=>'generate','model'=>'openai/image','prompt'=>'Final edited composition.','container_user_prompt'=>'My final instructions','container_prompt_model'=>'openai/test'];
  $this->postJson($url,$payload)->assertOk()->assertJsonCount(2,'chat_messages');
  $this->assertSame(['My final instructions','Final edited composition.'],$s->messages()->orderBy('id')->pluck('content')->all());
  $this->assertSame('Final edited composition.',ContainerPlan::find($plan['id'])->prompt);
  $this->postJson($url,$payload)->assertUnprocessable();$this->assertDatabaseCount('chat_messages',2);
 }

 public function test_prompt_image_binary_does_not_count_as_text_context():void {
  [$s,$p,$data,$asset]=$this->fixture();config(['story.openrouter_key'=>'test','story.openrouter_providers'=>['openai']]);
  Storage::disk('local')->put($asset->path,Storage::disk('local')->get($asset->path).str_repeat('x',200000));
  \Illuminate\Support\Facades\Cache::put('openrouter.models',[['id'=>'openai/chat-choice','context_length'=>32000,'architecture'=>['input_modalities'=>['text','image'],'output_modalities'=>['text']],'pricing'=>['prompt'=>.000002,'completion'=>.00001]]],60);
  \Illuminate\Support\Facades\Http::fake(['*/chat/completions'=>\Illuminate\Support\Facades\Http::response(['id'=>'draft-image','choices'=>[['message'=>['content'=>'Open the stone.']]],'usage'=>['prompt_tokens'=>9000,'completion_tokens'=>10]])]);
  $this->postJson(route('containers.prompt',[$s,$p]),$data+['padding'=>['top'=>500,'right'=>20,'bottom'=>5,'left'=>5],'user_prompt'=>'Open the lid','model'=>'openai/chat-choice','request_key'=>(string)Str::uuid()])->assertOk()->assertJsonPath('model','openai/chat-choice');
  \Illuminate\Support\Facades\Http::assertSent(fn($r)=>$r['model']==='openai/chat-choice'&&array_slice(getimagesizefromstring(base64_decode(explode(',',$r['messages'][1]['content'][1]['image_url']['url'],2)[1])),0,2)===[75,65]);
  $this->assertDatabaseCount('chat_messages',0);$this->assertDatabaseCount('assets',1);
 }
}
