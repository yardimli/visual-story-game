<?php
namespace Tests\Feature;
use App\Models\{User,LlmRequest,ImageGeneration,Story};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class AiCallHistoryTest extends TestCase {
 use RefreshDatabase;
 private function makeCall(User $u,array $extra=[]): LlmRequest {return LlmRequest::create(array_merge(['user_id'=>$u->id,'model'=>'test/model','request_key'=>(string)Str::uuid(),'funding'=>'personal','status'=>'completed','cost'=>1234],$extra));}
 public function test_summaries_paginate_fifty_without_loading_payloads(): void {
  $u=User::factory()->create();
  for($i=0;$i<51;$i++)$this->makeCall($u,['model'=>'model-'.$i,'request_content'=>['secret'=>'large private contents'],'response_content'=>['answer'=>'large response']]);
  $this->makeCall(User::factory()->create(),['model'=>'foreign-model']);
  DB::enableQueryLog();
  $response=$this->actingAs($u)->get(route('ai.edit'));
  $response->assertOk()->assertViewHas('calls',fn($calls)=>$calls->count()===50 && $calls->total()===51)->assertDontSee('large private contents')->assertDontSee('large response')->assertDontSee('foreign-model');
  $queries=collect(DB::getQueryLog())->pluck('query')->filter(fn($q)=>str_contains($q,'llm_requests'))->implode(' ');
  $this->assertStringNotContainsString('request_content',$queries);$this->assertStringNotContainsString('response_content',$queries);
  $this->get(route('ai.edit',['page'=>2]))->assertOk()->assertViewHas('calls',fn($calls)=>$calls->count()===1);
 }
 public function test_details_are_private_lazy_and_redacted(): void {
  $u=User::factory()->create();$call=$this->makeCall($u,['request_content'=>['messages'=>[['role'=>'user','content'=>'Hello sk-or-example-secret']]],'response_content'=>['answer'=>'<script>alert(1)</script>','authorization'=>'Bearer secret']]);
  $this->actingAs($u)->getJson(route('ai.calls.show',$call->id))->assertOk()->assertJsonPath('request.messages.0.content','Hello [redacted]')->assertJsonPath('response.authorization','[redacted]')->assertJsonPath('accounting.cost_usd',0.001234)->assertHeader('Cache-Control','no-store, private');
  $old=$this->makeCall($u,['cost'=>null]);$this->getJson(route('ai.calls.show',$old->id))->assertJsonPath('request',null)->assertJsonPath('accounting.cost_usd',null)->assertJsonPath('note','Detailed request and response contents were not recorded for this older call.');
  $this->actingAs(User::factory()->create())->getJson(route('ai.calls.show',$call->id))->assertNotFound();
 }
 public function test_existing_image_call_details_use_stored_job_without_file_paths(): void {
  $u=User::factory()->create();$s=Story::create(['user_id'=>$u->id,'title'=>'House','premise'=>'Explore']);$p=$s->pages()->create(['position'=>1,'title'=>'Page 1']);$call=$this->makeCall($u,['story_id'=>$s->id]);
  ImageGeneration::create(['user_id'=>$u->id,'story_id'=>$s->id,'story_page_id'=>$p->id,'llm_request_id'=>$call->id,'visual_id'=>(string)Str::uuid(),'name'=>'Envelope','kind'=>'object','request_key'=>(string)Str::uuid(),'pricing'=>[],'payload'=>['prompt'=>'Open envelope'],'references'=>[['id'=>5,'path'=>'private/path','mime'=>'image/png']],'status'=>'failed','error'=>'Provider failed']);
  $this->actingAs($u)->getJson(route('ai.calls.show',$call->id))->assertOk()->assertJsonPath('request.parameters.prompt','Open envelope')->assertJsonPath('response.error','Provider failed')->assertDontSee('private/path');
 }
}


