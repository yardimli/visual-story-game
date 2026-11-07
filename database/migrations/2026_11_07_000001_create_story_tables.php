<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->text('openrouter_key')->nullable();
            $t->unsignedBigInteger('llm_spent')->default(0);
            $t->unsignedBigInteger('llm_reserved')->default(0);
        });
        Schema::create('stories', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title'); $t->text('premise'); $t->string('genre')->default('Mystery');
            $t->string('language')->default('English'); $t->text('style')->nullable();
            $t->longText('bible')->nullable(); $t->string('llm_model')->nullable(); $t->string('image_model')->nullable();
            $t->unsignedInteger('revision')->default(1); $t->timestamp('plan_approved_at')->nullable(); $t->timestamps();
        });
        Schema::create('story_pages', function (Blueprint $t) {
            $t->id(); $t->foreignId('story_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('position'); $t->string('title'); $t->text('narrative')->nullable();
            $t->text('scene_prompt')->nullable(); $t->text('video_prompt')->nullable();
            $t->text('interaction_plan')->nullable(); $t->text('completion')->nullable();
            $t->timestamps(); $t->unique(['story_id','position']);
        });
        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id(); $t->foreignId('story_id')->constrained()->cascadeOnDelete();
            $t->string('role'); $t->longText('content'); $t->string('model')->nullable();
            $t->json('proposal')->nullable(); $t->unsignedInteger('revision')->nullable(); $t->timestamps();
        });
        Schema::create('llm_requests', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('story_id')->constrained()->cascadeOnDelete();
            $t->uuid('request_key'); $t->string('model'); $t->string('funding');
            $t->string('status')->default('reserved'); $t->unsignedBigInteger('reserved')->default(0);
            $t->unsignedBigInteger('cost')->nullable(); $t->string('provider_id')->nullable();
            $t->timestamps(); $t->unique(['user_id','request_key']);
        });
        Schema::create('story_revisions', function (Blueprint $t) {
            $t->id(); $t->foreignId('story_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('revision'); $t->json('snapshot'); $t->string('reason'); $t->timestamps();
        });
        Schema::create('assets', function (Blueprint $t) {
            $t->id(); $t->foreignId('story_id')->constrained()->cascadeOnDelete();
            $t->string('name'); $t->string('path'); $t->string('mime'); $t->string('role')->default('reference');
            $t->unsignedBigInteger('bytes'); $t->timestamps();
        });
    }
    public function down(): void {
        foreach (['assets','story_revisions','llm_requests','chat_messages','story_pages','stories'] as $table) Schema::dropIfExists($table);
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['openrouter_key','llm_spent','llm_reserved']));
    }
};
