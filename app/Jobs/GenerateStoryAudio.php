<?php
namespace App\Jobs;
use App\Models\{AudioGeneration,Story};
use App\Services\StoryAudio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue,SerializesModels};
use Illuminate\Support\Facades\Http;
class GenerateStoryAudio implements ShouldQueue {
 use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
 public $tries=500;public $timeout=90;
 public function __construct(public int $id){}
 public function middleware():array {return [(new \Illuminate\Queue\Middleware\WithoutOverlapping('audio.'.$this->id))->releaseAfter(5)->expireAfter(110)];}
 public function handle(StoryAudio $audio):void {
  $g=AudioGeneration::find($this->id);if(!$g||in_array($g->status,['completed','failed','draft']))return;
  $base='https://queue.fal.run/'.($g->model?:'fal-ai/stable-audio');$client=Http::withHeaders(['Authorization'=>'Key '.config('services.fal.key')])->timeout(25);
  try{
   if(!$g->provider_request_id){
    // Claim before POST. An uncertain submission must never create a second paid request.
    if(!AudioGeneration::whereKey($g->id)->where('status','queued')->whereNull('submitted_at')->update(['status'=>'submitting','submitted_at'=>now()])){return;}
    $g->refresh();$base='https://queue.fal.run/'.$g->model;
    $r=$client->post($base,['prompt'=>$g->prompt,($g->model==='cassetteai/sound-effects-generator'?'duration':'seconds_total')=>$g->seconds]);
    if(!$r->successful()||!$r->json('request_id'))throw new \RuntimeException('fal submission failed or was not confirmed. Check fal activity before trying again.');
    $g->refresh();$g->update(['provider_request_id'=>$r->json('request_id'),'status'=>'queued']);$this->release(5);return;
   }
   $request=$base.'/requests/'.rawurlencode($g->provider_request_id);
   $r=$client->get($request.'/status',['logs'=>'1']);
   if(!$r->successful())throw new \RuntimeException('fal status request failed (HTTP '.$r->status().').');
   $state=$r->json('status');$g->update(['queue_position'=>$r->json('queue_position'),'logs'=>$r->json('logs')??[]]);
   if($state==='IN_QUEUE'){$g->update(['status'=>'queued']);$this->release(5);return;}
   if($state==='IN_PROGRESS'){
    if(!$g->started_at)$g->update(['started_at'=>now()]);
    if($g->started_at->lt(now()->subMinutes(10)))throw new \RuntimeException('Audio exceeded ten minutes of rendering. Check fal activity before creating another request.');
    $g->update(['status'=>'running']);$this->release(5);return;
   }
   if($state!=='COMPLETED')throw new \RuntimeException('fal returned an unexpected audio status.');
   $result=$client->get($request);if(!$result->successful()||!$result->json('audio_file.url'))throw new \RuntimeException('fal did not return a completed audio file.');
   $sound=$audio->save(Story::findOrFail($g->story_id),$result->json('audio_file.url'),['name'=>$g->name,'provider'=>'fal','external_id'=>$g->provider_request_id,'prompt'=>$g->prompt,'duration'=>$g->seconds]);
   $g->update(['status'=>'completed','sound_id'=>$sound->id,'error'=>null]);
   if($account=$g->accounting){$account->update(['response_content'=>['status'=>'completed','sound_id'=>$sound->id,'duration'=>$sound->duration]]);app(\App\Services\LlmBudget::class)->settle($account,(int)round((float)$sound->duration*20000),'completed',$g->provider_request_id);}
  }catch(\Throwable $e){if($account=$g->accounting)app(\App\Services\LlmBudget::class)->settle($account,0,'failed',$g->provider_request_id);$g->update(['status'=>'failed','error'=>$e instanceof \RuntimeException && !$e instanceof \Illuminate\Http\Client\RequestException?$e->getMessage():'Audio request failed. Check provider activity before creating another request.']);}
 }
 public function failed(?\Throwable $e):void {if($g=AudioGeneration::find($this->id)){if($g->accounting)app(\App\Services\LlmBudget::class)->settle($g->accounting,0,'failed',$g->provider_request_id);}AudioGeneration::whereKey($this->id)->whereNotIn('status',['completed','failed'])->update(['status'=>'failed','error'=>'Audio worker stopped before completion. Check fal activity before creating another request.']);}
}
