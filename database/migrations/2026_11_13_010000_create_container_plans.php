<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::create('container_plans',function(Blueprint $t){$t->id();$t->foreignId('story_id')->constrained()->cascadeOnDelete();$t->foreignId('story_page_id')->constrained()->cascadeOnDelete();$t->uuid('visual_id');$t->uuid('area_id');$t->string('name');$t->text('prompt');$t->json('bounds');$t->json('contents');$t->foreignId('source_asset_id')->constrained('assets');$t->foreignId('extraction_id')->constrained('scene_extractions');$t->unsignedBigInteger('preview_asset_id')->nullable();$t->string('stage')->default('preview');$t->timestamps();});}
 public function down():void {Schema::dropIfExists('container_plans');}
};
