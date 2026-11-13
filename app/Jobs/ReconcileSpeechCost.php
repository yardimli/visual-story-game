<?php
namespace App\Jobs;
use App\Models\{AudioGeneration,User};
use App\Services\LlmBudget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue,SerializesModels};
use Illuminate\Support\Facades\Http;
class ReconcileSpeechCost implements ShouldQueue {
 use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
 public $tries=20;public $timeout=30;
 public function __construct(public int $id){}
 public function handle(LlmBudget $budget):void {
  $g=AudioGeneration::find($this->id);$a=$g?->accounting;if(!$a||$a->status!=='uncertain'||!$g->provider_request_id)return;
  $u=User::findOrFail($a->user_id);$key=$a->funding==='personal'?$u->openrouter_key:config('story.openrouter_key');
  try{$r=Http::withToken($key)->withHeaders(['HTTP-Referer'=>config('story.openrouter_referer'),'X-OpenRouter-Title'=>config('app.name')])->timeout(20)->get('https://openrouter.ai/api/v1/generation',['id'=>$g->provider_request_id]);
   $d=$r->successful()?($r->json('data')??[]):[];$cost=$budget->tokenCost($a->pricing_snapshot,['prompt_tokens'=>$d['native_tokens_prompt']??null,'completion_tokens'=>$d['native_tokens_completion']??null],$g->started_at??now());
   if($cost===null){$this->release(30);return;}
   $a->update(['prompt_tokens'=>$d['native_tokens_prompt'],'completion_tokens'=>$d['native_tokens_completion'],'response_content'=>array_merge($a->response_content??[],['usage'=>$d])]);$budget->settle($a,$cost,'completed',$g->provider_request_id);
  }catch(\Throwable $e){$this->release(30);}
 }
}
