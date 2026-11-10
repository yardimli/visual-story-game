<?php
namespace Tests\Feature;
use App\Models\{Story,User,ChatMessage};
use App\Services\CompanionChanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class InventoryLibraryTest extends TestCase {
 use RefreshDatabase;
 private function setupLibrary(): array {
  $u=User::factory()->create();$this->actingAs($u);$s=Story::create(['user_id'=>$u->id,'title'=>'Shared items','premise'=>'Find a key']);
  $item=['id'=>(string)Str::uuid(),'kind'=>'object','name'=>'Key','description'=>'Brass key','asset_id'=>null,'prompt'=>'','hotspots'=>[],'actions'=>[]];
  $s->update(['inventory_document'=>['visuals'=>[$item]]]);
  $p=$s->pages()->create(['position'=>1,'title'=>'Porch','visual_document'=>['visuals'=>[['id'=>(string)Str::uuid(),'kind'=>'scene','name'=>'Door','asset_id'=>null,'hotspots'=>[]]]]]);
  return [$u,$s->fresh(),$p->fresh(),$item];
 }
 public function test_shared_item_edit_pickup_usage_and_deletion_guards(): void {
  [$u,$s,$p,$item]=$this->setupLibrary();$item['description']='An old key';
  $this->putJson(route('inventory.update',$s),['revision'=>1,'items'=>[$item]])->assertOk()->assertJsonPath('revision',2);
  $doc=$p->visual_document;$doc['visuals'][0]['hotspots'][]=['id'=>(string)Str::uuid(),'label'=>'Take key','description'=>'','action'=>'pickup','object_id'=>$item['id'],'overlay'=>false,'x'=>0.1,'y'=>0.1,'width'=>0.2,'height'=>0.2];
  $this->putJson(route('visuals.update',[$s,$p]),['revision'=>1,'document'=>$doc])->assertOk();
  $this->getJson(route('inventory.show',$s))->assertOk()->assertJsonPath('items.0.description','An old key')->assertJsonPath('uses.'.$item['id'].'.0.scene_id',$doc['visuals'][0]['id']);
  $this->assertCount(1,$p->fresh()->visual_document['visuals']);
  $this->putJson(route('inventory.update',$s),['revision'=>1,'items'=>[$item]])->assertUnprocessable();
  $this->putJson(route('inventory.update',$s),['revision'=>2,'items'=>[]])->assertUnprocessable();
  $this->actingAs(User::factory()->create())->getJson(route('inventory.show',$s))->assertNotFound();
 }
 public function test_shared_actions_and_invalid_replacement(): void {
  [$u,$s,$p,$item]=$this->setupLibrary();$open=$item;$open['id']=(string)Str::uuid();$open['name']='Open key case';
  $item['actions']=[['id'=>(string)Str::uuid(),'label'=>'Open','target_id'=>$open['id']]];
  $this->putJson(route('inventory.update',$s),['revision'=>1,'items'=>[$item,$open]])->assertOk();
  $this->putJson(route('inventory.update',$s),['revision'=>2,'items'=>[$item]])->assertUnprocessable();
 }
 public function test_companion_creates_and_edits_game_wide_items_without_page_definitions(): void {
  [$u,$s,$p,$item]=$this->setupLibrary();$service=app(CompanionChanges::class);
  $proposal=['changes'=>[['scope'=>'inventory','item_id'=>$item['id'],'fields'=>['description'=>'A shared brass key']],['scope'=>'create_inventory','fields'=>['name'=>'Letter','description'=>'A sealed letter']]]];
  $proposal['_review']=$service->prepare($s,$proposal);
  $m=$s->messages()->create(['user_id'=>$u->id,'role'=>'assistant','content'=>'Review','proposal'=>$proposal]);
  $this->postJson(route('companion.changes',[$s,$m]),['selected'=>[0,1]])->assertOk();
  $this->assertCount(2,$s->fresh()->inventory_document['visuals']);$this->assertSame('A shared brass key',$s->fresh()->inventory_document['visuals'][0]['description']);$this->assertCount(1,$p->fresh()->visual_document['visuals']);
 }
}
