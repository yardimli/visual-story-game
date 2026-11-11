<?php
namespace Tests\Feature;
use App\Models\{User,Story,Asset,ImageGeneration,SceneExtraction};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Storage,Cache,Queue};
use Illuminate\Support\Str;
use Tests\TestCase;
class SceneExtractionTest extends TestCase {
 use RefreshDatabase;
 private function asset(Story $s,bool $alpha=false):Asset {
  $im=imagecreatetruecolor(100,100);imagealphablending($im,false);imagesavealpha($im,true);imagefill($im,0,0,imagecolorallocatealpha($im,120,80,40,$alpha?127:0));ob_start();imagepng($im);$bytes=ob_get_clean();imagedestroy($im);$path='test/'.Str::uuid().'.png';Storage::disk('local')->put($path,$bytes);return Asset::create(['story_id'=>$s->id,'name'=>'Image','path'=>$path,'mime'=>'image/png','bytes'=>strlen($bytes),'role'=>'scene']);
 }
 private function fixture():array {
  Storage::fake('local');Queue::fake();$u=User::factory()->create();$u->forceFill(['openrouter_key'=>'test-key'])->save();$this->actingAs($u);
  $s=Story::create(['user_id'=>$u->id,'title'=>'House','premise'=>'Test']);$a=$this->asset($s);$item=['id'=>(string)Str::uuid(),'name'=>'Key','description'=>'Brass','kind'=>'object','asset_id'=>null,'hotspots'=>[]];$s->update(['inventory_document'=>['visuals'=>[$item]]]);
  $area=['id'=>(string)Str::uuid(),'label'=>'Key','action'=>'pickup_v2','object_id'=>$item['id'],'target_id'=>null,'description'=>'','overlay'=>false,'x'=>.1,'y'=>.2,'width'=>.2,'height'=>.3];
  $v=['id'=>(string)Str::uuid(),'name'=>'Hall','kind'=>'scene','asset_id'=>$a->id,'hotspots'=>[$area]];
  $p=$s->pages()->create(['position'=>1,'title'=>'Hall','visual_document'=>['visuals'=>[$v]]]);$s->refresh();$p->refresh();
  $data=['visual_id'=>$v['id'],'revision'=>$p->visual_revision,'areas'=>[['id'=>$area['id'],'bounds'=>['x'=>.08,'y'=>.18,'width'=>.25,'height'=>.35]]]];
  Cache::put('openrouter.image-models',[['id'=>'openai/image','name'=>'Image','architecture'=>['output_modalities'=>['image']]]],60);
  Cache::put('openrouter.image-endpoints.'.hash('sha256','openai/image'),[['provider_name'=>'OpenAI','provider_tag'=>'openai','supported_parameters'=>['input_references'=>['max'=>4]],'pricing'=>[['billable'=>'output_image','unit'=>'image','cost_usd'=>.05]]]],60);
  return [$u,$s,$p,$data];
 }
 public function test_background_uses_numbered_reference_with_optional_original():void {
  [$u,$s,$p,$data]=$this->fixture();$original=$s->assets()->first();$originalBytes=Storage::disk('local')->get($original->path);
  foreach(['original_and_annotated'=>2,'annotated_only'=>1] as $mode=>$count){
   $data['background_references']=$mode;
   $batch=$this->postJson(route('extractions.store',[$s,$p]),$data)->assertCreated()->json();$output=$batch['outputs'][0];
   $this->assertCount($count,$output['references']);$this->assertSame($mode,$output['reference_mode']);
   if($count===2)$this->assertSame($original->id,$output['references'][0]);
   $annotated=Asset::findOrFail(end($output['references']));$bytes=Storage::disk('local')->get($annotated->path);
   $this->assertNotSame($originalBytes,$bytes);$this->assertSame([100,100],array_slice(getimagesizefromstring($bytes),0,2));
   $this->assertStringContainsString('1. Key',$output['prompt']);$this->assertStringNotContainsString('normalized rectangle',$output['prompt']);
   $this->assertStringContainsString($count===2?'Edit image 1':'Edit the supplied annotated',$output['prompt']);
   $this->assertCount(1,$batch['outputs'][1]['references']);
  }
  $this->assertSame($originalBytes,Storage::disk('local')->get($original->path));$this->assertDatabaseCount('image_generations',0);
 }
 public function test_missing_items_are_created_linked_and_reused_on_later_plans():void {
  [$u,$s,$p,$data]=$this->fixture();$doc=$p->visual_document;$missing=$doc['visuals'][0]['hotspots'][0];$missing['id']=(string)Str::uuid();$missing['label']='Fedora';$missing['description']='A felt hat';$missing['object_id']=null;$doc['visuals'][0]['hotspots'][]=$missing;$p->update(['visual_document'=>$doc]);
  $data['areas'][]=['id'=>$missing['id'],'bounds'=>['x'=>.5,'y'=>.5,'width'=>.2,'height'=>.2]];
  $result=$this->postJson(route('extractions.store',[$s,$p]),$data)->assertCreated()->assertJsonCount(5,'outputs')->json();
  $items=$s->fresh()->inventory_document['visuals'];$this->assertCount(2,$items);$this->assertSame('Fedora',$items[1]['name']);$this->assertSame('A felt hat',$items[1]['description']);$this->assertNull($items[1]['asset_id']);
  $this->assertSame($items[1]['id'],$p->fresh()->visual_document['visuals'][0]['hotspots'][1]['object_id']);$this->assertSame($items[0]['id'],$p->fresh()->visual_document['visuals'][0]['hotspots'][0]['object_id']);$this->assertSame($p->visual_revision+1,$result['revision']);
  $data['revision']=$result['revision'];$this->postJson(route('extractions.store',[$s,$p]),$data)->assertCreated();$this->assertCount(2,$s->fresh()->inventory_document['visuals']);$this->assertDatabaseCount('image_generations',0);
 }
 public function test_invalid_plan_does_not_leave_created_inventory_items_or_links():void {
  [$u,$s,$p,$data]=$this->fixture();$doc=$p->visual_document;$doc['visuals'][0]['hotspots'][0]['object_id']=null;$p->update(['visual_document'=>$doc]);$before=$s->inventory_document;$data['areas'][0]['bounds']['x']=.99;
  $this->postJson(route('extractions.store',[$s,$p]),$data)->assertUnprocessable();$this->assertSame($before,$s->fresh()->inventory_document);$this->assertNull($p->fresh()->visual_document['visuals'][0]['hotspots'][0]['object_id']);
 }
 public function test_legacy_inventory_without_description_can_be_extracted():void {
  [$u,$s,$p,$data]=$this->fixture();$inventory=$s->inventory_document;unset($inventory['visuals'][0]['description']);$s->update(['inventory_document'=>$inventory]);
  $this->postJson(route('extractions.store',[$s,$p]),$data)->assertCreated()->assertJsonCount(3,'outputs');
  $this->assertCount(1,$s->fresh()->inventory_document['visuals']);$this->assertDatabaseCount('image_generations',0);
 }
 public function test_switches_use_two_local_patches_without_inventory_or_scene_replacement():void {
  [$u,$s,$p,$data]=$this->fixture();$doc=$p->visual_document;$doc['visuals'][0]['hotspots'][0]['action']='state_v2';$doc['visuals'][0]['hotspots'][0]['object_id']=null;$doc['visuals'][0]['hotspots'][0]['state_prompt']='Button pressed inward';$doc['visuals'][0]['hotspots'][0]['state_description']='The button is pressed.';$p->update(['visual_document'=>$doc]);$data['mode']='switches';$beforeInventory=$s->inventory_document;
  $b=$this->postJson(route('extractions.store',[$s,$p]),$data)->assertCreated()->assertJsonCount(2,'outputs')->json();$this->assertSame('switches',$b['mode']);$this->assertSame('switch_off',$b['outputs'][0]['type']);$this->assertNotNull($b['outputs'][0]['accepted_asset_id']);$this->assertSame($beforeInventory,$s->fresh()->inventory_document);
  $o=$b['outputs'][1];$asset=$this->asset($s);$job=ImageGeneration::create(['user_id'=>$u->id,'story_id'=>$s->id,'story_page_id'=>$p->id,'visual_id'=>(string)Str::uuid(),'kind'=>'scene','name'=>'Pressed','request_key'=>(string)Str::uuid(),'status'=>'completed','asset_ids'=>[$asset->id],'payload'=>[],'references'=>[],'pricing'=>[]]);
  $batch=SceneExtraction::find($b['id']);$outputs=$batch->outputs;$outputs[1]['versions']=[$job->id];$batch->update(['outputs'=>$outputs]);
  $this->postJson(route('extractions.output',[$s,$p,$batch,$o['id']]),['action'=>'accept','asset_id'=>$asset->id])->assertOk();
  $this->assertSame(0,$s->messages()->count());
  $this->postJson(route('extractions.activate',[$s,$p,$batch]),['revision'=>$p->visual_revision,'inventory_revision'=>$s->inventory_revision])->assertOk();
  $this->assertSame(1,$s->messages()->count());$this->assertStringContainsString('Switches applied',$s->messages()->first()->content);
  $visual=$p->fresh()->visual_document['visuals'][0];$h=$visual['hotspots'][0];$this->assertSame($doc['visuals'][0]['asset_id'],$visual['asset_id']);$this->assertSame($asset->id,$h['state_asset_id']);$this->assertSame('The button is pressed.',$h['state_description']);$this->assertNull($h['object_id']);$this->assertSame($beforeInventory,$s->fresh()->inventory_document);$this->assertSame($s->inventory_revision,$s->fresh()->inventory_revision);$this->assertNotEmpty($h['state_flag']);
 }
 public function test_legacy_pickup_is_not_eligible_for_v2_extraction():void {
  [$u,$s,$p,$data]=$this->fixture();$doc=$p->visual_document;$doc['visuals'][0]['hotspots'][0]['action']='pickup';$p->update(['visual_document'=>$doc]);
  $this->postJson(route('extractions.store',[$s,$p]),$data)->assertUnprocessable();$this->assertSame($doc,$p->fresh()->visual_document);
 }
 public function test_preparation_is_free_and_activation_requires_reviewed_outputs():void {
  [$u,$s,$p,$data]=$this->fixture();$original=$p->visual_document;
  $batch=$this->postJson(route('extractions.store',[$s,$p]),$data)->assertCreated()->assertJsonCount(3,'outputs')->json();
  $this->assertDatabaseCount('image_generations',0);$this->assertDatabaseCount('llm_requests',0);$this->assertSame($original,$p->fresh()->visual_document);
  $this->postJson(route('extractions.activate',[$s,$p,$batch['id']]),['revision'=>$p->visual_revision,'inventory_revision'=>$s->inventory_revision])->assertUnprocessable();
  foreach($batch['outputs'] as $o){
   $a=$this->asset($s,$o['type']==='layer');$j=ImageGeneration::create(['user_id'=>$u->id,'story_id'=>$s->id,'story_page_id'=>$p->id,'visual_id'=>(string)Str::uuid(),'kind'=>'scene','name'=>'Result','request_key'=>(string)Str::uuid(),'status'=>'completed','asset_ids'=>[$a->id],'payload'=>[],'references'=>[],'pricing'=>[]]);
   $b=SceneExtraction::find($batch['id']);$outputs=$b->outputs;foreach($outputs as &$row)if($row['id']===$o['id'])$row['versions'][]=$j->id;unset($row);$b->update(['outputs'=>$outputs]);
   $this->postJson(route('extractions.output',[$s,$p,$b,$o['id']]),['action'=>'accept','asset_id'=>$a->id])->assertOk();
  }
  $this->assertSame(0,$s->messages()->count());
  $this->postJson(route('extractions.activate',[$s,$p,$batch['id']]),['revision'=>$p->visual_revision,'inventory_revision'=>$s->inventory_revision])->assertOk();
  $this->assertSame(1,$s->messages()->count());$this->assertStringContainsString('Key: removable scene layer',$s->messages()->first()->content);
  $h=$p->fresh()->visual_document['visuals'][0]['hotspots'][0];$this->assertTrue($h['overlay']);$this->assertSame(.08,$h['overlay_bounds']['x']);$this->assertSame(.1,$h['x']);$this->assertNull($h['target_id']);$this->assertFalse($h['flag_conditions'][0]['value']);$this->assertTrue($h['flag_effects'][0]['value']);$this->assertNotNull($s->fresh()->inventory_document['visuals'][0]['asset_id']);
  $this->assertSame($original['visuals'][0]['asset_id'],SceneExtraction::find($batch['id'])->source_asset_id);
  $this->postJson(route('extractions.activate',[$s,$p,$batch['id']]),['revision'=>$p->visual_revision,'inventory_revision'=>$s->inventory_revision])->assertUnprocessable();
 }
 public function test_versions_are_independent_and_active_output_is_not_submitted_twice():void {
  [$u,$s,$p,$data]=$this->fixture();$b=$this->postJson(route('extractions.store',[$s,$p]),$data)->assertCreated()->json();$o=$b['outputs'][0];$url=route('extractions.output',[$s,$p,$b['id'],$o['id']]);
  $this->postJson($url,['action'=>'quote','model'=>'openai/image'])->assertOk()->assertJsonStructure(['estimate']);$this->assertDatabaseCount('image_generations',0);
  $this->postJson($url,['action'=>'generate','model'=>'openai/image'])->assertOk();$this->assertDatabaseCount('image_generations',1);
  $this->postJson($url,['action'=>'generate','model'=>'openai/image'])->assertUnprocessable();
  $job=ImageGeneration::first();$job->update(['status'=>'failed']);
  $this->postJson($url,['action'=>'generate','model'=>'openai/image','prompt'=>'Improved background'])->assertOk();
  $outputs=SceneExtraction::find($b['id'])->outputs;$this->assertCount(2,$outputs[0]['versions']);$this->assertCount(0,$outputs[1]['versions']);$this->assertNull($outputs[0]['accepted_asset_id']);
  $this->actingAs(User::factory()->create())->getJson(route('extractions.index',[$s,$p]))->assertNotFound();
  $this->postJson($url,['action'=>'generate','model'=>'openai/image'])->assertNotFound();
 }
 public function test_completed_layers_auto_accept_and_unaccept_persists_until_a_new_render_completes():void {
  [$u,$s,$p,$data]=$this->fixture();$b=$this->postJson(route('extractions.store',[$s,$p]),$data)->assertCreated()->json();$o=$b['outputs'][1];$asset=$this->asset($s);
  $job=ImageGeneration::create(['user_id'=>$u->id,'story_id'=>$s->id,'story_page_id'=>$p->id,'visual_id'=>(string)Str::uuid(),'kind'=>'scene','name'=>'Opaque layer','request_key'=>(string)Str::uuid(),'status'=>'completed','asset_ids'=>[$asset->id],'payload'=>[],'references'=>[],'pricing'=>[]]);
  $batch=SceneExtraction::find($b['id']);$outputs=$batch->outputs;$outputs[1]['versions']=[$job->id];$batch->update(['outputs'=>$outputs]);$url=route('extractions.output',[$s,$p,$batch,$o['id']]);
  $this->getJson(route('extractions.index',[$s,$p]))->assertOk();$this->assertSame($asset->id,$batch->fresh()->outputs[1]['accepted_asset_id']);
  $this->postJson($url,['action'=>'unaccept'])->assertOk();
  $this->getJson(route('extractions.index',[$s,$p]))->assertOk();$this->assertNull($batch->fresh()->outputs[1]['accepted_asset_id']);
  $this->assertSame($p->visual_document,$p->fresh()->visual_document);
  $new=$job->replicate();$new->request_key=(string)Str::uuid();$new->status='running';$new->asset_ids=[];$new->save();
  $outputs=$batch->fresh()->outputs;$outputs[1]['versions'][]=$new->id;$batch->update(['outputs'=>$outputs]);
  $this->getJson(route('extractions.index',[$s,$p]))->assertOk();$this->assertNull($batch->fresh()->outputs[1]['accepted_asset_id']);
  $replacement=$this->asset($s,true);$new->update(['status'=>'completed','asset_ids'=>[$replacement->id]]);
  $this->getJson(route('extractions.index',[$s,$p]))->assertOk();$this->assertSame($replacement->id,$batch->fresh()->outputs[1]['accepted_asset_id']);
  $this->postJson($url,['action'=>'accept','asset_id'=>$asset->id])->assertOk();
  $this->getJson(route('extractions.index',[$s,$p]))->assertOk();$this->assertSame($asset->id,$batch->fresh()->outputs[1]['accepted_asset_id']);
  $bounds=['x'=>.2,'y'=>.2,'width'=>.3,'height'=>.3];$this->postJson($url,['action'=>'align','bounds'=>$bounds])->assertOk();$this->assertSame($bounds,$batch->fresh()->outputs[1]['bounds']);$this->assertSame(.1,$p->fresh()->visual_document['visuals'][0]['hotspots'][0]['x']);
 }
 public function test_invalid_rectangles_and_foreign_acceptance_are_rejected():void {
  [$u,$s,$p,$data]=$this->fixture();$invalid=$data;$invalid['areas'][0]['bounds']['x']=.9;$this->postJson(route('extractions.store',[$s,$p]),$invalid)->assertUnprocessable();$this->assertDatabaseCount('scene_extractions',0);
  $b=$this->postJson(route('extractions.store',[$s,$p]),$data)->assertCreated()->json();
  $this->postJson(route('extractions.output',[$s,$p,$b['id'],$b['outputs'][1]['id']]),['action'=>'accept','asset_id'=>$b['source_asset_id']])->assertUnprocessable();
 }
}
