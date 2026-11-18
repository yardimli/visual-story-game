<?php
namespace Tests\Feature;
use App\Models\{Story,User,SceneExtraction};
use App\Services\StoryFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class GeneratedFlagKeysTest extends TestCase {
 use RefreshDatabase;
 public function test_inline_rename_preserves_references_and_rejects_collisions_and_foreign_users():void {
  $u=User::factory()->create();$this->actingAs($u);$s=Story::create(['user_id'=>$u->id,'title'=>'House','premise'=>'Test','game_flags'=>[['key'=>'switch_door','label'=>'Door','initial'=>false],['key'=>'unlocked','label'=>'Unlocked','initial'=>false]]]);
  $p=$s->pages()->create(['position'=>1,'title'=>'Porch','visual_document'=>['visuals'=>[['hotspots'=>[['state_flag'=>'switch_door'],['visible_when'=>[['key'=>'switch_door','value'=>true]],'disabled_when'=>[['key'=>'switch_door','value'=>false]],'flag_conditions'=>[['key'=>'switch_door','value'=>true]],'inventory_rules'=>[['flag_effects'=>[['key'=>'switch_door','value'=>true]]]]]]]]]]);
  $revision=$s->fresh()->flags_revision;$url=route('flags.rename',[$s,'switch_door']);
  $this->putJson($url,['revision'=>$revision,'key'=>'unlocked','label'=>'New'])->assertUnprocessable();
  $this->putJson($url,['revision'=>$revision,'key'=>'porch_door_open','label'=>'Porch door open'])->assertOk()->assertJsonPath('flags.0.key','porch_door_open');
  $areas=$p->fresh()->visual_document['visuals'][0]['hotspots'];$this->assertSame('porch_door_open',$areas[0]['state_flag']);$this->assertSame('porch_door_open',$areas[1]['visible_when'][0]['key']);$this->assertSame('porch_door_open',$areas[1]['disabled_when'][0]['key']);$this->assertSame('porch_door_open',$areas[1]['inventory_rules'][0]['flag_effects'][0]['key']);
  $url=route('flags.rename',[$s,'porch_door_open']);$this->putJson($url,['revision'=>$revision,'key'=>'new_name','label'=>'New'])->assertUnprocessable();
  $this->actingAs(User::factory()->create())->putJson($url,['revision'=>$s->fresh()->flags_revision,'key'=>'new_name','label'=>'New'])->assertNotFound();
 }
 public function test_generated_names_are_readable_unique_and_valid():void {
  $service=new StoryFlags;
  $this->assertSame('collected_brass_key',$service->generatedKey('Collected Brass Key',[]));
  $this->assertSame('collected_brass_key_2',$service->generatedKey('Collected Brass Key',[['key'=>'collected_brass_key'],['key'=>'collected_brass_key_1']]));
  $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]{0,63}$/',$service->generatedKey('123 '.str_repeat('long ',40),[]));
  $base=$service->generatedKey(str_repeat('long ',40),[]);$next=$service->generatedKey(str_repeat('long ',40),[['key'=>$base]]);$this->assertLessThanOrEqual(64,strlen($next));$this->assertStringEndsWith('_1',$next);
 }
 public function test_database_rename_updates_links_and_snapshots_once_with_backup():void {
  Storage::fake('local');$old='collected_'.str_repeat('a',32);$second='collected_'.str_repeat('b',32);$s=Story::create(['user_id'=>User::factory()->create()->id,'title'=>'Test','premise'=>'Test','game_flags'=>[['key'=>$old,'label'=>'Collected Key','initial'=>false],['key'=>$second,'label'=>'Collected Key','initial'=>true]],'inventory_document'=>['visuals'=>[['flag_effects'=>[['key'=>$old,'value'=>true]]]]]]);
  $doc=['visuals'=>[['hotspots'=>[['collectible_flag'=>$old,'state_flag'=>$second,'disabled_when'=>[['key'=>$old,'value'=>true]],'inventory_rules'=>[['flag_effects'=>[['key'=>$second,'value'=>false]]]]]]]]];
  $page=$s->pages()->create(['position'=>1,'title'=>'Page','visual_document'=>$doc]);$before=$page->fresh()->visual_revision;
  $asset=\App\Models\Asset::create(['story_id'=>$s->id,'name'=>'Test','path'=>'test.png','mime'=>'image/png','bytes'=>1,'role'=>'scene']);
  $batch=SceneExtraction::create(['story_id'=>$s->id,'story_page_id'=>$page->id,'visual_id'=>\Illuminate\Support\Str::uuid(),'source_asset_id'=>$asset->id,'mode'=>'container','outputs'=>[['source_parent'=>['state_flag'=>$second]]]]);
  $message=$s->messages()->create(['role'=>'assistant','content'=>'History kept','revision'=>1,'proposal'=>['fields'=>['flag_conditions'=>[['key'=>$old,'value'=>true]]]]]);
  $this->artisan('story:rename-generated-flags')->assertSuccessful();$this->assertSame($old,$s->fresh()->game_flags[0]['key']);
  $this->artisan('story:rename-generated-flags --apply')->assertSuccessful();$this->assertSame(['collected_key','collected_key_1'],array_column($s->fresh()->game_flags,'key'));
  $area=$page->fresh()->visual_document['visuals'][0]['hotspots'][0];$this->assertSame('collected_key',$area['collectible_flag']);$this->assertSame('collected_key',$area['disabled_when'][0]['key']);$this->assertSame('collected_key_1',$area['inventory_rules'][0]['flag_effects'][0]['key']);$this->assertSame('collected_key_1',$batch->fresh()->outputs[0]['source_parent']['state_flag']);$this->assertSame('collected_key',$message->fresh()->proposal['fields']['flag_conditions'][0]['key']);$this->assertSame($before+1,$page->fresh()->visual_revision);
  $this->assertCount(1,Storage::disk('local')->allFiles('flag-key-backups'));$this->artisan('story:rename-generated-flags --apply')->assertSuccessful();$this->assertCount(1,Storage::disk('local')->allFiles('flag-key-backups'));
 }
}
