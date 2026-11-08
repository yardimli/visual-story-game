<?php
namespace App\Services;
use App\Models\{User,LlmRequest,Story};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class LlmBudget {
    public function expireOverdue(?int $userId=null): int {
        $count=0;
        LlmRequest::whereIn('status',['reserved','uncertain'])->where('created_at','<=',now()->subMinutes(3))
            ->when($userId,fn($q)=>$q->where('user_id',$userId))->select(['id','user_id'])->chunkById(100,function($rows) use (&$count) {
                foreach($rows as $row) $count+=DB::transaction(function() use($row) {
                    $u=User::whereKey($row->user_id)->lockForUpdate()->first();
                    $r=LlmRequest::whereKey($row->id)->lockForUpdate()->first();
                    if(!$u || !$r || !in_array($r->status,['reserved','uncertain']) || $r->created_at->gt(now()->subMinutes(3)))return 0;
                    if($r->funding==='platform'){$u->llm_reserved-=$r->reserved;$u->save();}
                    $r->update(['status'=>'failed','cost'=>0]);
                    \App\Models\ImageGeneration::where('llm_request_id',$r->id)->whereIn('status',['queued','running','failed'])->update(['status'=>'failed','error'=>'Request exceeded the three-minute limit. Any reserved trial credit was released. Start a new request to try again.']);
                    return 1;
                });
            });
        return $count;
    }
    public function reserve(User $user, ?Story $story, string $requestKey, string $model, int $amount): LlmRequest {
        $this->expireOverdue($user->id);
        return DB::transaction(function () use ($user,$story,$requestKey,$model,$amount) {
            $u = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($u->openrouter_key !== $user->openrouter_key)
                throw ValidationException::withMessages(['message'=>'Your API key changed. Reload before submitting.']);
            if (LlmRequest::where('user_id',$u->id)->where('request_key',$requestKey)->exists())
                throw ValidationException::withMessages(['message'=>'This request was already submitted. Reload to see its result.']);
            $personal = filled($u->openrouter_key);
            if (!$personal && $u->llm_spent >= config('story.allowance'))
                throw ValidationException::withMessages(['message'=>'Your free $1 credit has been used. Add your own OpenRouter key in AI settings to continue.']);
            if (!$personal && ($u->llm_spent + $u->llm_reserved + $amount > config('story.allowance')))
                throw ValidationException::withMessages(['message'=>'This request needs an estimated $'.number_format($amount/1000000,6).' reservation, but $'.number_format(max(0,config('story.allowance')-$u->llm_spent-$u->llm_reserved)/1000000,6).' of trial credit is available. No generation was started. Choose a lower-cost model/settings or add a personal OpenRouter key in AI settings.']);
            if (!$personal) { $u->llm_reserved += $amount; $u->save(); }
            return LlmRequest::create(['user_id'=>$u->id,'story_id'=>$story?->id,'request_key'=>$requestKey,
                'model'=>$model,'funding'=>$personal?'personal':'platform','reserved'=>$personal?0:$amount]);
        });
    }
    public function settle(LlmRequest $request, ?int $cost, string $status, ?string $providerId=null): void {
        $this->expireOverdue($request->user_id);
        DB::transaction(function () use ($request,$cost,$status,$providerId) {
            $u = User::whereKey($request->user_id)->lockForUpdate()->firstOrFail();
            $r = LlmRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (!in_array($r->status,['reserved','uncertain'])) return;
            // Unknown costs remain reserved until an operator reconciles provider billing.
            if ($r->funding === 'platform' && $cost !== null) {
                $u->llm_reserved -= $r->reserved; $u->llm_spent += $cost; $u->save();
            }
            $r->update(['cost'=>$cost,'status'=>$cost===null?'uncertain':$status,'provider_id'=>$providerId]);
        });
    }
    public function quotation(array $model, array $messages): array {
        $input = strlen(json_encode($messages, JSON_UNESCAPED_UNICODE)) + 1024;
        $pricing = $this->pricingForReservation($model['pricing'] ?? [], $input);
        foreach (['prompt','completion'] as $field) {
            if (!isset($pricing[$field]) || !is_numeric($pricing[$field]) || (float)$pricing[$field]<0)
                throw ValidationException::withMessages(['model'=>'Pricing is unavailable for this model. Choose another model or try again later.']);
        }
        // Catalogs include prices for capabilities this text-only request does not use.
        // Cache and reasoning prices are accounted for below rather than rejecting the model.
        foreach ($pricing as $field=>$price) {
            if (in_array($field,['image','image_token','image_output','audio','audio_output','input_audio_cache','web_search'])) continue;
            if (!is_numeric($price) || !is_finite((float)$price) || (float)$price < 0)
                throw ValidationException::withMessages(['model'=>'Pricing is unavailable for this model. Choose another model or try again later.']);
            if (!in_array($field,['prompt','completion','request','input_cache_read','input_cache_write','internal_reasoning']) && (float)$price > 0)
                throw ValidationException::withMessages(['model'=>'The cost of this model cannot be estimated yet. Choose another model while we verify its pricing.']);
        }
        $output = config('story.max_output_tokens');
        if ($input+$output > ($model['context_length'] ?? 4096))
            throw ValidationException::withMessages(['model'=>'The complete story context exceeds this model’s safe context limit. Choose a larger-context model.']);
        // UTF-8 bytes + framing is intentionally conservative for text-only input.
        $inputRate = max((float)$pricing['prompt'], (float)($pricing['input_cache_read']??0)) + (float)($pricing['input_cache_write']??0);
        $outputRate = (float)$pricing['completion'] + (float)($pricing['internal_reasoning']??0);
        $amount = (int) ceil(($input*$inputRate+$output*$outputRate+(float)($pricing['request']??0))*1000000*1.05);
        return [$amount, ['max_price'=>['prompt'=>(float)$pricing['prompt']*1000000,
            'completion'=>(float)$pricing['completion']*1000000,'request'=>(float)($pricing['request']??0)],
            'require_parameters'=>true,'allow_fallbacks'=>false]];
    }
    public function tokenCost(array $pricing, array $usage, \Carbon\CarbonInterface $at): ?int {
        foreach (['prompt_tokens','completion_tokens'] as $key) {
            if (!isset($usage[$key]) || !(is_int($usage[$key]) || (is_string($usage[$key]) && ctype_digit($usage[$key]))) || $usage[$key] < 0 || $usage[$key] > 1000000000) return null;
        }
        $utc=$at->copy()->utc(); $clock=(int)$utc->format('Hi');
        $overrides=$pricing['overrides'] ?? [];
        if (!is_array($overrides)) return null;
        foreach ($overrides as $tier) {
            if (!is_array($tier)) return null;
            if (array_diff(array_keys($tier), ['min_prompt_tokens','utc_start','utc_end','utc_days','prompt','completion','request','input_cache_read','input_cache_write','internal_reasoning','image','web_search'])) return null;
            if (isset($tier['min_prompt_tokens']) && $usage['prompt_tokens'] <= $tier['min_prompt_tokens']) continue;
            if (isset($tier['utc_days']) && (!is_array($tier['utc_days']) || !in_array(strtolower($utc->format('l')),$tier['utc_days'],true))) continue;
            if (isset($tier['utc_start']) || isset($tier['utc_end'])) {
                if (!isset($tier['utc_start'],$tier['utc_end'])) return null;
                $start=(int)$tier['utc_start']; $end=(int)$tier['utc_end'];
                if (!($end>$start ? $clock >= $start && $clock < $end : $clock >= $start || $clock < $end)) continue;
            }
            foreach (['prompt','completion'] as $key) if (array_key_exists($key,$tier)) $pricing[$key]=$tier[$key];
        }
        foreach (['prompt','completion'] as $key) if (!isset($pricing[$key]) || !is_numeric($pricing[$key]) || !is_finite((float)$pricing[$key]) || $pricing[$key]<0) return null;
        // App allowance is catalog input/output token pricing, including BYOK calls.
        // Completion token totals already include reasoning tokens: do not count twice.
        $microUsd = ($usage['prompt_tokens']*(float)$pricing['prompt'] + $usage['completion_tokens']*(float)$pricing['completion'])*1000000;
        if (!is_finite($microUsd) || $microUsd > PHP_INT_MAX) return null;
        return (int)ceil(round($microUsd, 8));
    }
    private function pricingForReservation(array $pricing, int $inputBound): array {
        $overrides = $pricing['overrides'] ?? [];
        unset($pricing['overrides']);
        if (!is_array($overrides))
            throw ValidationException::withMessages(['model'=>'Invalid pricing tiers returned for this model. Try refreshing the catalog.']);
        $conditions = ['min_prompt_tokens','utc_start','utc_end','utc_days'];
        foreach ($overrides as $tier) {
            if (!is_array($tier))
                throw ValidationException::withMessages(['model'=>'Invalid pricing tier returned for this model.']);
            if (isset($tier['min_prompt_tokens'])) {
                if (!is_numeric($tier['min_prompt_tokens']) || (float)$tier['min_prompt_tokens'] < 0)
                    throw ValidationException::withMessages(['model'=>'Invalid pricing threshold returned for this model.']);
                // This byte-based bound cannot reach the higher context tier.
                if ($inputBound <= (float)$tier['min_prompt_tokens']) continue;
            }
            // Reserve the highest possible rate, including scheduled rates, so a
            // cached catalog or a request crossing a time boundary cannot underquote.
            // Actual provider cost is still used at settlement, not this upper bound.
            foreach ($tier as $field=>$price) {
                if (in_array($field,$conditions,true)) continue;
                if (!is_numeric($price) || !is_finite((float)$price) || (float)$price < 0)
                    throw ValidationException::withMessages(['model'=>'Invalid pricing rate returned for this model.']);
                if (isset($pricing[$field]) && (!is_numeric($pricing[$field]) || !is_finite((float)$pricing[$field]) || (float)$pricing[$field] < 0))
                    throw ValidationException::withMessages(['model'=>'Invalid base pricing rate returned for this model.']);
                $pricing[$field] = max((float)($pricing[$field] ?? 0), (float)$price);
            }
        }
        return $pricing;
    }
}
