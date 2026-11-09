<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
use Illuminate\Support\Str;
return new class extends Migration {
 public function up(): void {
  Schema::table('story_pages',function(Blueprint $table){$table->json('narrative_delta')->nullable();$table->json('interaction_plan_delta')->nullable();});
  DB::table('story_pages')->orderBy('id')->chunkById(100,function($pages){foreach($pages as $page){
   $doc=json_decode($page->visual_document??'',true)?:['visuals'=>[]];$prompt=trim($page->scene_prompt??'');$video=trim($page->video_prompt??'');
   if($prompt!==''||$video!==''){
    $index=null;foreach($doc['visuals'] as $i=>$v)if($v['kind']==='scene' && ($prompt==='' || empty($v['prompt']) || $v['prompt']===$prompt)){$index=$i;break;}
    if($index===null){$doc['visuals'][]=['id'=>(string)Str::uuid(),'name'=>$page->title.' artwork','kind'=>'scene','asset_id'=>null,'prompt'=>$prompt,'hotspots'=>[]];$index=count($doc['visuals'])-1;}
    if($prompt!=='')$doc['visuals'][$index]['prompt']=$prompt;
    if($video!=='')$doc['visuals'][$index]['video_prompt']=$video;
    DB::table('story_pages')->where('id',$page->id)->update(['visual_document'=>json_encode($doc),'visual_revision'=>$page->visual_revision+1]);
   }
  }});
  Schema::table('story_pages',fn(Blueprint $table)=>$table->dropColumn(['scene_prompt','video_prompt']));
 }
 public function down(): void {
  Schema::table('story_pages',function(Blueprint $table){$table->text('scene_prompt')->nullable();$table->text('video_prompt')->nullable();$table->dropColumn(['narrative_delta','interaction_plan_delta']);});
 }
};
