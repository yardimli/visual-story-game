<?php
namespace App\Http\Controllers;
use App\Models\{Story,StoryPage,ChatMessage,StoryRevision};
use App\Services\StoryDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
class StudioController extends Controller {
    public function index(Request $request) {
        return view('studio.index',['stories'=>Story::where('user_id',$request->user()->id)->withCount(['pages','pages as ready_pages_count'=>function($q) { foreach(['narrative','scene_prompt','video_prompt','interaction_plan','completion'] as $field) $q->whereNotNull($field)->whereRaw('TRIM('.$field.') <> ?', ['']); }])->latest('updated_at')->get()]);
    }
    public function store(Request $request) {
        $data=$request->validate(['title'=>'required|string|max:160','premise'=>'required|string|max:5000','page_count'=>'required|integer|min:1|max:100','genre'=>'required|string|max:80']);
        $story=DB::transaction(function () use ($request,$data) {
            $story=Story::create(['user_id'=>$request->user()->id,'title'=>$data['title'],'premise'=>$data['premise'],'genre'=>$data['genre']]);
            for($i=1;$i<=$data['page_count'];$i++) $story->pages()->create(['position'=>$i,'title'=>'Scene '.$i]);
            return $story;
        });
        return redirect()->route('stories.edit',$story);
    }
    public function edit(Request $request,Story $story) {
        $this->owner($request,$story);
        $story->load(['pages','assets']);
        $page=$request->filled('page') ? $story->pages->firstWhere('id',(int)$request->page) : $story->pages->first();
        abort_unless($page,404);
        return view('studio.edit',['story'=>$story,'page'=>$page,'messages'=>$story->messages()->latest()->limit(40)->get()->reverse(),
            'revisions'=>$story->revisions()->latest()->limit(8)->get()]);
    }
    public function update(Request $request,Story $story,StoryDraft $draft) {
        $this->owner($request,$story);
        $data=$request->validate(['revision'=>'required|integer','title'=>'required|string|max:160','premise'=>'required|string|max:5000','genre'=>'required|string|max:80','language'=>'required|string|max:80','style'=>'nullable|string|max:5000','bible'=>'nullable|string|max:40000','llm_model'=>'nullable|string|max:200','image_model'=>'nullable|string|max:200']);
        $revision=$data['revision'];unset($data['revision']);
        $draft->change($story,$revision,'Project details',fn($s)=>$s->update($data));
        if ($request->expectsJson()) return response()->json(['revision'=>(int)$revision+1]);
        return back()->with('status','Story saved. Review the plan again before generation.');
    }
    public function page(Request $request,Story $story,StoryPage $page,StoryDraft $draft) {
        $this->owner($request,$story); abort_unless($page->story_id===$story->id,404);
        $data=$request->validate(['revision'=>'required|integer','title'=>'required|string|max:160','narrative'=>'nullable|string|max:10000','scene_prompt'=>'nullable|string|max:10000','video_prompt'=>'nullable|string|max:5000','interaction_plan'=>'nullable|string|max:10000','completion'=>'nullable|string|max:2000']);
        $revision=$data['revision'];unset($data['revision']);
        $draft->change($story,$revision,'Page '.$page->position,fn()=> $page->update($data));
        if ($request->expectsJson()) return response()->json(['revision'=>(int)$revision+1]);
        return back()->with('status','Page saved.');
    }
    public function approve(Request $request,Story $story) {
        $this->owner($request,$story);$request->validate(['revision'=>'required|integer']);
        DB::transaction(function () use($request,$story) {
            $s=Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
            abort_unless($s->revision===(int)$request->revision,409);
            $ready=filled($s->bible)&&$s->pages()->get()->every(fn($p)=>filled($p->narrative)&&filled($p->scene_prompt)&&filled($p->video_prompt)&&filled($p->interaction_plan)&&filled($p->completion));
            if(!$ready) throw \Illuminate\Validation\ValidationException::withMessages(['plan'=>'Complete the story bible and every page’s prose, prompts, interactions, and completion rule first.']);
            $s->update(['plan_approved_at'=>now()]);
        });
        return back()->with('status','Complete plan approved.');
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
            foreach($revision->snapshot['pages'] as $page) { $id=$page['id'];unset($page['id']);$s->pages()->whereKey($id)->update($page); }
        });
        return back()->with('status','Earlier draft restored.');
    }
    private function owner(Request $request,Story $story): void { abort_unless($story->user_id===$request->user()->id,404); }
}
