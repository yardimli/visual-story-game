<?php
namespace App\Services;
use App\Models\Story;
class GameplayMessages {
 public const DEFAULTS = [
  'placed'=>['label'=>'Item placed message','text'=>'You place the item back.','timeout_ms'=>3000],
  'occupied'=>['label'=>'Occupied location message','text'=>'This location is already occupied.','timeout_ms'=>3000],
  'placement_denied'=>['label'=>'Placement not allowed message','text'=>'You cannot place an item here.','timeout_ms'=>3000],
  'nothing'=>['label'=>'No effect message','text'=>'Nothing happens.','timeout_ms'=>3000],
  'complete'=>['label'=>'Story completion message','text'=>'You have reached the end of the story.','timeout_ms'=>5000],
  'scene_load_error'=>['label'=>'Scene image loading error','text'=>'Could not load the next scene image. Please try again.','timeout_ms'=>4000],
  'no_scenes'=>['label'=>'No scenes available','text'=>'Add a scene before testing scenes.','timeout_ms'=>4000],
  'missing_pages'=>['label'=>'Story pages unavailable','text'=>'Reload the editor to load storyboard pages.','timeout_ms'=>4000],
  'next_scene_missing'=>['label'=>'Next scene unavailable','text'=>'Add a scene to the next page before testing this transition.','timeout_ms'=>4000],
  'destination_page_missing'=>['label'=>'Destination page unavailable','text'=>'Choose an available destination page before testing this interaction.','timeout_ms'=>4000],
  'destination_scene_missing'=>['label'=>'Destination scene unavailable','text'=>'Choose an available scene on the destination page before testing this transition.','timeout_ms'=>4000],

 ];
 public static function forStory(Story $story):array {
  $result=[];foreach(self::DEFAULTS as $key=>$default)$result[$key]=array_replace($default,$story->gameplay_messages[$key]??[]);return $result;
 }
}
