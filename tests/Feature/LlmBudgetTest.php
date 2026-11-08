<?php
namespace Tests\Feature;
use App\Models\{User,Story,LlmRequest};
use App\Services\{LlmBudget,OpenRouter};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Cache};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
class LlmBudgetTest extends TestCase {
    use RefreshDatabase;
    private function setupStory(): array {
        config(['story.openrouter_providers'=>['test','openai'], 'story.openrouter_key'=>'test-platform-key','story.allowance'=>1000000]);
        $u=User::factory()->create();$s=Story::create(['user_id'=>$u->id,'title'=>'Test','premise'=>'Test']);
        $s->pages()->create(['position'=>1,'title'=>'Start']);
        Cache::put('openrouter.models',[['id'=>'test/model','name'=>'Test','context_length'=>100000,'architecture'=>['output_modalities'=>['text']],'pricing'=>['prompt'=>'0.000001','completion'=>'0.000002','request'=>'0']]],600);
        return [$u,$s];
    }
    public function test_reservations_prevent_a_second_request_spending_same_allowance(): void {
        [$u,$s]=$this->setupStory();$b=app(LlmBudget::class);
        $b->reserve($u,$s,(string)Str::uuid(),'test/model',700000);
        try { $b->reserve($u,$s,(string)Str::uuid(),'test/model',400000);$this->fail('Overspend accepted'); }
        catch(ValidationException $e) { $this->assertSame(700000,$u->fresh()->llm_reserved); }
    }
    public function test_settlement_is_idempotent_and_reconciles_unknown_cost(): void {
        [$u,$s]=$this->setupStory();$b=app(LlmBudget::class);$r=$b->reserve($u,$s,(string)Str::uuid(),'test/model',500000);
        $b->settle($r,null,'uncertain');$this->assertSame(500000,$u->fresh()->llm_reserved);
        $b->settle($r,120000,'completed');$b->settle($r,120000,'completed');
        $this->assertSame(120000,$u->fresh()->llm_spent);$this->assertSame(0,$u->fresh()->llm_reserved);
    }
    public function test_chat_records_actual_cost_and_blocks_duplicate_submission(): void {
        [$u,$s]=$this->setupStory();
        Http::fake(['*/chat/completions'=>Http::response(['id'=>'gen-test','usage'=>['cost'=>0,'prompt_tokens'=>500,'completion_tokens'=>1000],'choices'=>[['message'=>['content'=>'A thoughtful plan.']]]])]);
        $data=['message'=>'Plan this','model'=>'test/model','request_key'=>(string)Str::uuid()];
        $this->actingAs($u)->post(route('chat.store',$s),$data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2500,$u->fresh()->llm_spent);$this->assertSame(0,$u->fresh()->llm_reserved);
        $this->assertSame(2,$s->messages()->count());
        $this->post(route('chat.store',$s),$data)->assertSessionHasErrors('message');
        Http::assertSentCount(1);
    }
    public function test_exhausted_allowance_never_calls_provider(): void {
        [$u,$s]=$this->setupStory();$u->forceFill(['llm_spent'=>1000000])->save();Http::fake();
        $this->actingAs($u)->post(route('chat.store',$s),['message'=>'Hi','model'=>'test/model','request_key'=>(string)Str::uuid()])->assertSessionHasErrors('message');
        Http::assertNothingSent();
    }
    public function test_personal_key_bypasses_allowance_without_platform_fallback(): void {
        [$u,$s]=$this->setupStory();$u->forceFill(['openrouter_key'=>'sk-or-v1-own-key','llm_spent'=>1000000])->save();
        Http::fake(['*/chat/completions'=>Http::response(['usage'=>['cost'=>0,'prompt_tokens'=>1000,'completion_tokens'=>500],'choices'=>[['message'=>['content'=>'Hello']]]])]);
        $this->actingAs($u)->post(route('chat.store',$s),['message'=>'Hi','model'=>'test/model','request_key'=>(string)Str::uuid()])->assertSessionHasNoErrors();
        Http::assertSent(fn($r)=>$r->hasHeader('Authorization','Bearer sk-or-v1-own-key'));
        $this->assertSame(1000000,$u->fresh()->llm_spent);$this->assertSame('personal',LlmRequest::first()->funding);
    }
    public function test_unknown_cost_keeps_reservation_and_records_response(): void {
        [$u,$s]=$this->setupStory();
        Http::fake(['*/chat/completions'=>Http::response(['id'=>'gen-unknown','choices'=>[['message'=>['content'=>'Hello']]]])]);
        $this->actingAs($u)->post(route('chat.store',$s),['message'=>'Hi','model'=>'test/model','request_key'=>(string)Str::uuid()])->assertRedirect();
        $this->assertGreaterThan(0,$u->fresh()->llm_reserved);$this->assertSame('uncertain',LlmRequest::first()->status);
    }
    public function test_ambiguous_failure_does_not_refund_or_retry(): void {
        [$u,$s]=$this->setupStory();Http::fake(['*/chat/completions'=>Http::response(['error'=>['message'=>'Failure']],500)]);
        $this->actingAs($u)->post(route('chat.store',$s),['message'=>'Hi','model'=>'test/model','request_key'=>(string)Str::uuid()])->assertSessionHasErrors('message');
        $this->assertGreaterThan(0,$u->fresh()->llm_reserved);Http::assertSentCount(1);
    }
    public function test_unknown_pricing_is_rejected_for_platform_funding(): void {
        $this->expectException(ValidationException::class);
        app(LlmBudget::class)->quotation(['pricing'=>[]],[['role'=>'user','content'=>'hi']]);
    }
    public function test_additional_catalog_fees_do_not_block_remaining_free_credit(): void {
        [$u,$story]=$this->setupStory();
        $models=Cache::get('openrouter.models');
        $models[0]['pricing'] += ['input_cache_write'=>'0.000001','internal_reasoning'=>'0.000001','image'=>'0.02','web_search'=>'0.01'];
        Cache::put('openrouter.models',$models,600);
        Http::fake(['*/chat/completions'=>Http::response(['usage'=>['cost'=>0,'prompt_tokens'=>1000,'completion_tokens'=>1000],'choices'=>[['message'=>['content'=>'Hello']]]])]);
        $this->actingAs($u)->postJson(route('companion.store'),['message'=>'Hi','model'=>'test/model','request_key'=>(string)Str::uuid()])->assertOk();
        $this->assertSame(3000,$u->fresh()->llm_spent);
        $this->assertSame('platform',LlmRequest::first()->funding);
    }
    public function test_cache_and_reasoning_fees_are_reserved(): void {
        $base=['context_length'=>100000,'architecture'=>['output_modalities'=>['text']],'pricing'=>['prompt'=>'0.000001','completion'=>'0.000002']];
        $messages=[['role'=>'user','content'=>'Hi']];
        [$plain]=app(LlmBudget::class)->quotation($base,$messages);
        $base['pricing'] += ['input_cache_write'=>'0.000001','internal_reasoning'=>'0.000001'];
        [$extra]=app(LlmBudget::class)->quotation($base,$messages);
        $this->assertGreaterThan($plain,$extra);
    }
    public function test_personal_key_prompt_only_when_credit_is_spent(): void {
        [$u,$story]=$this->setupStory(); $budget=app(LlmBudget::class);
        try { $budget->reserve($u,$story,(string)Str::uuid(),'test/model',1000001); $this->fail('Overspend accepted'); }
        catch (ValidationException $e) { $this->assertStringNotContainsString('own OpenRouter key',$e->getMessage()); }
        $u->forceFill(['llm_spent'=>1000000])->save();
        try { $budget->reserve($u,$story,(string)Str::uuid(),'test/model',0); $this->fail('Exhausted allowance accepted'); }
        catch (ValidationException $e) { $this->assertStringContainsString('Your AI credits have been used',$e->getMessage()); }
    }
    private function solCatalogModel(): array {
        return ['id'=>'openai/gpt-6.1-sol','architecture'=>['output_modalities'=>['text']],'context_length'=>1000000,'pricing'=>[
            'prompt'=>'0.000002','completion'=>'0.00001','web_search'=>'0.01',
            'input_cache_read'=>'0.0000001','input_cache_write'=>'0.0000025',
            'overrides'=>[['min_prompt_tokens'=>272000,'prompt'=>'0.000004','completion'=>'0.000015',
                'input_cache_read'=>'0.0000002','input_cache_write'=>'0.000005']],
        ]];
    }
    public function test_sol_catalog_pricing_overrides_allow_chat_with_free_credit(): void {
        [$u,$story]=$this->setupStory();
        Cache::put('openrouter.models',[$this->solCatalogModel()],600);
        Http::fake(['*/chat/completions'=>Http::response(['usage'=>['cost'=>0,'prompt_tokens'=>1000,'completion_tokens'=>1000],'choices'=>[['message'=>['content'=>'Hello']]]])]);
        $this->actingAs($u)->postJson(route('companion.store'),['message'=>'Hi','model'=>'openai/gpt-6.1-sol','request_key'=>(string)Str::uuid()])->assertOk();
        Http::assertSent(fn($request)=>$request['provider']['max_price']['prompt']===2.0 && $request['provider']['max_price']['completion']===10.0);
        $this->assertSame(12000,$u->fresh()->llm_spent);
    }
    public function test_long_context_override_raises_reserved_cost_and_provider_ceiling(): void {
        $model=$this->solCatalogModel();
        $messages=[['role'=>'user','content'=>str_repeat('a',273000)]];
        [$tierAmount,$provider]=app(LlmBudget::class)->quotation($model,$messages);
        unset($model['pricing']['overrides']);
        [$baseAmount]=app(LlmBudget::class)->quotation($model,$messages);
        $this->assertGreaterThan($baseAmount,$tierAmount);
        $this->assertEquals(4,$provider['max_price']['prompt']);
        $this->assertEquals(15,$provider['max_price']['completion']);
    }
    public function test_scheduled_pricing_reserves_highest_rate_and_inherits_base_fields(): void {
        $model=$this->solCatalogModel();
        $model['pricing']['overrides']=[['utc_start'=>1630,'utc_end'=>30,'utc_days'=>['monday'],'prompt'=>'0.000003']];
        [, $provider]=app(LlmBudget::class)->quotation($model,[['role'=>'user','content'=>'Hi']]);
        $this->assertEquals(3,$provider['max_price']['prompt']);
        $this->assertEquals(10,$provider['max_price']['completion']);
    }
}
