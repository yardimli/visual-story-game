<?php
namespace App\Console\Commands;
use App\Models\{Story,SceneExtraction,ContainerPlan,ChatMessage};
use App\Services\StoryFlags;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Storage};
class RenameGeneratedFlagKeys extends Command {
 protected $signature='story:rename-generated-flags {--apply : Apply the renames after saving a backup}';
 protected $description='Rename UUID-based generated story flags and their structured references';
 public function handle(StoryFlags $service):int {
  return DB::transaction(function()use($service){
   $changes=[];$backups=[];
   foreach(Story::query()->lockForUpdate()->get() as $story){
    $flags=$story->game_flags??[];$reserved=$flags;$map=[];
    foreach($flags as $flag)if(preg_match('/^(collected|switch|container)_[a-f0-9]{32}$/',$flag['key'])){$name=$flag['label'];if(str_starts_with($flag['key'],'container_')){$name=preg_replace('/ plan(?= open$)/i','',$name);if(preg_match('/^open (.+) open$/i',$name,$match))$name=$match[1].' open';}$key=$service->generatedKey($name,$reserved);$map[$flag['key']]=$key;$reserved[]=['key'=>$key];$this->line('Story '.$story->id.': '.$flag['key'].' -> '.$key);}
    if(!$map)continue;
    $updates=[];
    $record=function($model,array $values)use(&$updates,&$backups){$old=[];foreach($values as $column=>$value)$old[$column]=$model->getRawOriginal($column);$backups[]=['table'=>$model->getTable(),'id'=>$model->id,'values'=>$old];$updates[]=[$model,$values];};
    $record($story,['game_flags'=>$service->renameKeys($flags,$map),'flags_revision'=>$story->flags_revision+1,'inventory_document'=>$service->renameKeys($story->inventory_document??['visuals'=>[]],$map),'inventory_revision'=>$story->inventory_revision+1]);
    foreach($story->pages()->lockForUpdate()->get() as $page){$doc=$service->renameKeys($page->visual_document??[],$map);if($doc!==($page->visual_document??[]))$record($page,['visual_document'=>$doc,'visual_revision'=>$page->visual_revision+1]);}
    foreach(SceneExtraction::where('story_id',$story->id)->lockForUpdate()->get() as $plan){$outputs=$service->renameKeys($plan->outputs??[],$map);if($outputs!==($plan->outputs??[]))$record($plan,['outputs'=>$outputs]);}
    foreach(ContainerPlan::where('story_id',$story->id)->lockForUpdate()->get() as $plan){$contents=$service->renameKeys($plan->contents??[],$map);if($contents!==($plan->contents??[]))$record($plan,['contents'=>$contents]);}
    foreach(ChatMessage::where('story_id',$story->id)->whereNotNull('proposal')->lockForUpdate()->get() as $message){$proposal=$service->renameKeys($message->proposal,$map);if($proposal!==$message->proposal)$record($message,['proposal'=>$proposal]);}
    $changes=array_merge($changes,$updates);
   }
   if(!$changes){$this->info('No UUID-based generated flags found.');return self::SUCCESS;}
   if(!$this->option('apply')){$this->info('Dry run only. Pass --apply to save.');return self::SUCCESS;}
   $path='flag-key-backups/'.now()->format('Ymd-His').'-'.\Illuminate\Support\Str::uuid().'.json';
   if(!Storage::disk('local')->put($path,json_encode($backups,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)))throw new \RuntimeException('Could not save backup; no flags changed.');
   foreach($changes as [$model,$values])$model->update($values);
   $this->info('Updated '.count($changes).' records. Backup: '.Storage::disk('local')->path($path));
   return self::SUCCESS;
  });
 }
}
