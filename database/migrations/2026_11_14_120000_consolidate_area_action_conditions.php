<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up():void {
  DB::transaction(function(){foreach(DB::table('story_pages')->orderBy('id')->get() as $page){$doc=json_decode($page->visual_document??'null',true);if(!is_array($doc))continue;$clean=\App\Services\AreaActionSettings::document($doc);if($clean!==$doc)DB::table('story_pages')->where('id',$page->id)->update(['visual_document'=>json_encode($clean),'visual_revision'=>$page->visual_revision+1]);}});
 }
 // The consolidated conditions are retained on rollback; splitting them would be ambiguous.
 public function down():void {}
};
