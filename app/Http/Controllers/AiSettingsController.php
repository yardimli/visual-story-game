<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class AiSettingsController extends Controller {
    public function edit(Request $request) { return view('studio.settings',['user'=>$request->user()]); }
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
