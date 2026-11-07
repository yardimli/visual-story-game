<?php
namespace App\Console\Commands;
use App\Models\LlmRequest;
use App\Services\LlmBudget;
use Illuminate\Console\Command;
class ReconcileLlmUsage extends Command {
    protected $signature = 'llm:reconcile {request : Local ledger ID} {micro_usd : Confirmed total cost in millionths of a dollar}';
    protected $description = 'Reconcile an uncertain request after calculating catalog cost from verified input/output token usage';
    public function handle(LlmBudget $budget): int {
        $id=$this->argument('request'); $cost=$this->argument('micro_usd');
        if(!ctype_digit($cost)) { $this->error('Cost must be a non-negative integer.');return self::FAILURE; }
        $r=LlmRequest::findOrFail($id);
        if(!in_array($r->status,['reserved','uncertain'])) { $this->error('Request is already finalized.');return self::FAILURE; }
        if(!$this->confirm('Have you calculated this request cost from verified input/output tokens and its stored catalog rates (not OpenRouter BYOK cost)?'))return self::FAILURE;
        $budget->settle($r,(int)$cost,'reconciled',$r->provider_id);
        $this->info('Usage reconciled.');return self::SUCCESS;
    }
}
