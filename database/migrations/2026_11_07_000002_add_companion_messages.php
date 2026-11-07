<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('llm_requests', fn (Blueprint $t) => $t->unsignedBigInteger('story_id')->nullable()->change());
        Schema::create('companion_messages', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role'); $t->longText('content'); $t->string('model')->nullable();
            $t->json('proposal')->nullable(); $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('companion_messages');
        // Keep the nullable ledger association: account requests must retain their billing records.
    }
};
