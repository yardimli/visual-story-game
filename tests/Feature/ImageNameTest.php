<?php
namespace Tests\Feature;
use App\Models\{User,Story,LlmRequest};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache,Http};
use Illuminate\Support\Str;
use Tests\TestCase;
class ImageNameTest extends TestCase {
 use RefreshDatabase;
 private function setupNaming(): array {
  config(['story.openrouter_key'=>'platform-test','story.openrouter_providers'=>['openai']]);
  $user=User::factory()->create();$this->actingAs($user);$story=Story::create(['user_id'=>$user->id,'title'=>'House','premise'=>'Mystery']);
  Cache::put('openrouter.models',[['id'=>'openai/gpt-5.5','name'=>'OpenAI: GPT-5.5','context_length'=>100000,'architecture'=>['output_modalities'=>['text']],'pricing'=>['prompt'=>0.000002,'completion'=>0.00001]]],60);
  return [$user,$story,['model'=>'openai/gpt-5.5','prompt'=>'A brass key on a rainy doorstep.','request_key'=>(string)Str::uuid()]];
 }
 public function test_naming_uses_selected_model_logs_call_and_charges_catalog_tokens(): void {
  [$u,$s,$payload]=$this->setupNaming();
  Http::fake(['*/chat/completions'=>Http::response(['id'=>'naming-1','choices'=>[['message'=>['content'=>'Rainy Doorstep Key']]],'usage'=>['prompt_tokens'=>100,'completion_tokens'=>5,'cost'=>0]])]);
  $this->postJson(route('images.name',$s),$payload)->assertOk()->assertJsonPath('name','Rainy Doorstep Key');
  $this->assertSame(250,$u->fresh()->llm_spent);$this->assertSame(0,$u->fresh()->llm_reserved);
  $this->assertSame('image_name',LlmRequest::first()->request_content['purpose']);
  $this->assertDatabaseCount('chat_messages',0);$this->assertDatabaseCount('image_generations',0);
  Http::assertSent(fn($r)=>$r['model']==='openai/gpt-5.5' && $r['max_tokens']===512 && $r['messages'][1]['content']===$payload['prompt']);
  $this->postJson(route('images.name',$s),$payload)->assertUnprocessable();Http::assertSentCount(1);
 }
 public function test_naming_checks_ownership_and_exhausted_credit_before_calling_provider(): void {
  [$u,$s,$payload]=$this->setupNaming();Http::fake();
  $u->forceFill(['llm_spent'=>1000000])->save();$this->postJson(route('images.name',$s),$payload)->assertUnprocessable();
  $this->actingAs(User::factory()->create())->postJson(route('images.name',$s),$payload)->assertNotFound();Http::assertNothingSent();
 }
 public function test_personal_naming_does_not_spend_trial_and_provider_failure_does_not_queue_image(): void {
  [$u,$s,$payload]=$this->setupNaming();$u->forceFill(['openrouter_key'=>'personal-test','llm_spent'=>1000000])->save();
  Http::fake(['*/chat/completions'=>Http::response(['error'=>['message'=>'Rejected']],400)]);
  $this->postJson(route('images.name',$s),$payload)->assertUnprocessable();
  $this->assertSame('personal',LlmRequest::first()->funding);$this->assertSame('failed',LlmRequest::first()->status);$this->assertSame(1000000,$u->fresh()->llm_spent);$this->assertDatabaseCount('image_generations',0);Http::assertSentCount(1);
 }
}
