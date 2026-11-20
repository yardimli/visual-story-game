<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the legacy nullable columns for compatibility, but detach service
        // credentials and relay imports from accounts that may later be deleted.
        Schema::table('video_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
        Schema::table('video_generations', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
        DB::table('video_access_tokens')->update(['user_id' => null]);
        DB::table('video_generations')->whereNotNull('origin_token_id')->update(['user_id' => null]);
    }

    public function down(): void
    {
        // An account cannot be inferred for installation-owned records. Refuse
        // rollback rather than delete credentials/jobs or assign an arbitrary user.
        if (DB::table('video_access_tokens')->whereNull('user_id')->exists()
            || DB::table('video_generations')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Installation-owned video records cannot be converted back to account-owned records automatically.');
        }
        Schema::table('video_access_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
        Schema::table('video_generations', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
