<?php
namespace App\Services;
use App\Models\Story;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
class SceneDocument {
 public function validate(Story $story, array $document, ?int $pageId=null): array {
  $stateRules=[];
  foreach(['visuals.*','visuals.*.hotspots.*'] as $path){$stateRules[$path.'.sound_id']=['sometimes','nullable','integer',\Illuminate\Validation\Rule::exists('story_sounds','id')->where('story_id',$story->id)];$stateRules[$path.'.sound_volume']='sometimes|numeric|min:0|max:1';}
  $stateRules['visuals.*.sound_loop']='sometimes|boolean';
  foreach(['visuals.*.skip_rules.*.flag_conditions','visuals.*.hotspots.*.flag_conditions','visuals.*.hotspots.*.flag_effects','visuals.*.hotspots.*.inventory_rules.*.flag_conditions','visuals.*.hotspots.*.inventory_rules.*.flag_effects','visuals.*.hotspots.*.choices.*.flag_effects'] as $path){$stateRules[$path]='sometimes|array|max:100';$stateRules[$path.'.*']='array:key,value';$stateRules[$path.'.*.key']=['required',\Illuminate\Validation\Rule::in(array_column($story->game_flags??[],'key'))];$stateRules[$path.'.*.value']='required|boolean';}
  foreach(['default_bounds','default_overlay_bounds'] as $field){
   $path='visuals.*.hotspots.*.'.$field;$stateRules[$path]='sometimes|array:x,y,width,height';
   foreach(['x','y','width','height'] as $axis)$stateRules[$path.'.'.$axis]='required_with:'.$path.'|numeric|min:'.(in_array($axis,['width','height'])?'0.005':'0').'|max:1';
  }
  $v=Validator::make($document,$stateRules+[
   'visuals'=>'present|array|max:100', 'visuals.*'=>'array:id,name,kind,sound_id,sound_volume,sound_loop,asset_id,prompt,video_prompt,hotspots,actions,description,description_position,skip_rules,dialog_background,dialog_border,dialog_footer',
   'visuals.*.id'=>'required|uuid|distinct','visuals.*.name'=>'required|string|max:160','visuals.*.kind'=>'required|in:scene,object',
   'visuals.*.description'=>'sometimes|nullable|string|max:3000','visuals.*.asset_id'=>'nullable|integer','visuals.*.video_prompt'=>'sometimes|nullable|string|max:5000','visuals.*.prompt'=>'nullable|string|max:10000','visuals.*.hotspots'=>'present|array|max:100',
   'visuals.*.dialog_background'=>['sometimes','regex:/^#[0-9a-fA-F]{6}$/'],
   'visuals.*.dialog_border'=>['sometimes','regex:/^#[0-9a-fA-F]{6}$/'],
   'visuals.*.dialog_footer'=>['sometimes','regex:/^#[0-9a-fA-F]{6}$/'],
   'visuals.*.hotspots.*.animate_hover_text'=>'sometimes|boolean',
   'visuals.*.hotspots.*.overlay_always'=>'sometimes|boolean',
   'visuals.*.hotspots.*.show_hover_text'=>'sometimes|boolean',
   'visuals.*.hotspots.*.use_item_description'=>'sometimes|boolean',
   'visuals.*.skip_rules'=>'sometimes|array|max:50',
   'visuals.*.skip_rules.*'=>'array:id,object_ids,flag_conditions,target_page_id,target_scene_id',
   'visuals.*.skip_rules.*.id'=>'required|uuid',
   'visuals.*.skip_rules.*.object_ids'=>'present|array|max:100',
   'visuals.*.skip_rules.*.object_ids.*'=>'required|uuid',
   'visuals.*.skip_rules.*.target_page_id'=>'nullable|integer',
   'visuals.*.skip_rules.*.target_scene_id'=>'nullable|uuid',
   'visuals.*.actions'=>'sometimes|array|max:50','visuals.*.actions.*'=>'array:id,label,target_id','visuals.*.actions.*.id'=>'required|uuid','visuals.*.actions.*.label'=>'required|string|max:160','visuals.*.actions.*.target_id'=>'nullable|uuid',
   'visuals.*.hotspots.*'=>'array:id,label,sound_id,sound_volume,description,x,y,width,height,cursor,initial_empty,placement_enabled,collectible_flag,action,target_id,object_id,state_prompt,state_description,state_asset_id,state_flag,overlay,overlay_bounds,default_bounds,default_overlay_bounds,overlay_asset_id,overlay_alignment,overlay_always,animate_hover_text,use_item_description,show_hover_text,interaction_text,fallback_text,inventory_rules,choices,description_position,target_page_id,target_scene_id,availability_enabled,flag_conditions,required_items,unavailable_behavior,unavailable_text,flag_effects',
   'visuals.*.hotspots.*.initial_empty'=>'sometimes|boolean',
   'visuals.*.hotspots.*.placement_enabled'=>'sometimes|boolean',
   'visuals.*.hotspots.*.collectible_flag'=>['sometimes','nullable',\Illuminate\Validation\Rule::in(array_column($story->game_flags??[],'key'))],
   'visuals.*.hotspots.*.cursor'=>'sometimes|in:auto,pointer,grab,help,alias,crosshair,zoom-in,not-allowed,default',
   'visuals.*.hotspots.*.availability_enabled'=>'sometimes|boolean',
   'visuals.*.hotspots.*.required_items'=>'sometimes|array|max:100',
   'visuals.*.hotspots.*.required_items.*'=>'required|uuid',
   'visuals.*.hotspots.*.unavailable_behavior'=>'sometimes|in:hide,disable',
   'visuals.*.hotspots.*.unavailable_text'=>'sometimes|nullable|string|max:3000',
   'visuals.*.hotspots.*.state_description'=>'sometimes|nullable|string|max:3000',
   'visuals.*.hotspots.*.state_prompt'=>'sometimes|nullable|string|max:3000',
   'visuals.*.hotspots.*.state_asset_id'=>'sometimes|nullable|integer',
   'visuals.*.hotspots.*.state_flag'=>['sometimes','nullable',\Illuminate\Validation\Rule::in(array_column($story->game_flags??[],'key'))],
   'visuals.*.hotspots.*.overlay_bounds'=>'sometimes|array:x,y,width,height',
   'visuals.*.hotspots.*.overlay_bounds.x'=>'required_with:visuals.*.hotspots.*.overlay_bounds|numeric|min:0|max:1',
   'visuals.*.hotspots.*.overlay_bounds.y'=>'required_with:visuals.*.hotspots.*.overlay_bounds|numeric|min:0|max:1',
   'visuals.*.hotspots.*.overlay_bounds.width'=>'required_with:visuals.*.hotspots.*.overlay_bounds|numeric|min:0.005|max:1',
   'visuals.*.hotspots.*.overlay_bounds.height'=>'required_with:visuals.*.hotspots.*.overlay_bounds|numeric|min:0.005|max:1',
   'visuals.*.hotspots.*.overlay_asset_id'=>'sometimes|nullable|integer',
   'visuals.*.hotspots.*.overlay_alignment'=>'sometimes|in:top,middle,bottom',
   'visuals.*.hotspots.*.id'=>'required|uuid','visuals.*.hotspots.*.label'=>'required|string|max:160','visuals.*.hotspots.*.description'=>'nullable|string|max:3000',
   'visuals.*.hotspots.*.x'=>'required|numeric|min:0|max:1','visuals.*.hotspots.*.y'=>'required|numeric|min:0|max:1',
   'visuals.*.hotspots.*.width'=>'required|numeric|min:0.005|max:1','visuals.*.hotspots.*.height'=>'required|numeric|min:0.005|max:1',
   'visuals.*.hotspots.*.action'=>'required|in:examine,inspect,transition,pickup,pickup_v2,state_v2,inventory,choices,next_page,page,complete',
   'visuals.*.hotspots.*.interaction_text'=>'sometimes|nullable|string|max:3000','visuals.*.hotspots.*.fallback_text'=>'sometimes|nullable|string|max:3000',
   'visuals.*.hotspots.*.inventory_rules'=>'sometimes|array|max:100',
   'visuals.*.hotspots.*.inventory_rules.*'=>'array:object_id,response,target_id,consume,description_position,flag_effects,flag_conditions,allowed',
   'visuals.*.hotspots.*.inventory_rules.*.object_id'=>'nullable|uuid',
   'visuals.*.hotspots.*.inventory_rules.*.response'=>'nullable|string|max:3000',
   'visuals.*.hotspots.*.inventory_rules.*.target_id'=>'nullable|uuid',
   'visuals.*.hotspots.*.inventory_rules.*.allowed'=>'sometimes|boolean',
   'visuals.*.hotspots.*.inventory_rules.*.consume'=>'sometimes|boolean',
   'visuals.*.hotspots.*.choices'=>'sometimes|array|max:20',
   'visuals.*.hotspots.*.choices.*'=>'array:label,response,target_id,description_position,flag_effects',
   'visuals.*.hotspots.*.choices.*.label'=>'required|string|max:160',
   'visuals.*.hotspots.*.choices.*.response'=>'nullable|string|max:3000',
   'visuals.*.hotspots.*.choices.*.target_id'=>'nullable|uuid',
   'visuals.*.hotspots.*.target_scene_id'=>'sometimes|nullable|uuid',
   'visuals.*.hotspots.*.target_page_id'=>'sometimes|nullable|integer',
   'visuals.*.hotspots.*.target_id'=>'nullable|uuid','visuals.*.hotspots.*.object_id'=>'nullable|uuid','visuals.*.hotspots.*.overlay'=>'required|boolean',
   'visuals.*.description_position'=>'sometimes|in:top,bottom',
   'visuals.*.hotspots.*.description_position'=>'sometimes|in:top,bottom,area_top,area_bottom',
   'visuals.*.hotspots.*.inventory_rules.*.description_position'=>'sometimes|in:top,bottom',
   'visuals.*.hotspots.*.choices.*.description_position'=>'sometimes|in:top,bottom,area_top,area_bottom',
  ])->validate();
  foreach($v['visuals'] as &$visual)foreach($visual['hotspots'] as &$area)if(!in_array($area['action'],['pickup','inspect','transition'],true))$area['target_id']=null;
  unset($visual,$area);
  $visuals=collect($v['visuals'])->keyBy('id');
  $assets=collect($v['visuals'])->pluck('asset_id')->merge(collect($v['visuals'])->flatMap(fn($visual)=>collect($visual['hotspots'])->flatMap(fn($h)=>[$h['overlay_asset_id']??null,$h['state_asset_id']??null])))->filter()->unique();
  if($story->assets()->whereIn('id',$assets)->count()!==$assets->count()) $this->invalid('An image does not belong to this story.');
  $pageVisuals=$story->pages()->get()->mapWithKeys(fn($page)=>[$page->id=>$page->id===$pageId?$v['visuals']:($page->visual_document['visuals']??[])]);
  $objects=$pageVisuals->flatten(1)->merge($story->inventory_document['visuals']??[])->merge($v['visuals'])->where('kind','object')->pluck('id')->all();
  // Drafts may have unfinished interaction links; supplied links must still be valid.
  foreach($visuals as $visual) {
   if($visual['kind']!=='scene' && !empty($visual['skip_rules']))$this->invalid('Only scenes can have skip rules.');
   $skipIds=[];
   foreach($visual['skip_rules']??[] as $rule){
    if(in_array($rule['id'],$skipIds,true))$this->invalid('Duplicate scene skip rule ID.');$skipIds[]=$rule['id'];
    foreach($rule['object_ids'] as $id)if(!in_array($id,$objects,true))$this->invalid('Skip rule items must be objects in this story.');
    if(!empty($rule['target_page_id']) && !$pageVisuals->has($rule['target_page_id']))$this->invalid('Skip destination must belong to this story.');
    if(!empty($rule['target_scene_id']) && !collect($pageVisuals->get($rule['target_page_id']??null,[]))->contains(fn($scene)=>$scene['id']===$rule['target_scene_id'] && $scene['kind']==='scene'))$this->invalid('Skip destination scene must belong to the selected page.');
   }
   $actionIds=[];
   if($visual['kind']==='scene' && count($visual['actions']??[]))$this->invalid('Only objects can have inventory actions.');
   foreach($visual['actions']??[] as $action){
    if(in_array($action['id'],$actionIds,true))$this->invalid('Duplicate object action ID.');$actionIds[]=$action['id'];
    if(!empty($action['target_id']) && !in_array($action['target_id'],$objects,true))$this->invalid('Action replacement must be an object in this game.');
   }
   $ids=[];
   if($visual['kind']==='object' && count($visual['hotspots'])) $this->invalid('Object images cannot contain scene hotspots.');
   foreach($visual['hotspots'] as $h) {
    foreach($h['required_items']??[] as $id)if(!in_array($id,$objects,true))$this->invalid('Required item must belong to this game.');
    if(!empty($h['target_scene_id'])){
     $destination=$story->pages()->find($h['target_page_id']??null);
     $destinationVisuals=$destination && $destination->id===$pageId?$v['visuals']:($destination?->visual_document['visuals']??[]);
     if(!collect($destinationVisuals)->contains(fn($scene)=>$scene['id']===$h['target_scene_id'] && $scene['kind']==='scene'))$this->invalid('Destination scene must belong to the selected page.');
    }
    if(!empty($h['target_page_id']) && !$story->pages()->whereKey($h['target_page_id'])->exists())$this->invalid('Destination page must belong to this story.');
    $ruleObjects=[];
    foreach($h['inventory_rules']??[] as $rule){
     if(!empty($rule['object_id'])){
      if(!in_array($rule['object_id'],$objects,true))$this->invalid('Inventory rule must use an object in this story.');
      if(in_array($rule['object_id'],$ruleObjects,true))$this->invalid('Each inventory object can have only one rule per area.');
      $ruleObjects[]=$rule['object_id'];
     }
    }
    foreach(array_merge($h['inventory_rules']??[],$h['choices']??[]) as $outcome){
     if(!empty($outcome['target_id']) && ($visuals->get($outcome['target_id'])['kind']??null)!=='scene')$this->invalid('Interaction outcome must target a scene on this page.');
    }
    if(in_array($h['id'],$ids,true)) $this->invalid('Duplicate hotspot ID.'); $ids[]=$h['id'];
    if($h['x']+$h['width']>1.000001 || $h['y']+$h['height']>1.000001) $this->invalid('Clickable areas must stay within the image.');
    if(!empty($h['target_id']) && ($visuals->get($h['target_id'])['kind']??null)!=='scene') $this->invalid('Scene target must be a scene on this page.');
    if(!empty($h['object_id']) && !in_array($h['object_id'],$objects,true)) $this->invalid('Object target must be an object in this story.');
   }
  }
  return $v;
 }
 private function invalid(string $message): void {throw ValidationException::withMessages(['visuals'=>$message]);}
}
