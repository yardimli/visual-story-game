<?php
namespace App\Services;
use App\Models\Story;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
class SceneDocument {
 public function validate(Story $story, array $document): array {
  $v=Validator::make($document,[
   'visuals'=>'present|array|max:100', 'visuals.*'=>'array:id,name,kind,asset_id,prompt,hotspots',
   'visuals.*.id'=>'required|uuid|distinct','visuals.*.name'=>'required|string|max:160','visuals.*.kind'=>'required|in:scene,object',
   'visuals.*.asset_id'=>'nullable|integer','visuals.*.prompt'=>'nullable|string|max:10000','visuals.*.hotspots'=>'present|array|max:100',
   'visuals.*.hotspots.*'=>'array:id,label,description,x,y,width,height,action,target_id,object_id,overlay',
   'visuals.*.hotspots.*.id'=>'required|uuid','visuals.*.hotspots.*.label'=>'required|string|max:160','visuals.*.hotspots.*.description'=>'nullable|string|max:3000',
   'visuals.*.hotspots.*.x'=>'required|numeric|min:0|max:1','visuals.*.hotspots.*.y'=>'required|numeric|min:0|max:1',
   'visuals.*.hotspots.*.width'=>'required|numeric|min:0.005|max:1','visuals.*.hotspots.*.height'=>'required|numeric|min:0.005|max:1',
   'visuals.*.hotspots.*.action'=>'required|in:examine,inspect,transition,pickup',
   'visuals.*.hotspots.*.target_id'=>'nullable|uuid','visuals.*.hotspots.*.object_id'=>'nullable|uuid','visuals.*.hotspots.*.overlay'=>'required|boolean',
  ])->validate();
  $visuals=collect($v['visuals'])->keyBy('id');
  $assets=collect($v['visuals'])->pluck('asset_id')->filter()->unique();
  if($story->assets()->whereIn('id',$assets)->count()!==$assets->count()) $this->invalid('An image does not belong to this story.');
  // Drafts may have unfinished interaction links; supplied links must still be valid.
  foreach($visuals as $visual) {
   $ids=[];
   if($visual['kind']==='object' && count($visual['hotspots'])) $this->invalid('Object images cannot contain scene hotspots.');
   foreach($visual['hotspots'] as $h) {
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
