<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('stories',fn(Blueprint $t)=>$t->json('audio_settings')->nullable());
  Schema::table('story_sounds',fn(Blueprint $t)=>$t->text('source_text')->nullable());
  Schema::table('audio_generations',function(Blueprint $t){
   $t->string('model')->default('fal-ai/stable-audio');$t->json('settings')->nullable();$t->text('source_text')->nullable();
   $t->foreignId('llm_request_id')->nullable()->constrained('llm_requests')->nullOnDelete();
  });
 }
 public function down():void {
  Schema::table('audio_generations',function(Blueprint $t){$t->dropConstrainedForeignId('llm_request_id');$t->dropColumn(['model','settings','source_text']);});
  Schema::table('story_sounds',fn(Blueprint $t)=>$t->dropColumn('source_text'));
  Schema::table('stories',fn(Blueprint $t)=>$t->dropColumn('audio_settings'));
 }
};
