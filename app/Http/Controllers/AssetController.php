<?php
namespace App\Http\Controllers;
use App\Models\{Story,Asset};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class AssetController extends Controller {
    public function store(Request $request,Story $story) {
        abort_unless($story->user_id===$request->user()->id,404);
        $request->validate(['images'=>'required|array|min:1|max:8','images.*'=>'required|file|mimes:png,jpg,jpeg,webp|max:10240','role'=>'required|in:reference,character,environment,object,style,start,end,scene']);
        foreach($request->file('images') as $file) {
            $path=$file->store('stories/'.$story->id,'local');
            $story->assets()->create(['name'=>$file->getClientOriginalName(),'path'=>$path,'mime'=>$file->getMimeType(),'bytes'=>$file->getSize(),'role'=>$request->role]);
        }
        return back()->with('status','Reference images uploaded.');
    }
    public function show(Request $request,Asset $asset) {
        abort_unless(Story::whereKey($asset->story_id)->where('user_id',$request->user()->id)->exists(),404);
        return Storage::disk('local')->response($asset->path,null,['Content-Type'=>$asset->mime,'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, max-age=3600']);
    }
}
