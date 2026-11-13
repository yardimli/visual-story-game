<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('stories',fn(Blueprint $t)=>$t->json('audio_categories')->nullable());Schema::table('story_sounds',fn(Blueprint $t)=>$t->string('category',80)->default('effects'));Schema::table('audio_generations',fn(Blueprint $t)=>$t->string('category',80)->default('effects'));}
 public function down():void {Schema::table('stories',fn(Blueprint $t)=>$t->dropColumn('audio_categories'));Schema::table('story_sounds',fn(Blueprint $t)=>$t->dropColumn('category'));Schema::table('audio_generations',fn(Blueprint $t)=>$t->dropColumn('category'));}
};
