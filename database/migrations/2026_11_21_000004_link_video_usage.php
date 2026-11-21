<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_generations', fn (Blueprint $table) => $table->foreignId('llm_request_id')->nullable()->constrained('llm_requests')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('video_generations', fn (Blueprint $table) => $table->dropConstrainedForeignId('llm_request_id'));
    }
};
