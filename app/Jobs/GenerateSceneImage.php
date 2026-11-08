<?php
namespace App\Jobs;
use App\Models\{ImageGeneration,User,StoryPage,Asset,LlmRequest};
use App\Services\{OpenRouter,LlmBudget,ImagePricing};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue,SerializesModels};
use Illuminate\Support\Facades\{Storage,DB};
use Illuminate\Support\Str;
class GenerateSceneImage implements ShouldQueue {
 use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
 public $tries=1; public $timeout=180; public $failOnTimeout=true;
 public function __construct(public int $generationId) {}
 public function handle(OpenRouter $router): void {
  $pending=ImageGeneration::find($this->generationId);
  if(!$pending)return;
  app(LlmBudget::class)->expireOverdue($pending->user_id);
  // Fencing prevents a re-delivered queue message from submitting a paid call twice.
  if(!ImageGeneration::whereKey($this->generationId)->where('status','queued')->update(['status'=>'running','started_at'=>now()])) return;
  $job=ImageGeneration::findOrFail($this->generationId);$paths=[];$submitted=false;$failure='Image generation failed before contacting OpenRouter.';$httpStatus=null;
  $ledger=$job->llm_request_id?LlmRequest::findOrFail($job->llm_request_id):null;$budget=app(LlmBudget::class);
  try {
   $user=User::findOrFail($job->user_id);
   // Funding is fixed when queued; never move a personal job onto the platform key.
   $key=$ledger?->funding==='platform'?config('story.openrouter_key'):$user->openrouter_key;
   if(!$key || !StoryPage::whereKey($job->story_page_id)->exists()) throw new \RuntimeException('Key missing or page removed.');
   $payload=$job->payload;
   foreach($job->references as $ref) {
    $bytes=Storage::disk('local')->get($ref['path']);
    if(!is_string($bytes)) throw new \RuntimeException('Reference missing.');
    $payload['input_references'][]=['type'=>'image_url','image_url'=>['url'=>'data:'.$ref['mime'].';base64,'.base64_encode($bytes)]];
   }
   // Old queued automatic-routing jobs may contain this unsupported image API field.
   unset($payload['provider']['require_parameters']);if(empty($payload['provider']))unset($payload['provider']);
   $failure='OpenRouter connection failed or timed out. Billing is unconfirmed; this request will not retry automatically.';
   $submitted=true;$response=$router->generateImage($key,$payload);
   $httpStatus=$response->status();
   if(!$response->successful() || $response->json('error')) {
    $message=$response->json('error.message');
    if(!is_string($message))$message='The provider rejected the request.';
    $message=str_replace(array_filter([$key,$payload['prompt']??null]),'[redacted]',$message);
    $message=preg_replace('/data:[^\s\"<>]+|Bearer\s+\S+|sk-[a-zA-Z0-9_-]+/i','[redacted]',$message);
    $failure='OpenRouter returned HTTP '.$httpStatus.': '.mb_substr(strip_tags($message),0,500).' No automatic retry was made.';
    throw new \RuntimeException('Provider rejected image request.');
   }
   $failure='OpenRouter returned an unusable image response. Billing is unconfirmed; no automatic retry was made.';
   $outputs=$response->json('data');
   if(!is_array($outputs) || !count($outputs) || count($outputs)>10) throw new \RuntimeException('No image output.');
   $records=[];$dimensions=[];
   foreach($outputs as $index=>$output) {
    $encoded=$output['b64_json']??'';
    if(!is_string($encoded) || strlen($encoded)>40*1024*1024) throw new \RuntimeException('Image too large.');
    $bytes=base64_decode($encoded,true);$info=$bytes!==false ? @getimagesizefromstring($bytes) : false;
    $extensions=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
    if(!$info || !isset($extensions[$info['mime']])) throw new \RuntimeException('Unsupported or invalid image.');
    $dimensions[]=['width'=>$info[0],'height'=>$info[1]];
    $path='stories/'.$job->story_id.'/generated/'.Str::uuid().'.'.$extensions[$info['mime']];
    $failure='The image was rendered but could not be saved locally. Billing needs reconciliation; no automatic retry was made.';
    if(!Storage::disk('local')->put($path,$bytes)) throw new \RuntimeException('Storage failed.');
    $paths[]=$path;$records[]=['story_id'=>$job->story_id,'name'=>$job->name.' · render '.$job->id.'-'.($index+1),'path'=>$path,'mime'=>$info['mime'],'bytes'=>strlen($bytes),'role'=>$job->kind==='object'?'object':'scene'];
   }
   // Settle even if storing the completed output later fails; the provider already rendered it.
   if($ledger) {
    $usage=$response->json('usage')??[];
    $cost=app(ImagePricing::class)->cost($job->pricing,$job->payload,is_array($usage)?$usage:[],$job->references,$dimensions,is_string($response->json('provider'))?$response->json('provider'):null);
    $budget->settle($ledger,$cost,'completed');
   }
   DB::transaction(function() use($job,$records,$response,$ledger) {
    if($ledger && LlmRequest::whereKey($ledger->id)->lockForUpdate()->first()->status==='failed')throw new \RuntimeException('Request expired.');
    $ids=[];foreach($records as $record) $ids[]=Asset::create($record)->id;
    $job->update(['status'=>'completed','asset_ids'=>$ids,'usage'=>$response->json('usage'),'error'=>null]);
   });
  } catch(\Throwable $e) {
   // Explicit request/auth/rate rejections occur before generation; transport/server
   // errors and local output failures remain uncertain rather than guessing cost.
   $rejected=in_array($httpStatus,[400,401,402,403,404,413,422,429],true);
   if($ledger)$budget->settle($ledger,(!$submitted||$rejected)?0:null,(!$submitted||$rejected)?'failed':'uncertain');
   \Illuminate\Support\Facades\Log::warning('Image generation failed',['generation_id'=>$job->id,'http_status'=>$httpStatus,'exception_type'=>get_class($e)]);
   foreach($paths as $path) Storage::disk('local')->delete($path);
   if(!$ledger || $ledger->fresh()->status!=='failed' || $job->fresh()->status!=='failed')$job->update(['status'=>'failed','error'=>$failure]);
  }
 }
 public function failed(?\Throwable $e): void {
  $job=ImageGeneration::find($this->generationId);
  if($job?->llm_request_id && ($ledger=LlmRequest::find($job->llm_request_id))) app(LlmBudget::class)->settle($ledger,$job->status==='queued'?0:null,'failed');
  ImageGeneration::whereKey($this->generationId)->whereIn('status',['queued','running'])->update(['status'=>'failed','error'=>'Worker stopped or timed out. Check provider activity before submitting again.']);
 }
}
