<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('scene_extractions',function(Blueprint $t){$t->id();$t->foreignId('story_id')->constrained()->cascadeOnDelete();$t->foreignId('story_page_id')->constrained()->cascadeOnDelete();$t->uuid('visual_id');$t->unsignedBigInteger('source_asset_id');$t->json('outputs');$t->timestamps();});}
 public function down():void {Schema::dropIfExists('scene_extractions');}
};
