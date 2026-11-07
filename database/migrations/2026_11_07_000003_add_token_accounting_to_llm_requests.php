<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('llm_requests', function (Blueprint $t) {
            $t->json('pricing_snapshot')->nullable();
            $t->unsignedBigInteger('prompt_tokens')->nullable();
            $t->unsignedBigInteger('completion_tokens')->nullable();
        });
    }
    public function down(): void {
        Schema::table('llm_requests', fn (Blueprint $t) => $t->dropColumn(['pricing_snapshot','prompt_tokens','completion_tokens']));
    }
};
