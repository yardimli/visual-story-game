<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\{LlmRequest,Asset};
use App\Services\AiCallContents;
class AiSettingsController extends Controller {
    public function edit(Request $request) {
        $calls=LlmRequest::where('user_id',$request->user()->id)
            ->select(['id','user_id','story_id','model','funding','status','cost','reserved','created_at'])
            ->with(['imageGeneration'=>fn($q)=>$q->select(['id','llm_request_id','name','kind','status'])])
            ->latest('id')->paginate(50)->withQueryString()->fragment('ai-call-history');
        return view('studio.settings',['user'=>$request->user(),'calls'=>$calls,'credits'=>$request->user()->tokenCredits()->latest('id')->paginate(25,['*'],'credits_page')->withQueryString()]);
    }
    public function call(Request $request, int $call) {
        $entry=LlmRequest::where('user_id',$request->user()->id)->findOrFail($call);
        $job=$entry->imageGeneration;
        $assets=$job?Asset::where('story_id',$job->story_id)->whereIn('id',$job->asset_ids??[])->get(['id','name']):collect();
        $inputs=$entry->request_content;
        $output=$entry->response_content;
        if($job){
            $inputs=['parameters'=>$job->payload,'references'=>array_map(fn($ref)=>array_intersect_key($ref,array_flip(['id','mime','bytes','width','height'])),$job->references??[])];
            $output=['status'=>$job->status,'usage'=>$job->usage,'error'=>$job->error,'asset_ids'=>$job->asset_ids];
        }
        return response()->json(AiCallContents::clean([
            'request'=>$inputs,'response'=>$output,
            'note'=>!$job && !$inputs?'Detailed request and response contents were not recorded for this older call.':null,
            'accounting'=>['status'=>$entry->status,'funding'=>$entry->funding,'cost_usd'=>$entry->cost===null?null:$entry->cost/1000000,'reserved_usd'=>$entry->reserved/1000000,'input_tokens'=>$entry->prompt_tokens,'output_tokens'=>$entry->completion_tokens,'pricing'=>$entry->pricing_snapshot],
            'images'=>$assets->map(fn($asset)=>['id'=>$asset->id,'name'=>$asset->name,'url'=>route('assets.show',$asset)])->values(),
        ]))->header('Cache-Control','private, no-store');
    }
    public function update(Request $request) {
        $request->validate(['openrouter_key'=>'required|string|min:10|max:500|regex:/^sk-or-[A-Za-z0-9_-]+$/','current_password'=>'required|current_password']);
        $request->user()->forceFill(['openrouter_key'=>$request->openrouter_key])->save();
        return back()->with('status','Personal key saved securely. Future requests use your key.');
    }
    public function destroy(Request $request) {
        $request->validate(['current_password'=>'required|current_password']);
        $request->user()->forceFill(['openrouter_key'=>null])->save();
        return back()->with('status','Personal key removed.');
    }
}
