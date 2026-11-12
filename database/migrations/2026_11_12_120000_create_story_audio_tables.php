<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('story_sounds',function(Blueprint $t){$t->id();$t->foreignId('story_id')->constrained()->cascadeOnDelete();$t->string('name');$t->string('provider');$t->string('external_id')->nullable();$t->string('path');$t->string('mime');$t->decimal('duration',10,3)->nullable();$t->string('creator')->nullable();$t->text('license')->nullable();$t->text('source_url')->nullable();$t->text('prompt')->nullable();$t->timestamps();$t->unique(['story_id','provider','external_id']);});
  Schema::create('audio_generations',function(Blueprint $t){$t->id();$t->foreignId('story_id')->constrained()->cascadeOnDelete();$t->uuid('request_key');$t->string('name');$t->text('prompt');$t->unsignedInteger('seconds');$t->string('status')->default('queued');$t->string('provider_request_id')->nullable();$t->unsignedInteger('queue_position')->nullable();$t->json('logs')->nullable();$t->text('error')->nullable();$t->foreignId('sound_id')->nullable()->constrained('story_sounds')->nullOnDelete();$t->timestamp('submitted_at')->nullable();$t->timestamp('started_at')->nullable();$t->timestamps();$t->unique(['story_id','request_key']);});
  Schema::create('audio_searches',function(Blueprint $t){$t->id();$t->foreignId('story_id')->constrained()->cascadeOnDelete();$t->string('query');$t->timestamps();$t->unique(['story_id','query']);});
 }
 public function down():void {Schema::dropIfExists('audio_searches');Schema::dropIfExists('audio_generations');Schema::dropIfExists('story_sounds');}
};
