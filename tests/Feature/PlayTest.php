<?php
namespace Tests\Feature;
use App\Models\{Story,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PlayTest extends TestCase {
 use RefreshDatabase;
 private function story():array {
  $u=User::factory()->create();$s=Story::create(['user_id'=>$u->id,'title'=>'House','premise'=>'Explore','game_flags'=>[['key'=>'open','label'=>'Open','initial'=>false]],'inventory_document'=>['visuals'=>[['id'=>'key','kind'=>'object','name'=>'Key']]]]);
  $p=$s->pages()->create(['position'=>1,'title'=>'Porch','visual_document'=>['visuals'=>[['id'=>'porch','kind'=>'scene','name'=>'Porch','asset_id'=>null,'hotspots'=>[]]]]]);return [$u,$s,$p];
 }
 public function test_player_is_owner_only_and_renders_without_the_builder():void {
  [$u,$s,$p]=$this->story();$url=route('stories.play',[$s,'page'=>$p->id,'scene'=>'porch','inventory'=>['key'],'flags'=>['open']]);
  $this->get($url)->assertRedirect(route('login'));
  $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
  $this->actingAs($u)->get($url)->assertOk()->assertSee('gamePlayer',false)->assertDontSee('sceneEditor(',false)->assertDontSee('Creative companion')->assertViewHas('game',fn($g)=>$g['start']['inventory']===['key']&&$g['start']['flags']===['open']);
 }
 public function test_player_rejects_foreign_starting_data_and_does_not_write_story():void {
  [$u,$s,$p]=$this->story();$this->actingAs($u);$before=$s->fresh()->toArray();
  $this->getJson(route('stories.play',[$s,'page'=>$p->id,'inventory'=>['unknown']]))->assertUnprocessable();
  $this->getJson(route('stories.play',[$s,'page'=>$p->id,'flags'=>['unknown']]))->assertUnprocessable();
  $this->get(route('stories.play',[$s,'page'=>$p->id,'scene'=>'missing']))->assertUnprocessable();
  $this->get(route('stories.play',[$s,'page'=>$p->id]))->assertOk();$this->assertEquals($before,$s->fresh()->toArray());
 }
}
