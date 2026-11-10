<?php
namespace App\Services;
use App\Models\{Story,ChatMessage,ImageGeneration};
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanionChanges {
 public const RULES=[
  'inventory'=>['name'=>'required|string|max:160','description'=>'nullable|string|max:3000','description_position'=>'in:top,bottom','asset_id'=>'nullable|integer','prompt'=>'nullable|string|max:10000','actions'=>'array|max:50'],
  'story'=>['game_flags'=>'array|max:100','title'=>'required|string|max:160','premise'=>'required|string|max:5000','genre'=>'required|string|max:80','language'=>'required|string|max:80','style'=>'nullable|string|max:5000','bible'=>'nullable|string|max:40000','llm_model'=>'nullable|string|max:200','image_model'=>'nullable|string|max:200'],
  'page'=>['title'=>'required|string|max:160','narrative'=>'nullable|string|max:10000','interaction_plan'=>'nullable|string|max:10000'],
  'visual'=>['skip_rules'=>'array|max:50','video_prompt'=>'nullable|string|max:5000','description_position'=>'sometimes|in:top,bottom','name'=>'required|string|max:160','description'=>'nullable|string|max:3000','prompt'=>'nullable|string|max:10000','asset_id'=>'nullable|integer'],
  'hotspot'=>['availability_enabled'=>'boolean','flag_conditions'=>'array|max:100','flag_effects'=>'array|max:100','required_items'=>'array|max:100','unavailable_behavior'=>'in:hide,disable','unavailable_text'=>'nullable|string|max:3000','animate_hover_text'=>'boolean','overlay_always'=>'boolean','show_hover_text'=>'boolean','use_item_description'=>'boolean','overlay_asset_id'=>'nullable|integer','overlay_alignment'=>'in:top,middle,bottom','target_scene_id'=>'nullable|uuid','target_page_id'=>'nullable|integer','description_position'=>'sometimes|in:top,bottom,area_top,area_bottom','interaction_text'=>'nullable|string|max:3000','fallback_text'=>'nullable|string|max:3000','inventory_rules'=>'array|max:100','choices'=>'array|max:20','label'=>'required|string|max:160','description'=>'nullable|string|max:3000','action'=>'required|in:examine,inspect,pickup,transition,inventory,choices,next_page,page,complete','object_id'=>'nullable|uuid','target_id'=>'nullable|uuid','overlay'=>'required|boolean','x'=>'required|numeric|min:0|max:1','y'=>'required|numeric|min:0|max:1','width'=>'required|numeric|min:0.005|max:1','height'=>'required|numeric|min:0.005|max:1'],
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
   $shared=collect($story->inventory_document['visuals']??[])->firstWhere('id',$request['visual_id']??$request['item_id']??null);
   if($scope==='visual' && $shared){$scope='inventory';$base['scope']='inventory';}
   if(in_array($scope,['inventory','create_inventory'])){
    if($scope==='inventory'&&!$shared)$this->invalid('Unknown shared inventory item.');
    $fields=Validator::make($request['fields']??[],array_intersect_key(self::RULES['inventory'],$request['fields']??[]))->validate();
    if($scope==='create_inventory'){
     Validator::make($fields,['name'=>'required|string|max:160'])->validate();
     $items[]=['id'=>count($items),'scope'=>$scope,'title'=>'Create inventory item / '.$fields['name'],'before'=>null,'after'=>array_merge(['id'=>(string)Str::uuid(),'kind'=>'object','asset_id'=>null,'description'=>'','prompt'=>'','hotspots'=>[],'actions'=>[]],$fields)];
    }else foreach($fields as $field=>$value)if(($shared[$field]??null)!==$value)$items[]=['id'=>count($items),'scope'=>'inventory','item_id'=>$shared['id'],'field'=>$field,'title'=>'Inventory / '.$shared['name'].' / '.ucfirst($field),'before'=>$shared[$field]??null,'after'=>$value];
    continue;
   }
   if($scope==='create_page'){
    $fields=Validator::make($request['fields']??[],self::RULES['page']+['visuals'=>'sometimes|array|max:100'])->validate();
    $after=$pages->get($request['after_page_id']??null);if(!$after)$this->invalid('Choose an existing page to insert after.');
    $visuals=$fields['visuals']??[];
    foreach($visuals as &$v){$v['id']=(string)Str::uuid();$v['hotspots']??=[];foreach($v['hotspots'] as &$h){$h['id']=(string)Str::uuid();$h+=['overlay'=>false,'target_id'=>null,'object_id'=>null,'description'=>''];}unset($h);}unset($v);
    foreach(['narrative','interaction_plan'] as $textField)if(array_key_exists($textField,$fields))$fields=array_merge($fields,PageRichText::companion($textField,$fields[$textField]));
    $fields['visual_document']=app(SceneDocument::class)->validate($story,['visuals'=>$visuals]);unset($fields['visuals']);
    $items[]=['id'=>count($items),'scope'=>'create_page','after_page_id'=>$after->id,'title'=>'Create page after '.$after->title.' / '.$fields['title'],'before'=>null,'after'=>$fields];continue;
   }
   if($scope!=='story'){
    $page=$pages->get($request['page_id']??null);if(!$page)$this->invalid('A proposed page is outside this story.');
    $base['page_id']=$page->id;$base['visual_revision']=$page->visual_revision;$label='Page '.$page->position.' · '.$page->title;$before=$page->toArray();
   }
   if($scope==='copy_areas'){
    $visuals=collect($page->visual_document['visuals']??[])->keyBy('id');
    $source=$visuals->get($request['source_visual_id']??null);$destination=$visuals->get($request['visual_id']??null);
    if(($source['kind']??null)!=='scene'||($destination['kind']??null)!=='scene')$this->invalid('Choose source and destination scenes on the same page.');
    if($source['id']===$destination['id'])$this->invalid('Choose a different destination scene.');
    $ids=$request['hotspot_ids']??array_column($source['hotspots'],'id');
    Validator::make(['ids'=>$ids],['ids'=>'required|array|min:1|max:100','ids.*'=>'required|uuid|distinct'])->validate();
    foreach($ids as $id){
     $area=collect($source['hotspots'])->firstWhere('id',$id);if(!$area)$this->invalid('The source area does not belong to the source scene.');
     $area['id']=(string)Str::uuid();
     $items[]=array_merge($base,['id'=>count($items),'scope'=>'copy_area','visual_id'=>$destination['id'],'source_visual_id'=>$source['id'],'title'=>$label.' / Copy '.$area['label'].' from '.$source['name'].' to '.$destination['name'],'before'=>null,'after'=>$area]);
    }
    continue;
   }
   if($scope==='image'){
    $fields=app(ImageDrafts::class)->validate($story,$request['fields']??[]);
    $items[]=array_merge($base,['id'=>count($items),'title'=>$label.' / Prepare image: '.($fields['name']??'Untitled image'),'before'=>null,'after'=>$fields]);continue;
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
    $object=collect(array_merge($page->visual_document['visuals']??[],$story->inventory_document['visuals']??[]))->firstWhere('id',$hotspot['object_id']??null);
    $items[]=array_merge($base,['id'=>count($items),'title'=>$label.' / Prepare object image','before'=>$object?array_intersect_key($object,$fields):null,'after'=>$fields]);
   }else{
    if(!isset(self::RULES[$scope]))$this->invalid('Unsupported proposal scope.');
    foreach($fields as $field=>$value){
     if(!isset(self::RULES[$scope][$field]))$this->invalid('Unsupported proposal field.');
     Validator::make([$field=>$value],[$field=>self::RULES[$scope][$field]])->validate();
     if(($before[$field]??null)===$value)continue;
     $rich=$scope==='page' && in_array($field,['narrative','interaction_plan'],true)?PageRichText::companion($field,$value):null;
     $items[]=array_merge($base,$rich?['rich_delta'=>$rich[$field.'_delta']]:[],['id'=>count($items),'field'=>$field,'title'=>$label.' / '.ucfirst(str_replace('_',' ',$field)),'before'=>$before[$field]??null,'after'=>$rich?$rich[$field]:$value]);
    }
   }
   if(!empty($request['open_image_generation']) && in_array($scope,['visual','object']) && count($items)){
    $dependency=count($items)-1;
    $items[]=array_merge($base,['scope'=>'open_image','id'=>count($items),'depends_on'=>$scope==='object'?$dependency:null,'title'=>'Open image generation · '.$label,'before'=>null,'after'=>'Open the prepared visual. Rendering starts only when you click Generate.']);
   }
  }
  return ['revision'=>$story->revision,'inventory_revision'=>$story->inventory_revision,'flags_revision'=>$story->flags_revision,'items'=>$items];
 }
 public function apply(Story $story,ChatMessage $message,array $selected): array {
  return DB::transaction(function()use($story,$message,$selected){
   $s=Story::whereKey($story->id)->lockForUpdate()->firstOrFail();$review=$message->proposal['_review']??null;
   if(!$review||$s->revision!==(int)$review['revision'])$this->invalid('This story changed. Ask for a fresh proposal.');
   if(isset($review['inventory_revision']) && $s->inventory_revision!==(int)$review['inventory_revision'])$this->invalid('Inventory changed. Ask for a fresh proposal.');
   if(isset($review['flags_revision']) && $s->flags_revision!==(int)$review['flags_revision'])$this->invalid('Story flags changed. Ask for a fresh proposal.');
   $all=collect($review['items'])->keyBy('id');$items=[];sort($selected);
   foreach($selected as $id){if(!$all->has($id))$this->invalid('Unknown selected change.');$items[]=$all[$id];}
   foreach($items as $item)if(isset($item['depends_on'])&&!in_array($item['depends_on'],$selected,true))$this->invalid('Select the object preparation before opening its image workspace.');
   $pages=$s->pages()->lockForUpdate()->get()->keyBy('id');$documents=[];$pageFields=[];$storyFields=[];$objects=[];$navigate=null;$newPages=[];$newImages=[];$inventory=collect($s->inventory_document['visuals']??[])->keyBy('id');$inventoryDirty=false;$originalInventory=$s->inventory_document;$originalFlags=$s->game_flags;
   foreach($items as $item){
    $scope=$item['scope'];if($scope==='story'){$storyFields[$item['field']]=$item['after'];continue;}
    if($scope==='create_inventory'){$inventory[$item['after']['id']]=$item['after'];$inventoryDirty=true;continue;}
    if($scope==='inventory'){if(!$inventory->has($item['item_id']))$this->invalid('Inventory item no longer exists.');$v=$inventory[$item['item_id']];$v[$item['field']]=$item['after'];$inventory[$v['id']]=$v;$inventoryDirty=true;continue;}
    if($scope==='create_page'){$newPages[]=$item;continue;}
    $page=$pages->get($item['page_id']);if(!$page)$this->invalid('A proposed page no longer exists.');
    if($scope==='image'){$newImages[]=$item;continue;}
    if($scope==='page'){if(!isset(self::RULES['page'][$item['field']]))$this->invalid('This proposal uses a retired page field. Ask for a fresh proposal in image studio.');$pageFields[$page->id][$item['field']]=$item['after'];if(array_key_exists('rich_delta',$item))$pageFields[$page->id][$item['field'].'_delta']=$item['rich_delta'];continue;}
    if($page->visual_revision!==$item['visual_revision'])$this->invalid('Scene artwork changed. Ask for a fresh proposal.');
    $documents[$page->id]??=$page->visual_document??['visuals'=>[]];$doc=&$documents[$page->id];
    $vi=array_search($item['visual_id'],array_column($doc['visuals'],'id'),true);if($vi===false)$this->invalid('The visual no longer exists.');
    if($scope==='open_image'){$visualId=isset($item['depends_on'])?($objects[$item['depends_on']]??null):$item['visual_id'];if($visualId)$navigate=route('images.studio',[$s,$page]).'?visual='.urlencode($visualId);continue;}
    if($scope==='copy_area'){if($doc['visuals'][$vi]['kind']!=='scene')$this->invalid('Copy destination must be a scene.');$doc['visuals'][$vi]['hotspots'][]=$item['after'];continue;}
    if($scope==='visual'){$doc['visuals'][$vi][$item['field']]=$item['after'];continue;}
    $hi=array_search($item['hotspot_id'],array_column($doc['visuals'][$vi]['hotspots'],'id'),true);if($hi===false)$this->invalid('The area no longer exists.');
    if($scope==='hotspot'){if($item['field']==='description')$doc['visuals'][$vi]['hotspots'][$hi]['use_item_description']=false;$doc['visuals'][$vi]['hotspots'][$hi][$item['field']]=$item['after'];continue;}
    $objectId=$doc['visuals'][$vi]['hotspots'][$hi]['object_id']??null;
    $object=$inventory->get($objectId)??collect($doc['visuals'])->firstWhere('id',$objectId);
    if(!$object){$objectId=(string)Str::uuid();$object=['id'=>$objectId,'kind'=>'object','asset_id'=>null,'hotspots'=>[]];}
    if($object['kind']!=='object')$this->invalid('Invalid object reference.');
    $inventory[$objectId]=array_merge($object,$item['after']);$inventoryDirty=true;
    $doc['visuals'][$vi]['hotspots'][$hi]['object_id']=$objectId;$objects[$item['id']]=$objectId;
   }
   unset($doc); // Release the reference before iterating documents by value.
   if(isset($storyFields['game_flags'])){$storyFields['game_flags']=app(StoryFlags::class)->validate($storyFields['game_flags']);$s->game_flags=$storyFields['game_flags'];$s->flags_revision++;}
   if($inventoryDirty){$s->inventory_document=['visuals'=>$inventory->values()->all()];app(SceneDocument::class)->validate($s,$s->inventory_document);$s->inventory_revision++;$s->save();}
   foreach($documents as $id=>$doc){$doc=app(SceneDocument::class)->validate($s,$doc,$id);$documents[$id]=app(InventoryLibrary::class)->extract($s,$doc);}
   if(isset($storyFields['game_flags']))foreach($pages as $p)if(!isset($documents[$p->id]))app(SceneDocument::class)->validate($s,$p->visual_document??['visuals'=>[]],$p->id);
   $s->revisions()->create(['revision'=>$s->revision,'reason'=>'Applied selected companion changes','snapshot'=>['story'=>array_merge($s->only(array_keys(self::RULES['story'])),['inventory_document'=>$originalInventory,'game_flags'=>$originalFlags]),'pages'=>$pages->values()->map->only(['id','position','title','narrative','interaction_plan','narrative_delta','interaction_plan_delta','visual_document','visual_revision'])->all()]]);
   if($pages->count()+count($newPages)>100)$this->invalid('A story can have at most 100 pages.');
   $insertedAfter=[];
   foreach($newPages as $item){
    $anchor=$insertedAfter[$item['after_page_id']]??$item['after_page_id'];
    $after=$s->pages()->find($anchor);if(!$after)$this->invalid('The insertion page no longer exists.');
    foreach($s->pages()->reorder('position','desc')->where('position','>',$after->position)->get() as $later)$later->increment('position');
    app(SceneDocument::class)->validate($s,$item['after']['visual_document']);
    $fields=$item['after'];$fields['visual_document']=app(InventoryLibrary::class)->extract($s,$fields['visual_document']);
    $created=$s->pages()->create($fields+['position'=>$after->position+1]);$insertedAfter[$item['after_page_id']]=$created->id;
    $navigate=route('stories.edit',['story'=>$s,'page'=>$created,'tab'=>'page']);
   }
   foreach($newImages as $item){
    $draft=app(ImageDrafts::class)->save($s,$pages[$item['page_id']],$item['after']);
    $navigate=route('images.studio',[$s,$pages[$item['page_id']]]).'?image='.$draft->id;
   }
   if($storyFields)$s->fill($storyFields);
   $s->revision++;$s->save();
   foreach($pageFields as $id=>$fields)$pages[$id]->update($fields);
   foreach($documents as $id=>$doc)$pages[$id]->update(['visual_document'=>$doc,'visual_revision'=>$pages[$id]->visual_revision+1]);
   return ['revision'=>$s->revision,'navigate'=>$navigate,'message'=>'Selected changes applied.'];
  });
 }
 private function invalid(string $message): never {throw ValidationException::withMessages(['proposal'=>$message]);}
}
