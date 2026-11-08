<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
class Asset extends Model {
 protected $guarded = ['id'];
 public static function withGenerationNames(Collection $assets, int $storyId): Collection {
  $byId=$assets->keyBy('id');
  foreach(ImageGeneration::where('story_id',$storyId)->where('status','completed')->get(['id','name','kind','asset_ids']) as $job) {
   foreach($job->asset_ids??[] as $index=>$id) {
    if($asset=$byId->get($id)) {$asset->name=$job->name.' · render '.$job->id.'-'.($index+1);$asset->role=$job->kind==='object'?'object':'scene';}
   }
  }
  return $assets;
 }
}
