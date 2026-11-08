<?php
namespace App\Http\Controllers;
use App\Models\{Story,StoryPage,ImageGeneration,Asset};
use App\Services\{SceneDocument,OpenRouter,LlmBudget,ImagePricing};
use App\Jobs\GenerateSceneImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class SceneController extends Controller {
 private function owner(Request $r,Story $s,StoryPage $p): void {abort_unless($s->user_id===$r->user()->id && $p->story_id===$s->id,404);}
 public function studio(Request $r,Story $story,StoryPage $page) {
  $this->owner($r,$story,$page);return view('studio.images',compact('story','page'));
 }
 public function show(Request $r,Story $story,StoryPage $page) {
  $this->owner($r,$story,$page);
  return response()->json(['remaining'=>max(0,config('story.allowance')-$r->user()->llm_spent-$r->user()->llm_reserved)/1000000,'document'=>$page->visual_document??['visuals'=>[]],'revision'=>$page->visual_revision,
   'assets'=>Asset::withGenerationNames($story->assets()->latest()->get(),$story->id)->map(fn($a)=>['id'=>$a->id,'name'=>$a->name,'url'=>route('assets.show',$a),'role'=>$a->role]),
   'jobs'=>ImageGeneration::where('story_page_id',$page->id)->latest('id')->get(['id','visual_id','name','status','error','asset_ids','created_at','payload'])->map(fn($j)=>['id'=>$j->id,'visual_id'=>$j->visual_id,'name'=>$j->name,'status'=>$j->status,'error'=>$j->error,'asset_ids'=>$j->asset_ids,'created_at'=>$j->created_at,'prompt'=>$j->payload['prompt']??'','model'=>$j->payload['model']??''])]);
 }
 public function update(Request $r,Story $story,StoryPage $page,SceneDocument $validator) {
  $this->owner($r,$story,$page);$data=$r->validate(['revision'=>'required|integer','document'=>'required|array']);
  $doc=$validator->validate($story,$data['document']);
  $revision=DB::transaction(function() use($page,$data,$doc) {
   $p=StoryPage::whereKey($page->id)->lockForUpdate()->firstOrFail();
   if($p->visual_revision!==(int)$data['revision']) throw ValidationException::withMessages(['revision'=>'Scene artwork changed in another tab. Reload before editing.']);
   $p->update(['visual_document'=>$doc,'visual_revision'=>$p->visual_revision+1]);return $p->visual_revision;
  });
  return response()->json(['revision'=>$revision]);
 }
 public function moveImage(Request $r,Story $story,StoryPage $page,ImageGeneration $image) {
  $this->owner($r,$story,$page);abort_unless($image->story_page_id===$page->id && $image->user_id===$r->user()->id,404);
  $data=$r->validate(['visual_id'=>'required|uuid','revision'=>'required|integer']);
  DB::transaction(function()use($page,$image,$data){
   $p=StoryPage::whereKey($page->id)->lockForUpdate()->firstOrFail();
   if($p->visual_revision!==(int)$data['revision'])throw ValidationException::withMessages(['revision'=>'Scene artwork changed. Reload before moving the image.']);
   $target=collect($p->visual_document['visuals']??[])->firstWhere('id',$data['visual_id']);
   if(!$target)throw ValidationException::withMessages(['visual_id'=>'Choose a visual on this page.']);
   $job=ImageGeneration::whereKey($image->id)->lockForUpdate()->firstOrFail();
   if($job->status!=='completed')throw ValidationException::withMessages(['image'=>'Only completed generations can be moved.']);
   $job->update(['visual_id'=>$target['id'],'name'=>$target['name'],'kind'=>$target['kind']]);
   foreach($job->asset_ids??[] as $index=>$id) {
    Asset::where('story_id',$job->story_id)->whereKey($id)->update(['name'=>$target['name'].' · render '.$job->id.'-'.($index+1),'role'=>$target['kind']==='object'?'object':'scene']);
   }
  });
  return response()->json(['message'=>'Generated image moved.']);
 }
 public function generate(Request $r,Story $story,StoryPage $page,OpenRouter $router) {
  $this->owner($r,$story,$page);
  $d=$r->validate(['revision'=>'required|integer','visual_id'=>'required|uuid','request_key'=>'required|uuid','model'=>'required|string|max:200','references'=>'present|array|max:16','references.*'=>'integer|distinct','settings'=>'present|array:quality,resolution,aspect_ratio,background','settings.*'=>'string|max:50']);
  if(!$r->user()->openrouter_key && !config('story.openrouter_key')) throw ValidationException::withMessages(['key'=>'Image generation is not configured. Add a personal OpenRouter key in AI settings.']);
  if(!$r->user()->openrouter_key && $r->user()->llm_spent>=config('story.allowance')) throw ValidationException::withMessages(['key'=>'Your free $1 credit has been used. Add your own OpenRouter key in AI settings to continue.']);
  $endpoints=$router->imageEndpoints($d['model']);
  if(!$endpoints) throw ValidationException::withMessages(['model'=>'No image endpoints are available.']);
  foreach($endpoints as $endpoint) {
   $caps=$endpoint['supported_parameters']??[];
   foreach($d['settings'] as $key=>$value) if(($caps[$key]['type']??'')!=='enum' || !in_array($value,$caps[$key]['values']??[],true)) throw ValidationException::withMessages(['settings'=>'Choose settings supported across this model’s available providers.']);
   if(count($d['references'])>($caps['input_references']['max']??0)) throw ValidationException::withMessages(['references'=>'This model does not support that many references across its available providers.']);
  }
  $pricing=['endpoints'=>array_map(fn($e)=>array_intersect_key($e,array_flip(['provider_tag','provider_name','provider_slug','pricing'])),$endpoints)];
  $refs=[];$bytes=0;
  foreach($d['references'] as $id) {$asset=$story->assets()->findOrFail($id);$bytes+=$asset->bytes;$ref=$asset->only(['id','path','mime','bytes']);$size=@getimagesize(\Illuminate\Support\Facades\Storage::disk('local')->path($asset->path));if($size){$ref['width']=$size[0];$ref['height']=$size[1];}$refs[]=$ref;}
  if($bytes>30*1024*1024) throw ValidationException::withMessages(['references'=>'Keep reference images under 30 MB combined.']);
  $job=DB::transaction(function() use($r,$story,$page,$d,$refs,$pricing) {
   $p=StoryPage::whereKey($page->id)->lockForUpdate()->firstOrFail();
   if($p->visual_revision!==(int)$d['revision']) throw ValidationException::withMessages(['revision'=>'Scene changed. Reload before generating.']);
   if(ImageGeneration::where('user_id',$r->user()->id)->where('request_key',$d['request_key'])->exists()) throw ValidationException::withMessages(['request_key'=>'This generation was already submitted. Refresh job status.']);
   $visual=collect($p->visual_document['visuals']??[])->firstWhere('id',$d['visual_id']);
   if(!$visual || !filled($visual['prompt']??'')) throw ValidationException::withMessages(['prompt'=>'Write and save a prompt for this visual first.']);
   $reserve=$r->user()->openrouter_key?0:app(ImagePricing::class)->quote($pricing,$d['settings'],$visual['prompt'],$refs);
   $ledger=app(LlmBudget::class)->reserve($r->user(),$story,$d['request_key'],$d['model'],$reserve);
   $ledger->update(['pricing_snapshot'=>$pricing]);
   $job=ImageGeneration::create(['llm_request_id'=>$ledger->id,'user_id'=>$r->user()->id,'story_id'=>$story->id,'story_page_id'=>$page->id,'visual_id'=>$visual['id'],'name'=>$visual['name'],'kind'=>$visual['kind'],'request_key'=>$d['request_key'],
    'payload'=>array_merge(['model'=>$d['model'],'prompt'=>$visual['prompt']],$d['settings']), 'references'=>$refs,'pricing'=>$pricing]);
   GenerateSceneImage::dispatch($job->id)->onConnection('database')->onQueue('images')->afterCommit();return $job;
  });
  return response()->json(['id'=>$job->id,'status'=>$job->status],202);
 }
}
