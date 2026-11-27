<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('users', fn(Blueprint $table) => $table->unsignedInteger('chat_max_output_tokens')->nullable()); }
    public function down(): void { Schema::table('users', fn(Blueprint $table) => $table->dropColumn('chat_max_output_tokens')); }
};
