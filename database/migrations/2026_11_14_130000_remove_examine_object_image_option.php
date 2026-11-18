<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up():void {
  DB::transaction(function(){
   foreach(DB::table('story_pages')->get() as $page){
    $doc=json_decode($page->visual_document??'null',true);if(!is_array($doc))continue;$changed=false;
    foreach($doc['visuals']??[] as $i=>$visual)foreach($visual['hotspots']??[] as $j=>$area){
     if(array_key_exists('show_object',$area)){unset($doc['visuals'][$i]['hotspots'][$j]['show_object']);$changed=true;}
    }
    if($changed)DB::table('story_pages')->where('id',$page->id)->update(['visual_document'=>json_encode($doc),'visual_revision'=>$page->visual_revision+1]);
   }
  });
 }
 public function down():void {}
};
