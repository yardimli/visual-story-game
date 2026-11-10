<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up(): void {
  Schema::table('stories',function(Blueprint $t){$t->json('inventory_document')->nullable();$t->unsignedInteger('inventory_revision')->default(1);});
  foreach(DB::table('stories')->orderBy('id')->get() as $story){
   $items=[];
   foreach(DB::table('story_pages')->where('story_id',$story->id)->get() as $page){
    $doc=json_decode($page->visual_document??'{}',true);$scenes=[];$changed=false;
    foreach($doc['visuals']??[] as $v){if(($v['kind']??'')==='object'){$items[$v['id']]??=$v;$changed=true;}else $scenes[]=$v;}
    if($changed)DB::table('story_pages')->where('id',$page->id)->update(['visual_document'=>json_encode(['visuals'=>$scenes]),'visual_revision'=>$page->visual_revision+1]);
   }
   DB::table('stories')->where('id',$story->id)->update(['inventory_document'=>json_encode(['visuals'=>array_values($items)])]);
  }
 }
 public function down(): void {
  foreach(DB::table('stories')->get() as $s){$p=DB::table('story_pages')->where('story_id',$s->id)->orderBy('position')->first();if(!$p)continue;$d=json_decode($p->visual_document??'{}',true);$d['visuals']=array_merge($d['visuals']??[],json_decode($s->inventory_document??'{}',true)['visuals']??[]);DB::table('story_pages')->where('id',$p->id)->update(['visual_document'=>json_encode($d)]);}
  Schema::table('stories',fn(Blueprint $t)=>$t->dropColumn(['inventory_document','inventory_revision']));
 }
};
