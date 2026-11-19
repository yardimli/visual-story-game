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
 public function test_gameplay_messages_save_validate_and_reach_player():void {
  [$u,$s,$p]=$this->story();$this->actingAs($u);
  $payload=['revision'=>$s->fresh()->revision,'title'=>$s->title,'premise'=>$s->premise,'genre'=>'Mystery','language'=>'English','gameplay_messages'=>['placed'=>['text'=>'Back in its slot.','timeout_ms'=>750]]];
  $this->putJson(route('stories.update',$s),$payload)->assertOk();
  $this->assertSame('Back in its slot.',$s->fresh()->gameplay_messages['placed']['text']);
  $this->get(route('stories.play',[$s,'page'=>$p->id]))->assertOk()->assertViewHas('game',fn($g)=>$g['messages']['placed']['text']==='Back in its slot.'&&(int)$g['messages']['placed']['timeout_ms']===750&&$g['messages']['nothing']['text']==='Nothing happens.');
  $payload['revision']=$s->fresh()->revision;$payload['gameplay_messages']['placed']['timeout_ms']=-1;
  $this->putJson(route('stories.update',$s),$payload)->assertUnprocessable();
 }
 public function test_player_and_editor_choose_newest_recording_for_the_same_description():void {
  [$u,$s,$p]=$this->story();$this->actingAs($u);
  $first=\App\Models\StorySound::create(['story_id'=>$s->id,'name'=>'Original voice','source_text'=>'The hall is quiet.','provider'=>'openrouter','path'=>'first.wav','mime'=>'audio/wav']);
  $second=\App\Models\StorySound::create(['story_id'=>$s->id,'name'=>'Replacement voice','source_text'=>'The hall is quiet.','provider'=>'openrouter','path'=>'second.wav','mime'=>'audio/wav']);
  $second->update(['created_at'=>$first->created_at]);
  $this->getJson(route('audio.index',$s))->assertOk()->assertJsonPath('sounds.0.id',$second->id);
  $this->get(route('stories.play',[$s,'page'=>$p->id]))->assertOk()->assertViewHas('game',fn($g)=>$g['sounds']->first()['id']===$second->id&&$g['sounds']->last()['id']===$first->id);
 }
}
