<?php
namespace App\Http\Controllers;
use App\Models\{Story,ChatMessage};
use App\Services\CompanionChanges;
use Illuminate\Http\Request;
class CompanionChangesController extends Controller {
 public function store(Request $request,Story $story,ChatMessage $message,CompanionChanges $changes){
  abort_unless($story->user_id===$request->user()->id && $message->story_id===$story->id,404);
  $data=$request->validate(['selected'=>'required|array|min:1|max:500','selected.*'=>'required|integer|distinct']);
  $result=$changes->apply($story,$message,$data['selected']);$request->session()->flash('status',$result['message']);return response()->json($result);
 }
}
