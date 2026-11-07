<?php
namespace Tests\Feature;
use App\Services\{ModelCatalogFilter,LlmBudget};
use Carbon\CarbonImmutable;
use Tests\TestCase;
class ModelCatalogFilterTest extends TestCase {
    private function model(string $id, string $name, array $output=['text'], int $created=1): array {
        return ['id'=>$id,'name'=>$name,'created'=>$created,'architecture'=>['output_modalities'=>$output]];
    }
    public function test_editorial_filters_and_qwen_total_parameter_size(): void {
        config(['story.openrouter_providers'=>['qwen','openai','google','deepseek']]);
        $models=collect([
            $this->model('qwen/qwen3-235b-a22b','Qwen: Qwen3 235B A22B'),
            $this->model('qwen/qwen3-32b','Qwen: Qwen3 32B'),
            $this->model('qwen/qwen3-1t','Qwen: Qwen3 1T'),
            $this->model('qwen/qwen3-235b-thinking','Qwen: Qwen3 235B Thinking'),
            $this->model('openai/gpt-4o','OpenAI: GPT-4o'),
            $this->model('openai/gpt-6-codex','OpenAI: GPT-6 Codex'),
            $this->model('openai/gpt-6:free','OpenAI: GPT-6 (free)'),
            $this->model('openai/gpt-6:batch','OpenAI: GPT-6 (batch)'),
            $this->model('google/nano-banana','Google: Nano Banana'),
            $this->model('google/image','Google: Image',['image']),
            $this->model('deepseek/deepseek-v3','DeepSeek: V3'),
            $this->model('other/great','Other: Great'),
        ]);
        $this->assertSame(['qwen/qwen3-235b-a22b','qwen/qwen3-1t'],app(ModelCatalogFilter::class)->apply($models)->pluck('id')->all());
    }
    public function test_versions_and_snapshots_deduplicate_without_merging_product_variants(): void {
        config(['story.openrouter_providers'=>['openai']]);
        $models=collect([
            $this->model('openai/gpt-6-sol','OpenAI: GPT-6 Sol'),
            $this->model('openai/gpt-6.1-sol-20261001','OpenAI: GPT-6.1 Sol October'),
            $this->model('openai/gpt-6.1-sol-20261101','OpenAI: GPT-6.1 Sol November'),
            $this->model('openai/gpt-6.1-terra','OpenAI: GPT-6.1 Terra'),
        ]);
        $this->assertSame(['openai/gpt-6.1-sol-20261101','openai/gpt-6.1-terra'],app(ModelCatalogFilter::class)->apply($models)->pluck('id')->all());
    }
    public function test_token_cost_ignores_provider_cost_and_rounds_up_microdollars(): void {
        $b=app(LlmBudget::class); $at=CarbonImmutable::parse('2026-11-07 10:00:00','UTC');
        $pricing=['prompt'=>'0.000002','completion'=>'0.00001'];
        $usage=['prompt_tokens'=>1000,'completion_tokens'=>100,'cost'=>0];
        $this->assertSame(3000,$b->tokenCost($pricing,$usage,$at));
        $usage['cost']=999;
        $this->assertSame(3000,$b->tokenCost($pricing,$usage,$at));
        $this->assertNull($b->tokenCost($pricing,['cost'=>0],$at));
        $this->assertNull($b->tokenCost($pricing,['prompt_tokens'=>-1,'completion_tokens'=>10],$at));
        $this->assertSame(1,$b->tokenCost(['prompt'=>'0.0000001','completion'=>'0'],['prompt_tokens'=>1,'completion_tokens'=>0],$at));
    }
    public function test_actual_token_tiers_and_utc_schedule_are_used_at_settlement(): void {
        $b=app(LlmBudget::class); $at=CarbonImmutable::parse('2026-11-07 23:00:00','UTC');
        $pricing=['prompt'=>'0.000002','completion'=>'0.00001','overrides'=>[
            ['min_prompt_tokens'=>1000,'prompt'=>'0.000004'],
            ['utc_start'=>2200,'utc_end'=>600,'utc_days'=>['saturday'],'completion'=>'0.00002'],
        ]];
        $this->assertSame(8000,$b->tokenCost($pricing,['prompt_tokens'=>1500,'completion_tokens'=>100],$at));
        $this->assertSame(4000,$b->tokenCost($pricing,['prompt_tokens'=>1000,'completion_tokens'=>100],$at));
    }
}
