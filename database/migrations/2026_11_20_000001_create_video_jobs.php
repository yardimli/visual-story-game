<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role', 16);
            $table->string('token_hash', 64)->unique();
            $table->timestamps();
        });
        Schema::create('video_generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('origin_token_id')->nullable()->constrained('video_access_tokens')->cascadeOnDelete();
            $table->uuid('origin_job_id')->nullable();
            $table->unique(['origin_token_id', 'origin_job_id']);
            $table->uuid('remote_id')->nullable();
            $table->string('name', 160);
            $table->text('prompt');
            $table->string('engine', 80);
            $table->unsignedInteger('engine_version');
            $table->json('parameters');
            $table->unsignedBigInteger('seed');
            $table->unsignedBigInteger('first_asset_id')->nullable();
            $table->unsignedBigInteger('last_asset_id')->nullable();
            $table->string('first_path')->nullable();
            $table->string('last_path')->nullable();
            $table->string('output_path')->nullable();
            $table->string('output_sha256', 64)->nullable();
            $table->unsignedBigInteger('output_bytes')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('error')->nullable();
            $table->text('sync_error')->nullable();
            $table->foreignId('worker_token_id')->nullable()->constrained('video_access_tokens')->nullOnDelete();
            $table->string('worker_id', 100)->nullable();
            $table->uuid('claim_key')->nullable();
            $table->string('prompt_id', 100)->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_generations');
        Schema::dropIfExists('video_access_tokens');
    }
};
