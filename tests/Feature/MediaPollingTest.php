<?php
namespace Tests\Feature;

use App\Models\{User,Story,ImageGeneration,AudioGeneration};
use App\Services\PollingSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaPollingTest extends TestCase {
 use RefreshDatabase;

 public function test_status_polls_exclude_libraries_and_scope_requested_ids():void {
  $user=User::factory()->create();$this->actingAs($user);
  $story=Story::create(['user_id'=>$user->id,'title'=>'Polling','premise'=>'Test']);
  $other=Story::create(['user_id'=>$user->id,'title'=>'Other','premise'=>'Test']);
  $page=$story->pages()->create(['position'=>1,'title'=>'Room']);
  foreach(['image','audio'] as $type){
   $make=function($s)use($type,$user,$page){
    $base=['story_id'=>$s->id,'request_key'=>(string)Str::uuid(),'name'=>'Job','status'=>'running'];
    return $type==='image'
     ? ImageGeneration::create($base+['user_id'=>$user->id,'story_page_id'=>$page->id,'visual_id'=>(string)Str::uuid(),'kind'=>'scene','payload'=>['prompt'=>str_repeat('secret',1000)],'references'=>[],'pricing'=>[]])
     : AudioGeneration::create($base+['prompt'=>str_repeat('secret',1000),'seconds'=>5]);
   };
   $job=$make($story);$unrequested=$make($story);$foreign=$make($other);
   $url=($type==='image'?route('visuals.show',[$story,$page]):route('audio.index',$story)).'?'.http_build_query(['status_ids'=>[$job->id,$foreign->id]]);
   $response=$this->getJson($url)->assertOk()->assertJsonCount(1,'jobs')->assertJsonPath('jobs.0.id',$job->id);
   $this->assertLessThan(500,strlen($response->getContent()));
   $this->assertArrayNotHasKey('payload',$response->json('jobs.0'));
   $this->assertArrayNotHasKey('prompt',$response->json('jobs.0'));
   $job->update(['status'=>'completed']);
   $this->getJson($url)->assertJsonPath('jobs.0.status','completed');
   $this->actingAs(User::factory()->create())->getJson($url)->assertNotFound();
   $this->actingAs($user);
  }
 }

 public function test_unchanged_plan_snapshot_is_small_and_changes_invalidate_it():void {
  $service=new PollingSnapshot();$data=['plans'=>[['prompt'=>str_repeat('plan',1000)]]];
  $first=$service->response(Request::create('/'),$data)->getData(true);
  $request=Request::create('/','GET',['poll_version'=>$first['version']]);
  $same=$service->response($request,$data);
  $this->assertTrue($same->getData(true)['unchanged']);
  $this->assertLessThan(120,strlen($same->getContent()));
  $changed=$service->response($request,['plans'=>[]])->getData(true);
  $this->assertNotSame($first['version'],$changed['version']);
  $this->assertArrayHasKey('plans',$changed);
 }
}
