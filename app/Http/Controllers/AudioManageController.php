<?php
namespace App\Http\Controllers;
use App\Models\{Story,StorySound,AudioGeneration,User};
use App\Services\{AudioDrafts,SpeechCatalog,LlmBudget};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
class AudioManageController extends Controller {
 private function owner(Request $r,Story $s):void {abort_unless($s->user_id===$r->user()->id,404);}
 public function start(Request $r,Story $story,AudioGeneration $job){
  $this->owner($r,$story);abort_unless($job->story_id===$story->id,404);abort_unless($job->status==='draft',409,'Only pending drafts can be started.');
  $data=$job->only(['request_key','name','prompt','source_text','category','model','settings','seconds']);
  $controller=app(AudioController::class);$input=Request::create($r->url(),'POST',$data);$input->setUserResolver(fn()=>$r->user());
  if(in_array($job->model,SpeechCatalog::MODELS)){$catalog=app(SpeechCatalog::class);$settings=$job->settings??[];if(empty($settings['voice']))$settings['voice']=$catalog->model($job->model)['supported_voices'][0]??'';$input->merge(['settings'=>$settings]);return $controller->speech($input,$story,$catalog);}
  return $controller->generate($input,$story);
 }
 private function editable(Request $r,Story $story,AudioGeneration $job,callable $fn){$this->owner($r,$story);abort_unless($job->story_id===$story->id,404);return DB::transaction(function()use($r,$job,$fn){User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();$g=AudioGeneration::whereKey($job->id)->lockForUpdate()->firstOrFail();abort_unless(in_array($g->status,['draft','queued'])&&!$g->submitted_at&&!$g->provider_request_id,409,'This request has already started and cannot be edited or deleted.');if($g->accounting)app(LlmBudget::class)->settle($g->accounting,0,'cancelled');return $fn($g);});}
 public function update(Request $r,Story $story,AudioGeneration $job){$fields=app(AudioDrafts::class)->validate($r->all());return $this->editable($r,$story,$job,function($g)use($fields){$values=$fields+['status'=>'draft','llm_request_id'=>null,'request_key'=>(string)Str::uuid()];if($g->status==='queued'){$draft=$g->replicate();$draft->fill($values);$draft->save();$g->delete();$g=$draft;}else $g->update($values);return response()->json($g);});}
 public function destroyJob(Request $r,Story $story,AudioGeneration $job){return $this->editable($r,$story,$job,function($g){$g->delete();return response()->json(['message'=>'Pending audio deleted.']);});}
 private function clean(array $node,StorySound $sound,string $path,array &$uses,bool $defaults=false):array {
  foreach($node as $key=>$value){if(is_array($value)){$label=$value['name']??$value['label']??$key;$node[$key]=$this->clean($value,$sound,$path.' / '.$label,$uses,$defaults||$key==='defaults');continue;}
   if(in_array($key,['sound_id','action_sound_id','music_id'])||($defaults&&in_array($key,['click','action']))){if((string)$value===(string)$sound->id){$uses[]=$path.' / '.str_replace('_',' ',$key);$node[$key]=$defaults||$key==='music_id'?null:'silent';if($key==='music_id'&&isset($node['music_mode']))$node['music_mode']='stop';}}
   if($sound->source_text&&in_array($key,['description','state_description','interaction_text','fallback_text','unavailable_text','response','narrative'])&&AudioDrafts::text($value)===AudioDrafts::text($sound->source_text))$uses[]=$path.' / '.str_replace('_',' ',$key).' speech';
  }return $node;
 }
 public function usage(Request $r,Story $story,StorySound $sound){$this->owner($r,$story);abort_unless($sound->story_id===$story->id,404);$uses=[];$this->clean($story->audio_settings??[],$sound,'Story bible',$uses);$this->clean($story->inventory_document??[],$sound,'Inventory',$uses);foreach($story->pages as $p)$this->clean(['narrative'=>$p->narrative,'scenes'=>$p->visual_document??[]],$sound,'Page '.$p->position.' / '.$p->title,$uses);return response()->json(['uses'=>array_values(array_unique($uses))]);}
 public function destroySound(Request $r,Story $story,StorySound $sound){$this->owner($r,$story);abort_unless($sound->story_id===$story->id,404);$r->validate(['confirm'=>'accepted']);
  DB::transaction(function()use($story,$sound){$s=Story::whereKey($story->id)->lockForUpdate()->firstOrFail();$uses=[];$settings=$this->clean($s->audio_settings??[],$sound,'Story bible',$uses);$inventory=$this->clean($s->inventory_document??[],$sound,'Inventory',$uses);if($inventory!==($s->inventory_document??[]))$s->inventory_revision++;$s->audio_settings=$settings;$s->inventory_document=$inventory;$s->revision++;$s->save();foreach($s->pages()->lockForUpdate()->get() as $page){$old=$page->visual_document??[];$doc=$this->clean($old,$sound,$page->title,$uses);if($doc!==$old)$page->update(['visual_document'=>$doc,'visual_revision'=>$page->visual_revision+1]);}AudioGeneration::where('sound_id',$sound->id)->update(['sound_id'=>null]);$sound->delete();DB::afterCommit(fn()=>Storage::disk('local')->delete($sound->getRawOriginal('path')));});return response()->json(['message'=>'Audio deleted; its assignments are now silent.']);
 }
}
