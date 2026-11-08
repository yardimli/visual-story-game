<?php
namespace App\Services;
use App\Models\{Story,ChatMessage};
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanionChanges {
 public const RULES=[
  'story'=>['title'=>'required|string|max:160','premise'=>'required|string|max:5000','genre'=>'required|string|max:80','language'=>'required|string|max:80','style'=>'nullable|string|max:5000','bible'=>'nullable|string|max:40000','llm_model'=>'nullable|string|max:200','image_model'=>'nullable|string|max:200'],
  'page'=>['title'=>'required|string|max:160','narrative'=>'nullable|string|max:10000','scene_prompt'=>'nullable|string|max:10000','video_prompt'=>'nullable|string|max:5000','interaction_plan'=>'nullable|string|max:10000','completion'=>'nullable|string|max:2000'],
  'visual'=>['name'=>'required|string|max:160','description'=>'nullable|string|max:3000','prompt'=>'nullable|string|max:10000','asset_id'=>'nullable|integer'],
  'hotspot'=>['interaction_text'=>'nullable|string|max:3000','fallback_text'=>'nullable|string|max:3000','inventory_rules'=>'array|max:100','choices'=>'array|max:20','label'=>'required|string|max:160','description'=>'nullable|string|max:3000','action'=>'required|in:examine,inspect,pickup,transition,inventory,choices,next_page','object_id'=>'nullable|uuid','target_id'=>'nullable|uuid','overlay'=>'required|boolean','x'=>'required|numeric|min:0|max:1','y'=>'required|numeric|min:0|max:1','width'=>'required|numeric|min:0.005|max:1','height'=>'required|numeric|min:0.005|max:1'],
 ];
 public function prepare(Story $story,array $proposal, $pages=null): array {
  $pages=($pages??$story->pages()->get())->keyBy('id');$requests=$proposal['changes']??[];$items=[];
  if(!is_array($requests)||count($requests)>300)$this->invalid('Too many proposed changes.');
  // Backward-compatible prose proposals become the same field-level review items.
  if(!$requests){
   $fields=array_intersect_key(is_array($proposal['story']??null)?$proposal['story']:[],self::RULES['story']);
   if(is_string($proposal['bible']??null))$fields['bible']=$proposal['bible'];
   if(is_array($proposal['bible']??null))$fields=array_merge($proposal['bible'],$fields);
   foreach(self::RULES['story'] as $key=>$rule)if(isset($proposal[$key])&&is_string($proposal[$key]))$fields[$key]=$proposal[$key];
   if($fields)$requests[]=['scope'=>'story','fields'=>$fields];
   foreach(is_array($proposal['pages']??null)?$proposal['pages']:[] as $fields){if(!is_array($fields))continue;$page=$pages->firstWhere('position',$fields['position']??null);if(!$page)continue;unset($fields['position']);
    if(isset($proposal['_image'])){$requests[]=['scope'=>'visual','page_id'=>$page->id,'visual_id'=>$proposal['_image']['id'],'fields'=>['prompt'=>$fields['scene_prompt']??'']];}
    else $requests[]=['scope'=>'page','page_id'=>$page->id,'fields'=>$fields];
   }
   if(isset($proposal['_interaction'],$proposal['interaction'])){
    $target=$proposal['_interaction'];$change=$proposal['interaction'];$base=['page_id'=>$target['page_id'],'visual_id'=>$target['visual_id'],'hotspot_id'=>$target['hotspot_id']];
    $fields=array_intersect_key($change,array_flip(['description','action']));if($fields)$requests[]=array_merge($base,['scope'=>'hotspot','fields'=>$fields]);
    if(isset($change['object']))$requests[]=array_merge($base,['scope'=>'object','fields'=>$change['object'],'open_image_generation'=>true]);
   }
  }
  foreach($requests as $request){
   if(!is_array($request))$this->invalid('Invalid proposed change.');
   $scope=$request['scope']??'';$page=null;$visual=null;$hotspot=null;$base=['scope'=>$scope];$label='Story';$before=$story->toArray();
   if($scope!=='story'){
    $page=$pages->get($request['page_id']??null);if(!$page)$this->invalid('A proposed page is outside this story.');
    $base['page_id']=$page->id;$base['visual_revision']=$page->visual_revision;$label='Page '.$page->position.' · '.$page->title;$before=$page->toArray();
   }
   if(in_array($scope,['visual','hotspot','object'])){
    $visual=collect($page->visual_document['visuals']??[])->firstWhere('id',$request['visual_id']??null);if(!$visual)$this->invalid('Unknown visual in proposal.');
    $base['visual_id']=$visual['id'];$label.=' / '.$visual['name'];$before=$visual;
   }
   if(in_array($scope,['hotspot','object'])){
    $hotspot=collect($visual['hotspots']??[])->firstWhere('id',$request['hotspot_id']??null);if(!$hotspot)$this->invalid('Unknown clickable area in proposal.');
    $base['hotspot_id']=$hotspot['id'];$label.=' / '.$hotspot['label'];$before=$hotspot;
   }
   $fields=$request['fields']??[];if(!is_array($fields))$this->invalid('Invalid proposed fields.');
   if($scope==='object'){
    $fields=Validator::make($fields,['name'=>'required|string|max:160','prompt'=>'required|string|max:10000'])->validate();
    $object=collect($page->visual_document['visuals']??[])->firstWhere('id',$hotspot['object_id']??null);
    $items[]=array_merge($base,['id'=>count($items),'title'=>$label.' / Prepare object image','before'=>$object?array_intersect_key($object,$fields):null,'after'=>$fields]);
   }else{
    if(!isset(self::RULES[$scope]))$this->invalid('Unsupported proposal scope.');
    foreach($fields as $field=>$value){
     if(!isset(self::RULES[$scope][$field]))$this->invalid('Unsupported proposal field.');
     Validator::make([$field=>$value],[$field=>self::RULES[$scope][$field]])->validate();
     if(($before[$field]??null)===$value)continue;
     $items[]=array_merge($base,['id'=>count($items),'field'=>$field,'title'=>$label.' / '.ucfirst(str_replace('_',' ',$field)),'before'=>$before[$field]??null,'after'=>$value]);
    }
   }
   if(!empty($request['open_image_generation']) && in_array($scope,['visual','object']) && count($items)){
    $dependency=count($items)-1;
    $items[]=array_merge($base,['scope'=>'open_image','id'=>count($items),'depends_on'=>$scope==='object'?$dependency:null,'title'=>'Open image generation · '.$label,'before'=>null,'after'=>'Open the prepared visual. Rendering starts only when you click Generate.']);
   }
  }
  return ['revision'=>$story->revision,'items'=>$items];
 }
 public function apply(Story $story,ChatMessage $message,array $selected): array {
  return DB::transaction(function()use($story,$message,$selected){
   $s=Story::whereKey($story->id)->lockForUpdate()->firstOrFail();$review=$message->proposal['_review']??null;
   if(!$review||$s->revision!==(int)$review['revision'])$this->invalid('This story changed. Ask for a fresh proposal.');
   $all=collect($review['items'])->keyBy('id');$items=[];sort($selected);
   foreach($selected as $id){if(!$all->has($id))$this->invalid('Unknown selected change.');$items[]=$all[$id];}
   foreach($items as $item)if(isset($item['depends_on'])&&!in_array($item['depends_on'],$selected,true))$this->invalid('Select the object preparation before opening its image workspace.');
   $pages=$s->pages()->lockForUpdate()->get()->keyBy('id');$documents=[];$pageFields=[];$storyFields=[];$objects=[];$navigate=null;
   foreach($items as $item){
    $scope=$item['scope'];if($scope==='story'){$storyFields[$item['field']]=$item['after'];continue;}
    $page=$pages->get($item['page_id']);if(!$page)$this->invalid('A proposed page no longer exists.');
    if($scope==='page'){$pageFields[$page->id][$item['field']]=$item['after'];continue;}
    if($page->visual_revision!==$item['visual_revision'])$this->invalid('Scene artwork changed. Ask for a fresh proposal.');
    $documents[$page->id]??=$page->visual_document??['visuals'=>[]];$doc=&$documents[$page->id];
    $vi=array_search($item['visual_id'],array_column($doc['visuals'],'id'),true);if($vi===false)$this->invalid('The visual no longer exists.');
    if($scope==='open_image'){$visualId=isset($item['depends_on'])?($objects[$item['depends_on']]??null):$item['visual_id'];if($visualId)$navigate=route('images.studio',[$s,$page]).'?visual='.urlencode($visualId);continue;}
    if($scope==='visual'){$doc['visuals'][$vi][$item['field']]=$item['after'];continue;}
    $hi=array_search($item['hotspot_id'],array_column($doc['visuals'][$vi]['hotspots'],'id'),true);if($hi===false)$this->invalid('The area no longer exists.');
    if($scope==='hotspot'){$doc['visuals'][$vi]['hotspots'][$hi][$item['field']]=$item['after'];continue;}
    $objectId=$doc['visuals'][$vi]['hotspots'][$hi]['object_id']??null;$oi=array_search($objectId,array_column($doc['visuals'],'id'),true);
    if($oi===false){$objectId=(string)Str::uuid();$doc['visuals'][]=['id'=>$objectId,'kind'=>'object','name'=>$item['after']['name'],'prompt'=>$item['after']['prompt'],'asset_id'=>null,'hotspots'=>[]];}
    else {if($doc['visuals'][$oi]['kind']!=='object')$this->invalid('Invalid object reference.');$doc['visuals'][$oi]=array_merge($doc['visuals'][$oi],$item['after']);}
    $doc['visuals'][$vi]['hotspots'][$hi]['object_id']=$objectId;$objects[$item['id']]=$objectId;
   }
   foreach($documents as $id=>$doc)app(SceneDocument::class)->validate($s,$doc);
   $s->revisions()->create(['revision'=>$s->revision,'reason'=>'Applied selected companion changes','snapshot'=>['story'=>$s->only(array_keys(self::RULES['story'])),'pages'=>$pages->values()->map->only(['id','position','title','narrative','scene_prompt','video_prompt','interaction_plan','completion','visual_document','visual_revision'])->all()]]);
   if($storyFields)$s->fill($storyFields);
   $s->revision++;$s->save();
   foreach($pageFields as $id=>$fields)$pages[$id]->update($fields);
   foreach($documents as $id=>$doc)$pages[$id]->update(['visual_document'=>$doc,'visual_revision'=>$pages[$id]->visual_revision+1]);
   return ['revision'=>$s->revision,'navigate'=>$navigate,'message'=>'Selected changes applied.'];
  });
 }
 private function invalid(string $message): never {throw ValidationException::withMessages(['proposal'=>$message]);}
}
