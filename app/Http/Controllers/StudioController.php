<?php
namespace App\Http\Controllers;
use App\Models\{Story,StoryPage,ChatMessage,StoryRevision};
use App\Services\StoryDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
class StudioController extends Controller {
    public function index(Request $request) {
        $historyStory=$request->filled('history')?Story::where('user_id',$request->user()->id)->findOrFail($request->integer('history')):null;
        return view('studio.index',['historyStory'=>$historyStory,'revisions'=>$historyStory?->revisions()->latest()->limit(20)->get(),'stories'=>Story::where('user_id',$request->user()->id)->withCount(['pages','pages as ready_pages_count'=>function($q) { foreach(['narrative','interaction_plan'] as $field) $q->whereNotNull($field)->whereRaw('TRIM('.$field.') <> ?', ['']); }])->latest('updated_at')->get()]);
    }
    public function store(Request $request) {
        $data=$request->validate(['title'=>'required|string|max:160','premise'=>'required|string|max:5000','page_count'=>'required|integer|min:1|max:100','genre'=>'required|string|max:80']);
        $story=DB::transaction(function () use ($request,$data) {
            $story=Story::create(['user_id'=>$request->user()->id,'title'=>$data['title'],'premise'=>$data['premise'],'genre'=>$data['genre']]);
            for($i=1;$i<=$data['page_count'];$i++) $story->pages()->create(['position'=>$i,'title'=>'Page '.$i]);
            return $story;
        });
        return redirect()->route('stories.edit',$story);
    }
    public function edit(Request $request,Story $story) {
        $this->owner($request,$story);
        $story->load(['pages','assets']);
        $story->setRelation('assets',\App\Models\Asset::withGenerationNames($story->assets,$story->id));
        $page=$request->filled('page') ? $story->pages->firstWhere('id',(int)$request->page) : $story->pages->first();
        abort_unless($page,404);
        return view('studio.edit',['story'=>$story,'page'=>$page,'messages'=>$story->messages()->latest()->limit(40)->get()->reverse()]);
    }
    public function update(Request $request,Story $story,StoryDraft $draft) {
        $this->owner($request,$story);
        $data=$request->validate(['revision'=>'required|integer','title'=>'required|string|max:160','premise'=>'required|string|max:5000','genre'=>'required|string|max:80','language'=>'required|string|max:80','style'=>'nullable|string|max:5000','bible'=>'nullable|string|max:40000','llm_model'=>'nullable|string|max:200','image_model'=>'nullable|string|max:200']);
        $revision=$data['revision'];unset($data['revision']);
        $draft->change($story,$revision,'Project details',fn($s)=>$s->update($data));
        if ($request->expectsJson()) return response()->json(['revision'=>(int)$revision+1]);
        return back()->with('status','Story saved.');
    }
    public function page(Request $request,Story $story,StoryPage $page,StoryDraft $draft) {
        $this->owner($request,$story); abort_unless($page->story_id===$story->id,404);
        $data=$request->validate(['revision'=>'required|integer','title'=>'required|string|max:160','narrative'=>'nullable|string|max:10000','narrative_delta'=>'nullable|json|max:200000','interaction_plan_delta'=>'nullable|json|max:200000','interaction_plan'=>'nullable|string|max:10000','scene_prompt'=>'prohibited','video_prompt'=>'prohibited']);
        $data=\App\Services\PageRichText::normalize($data);
        $revision=$data['revision'];unset($data['revision']);
        $draft->change($story,$revision,'Page '.$page->position,fn()=> $page->update($data));
        if ($request->expectsJson()) return response()->json(['revision'=>(int)$revision+1]);
        return back()->with('status','Page saved.');
    }
    public function structure(Request $request, Story $story, StoryDraft $draft, \App\Services\PageOrder $order) {
        $this->owner($request,$story);
        $data=$request->validate(['revision'=>'required|integer','action'=>'required|in:add,insert,up,down,remove','page_id'=>'required_unless:action,add|integer']);
        $selected=null;
        $draft->change($story,(int)$data['revision'],'Page structure: '.$data['action'],function($s) use ($data,$order,&$selected) {
            $ids=$s->pages()->pluck('id')->all();
            if (in_array($data['action'],['add','insert'])) {
                if (count($ids)>=100) throw \Illuminate\Validation\ValidationException::withMessages(['pages'=>'A story can have at most 100 pages.']);
                $position=(int)\App\Models\StoryPage::withTrashed()->where('story_id',$s->id)->max('position')+1;
                $new=$s->pages()->create(['position'=>$position,'title'=>$data['action']==='insert'?'Untitled page':'Page '.(count($ids)+1)]);
                if($data['action']==='insert'){$index=array_search((int)$data['page_id'],$ids,true);abort_if($index===false,404);array_splice($ids,$index+1,0,[$new->id]);}else{$ids[]=$new->id;} $selected=$new->id;
            } else {
                $index=array_search((int)$data['page_id'],$ids,true);
                abort_if($index===false,404);
                if ($data['action']==='remove') {
                    if (count($ids)===1) throw \Illuminate\Validation\ValidationException::withMessages(['pages'=>'Keep at least one page in your story.']);
                    array_splice($ids,$index,1); $selected=$ids[min($index,count($ids)-1)];
                } else {
                    $target=$index+($data['action']==='up' ? -1 : 1);
                    if (!isset($ids[$target])) throw \Illuminate\Validation\ValidationException::withMessages(['pages'=>'This page cannot move further in that direction.']);
                    [$ids[$index],$ids[$target]]=[$ids[$target],$ids[$index]];
                    $selected=(int)$data['page_id'];
                }
            }
            $order->arrange($s,$ids);
        });
        return redirect()->route('stories.edit',[$story,'page'=>$selected])->with('status','Page order updated. Review the linear interaction plan; restore removed pages from History.');
    }
    public function apply(Request $request,Story $story,ChatMessage $message,StoryDraft $draft) {
        $this->owner($request,$story); abort_unless($message->story_id===$story->id && $message->proposal,404);
        $data=Validator::make($message->proposal,[
            'bible'=>'sometimes|string|max:40000','pages'=>'sometimes|array|max:100',
            'pages.*'=>'array:position,title,narrative,scene_prompt,video_prompt,interaction_plan,completion',
            'pages.*.position'=>'required|integer|min:1|max:100','pages.*.title'=>'required|string|max:160',
            'pages.*.narrative'=>'required|string|max:10000','pages.*.scene_prompt'=>'required|string|max:10000',
            'pages.*.video_prompt'=>'required|string|max:5000','pages.*.interaction_plan'=>'required|string|max:10000',
            'pages.*.completion'=>'required|string|max:2000',
        ])->validate();
        $draft->change($story,(int)$message->revision,'Applied assistant proposal',function($s)use($data) {
            if(isset($data['bible']))$s->update(['bible'=>$data['bible']]);
            foreach($data['pages']??[] as $fields) {
                $page=$s->pages()->where('position',$fields['position'])->first();
                if(!$page)throw \Illuminate\Validation\ValidationException::withMessages(['proposal'=>'The proposal references a page outside this story.']);
                unset($fields['position']); $page->update($fields);
            }
        });
        return back()->with('status','Proposal applied. Previous draft is available in revision history.');
    }
    public function restore(Request $request,Story $story,StoryRevision $revision,StoryDraft $draft) {
        $this->owner($request,$story);abort_unless($revision->story_id===$story->id,404);
        $request->validate(['revision'=>'required|integer']);
        $draft->change($story,(int)$request->revision,'Restore revision '.$revision->revision,function($s)use($revision) {
            $s->update($revision->snapshot['story']);
            app(\App\Services\PageOrder::class)->arrange($s,array_column($revision->snapshot['pages'],'id'));
            foreach($revision->snapshot['pages'] as $page) { $id=$page['id'];unset($page['id'],$page['scene_prompt'],$page['video_prompt']);$s->pages()->whereKey($id)->firstOrFail()->update($page); }
        });
        return redirect()->route('stories.edit',$story)->with('status','Earlier draft restored, including page order and removed pages.');
    }
    private function owner(Request $request,Story $story): void { abort_unless($story->user_id===$request->user()->id,404); }
}
