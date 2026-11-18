<?php
namespace App\Services;
class AreaActionSettings {
 public static function document(array $doc):array {
  foreach(is_array($doc['visuals']??null)?$doc['visuals']:[] as $i=>$visual)foreach(is_array($visual['hotspots']??null)?$visual['hotspots']:[] as $j=>$area)if(is_array($area))$doc['visuals'][$i]['hotspots'][$j]=self::area($area);
  return $doc;
 }
 public static function area(array $h):array {
  if(isset($h['cursor'])&&!array_key_exists('action_cursor',$h))$h['action_cursor']=$h['cursor'];
  // Collapse the former whole-area and second-click gates into one action gate.
  if(isset($h['action_requirements'])&&is_array($h['action_requirements'])){
   $r=$h['action_requirements'];$oldEnabled=$h['availability_enabled']??(!empty($h['flag_conditions'])||!empty($h['required_items']));$newEnabled=$r['availability_enabled']??true;
   foreach(['flag_conditions','required_items'] as $field){$old=$h[$field]??[];$new=$r[$field]??[];$rows=($oldEnabled===$newEnabled)?array_merge($old,$new):($oldEnabled?$old:$new);$h[$field]=array_values(array_unique($rows,SORT_REGULAR));}
   $h['availability_enabled']=$oldEnabled||$newEnabled;$h['unavailable_text']=($newEnabled&&!empty($r['unavailable_text']))?$r['unavailable_text']:($h['unavailable_text']??$r['unavailable_text']??'');
  }
  if(($h['action']??null)==='inventory' && array_key_exists('interaction_text',$h)){if(trim($h['description']??'')==='')$h['description']=$h['interaction_text'];unset($h['interaction_text']);}
  unset($h['action_requirements'],$h['unavailable_behavior'],$h['use_item_description'],$h['show_object']);
  return $h;
 }
}
