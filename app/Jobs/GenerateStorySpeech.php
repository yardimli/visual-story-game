<?php
namespace App\Jobs;
use App\Models\{AudioGeneration,Story,User};
use App\Services\{StoryAudio,SpeechCatalog,LlmBudget};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue,SerializesModels};
use Illuminate\Support\Facades\Http;
class GenerateStorySpeech implements ShouldQueue {
 use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
 public $tries=1;public $timeout=240;public $failOnTimeout=true;
 public function __construct(public int $id){}
 public function handle(StoryAudio $audio,LlmBudget $budget):void {
  $g=AudioGeneration::find($this->id);if(!$g||$g->status!=='queued')return;
  if(!AudioGeneration::whereKey($g->id)->where('status','queued')->whereNull('submitted_at')->update(['status'=>'running','submitted_at'=>now(),'started_at'=>now()]))return;
  $g->refresh();$account=$g->accounting;$story=Story::findOrFail($g->story_id);$user=User::findOrFail($story->user_id);
  $key=$account->funding==='personal'?$user->openrouter_key:config('story.openrouter_key');
  try {
   if(!$key)throw new \RuntimeException('OpenRouter key is missing.');
   $google=str_starts_with($g->model,'google/');$heygen=str_starts_with($g->model,'heygen/');$pcm=$google||$heygen;$s=$g->settings??[];
   $payload=['model'=>$g->model,'input'=>$g->prompt,'voice'=>$s['voice'],'response_format'=>$pcm?'pcm':'mp3'];
   if($google){if(!empty($s['instructions']))$payload['instructions']=$s['instructions'];if(isset($s['temperature']))$payload['provider']['options']['google-ai-studio']['temperature']=(float)$s['temperature'];}
   if($heygen && !empty($s['language']))$payload['provider']['options']['heygen']['language']=$s['language'];
   $account->update(['request_content'=>['type'=>'speech','parameters'=>$payload]]);
   $client=Http::withToken($key)->withHeaders(['HTTP-Referer'=>config('story.openrouter_referer'),'X-OpenRouter-Title'=>config('app.name')])->timeout(210);
   $r=$client->post('https://openrouter.ai/api/v1/audio/speech',$payload);
   if(!$r->successful())throw new \RuntimeException('Speech generation failed (HTTP '.$r->status().'). No automatic retry was made.');
   $type=strtolower(explode(';',$r->header('Content-Type'))[0]);
   if(!in_array($type,['audio/pcm','audio/mpeg','audio/wav','audio/x-wav','application/octet-stream'])||strlen($r->body())<2||strlen($r->body())>52428800)throw new \RuntimeException('Speech provider returned invalid audio.');
   $bytes=$r->body();$duration=null;
   if($pcm){$rate=$heygen?44100:24000;$duration=strlen($bytes)/($rate*2);$bytes=SpeechCatalog::wav($bytes,$rate);}
   $providerId=$r->header('X-Generation-Id');
   $sound=$audio->saveBytes($story,$bytes,$pcm?'audio/wav':'audio/mpeg',['name'=>$g->name,'provider'=>'openrouter','external_id'=>$providerId?:null,'prompt'=>$g->prompt,'source_text'=>$g->source_text,'category'=>$g->category,'duration'=>$duration]);
   $g->update(['status'=>'completed','sound_id'=>$sound->id,'provider_request_id'=>$providerId?:null]);
   $pricing=$account->pricing_snapshot;$cost=$google?null:(int)ceil(mb_strlen($g->prompt)*(float)$pricing['prompt']*1000000);
   $usage=[];
   if($google && $providerId){
    try{$details=$client->timeout(15)->get('https://openrouter.ai/api/v1/generation',['id'=>$providerId]);$usage=$details->successful()?($details->json('data')??[]):[];}catch(\Throwable $lookupError){$usage=[];}
    $cost=$budget->tokenCost($pricing,['prompt_tokens'=>$usage['native_tokens_prompt']??null,'completion_tokens'=>$usage['native_tokens_completion']??null],now());
   }
   $account->update(['response_content'=>['status'=>'completed','sound_id'=>$sound->id,'duration'=>$duration,'usage'=>$usage],'prompt_tokens'=>$usage['native_tokens_prompt']??0,'completion_tokens'=>$usage['native_tokens_completion']??0]);
   $budget->settle($account,$cost,'completed',$providerId?:null);
   if($cost===null)ReconcileSpeechCost::dispatch($g->id)->onConnection('database')->onQueue('audio')->delay(15);
  }catch(\Throwable $e){$g->update(['status'=>'failed','error'=>$e instanceof \RuntimeException&&!$e instanceof \Illuminate\Http\Client\RequestException?$e->getMessage():'Speech request did not complete. Check provider activity before trying again.']);$budget->settle($account,0,'failed');}
 }
 public function failed(?\Throwable $e):void {if($g=AudioGeneration::find($this->id)){if($g->status==='completed')return;$g->update(['status'=>'failed','error'=>'Speech exceeded the worker time limit. No automatic retry was made.']);if($g->accounting)app(LlmBudget::class)->settle($g->accounting,0,'failed');}}
}
