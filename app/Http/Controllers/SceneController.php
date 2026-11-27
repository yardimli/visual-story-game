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
  if($r->has('status_ids')){
   $d=$r->validate(['status_ids'=>'required|array|min:1|max:50','status_ids.*'=>'required|integer|distinct']);
   $jobs=ImageGeneration::where('story_id',$story->id)->whereIn('id',$d['status_ids'])->get(['id','status','error','asset_ids','started_at']);
   return response()->json(['jobs'=>$jobs,'missing_ids'=>array_values(array_diff($d['status_ids'],$jobs->modelKeys()))])->header('Cache-Control','private, no-store');
  }
  $uses=[];$assetPages=[];
  $pageLabels=$story->pages()->get(['id','position','title'])->mapWithKeys(function($p){$label='Page '.$p->position;$title=trim($p->title??'');return [$p->id=>!$title||strcasecmp($title,$label)===0?$label:$label.' · '.$title];});
  foreach(ImageGeneration::where('story_id',$story->id)->where('status','completed')->get(['story_page_id','asset_ids']) as $job)foreach($job->asset_ids??[] as $id)$assetPages[$id][]=$job->story_page_id;
  foreach($story->pages()->get(['id','visual_document']) as $usedPage)foreach($usedPage->visual_document['visuals']??[] as $visual){
   $visualAssets=[$visual['asset_id']??null];
   foreach($visual['hotspots']??[] as $area){
    $visualAssets[]=$area['state_asset_id']??null;
    $visualAssets[]=$area['overlay_asset_id']??null;
   }
   foreach(array_unique(array_filter($visualAssets)) as $assetId){
    $assetPages[$assetId][]=$usedPage->id;
    $uses[$assetId][]=['id'=>$visual['id'],'name'=>$visual['name'],'kind'=>$visual['kind'],'url'=>route('stories.edit',['story'=>$story,'page'=>$usedPage->id,'tab'=>'visuals','visual'=>$visual['id']])];
   }
  }
  foreach($story->inventory_document['visuals']??[] as $item)if(!empty($item['asset_id']))$uses[$item['asset_id']][]=['id'=>$item['id'],'name'=>$item['name'],'kind'=>'object','url'=>route('stories.edit',[$story,'page'=>$page->id,'tab'=>'inventory'])];
  $imageCategories=[];$planReferences=[];$containers=\App\Models\ContainerPlan::where('story_id',$story->id)->get()->keyBy('extraction_id');
  foreach(\App\Models\SceneExtraction::where('story_id',$story->id)->get() as $batch){
   foreach($batch->outputs??[] as $output)foreach($output['versions']??[] as $jobId)$imageCategories[$jobId]=in_array($output['type'],['switch_on','switch_off','container_preview'])?'change_state':(in_array($output['type'],['inventory','layer'])?'inventory':'scene');
   $container=$containers->get($batch->id);$type=$batch->mode==='container'?'Container':($batch->mode==='switches'?'Switch':'Extraction');
   $name=$container?->name??($pageLabels[$batch->story_page_id]??'Plan').' #'.$batch->id;
   $ids=[$batch->source_asset_id];if($container?->preview_asset_id)$ids[]=$container->preview_asset_id;
   foreach($batch->outputs??[] as $output)foreach($output['references']??[] as $id)$ids[]=$id;
   foreach(array_unique(array_filter($ids)) as $id)$planReferences[$id][]=['id'=>$batch->id,'label'=>$type.' reference · '.$name,'url'=>route('stories.edit',['story'=>$story,'page'=>$batch->story_page_id,'tab'=>'visuals','visual'=>$batch->visual_id])];
  }
  return response()->json(['characters'=>app(\App\Services\CharacterLibrary::class)->present($story),'remaining'=>$r->user()->availableCredit()/1000000,'page_id'=>$page->id,'container_plans'=>\App\Models\ContainerPlan::where('story_page_id',$page->id)->get(['id','visual_id','area_id','name','stage']),'game_flags'=>$story->game_flags??[],'unavailable_timeout_ms'=>(int)($story->unavailable_timeout_ms??1000),'unavailable_text'=>$story->fresh()->unavailable_text,'ui_theme'=>app(\App\Services\GuiThemes::class)->forStory($story),'cursor_tooltips'=>$story->cursor_tooltips??[],'image_categories'=>\App\Http\Controllers\ImageCategoryController::categories($story),'image_order'=>$story->image_order??[],'inventory_items'=>$story->inventory_document['visuals']??[],'preview_pages'=>$story->pages()->get(['id','position','title','visual_document'])->map(fn($p)=>['id'=>$p->id,'position'=>$p->position,'images_url'=>route('visuals.show',[$story,$p]),'title'=>$p->title,'visuals'=>$p->visual_document['visuals']??[]]),'document'=>$page->visual_document??['visuals'=>[]],'revision'=>$page->visual_revision,
   'assets'=>Asset::withGenerationNames($story->assets()->latest()->get(),$story->id)->map(fn($a)=>['id'=>$a->id,'name'=>$a->name,'url'=>route('assets.show',$a),'role'=>$a->role,'page_ids'=>array_values(array_unique($assetPages[$a->id]??[])),'archived'=>!!$a->archived_at,'update_url'=>route('assets.update',$a),'uses'=>$uses[$a->id]??[],'plan_references'=>$planReferences[$a->id]??[]]),
   'jobs'=>ImageGeneration::where('story_id',$story->id)->latest('id')->get(['id','story_page_id','visual_id','name','category','kind','status','error','asset_ids','created_at','started_at','payload','references'])->map(fn($j)=>['id'=>$j->id,'page_id'=>$j->story_page_id,'visual_id'=>$j->visual_id,'name'=>$j->name,'category'=>$j->category??$imageCategories[$j->id]??($j->kind==='object'?'inventory':'scene'),'status'=>$j->status,'error'=>$j->error,'asset_ids'=>$j->asset_ids,'created_at'=>$j->created_at,'started_at'=>$j->started_at,'composition'=>$j->payload['_composition']??null,'prompt'=>$j->payload['_base_prompt']??$j->payload['prompt']??'','model'=>$j->payload['model']??'','video_prompt'=>$j->payload['_video_prompt']??'','references'=>array_column($j->references??[],'id'),'settings'=>array_intersect_key($j->payload??[],array_flip(['quality','resolution','aspect_ratio','background'])),'retry_url'=>route('images.retry',[$story,$page,$j])])]);
 }
 public function deleteDraft(Request $r,Story $story,ImageGeneration $image) {
  abort_unless($story->user_id===$r->user()->id && $image->story_id===$story->id && $image->user_id===$r->user()->id,404);
  DB::transaction(function()use($story,$image){
   $s=Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
   $draft=ImageGeneration::whereKey($image->id)->lockForUpdate()->firstOrFail();
   if($draft->status!=='draft' || !empty($draft->asset_ids) || $draft->llm_request_id)throw ValidationException::withMessages(['image'=>'Only ungenerated image drafts can be deleted. Generated images can be archived.']);
   $draft->delete();$s->update(['image_order'=>array_values(array_filter($s->image_order??[],fn($id)=>(int)$id!==$draft->id))]);
  });
  return response()->json(['deleted'=>$image->id]);
 }
 public function draft(Request $r,Story $story,StoryPage $page,\App\Services\ImageDrafts $drafts) {
  $this->owner($r,$story,$page);$data=$r->validate(['draft_id'=>'nullable|integer']);
  $draft=DB::transaction(function()use($r,$story,$page,$drafts,$data){
   $draft=isset($data['draft_id'])?ImageGeneration::where('story_id',$story->id)->whereKey($data['draft_id'])->lockForUpdate()->firstOrFail():null;
   return $drafts->save($story,$page,$r->all(),$draft);
  });
  return response()->json(['id'=>$draft->id,'status'=>'draft']);
 }
 public function update(Request $r,Story $story,StoryPage $page,SceneDocument $validator) {
  $this->owner($r,$story,$page);$data=$r->validate(['revision'=>'required|integer','document'=>'required|array']);
  $revision=DB::transaction(function() use($story,$page,$data,$validator) {
   $s=Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
   $p=StoryPage::whereKey($page->id)->lockForUpdate()->firstOrFail();
   if($p->visual_revision!==(int)$data['revision']) throw ValidationException::withMessages(['revision'=>'Scene artwork changed in another tab. Reload before editing.']);
   $removed=collect($p->visual_document['visuals']??[])->where('kind','scene')->pluck('id')->diff(collect($data['document']['visuals']??[])->pluck('id'))->all();
   $doc=$this->clearDeletedDestinations($data['document'],$removed);
   $oldAreas=collect($p->visual_document['visuals']??[])->flatMap(fn($v)=>$v['hotspots']??[])->pluck('id')->all();
   foreach($doc['visuals'] as &$scene){$ids=array_column($scene['hotspots']??[],'id');foreach($scene['hotspots'] as &$area)if(isset($area['auto_state'])){$rows=$area['auto_state']['placements']??[];$kept=array_values(array_filter($rows,fn($r)=>!in_array($r['area_id']??null,$oldAreas,true)||in_array($r['area_id']??null,$ids,true)));if(count($kept)!==count($rows)){$area['auto_state']['placements']=$kept;$area['auto_state']['enabled']=false;}}unset($area);}unset($scene);
   $doc=$validator->validate($s,$doc,$p->id);
   if($removed)foreach($s->pages()->where('id','!=',$p->id)->lockForUpdate()->get() as $other){
    $clean=$this->clearDeletedDestinations($other->visual_document??['visuals'=>[]],$removed);
    if($clean!==$other->visual_document)$other->update(['visual_document'=>$clean,'visual_revision'=>$other->visual_revision+1]);
   }
   $doc=app(\App\Services\InventoryLibrary::class)->extract($s,$doc);
   // Plans cannot outlive the drawn container they target. Keep generated assets/history.
   foreach(\App\Models\ContainerPlan::where('story_page_id',$p->id)->lockForUpdate()->get() as $plan){
    $scene=collect($doc['visuals'])->firstWhere('id',$plan->visual_id);
    if(!$scene || !collect($scene['hotspots']??[])->contains('id',$plan->area_id)){$batchId=$plan->extraction_id;$plan->delete();\App\Models\SceneExtraction::whereKey($batchId)->delete();}
   }
   $p->update(['visual_document'=>$doc,'visual_revision'=>$p->visual_revision+1]);return $p->visual_revision;
  });
  return response()->json(['revision'=>$revision,'preview_pages'=>$story->pages()->get()->map(fn($p)=>['id'=>$p->id,'position'=>$p->position,'title'=>$p->title,'images_url'=>route('visuals.show',[$story,$p]),'visuals'=>$p->visual_document['visuals']??[]])]);
 }
 private function clearDeletedDestinations(array $doc,array $removed):array {
  if(!$removed)return $doc;
  foreach($doc['visuals']??[] as $i=>$visual){
   if(isset($visual['skip_rules']))$doc['visuals'][$i]['skip_rules']=array_values(array_filter($visual['skip_rules'],fn($r)=>!in_array($r['target_scene_id']??null,$removed,true)));
   foreach($visual['hotspots']??[] as $j=>$area){
    if(in_array($area['target_scene_id']??null,$removed,true)){
     $area['target_scene_id']=null;$area['move_after_examine']=false;$area['target_page_id']=null;if($area['action']==='page')$area['action']='examine';
    }
    if(in_array($area['target_id']??null,$removed,true)){$area['target_id']=null;if($area['action']==='transition')$area['action']='examine';}
    foreach(['inventory_rules','choices'] as $key)foreach($area[$key]??[] as $k=>$rule)if(in_array($rule['target_id']??null,$removed,true))$area[$key][$k]['target_id']=null;
    foreach(['original_target','changed_target'] as $field)if(in_array($area['auto_state'][$field]??null,$removed,true))$area['auto_state'][$field]=null;
    $doc['visuals'][$i]['hotspots'][$j]=$area;
   }
  }
  return $doc;
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
 public function retry(Request $r,Story $story,StoryPage $page,ImageGeneration $image,OpenRouter $router) {
  $this->owner($r,$story,$page);abort_unless($image->story_id===$story->id && $image->user_id===$r->user()->id,404);
  if($image->status!=='failed')throw ValidationException::withMessages(['image'=>'Only failed image requests can be retried.']);
  $data=$r->validate(['request_key'=>'required|uuid']);
  $r->merge(['request_key'=>$data['request_key'],'name'=>$image->payload['_asset_name']??$image->name,'revision'=>$page->visual_revision,'visual_id'=>$image->visual_id,'model'=>$image->payload['model']??'',
   'references'=>array_column($image->references??[],'id'),'settings'=>array_intersect_key($image->payload,array_flip(['quality','resolution','aspect_ratio','background']))]);
  return $this->submit($r,$story,$page,$router,$image);
 }
 public function generate(Request $r,Story $story,StoryPage $page,OpenRouter $router) {
  return $this->submit($r,$story,$page,$router);
 }
 private function submit(Request $r,Story $story,StoryPage $page,OpenRouter $router,?ImageGeneration $retry=null) {
  $this->owner($r,$story,$page);
  $d=$r->validate(['name'=>'nullable|string|max:160','revision'=>'required|integer','visual_id'=>'nullable|uuid','draft_id'=>'nullable|integer','request_key'=>'required|uuid','model'=>'required|string|max:200','references'=>'present|array|max:16','references.*'=>'integer|distinct','settings'=>'present|array:quality,resolution,aspect_ratio,background','settings.*'=>'string|max:50']);
  if(!$r->user()->openrouter_key && !config('story.openrouter_key')) throw ValidationException::withMessages(['key'=>'Image generation is not configured. Add a personal OpenRouter key in AI settings.']);
  if(!$r->user()->openrouter_key && $r->user()->llm_spent>=$r->user()->creditTotal()) throw ValidationException::withMessages(['key'=>'Your AI credits have been used. Add your own OpenRouter key in AI settings to continue.']);
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
  $job=DB::transaction(function() use($r,$story,$page,$d,$refs,$pricing,$retry) {
   $p=StoryPage::whereKey($page->id)->lockForUpdate()->firstOrFail();
   if(!$retry && $p->visual_revision!==(int)$d['revision']) throw ValidationException::withMessages(['revision'=>'Scene changed. Reload before generating.']);
   $draft=isset($d['draft_id'])?ImageGeneration::where('story_id',$story->id)->whereKey($d['draft_id'])->lockForUpdate()->firstOrFail():null;
   if($draft && $draft->status!=='draft')throw ValidationException::withMessages(['image'=>'This draft was already submitted. Refresh before generating again.']);
   if(ImageGeneration::where('user_id',$r->user()->id)->where('request_key',$d['request_key'])->exists()) throw ValidationException::withMessages(['request_key'=>'This generation was already submitted. Refresh job status.']);
   $visual=$retry?['id'=>$retry->visual_id,'name'=>$retry->name,'kind'=>$retry->kind,'prompt'=>$retry->payload['prompt']??'']:($draft?['id'=>$draft->visual_id,'name'=>$draft->name,'kind'=>$draft->kind,'prompt'=>$draft->payload['prompt']??'']:collect(array_merge($p->visual_document['visuals']??[],$story->inventory_document['visuals']??[]))->firstWhere('id',$d['visual_id']??null));
   if(!$visual || !filled($visual['prompt']??'')) throw ValidationException::withMessages(['prompt'=>'Write and save a prompt for this visual first.']);
   $basePrompt=$retry?($retry->payload['_base_prompt']??$visual['prompt']):$visual['prompt'];
   if(!$retry)$visual['prompt'] .= app(\App\Services\CharacterLibrary::class)->referenceInstructions($story, array_column($refs,'id'));
   $reserve=$r->user()->openrouter_key?0:app(ImagePricing::class)->quote($pricing,$d['settings'],$visual['prompt'],$refs);
   $ledger=app(LlmBudget::class)->reserve($r->user(),$story,$d['request_key'],$d['model'],$reserve);
   $ledger->update(['pricing_snapshot'=>$pricing]);
   $values=['category'=>$retry?->category??$draft?->category,'status'=>'queued','llm_request_id'=>$ledger->id,'user_id'=>$r->user()->id,'story_id'=>$story->id,'story_page_id'=>$page->id,'visual_id'=>$visual['id'],'name'=>$d['name']??$visual['name'],'kind'=>$visual['kind'],'request_key'=>$d['request_key'],
    'payload'=>array_merge(['model'=>$d['model'],'prompt'=>$visual['prompt'],'_base_prompt'=>$basePrompt],$d['settings'],!empty($d['name'])?['_asset_name'=>$d['name']]:[]), 'references'=>$refs,'pricing'=>$pricing];
   if($retry && isset($retry->payload['_video_prompt']))$values['payload']['_video_prompt']=$retry->payload['_video_prompt'];
   if($draft){$values['payload']['_video_prompt']=$draft->payload['_video_prompt']??'';$draft->update($values);$job=$draft;}else $job=ImageGeneration::create($values);
   GenerateSceneImage::dispatch($job->id)->onConnection('database')->onQueue('images')->afterCommit();return $job;
  });
  return response()->json(['id'=>$job->id,'status'=>$job->status],202);
 }
}
