<?php
namespace App\Http\Controllers;
use App\Models\Story;
use App\Services\{SceneDocument,InventoryLibrary};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class InventoryController extends Controller {
 public function show(Request $r,Story $story,InventoryLibrary $library){
  abort_unless($story->user_id===$r->user()->id,404);
  return response()->json(['items'=>$story->inventory_document['visuals']??[],'revision'=>$story->inventory_revision,'uses'=>$library->uses($story),'assets'=>$story->assets()->whereNull('archived_at')->latest()->get()->reject(fn($a)=>$a->role==='extraction_reference')->values()->map(fn($a)=>['id'=>$a->id,'name'=>$a->name,'url'=>route('assets.show',$a)])]);
 }
 public function update(Request $r,Story $story,InventoryLibrary $library){
  abort_unless($story->user_id===$r->user()->id,404);
  $data=$r->validate(['revision'=>'required|integer','items'=>'present|array|max:100']);
  $revision=DB::transaction(function()use($story,$data,$library){
   $s=Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
   if($s->inventory_revision!==$data['revision'])throw ValidationException::withMessages(['revision'=>'Inventory changed in another tab. Reload before editing.']);
   $old=$s->inventory_document['visuals']??[];$ids=array_column($data['items'],'id');$uses=$library->uses($s);
   foreach($old as $v)if(!in_array($v['id'],$ids,true)&&!empty($uses[$v['id']]))throw ValidationException::withMessages(['items'=>'Remove scene links to '.$v['name'].' before deleting this item.']);
   foreach($data['items'] as $v)if(($v['kind']??null)!=='object')throw ValidationException::withMessages(['items'=>'Inventory only contains objects.']);
   $s->inventory_document=['visuals'=>$data['items']];
   $valid=app(SceneDocument::class)->validate($s,['visuals'=>$data['items']]);
   $s->inventory_document=$valid;$s->inventory_revision++;$s->save();return $s->inventory_revision;
  });
  return response()->json(['revision'=>$revision]);
 }
}
