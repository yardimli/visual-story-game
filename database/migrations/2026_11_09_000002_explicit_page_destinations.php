<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
 public function up(): void {
  DB::transaction(function(){
   $stories=DB::table('story_pages')->whereNull('deleted_at')->orderBy('position')->get()->groupBy('story_id');
   foreach($stories as $pages)foreach($pages as $index=>$page){
    $document=json_decode($page->visual_document??'null',true);if(!$document)continue;$changed=false;
    foreach($document['visuals'] as &$visual){$visual['hotspots']??=[];foreach($visual['hotspots'] as &$area){
     if(($area['action']??'')!=='next_page')continue;
     $next=$pages[$index+1]??null;$area['action']=$next?'page':'complete';$area['target_page_id']=$next?->id;$changed=true;
    }}unset($visual,$area);
    if($changed)DB::table('story_pages')->where('id',$page->id)->update(['visual_document'=>json_encode($document),'visual_revision'=>$page->visual_revision+1]);
   }
  });
 }
 public function down(): void {
  // Explicit destinations cannot be reduced to "next" without changing gameplay.
 }
};
