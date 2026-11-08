<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('story_pages',function(Blueprint $t){$t->json('visual_document')->nullable();$t->unsignedInteger('visual_revision')->default(1);});
  Schema::create('image_generations',function(Blueprint $t){
   $t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->foreignId('story_id')->constrained()->cascadeOnDelete();$t->foreignId('story_page_id')->constrained()->cascadeOnDelete();
   $t->uuid('visual_id');$t->string('name');$t->string('kind');$t->uuid('request_key');$t->string('status')->default('queued');$t->json('payload');$t->json('references');$t->json('pricing');$t->json('usage')->nullable();$t->json('asset_ids')->nullable();$t->text('error')->nullable();$t->timestamp('started_at')->nullable();$t->timestamps();$t->unique(['user_id','request_key']);
  });
 }
 public function down(): void {Schema::dropIfExists('image_generations');Schema::table('story_pages',fn(Blueprint $t)=>$t->dropColumn(['visual_document','visual_revision']));}
};
