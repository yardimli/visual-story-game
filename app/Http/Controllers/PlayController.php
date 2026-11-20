<?php
namespace App\Http\Controllers;
use App\Models\{Story,StorySound};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlayController extends Controller {
 public function show(Request $request,Story $story) {
  abort_unless($story->user_id===$request->user()->id,404);
  $pages=$story->pages()->get()->map(fn($p)=>['id'=>$p->id,'position'=>$p->position,'title'=>$p->title,'visuals'=>$p->visual_document['visuals']??[]])->values();
  $items=$story->inventory_document['visuals']??[];
  $itemIds=collect($items)->pluck('id')->merge($pages->flatMap(fn($p)=>collect($p['visuals'])->where('kind','object')->pluck('id')))->unique()->all();
  $selected=$request->validate(['skip_first_video'=>'sometimes|boolean','page'=>['required','integer',Rule::in($pages->pluck('id')->all())],'scene'=>'nullable|string|max:100','inventory'=>'sometimes|array|max:200','inventory.*'=>['string',Rule::in($itemIds)],'flags'=>'sometimes|array|max:100','flags.*'=>['string',Rule::in(array_column($story->game_flags??[],'key'))]]);
  $page=$pages->firstWhere('id',(int)$selected['page']);
  $scene=collect($page['visuals'])->first(fn($v)=>$v['kind']==='scene'&&(!isset($selected['scene'])||$v['id']===$selected['scene']));
  abort_unless($scene,422,'Choose a scene on the selected page.');
  $game=['videos'=>\App\Models\VideoGeneration::where('story_id',$story->id)->where('status','completed')->get()->map(fn($v)=>['id'=>$v->id,'name'=>$v->name,'url'=>route('videos.file',[$story,$v])]),'messages'=>\App\Services\GameplayMessages::forStory($story),'pages'=>$pages,'inventory'=>$items,'assets'=>$story->assets()->get(['id'])->map(fn($a)=>['id'=>$a->id,'url'=>route('assets.show',$a)]),'flags'=>$story->game_flags??[],'cursorTooltips'=>$story->cursor_tooltips??[],'unavailableText'=>$story->unavailable_text??'You cannot do that yet.','unavailableTimeout'=>(int)($story->unavailable_timeout_ms??1000),'audioSettings'=>$story->audio_settings??[],'sounds'=>StorySound::where('story_id',$story->id)->latest()->orderByDesc('id')->get()->map(fn($s)=>['id'=>$s->id,'name'=>$s->name,'url'=>$s->url,'source_text'=>$s->source_text]),'start'=>['skipFirstVideo'=>$request->boolean('skip_first_video'),'pageId'=>$page['id'],'sceneId'=>$scene['id'],'inventory'=>$selected['inventory']??[],'flags'=>$selected['flags']??[]]];
  return response()->view('game.play',['story'=>$story,'game'=>$game])->header('Cache-Control','private, no-store');
 }
}
