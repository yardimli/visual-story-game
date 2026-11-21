<?php
namespace App\Http\Controllers;
use App\Models\{Story,StorySound,AudioGeneration};
use App\Services\StoryAudio;
use App\Jobs\GenerateStoryAudio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache,DB,Storage};
class AudioController extends Controller {
 public const CATEGORIES=['scenes'=>'Scenes','inventory'=>'Inventory','click_areas'=>'Click areas','music'=>'Music','effects'=>'Effects'];
 private function usedCategories(Story $story):array {
  return StorySound::where('story_id',$story->id)->pluck('category')->merge(AudioGeneration::where('story_id',$story->id)->pluck('category'))->map(fn($key)=>$key?:'effects')->unique()->values()->all();
 }
 private function categories(Story $story):array {
  $categories=json_decode($story->getRawOriginal('audio_categories')??'null',true)??self::CATEGORIES;
  foreach($this->usedCategories($story) as $key)$categories[$key]??=self::CATEGORIES[$key]??$key;
  return $categories;
 }
 public function saveCategories(Request $r,Story $story){
  $this->owner($r,$story);
  $d=$r->validate(['categories'=>'present|array|max:100','categories.*'=>'required|string|max:80']);
  foreach($this->usedCategories($story) as $key)abort_unless(isset($d['categories'][$key]),422,'This category is in use and cannot be deleted.');
  foreach(array_keys($d['categories']) as $key)abort_unless(preg_match('/^[a-z0-9_-]{1,80}$/',$key),422,'Invalid category key.');
  $story->update(['audio_categories'=>json_encode($d['categories'])]);return response()->json($d);
 }
 public function categorize(Request $r,Story $story,StorySound $sound){$this->owner($r,$story);abort_unless($sound->story_id===$story->id,404);$d=$r->validate(['category'=>['required',\Illuminate\Validation\Rule::in(array_keys($this->categories($story)))]]);$sound->update($d);return response()->json($sound);}
 private function owner(Request $r,Story $s):void {abort_unless($s->user_id===$r->user()->id,404);}
 public function index(Request $r,Story $story){$this->owner($r,$story);return response()->json(['categories'=>$this->categories($story),'remaining'=>$r->user()->fresh()->availableCredit()/1000000,'settings'=>$story->audio_settings??[],'sounds'=>StorySound::where('story_id',$story->id)->latest()->orderByDesc('id')->get(),'jobs'=>AudioGeneration::where('story_id',$story->id)->latest()->get(),'searches'=>DB::table('audio_searches')->where('story_id',$story->id)->latest('updated_at')->limit(50)->pluck('query')]);}
 public function search(Request $r,Story $story,StoryAudio $audio){$this->owner($r,$story);$d=$r->validate(['max_duration'=>'sometimes|nullable|numeric|min:0.1|max:3600','query'=>'required|string|max:200','page'=>'sometimes|integer|min:1|max:100']);$query=preg_replace('/\s+/u',' ',trim($d['query']));$result=$audio->search($query,(int)($d['page']??1),array_key_exists('max_duration',$d)?($d['max_duration']===null?null:(float)$d['max_duration']):3);DB::table('audio_searches')->updateOrInsert(['story_id'=>$story->id,'query'=>$query],['updated_at'=>now(),'created_at'=>now()]);return response()->json($result);}
 public function add(Request $r,Story $story,StoryAudio $audio){$this->owner($r,$story);$d=$r->validate(['id'=>'required|integer|min:1']);$sound=Cache::store('file')->get('freesound.sound.'.$d['id']);abort_unless($sound,422,'Search for this sound again before adding it.');
  return Cache::store('file')->lock('audio.import.'.$story->id.'.'.$d['id'],90)->block(2,function()use($story,$d,$sound,$audio){$existing=StorySound::where('story_id',$story->id)->where('provider','freesound')->where('external_id',(string)$d['id'])->first();if($existing)return response()->json($existing);
   try{$asset=$audio->save($story,$sound['preview_url'],['name'=>$sound['name'],'provider'=>'freesound','external_id'=>(string)$sound['id'],'creator'=>$sound['creator'],'license'=>$sound['license'],'source_url'=>$sound['source_url'],'duration'=>$sound['duration']]);}catch(\RuntimeException $e){abort(422,$e->getMessage());}return response()->json($asset,201);
  });
 }
 public function upload(Request $r,Story $story,StoryAudio $audio){
  $this->owner($r,$story);
  $data=$r->validate([
   'file'=>'required|file|max:65536|mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/vnd.wave,audio/ogg,application/ogg,audio/flac,audio/x-flac,audio/mp4,audio/x-m4a,audio/aac,audio/x-aac',
   'category'=>['required',\Illuminate\Validation\Rule::in(array_keys($this->categories($story)))],
  ]);
  $file=$data['file'];
  $bytes=file_get_contents($file->getRealPath());
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
  if(!in_array($mime,['audio/mpeg','audio/wav','audio/x-wav','audio/vnd.wave','audio/ogg','application/ogg','audio/flac','audio/x-flac','audio/mp4','audio/x-m4a','audio/aac','audio/x-aac'],true)){
   throw \Illuminate\Validation\ValidationException::withMessages(['file'=>'Choose a supported audio file.']);
  }
  $name=mb_substr(pathinfo($file->getClientOriginalName(),PATHINFO_FILENAME),0,160);
  $sound=$audio->saveBytes($story,$bytes,$mime,[
   'name'=>trim($name)?:'Uploaded audio','provider'=>'upload','category'=>$data['category'],
  ]);
  return response()->json($sound,201);
 }
 public function settings(Request $r,Story $story){
  $this->owner($r,$story);$sound=['nullable','integer',\Illuminate\Validation\Rule::exists('story_sounds','id')->where('story_id',$story->id)];
  $d=$r->validate(['music_id'=>$sound,'music_loop'=>'required|boolean','defaults'=>'present|array:examine,inspect,pickup,pickup_v2,state_v2,transition,inventory,choices,page,complete','defaults.*'=>'array:click,action','defaults.*.click'=>$sound,'defaults.*.action'=>$sound,'voices'=>'sometimes|array:scene,area,inventory','voices.*'=>'array:model,voice,instructions,temperature,language','voices.*.model'=>['nullable',\Illuminate\Validation\Rule::in(\App\Services\SpeechCatalog::MODELS)],'voices.*.voice'=>'nullable|string|max:200','voices.*.instructions'=>'nullable|string|max:1000','voices.*.temperature'=>'nullable|numeric|min:0|max:2','voices.*.language'=>'nullable|string|max:10']);
  $story->update(['audio_settings'=>$d]);return response()->json($d);
 }
 public function voices(Request $r,Story $story,\App\Services\SpeechCatalog $catalog){$this->owner($r,$story);return response()->json(['models'=>$catalog->models()]);}
 public function speech(Request $r,Story $story,\App\Services\SpeechCatalog $catalog){
  $this->owner($r,$story);$d=$r->validate(['request_key'=>'required|uuid','name'=>'required|string|max:160','prompt'=>'required|string|max:5000','source_text'=>'required|string|max:10000','category'=>['sometimes',\Illuminate\Validation\Rule::in(array_keys($this->categories($story)+self::CATEGORIES))],'model'=>['required',\Illuminate\Validation\Rule::in($catalog::MODELS)],'settings'=>'required|array:voice,instructions,temperature,language','settings.voice'=>'required|string|max:200','settings.instructions'=>'nullable|string|max:1000','settings.temperature'=>'nullable|numeric|min:0|max:2','settings.language'=>'nullable|string|max:10']);
  $model=$catalog->model($d['model']);abort_unless(in_array($d['settings']['voice'],$model['supported_voices']??[],true),422,'Choose a supported voice.');
  abort_unless($r->user()->openrouter_key || config('story.openrouter_key'),422,'OpenRouter key is not configured.');
  return $this->queue($r,$story,$d,$catalog->reservation($model,$d['prompt'],$d['settings']['instructions']??''),$model['pricing'],false);
 }
 public function generate(Request $r,Story $story){
  $this->owner($r,$story);$d=$r->validate(['request_key'=>'required|uuid','name'=>'required|string|max:160','prompt'=>'required|string|max:3000','model'=>'sometimes|in:minimax/music-3,fal-ai/stable-audio,cassetteai/sound-effects-generator','seconds'=>'required|integer|min:1|max:300','settings'=>'sometimes|array:lyrics','settings.lyrics'=>'nullable|string|max:10000']);
  $d['model']??='minimax/music-3';if($d['model']==='fal-ai/stable-audio')abort_if($d['seconds']>47,422,'Stable Audio supports up to 47 seconds.');if($d['model']==='cassetteai/sound-effects-generator')abort_if($d['seconds']>30,422,'CassetteAI supports up to 30 seconds.');
  abort_unless(config('services.fal.key'),422,'FAL_KEY is not configured.');
  $rate=$d['model']==='minimax/music-3'?2000:20000;
  return $this->queue($r,$story,$d,$d['seconds']*$rate,['per_second'=>$rate/1000000],true);
 }
 private function queue(Request $r,Story $story,array $d,int $amount,array $pricing,bool $fal){
  $job=DB::transaction(function()use($r,$story,$d,$amount,$pricing,$fal){
   // Serialize submissions per user so double clicks cannot reserve twice.
   \App\Models\User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
   $existing=AudioGeneration::where('story_id',$story->id)->where('request_key',$d['request_key'])->lockForUpdate()->first();if($existing&&$existing->status!=='draft')return $existing;
   $account=app(\App\Services\LlmBudget::class)->reserve($r->user(),$story,$d['request_key'],$d['model'],$amount,$fal);
   $account->update(['pricing_snapshot'=>$pricing,'request_content'=>['type'=>$fal?'sound_effect':'speech','parameters'=>collect($d)->except('request_key')->all()]]);
   $values=$d+['story_id'=>$story->id,'seconds'=>0,'llm_request_id'=>$account->id];$values['status']='queued';if($existing){$existing->update($values);$job=$existing;}else $job=AudioGeneration::create($values);
   if($fal)GenerateStoryAudio::dispatch($job->id)->onConnection('database')->onQueue('audio')->afterCommit();
   else \App\Jobs\GenerateStorySpeech::dispatch($job->id)->onConnection('database')->onQueue('audio')->afterCommit();
   return $job;
  });return response()->json($job,202);
 }
 public function file(Request $r,StorySound $sound){$this->owner($r,Story::findOrFail($sound->story_id));return response()->file(Storage::disk('local')->path($sound->getRawOriginal('path')),['Content-Type'=>$sound->mime,'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, max-age=3600']);}
}
