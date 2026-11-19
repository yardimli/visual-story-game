<?php
namespace App\Services;
use App\Models\Story;
class InventoryLibrary {
 // Compatibility for older proposals: extract object definitions once, never store them on a page.
 public function extract(Story $story,array $document): array {
  $items=collect($story->inventory_document['visuals']??[])->keyBy('id');$changed=false;$scenes=[];
  foreach($document['visuals']??[] as $v){if($v['kind']==='object'){$items[$v['id']]=$v;$changed=true;}else $scenes[]=$v;}
  if($changed)$story->update(['inventory_document'=>['visuals'=>$items->values()->all()],'inventory_revision'=>$story->inventory_revision+1]);
  return ['visuals'=>$scenes];
 }
 public function uses(Story $story): array {
  $uses=[];
  foreach($story->pages()->get() as $p)foreach($p->visual_document['visuals']??[] as $v){
   $ids=[];foreach($v['hotspots']??[] as $h){$ids=array_merge($ids,$h['required_items']??[],array_column($h['auto_state']['placements']??[],'object_id'));if(!empty($h['object_id']))$ids[]=$h['object_id'];foreach($h['inventory_rules']??[] as $r)if(!empty($r['object_id']))$ids[]=$r['object_id'];}
   foreach($v['skip_rules']??[] as $r)$ids=array_merge($ids,$r['object_ids']??[]);
   foreach(array_unique($ids) as $id)$uses[$id][]=['page_id'=>$p->id,'scene_id'=>$v['id'],'label'=>'Page '.$p->position.' · '.$p->title.' / '.$v['name'],'url'=>route('stories.edit',[$story,'page'=>$p->id,'tab'=>'visuals','visual'=>$v['id']])];
  }
  foreach(\App\Models\ContainerPlan::where('story_id',$story->id)->get() as $plan)foreach($plan->contents??[] as $content)if(!empty($content['object_id']))$uses[$content['object_id']][]=['page_id'=>$plan->story_page_id,'scene_id'=>$plan->visual_id,'label'=>'Container plan: '.$plan->name,'url'=>route('stories.edit',[$story,'page'=>$plan->story_page_id,'tab'=>'visuals','visual'=>$plan->visual_id])];
  return $uses;
 }
}
