<?php
namespace App\Http\Controllers;
use App\Models\Story;use App\Services\StoryFlags;use Illuminate\Http\Request;use Illuminate\Support\Facades\DB;use Illuminate\Validation\ValidationException;
class StoryStateController extends Controller {
 public function show(Request $r,Story $story){abort_unless($story->user_id===$r->user()->id,404);return response()->json(['flags'=>$story->game_flags??[],'revision'=>$story->flags_revision]);}
 public function update(Request $r,Story $story,StoryFlags $flags){abort_unless($story->user_id===$r->user()->id,404);$d=$r->validate(['revision'=>'required|integer','flags'=>'present|array']);$values=$flags->validate($d['flags']);
 $revision=DB::transaction(function()use($story,$d,$values,$flags){$s=Story::whereKey($story->id)->lockForUpdate()->firstOrFail();if($s->flags_revision!==$d['revision'])throw ValidationException::withMessages(['revision'=>'Story flags changed. Reload before editing.']);$flags->assertReferences($s,$values);$s->update(['game_flags'=>$values,'flags_revision'=>$s->flags_revision+1]);return $s->flags_revision;});return response()->json(['revision'=>$revision]);}
 public function order(Request $r,Story $story){abort_unless($story->user_id===$r->user()->id,404);$d=$r->validate(['order'=>'present|array|max:10000','order.*'=>'required|integer|distinct']);if(\App\Models\ImageGeneration::where('story_id',$story->id)->whereIn('id',$d['order'])->count()!==count($d['order']))throw ValidationException::withMessages(['order'=>'Image order must only contain images in this story.']);$story->update(['image_order'=>$d['order']]);return response()->json(['order'=>$story->image_order]);}
}
