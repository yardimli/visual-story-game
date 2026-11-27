<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('video_generations', function (Blueprint $table) {
            $table->json('reference_asset_ids')->nullable();
            $table->json('reference_paths')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('video_generations', fn (Blueprint $table) => $table->dropColumn(['reference_asset_ids', 'reference_paths']));
    }
};
