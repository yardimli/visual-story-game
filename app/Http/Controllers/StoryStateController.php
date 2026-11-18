<?php
namespace App\Http\Controllers;
use App\Models\Story;use App\Services\StoryFlags;use Illuminate\Http\Request;use Illuminate\Support\Facades\DB;use Illuminate\Validation\ValidationException;
class StoryStateController extends Controller {
 public function show(Request $r,Story $story){abort_unless($story->user_id===$r->user()->id,404);return response()->json(['flags'=>$story->game_flags??[],'revision'=>$story->flags_revision]);}
 public function update(Request $r,Story $story,StoryFlags $flags){abort_unless($story->user_id===$r->user()->id,404);$d=$r->validate(['revision'=>'required|integer','flags'=>'present|array']);$values=$flags->validate($d['flags']);
 $revision=DB::transaction(function()use($story,$d,$values,$flags){$s=Story::whereKey($story->id)->lockForUpdate()->firstOrFail();if($s->flags_revision!==$d['revision'])throw ValidationException::withMessages(['revision'=>'Story flags changed. Reload before editing.']);$flags->assertReferences($s,$values);$s->update(['game_flags'=>$values,'flags_revision'=>$s->flags_revision+1]);return $s->flags_revision;});return response()->json(['revision'=>$revision]);}
 public function rename(Request $r,Story $story,string $flag,StoryFlags $flags){
  abort_unless($story->user_id===$r->user()->id,404);$d=$r->validate(['revision'=>'required|integer','key'=>['required','string','max:64','regex:/^[a-z][a-z0-9_]*$/'],'label'=>'required|string|max:160']);
  return DB::transaction(function()use($story,$flag,$flags,$d){
   $s=Story::whereKey($story->id)->lockForUpdate()->firstOrFail();if($s->flags_revision!==$d['revision'])throw ValidationException::withMessages(['revision'=>'Story flags changed. Try saving the rename again.']);
   $definitions=$s->game_flags??[];$index=array_search($flag,array_column($definitions,'key'),true);abort_if($index===false,404);
   if($d['key']!==$flag&&collect($definitions)->contains('key',$d['key']))throw ValidationException::withMessages(['key'=>'That key already belongs to another flag. Choose a unique key.']);
   $map=[$flag=>$d['key']];$pages=$s->pages()->orderBy('id')->lockForUpdate()->get();
   foreach($pages as $p){$doc=$flags->renameKeys($p->visual_document??[],$map);if($doc!==($p->visual_document??[]))$p->update(['visual_document'=>$doc,'visual_revision'=>$p->visual_revision+1]);}
   $inventory=$flags->renameKeys($s->inventory_document??['visuals'=>[]],$map);if($inventory!==$s->inventory_document){$s->inventory_document=$inventory;$s->inventory_revision++;}
   foreach(\App\Models\SceneExtraction::where('story_id',$s->id)->lockForUpdate()->get() as $plan){$outputs=$flags->renameKeys($plan->outputs??[],$map);if($outputs!==($plan->outputs??[]))$plan->update(['outputs'=>$outputs]);}
   foreach(\App\Models\ContainerPlan::where('story_id',$s->id)->lockForUpdate()->get() as $plan){$contents=$flags->renameKeys($plan->contents??[],$map);if($contents!==($plan->contents??[]))$plan->update(['contents'=>$contents]);}
   foreach($s->messages()->whereNotNull('proposal')->lockForUpdate()->get() as $message){$proposal=$flags->renameKeys($message->proposal,$map);if($proposal!==$message->proposal)$message->update(['proposal'=>$proposal]);}
   $definitions[$index]['key']=$d['key'];$definitions[$index]['label']=$d['label'];$s->game_flags=$definitions;$s->flags_revision++;$s->save();
   return response()->json(['flags'=>$definitions,'revision'=>$s->flags_revision,'pages'=>$pages->map(fn($p)=>['id'=>$p->id,'document'=>$p->visual_document,'revision'=>$p->visual_revision]),'inventory_items'=>$s->inventory_document['visuals']??[]]);
  });
 }
 public function uses(Request $r,Story $story,string $flag,StoryFlags $flags){
  abort_unless($story->user_id===$r->user()->id,404);abort_unless(collect($story->game_flags)->contains('key',$flag),404);
  return response()->json($flags->usage($story,$flag,$story->pages()->orderBy('id')->get()));
 }
 public function destroy(Request $r,Story $story,string $flag,StoryFlags $flags){
  abort_unless($story->user_id===$r->user()->id,404);$d=$r->validate(['revision'=>'required|integer','fingerprint'=>'required|string']);
  return DB::transaction(function()use($story,$flag,$flags,$d){
   $s=Story::whereKey($story->id)->lockForUpdate()->firstOrFail();$pages=$s->pages()->orderBy('id')->lockForUpdate()->get();abort_unless(collect($s->game_flags)->contains('key',$flag),404);
   $usage=$flags->usage($s,$flag,$pages);if($s->flags_revision!==$d['revision']||!hash_equals($usage['fingerprint'],$d['fingerprint']))throw ValidationException::withMessages(['flag'=>'Flag usage changed. Close this dialog and review its uses again.']);
   foreach($pages as $p){$doc=$flags->inspectDocument($p->visual_document??[],$flag,'',true)['document'];if($doc!==($p->visual_document??[]))$p->update(['visual_document'=>$doc,'visual_revision'=>$p->visual_revision+1]);}
   $inventory=$flags->inspectDocument($s->inventory_document??[],$flag,'',true)['document'];if($inventory!==($s->inventory_document??[])){$s->inventory_document=$inventory;$s->inventory_revision++;}
   $s->game_flags=array_values(array_filter($s->game_flags,fn($f)=>$f['key']!==$flag));$s->flags_revision++;$s->save();
   return response()->json(['flags'=>$s->game_flags,'revision'=>$s->flags_revision,'pages'=>$pages->map(fn($p)=>['id'=>$p->id,'document'=>$p->visual_document,'revision'=>$p->visual_revision]),'inventory_items'=>$s->inventory_document['visuals']??[]]);
  });
 }
 public function order(Request $r,Story $story){abort_unless($story->user_id===$r->user()->id,404);$d=$r->validate(['order'=>'present|array|max:10000','order.*'=>'required|integer|distinct']);if(\App\Models\ImageGeneration::where('story_id',$story->id)->whereIn('id',$d['order'])->count()!==count($d['order']))throw ValidationException::withMessages(['order'=>'Image order must only contain images in this story.']);$story->update(['image_order'=>$d['order']]);return response()->json(['order'=>$story->image_order]);}
}
