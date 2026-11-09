<?php
namespace App\Services;
use App\Models\Story;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
class SceneDocument {
 public function validate(Story $story, array $document, ?int $pageId=null): array {
  $v=Validator::make($document,[
   'visuals'=>'present|array|max:100', 'visuals.*'=>'array:id,name,kind,asset_id,prompt,video_prompt,hotspots,actions,description,description_position',
   'visuals.*.id'=>'required|uuid|distinct','visuals.*.name'=>'required|string|max:160','visuals.*.kind'=>'required|in:scene,object',
   'visuals.*.description'=>'sometimes|nullable|string|max:3000','visuals.*.asset_id'=>'nullable|integer','visuals.*.video_prompt'=>'sometimes|nullable|string|max:5000','visuals.*.prompt'=>'nullable|string|max:10000','visuals.*.hotspots'=>'present|array|max:100',
   'visuals.*.actions'=>'sometimes|array|max:50','visuals.*.actions.*'=>'array:id,label,target_id','visuals.*.actions.*.id'=>'required|uuid','visuals.*.actions.*.label'=>'required|string|max:160','visuals.*.actions.*.target_id'=>'nullable|uuid',
   'visuals.*.hotspots.*'=>'array:id,label,description,x,y,width,height,action,target_id,object_id,overlay,interaction_text,fallback_text,inventory_rules,choices,description_position,target_page_id,target_scene_id',
   'visuals.*.hotspots.*.id'=>'required|uuid','visuals.*.hotspots.*.label'=>'required|string|max:160','visuals.*.hotspots.*.description'=>'nullable|string|max:3000',
   'visuals.*.hotspots.*.x'=>'required|numeric|min:0|max:1','visuals.*.hotspots.*.y'=>'required|numeric|min:0|max:1',
   'visuals.*.hotspots.*.width'=>'required|numeric|min:0.005|max:1','visuals.*.hotspots.*.height'=>'required|numeric|min:0.005|max:1',
   'visuals.*.hotspots.*.action'=>'required|in:examine,inspect,transition,pickup,inventory,choices,next_page,page,complete',
   'visuals.*.hotspots.*.interaction_text'=>'sometimes|nullable|string|max:3000','visuals.*.hotspots.*.fallback_text'=>'sometimes|nullable|string|max:3000',
   'visuals.*.hotspots.*.inventory_rules'=>'sometimes|array|max:100',
   'visuals.*.hotspots.*.inventory_rules.*'=>'array:object_id,response,target_id,consume,description_position',
   'visuals.*.hotspots.*.inventory_rules.*.object_id'=>'nullable|uuid',
   'visuals.*.hotspots.*.inventory_rules.*.response'=>'nullable|string|max:3000',
   'visuals.*.hotspots.*.inventory_rules.*.target_id'=>'nullable|uuid',
   'visuals.*.hotspots.*.inventory_rules.*.consume'=>'sometimes|boolean',
   'visuals.*.hotspots.*.choices'=>'sometimes|array|max:20',
   'visuals.*.hotspots.*.choices.*'=>'array:label,response,target_id,description_position',
   'visuals.*.hotspots.*.choices.*.label'=>'required|string|max:160',
   'visuals.*.hotspots.*.choices.*.response'=>'nullable|string|max:3000',
   'visuals.*.hotspots.*.choices.*.target_id'=>'nullable|uuid',
   'visuals.*.hotspots.*.target_scene_id'=>'sometimes|nullable|uuid',
   'visuals.*.hotspots.*.target_page_id'=>'sometimes|nullable|integer',
   'visuals.*.hotspots.*.target_id'=>'nullable|uuid','visuals.*.hotspots.*.object_id'=>'nullable|uuid','visuals.*.hotspots.*.overlay'=>'required|boolean',
   'visuals.*.description_position'=>'sometimes|in:top,bottom',
   'visuals.*.hotspots.*.description_position'=>'sometimes|in:top,bottom',
   'visuals.*.hotspots.*.inventory_rules.*.description_position'=>'sometimes|in:top,bottom',
   'visuals.*.hotspots.*.choices.*.description_position'=>'sometimes|in:top,bottom',
  ])->validate();
  $visuals=collect($v['visuals'])->keyBy('id');
  $assets=collect($v['visuals'])->pluck('asset_id')->filter()->unique();
  if($story->assets()->whereIn('id',$assets)->count()!==$assets->count()) $this->invalid('An image does not belong to this story.');
  // Drafts may have unfinished interaction links; supplied links must still be valid.
  foreach($visuals as $visual) {
   $actionIds=[];
   if($visual['kind']==='scene' && count($visual['actions']??[]))$this->invalid('Only objects can have inventory actions.');
   foreach($visual['actions']??[] as $action){
    if(in_array($action['id'],$actionIds,true))$this->invalid('Duplicate object action ID.');$actionIds[]=$action['id'];
    if(!empty($action['target_id']) && ($visuals->get($action['target_id'])['kind']??null)!=='object')$this->invalid('Action replacement must be an object on this page.');
   }
   $ids=[];
   if($visual['kind']==='object' && count($visual['hotspots'])) $this->invalid('Object images cannot contain scene hotspots.');
   foreach($visual['hotspots'] as $h) {
    if(!empty($h['target_scene_id'])){
     $destination=$story->pages()->find($h['target_page_id']??null);
     $destinationVisuals=$destination && $destination->id===$pageId?$v['visuals']:($destination?->visual_document['visuals']??[]);
     if(!collect($destinationVisuals)->contains(fn($scene)=>$scene['id']===$h['target_scene_id'] && $scene['kind']==='scene'))$this->invalid('Destination scene must belong to the selected page.');
    }
    if(!empty($h['target_page_id']) && !$story->pages()->whereKey($h['target_page_id'])->exists())$this->invalid('Destination page must belong to this story.');
    $ruleObjects=[];
    foreach($h['inventory_rules']??[] as $rule){
     if(!empty($rule['object_id'])){
      if(($visuals->get($rule['object_id'])['kind']??null)!=='object')$this->invalid('Inventory rule must use an object on this page.');
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
    if(!empty($h['object_id']) && ($visuals->get($h['object_id'])['kind']??null)!=='object') $this->invalid('Object target must be an object on this page.');
   }
  }
  return $v;
 }
 private function invalid(string $message): void {throw ValidationException::withMessages(['visuals'=>$message]);}
}
