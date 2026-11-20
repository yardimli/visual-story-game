<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['chat_messages', 'companion_messages'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('response_status', 20)->nullable()->index();
                $table->longText('response_partial')->nullable();
                $table->text('response_error')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['chat_messages', 'companion_messages'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['response_status']);
                $table->dropColumn(['response_status', 'response_partial', 'response_error']);
            });
        }
    }
};
