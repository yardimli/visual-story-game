<?php
namespace App\Http\Controllers;
use App\Models\{Story,StorySound,AudioGeneration};
use App\Services\StoryAudio;
use App\Jobs\GenerateStoryAudio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache,DB,Storage};
class AudioController extends Controller {
 private function owner(Request $r,Story $s):void {abort_unless($s->user_id===$r->user()->id,404);}
 public function index(Request $r,Story $story){$this->owner($r,$story);return response()->json(['sounds'=>StorySound::where('story_id',$story->id)->latest()->get(),'jobs'=>AudioGeneration::where('story_id',$story->id)->latest()->limit(100)->get(),'searches'=>DB::table('audio_searches')->where('story_id',$story->id)->latest('updated_at')->limit(50)->pluck('query')]);}
 public function search(Request $r,Story $story,StoryAudio $audio){$this->owner($r,$story);$d=$r->validate(['max_duration'=>'sometimes|nullable|numeric|min:0.1|max:3600','query'=>'required|string|max:200','page'=>'sometimes|integer|min:1|max:100']);$query=preg_replace('/\s+/u',' ',trim($d['query']));$result=$audio->search($query,(int)($d['page']??1),array_key_exists('max_duration',$d)?($d['max_duration']===null?null:(float)$d['max_duration']):3);DB::table('audio_searches')->updateOrInsert(['story_id'=>$story->id,'query'=>$query],['updated_at'=>now(),'created_at'=>now()]);return response()->json($result);}
 public function add(Request $r,Story $story,StoryAudio $audio){$this->owner($r,$story);$d=$r->validate(['id'=>'required|integer|min:1']);$sound=Cache::store('file')->get('freesound.sound.'.$d['id']);abort_unless($sound,422,'Search for this sound again before adding it.');
  return Cache::store('file')->lock('audio.import.'.$story->id.'.'.$d['id'],90)->block(2,function()use($story,$d,$sound,$audio){$existing=StorySound::where('story_id',$story->id)->where('provider','freesound')->where('external_id',(string)$d['id'])->first();if($existing)return response()->json($existing);
   try{$asset=$audio->save($story,$sound['preview_url'],['name'=>$sound['name'],'provider'=>'freesound','external_id'=>(string)$sound['id'],'creator'=>$sound['creator'],'license'=>$sound['license'],'source_url'=>$sound['source_url'],'duration'=>$sound['duration']]);}catch(\RuntimeException $e){abort(422,$e->getMessage());}return response()->json($asset,201);
  });
 }
 public function generate(Request $r,Story $story){$this->owner($r,$story);$d=$r->validate(['request_key'=>'required|uuid','name'=>'required|string|max:160','prompt'=>'required|string|max:3000','seconds'=>'required|integer|min:1|max:47']);abort_unless(config('services.fal.key'),422,'FAL_KEY is not configured.');
  $job=AudioGeneration::firstOrCreate(['story_id'=>$story->id,'request_key'=>$d['request_key']],$d);if($job->wasRecentlyCreated)GenerateStoryAudio::dispatch($job->id)->onConnection('database')->onQueue('audio')->afterCommit();return response()->json($job,202);
 }
 public function file(Request $r,StorySound $sound){$this->owner($r,Story::findOrFail($sound->story_id));return response()->file(Storage::disk('local')->path($sound->getRawOriginal('path')),['Content-Type'=>$sound->mime,'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, max-age=3600']);}
}
