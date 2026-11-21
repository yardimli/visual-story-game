<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('story_characters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->json('views');
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('story_characters'); }
};
