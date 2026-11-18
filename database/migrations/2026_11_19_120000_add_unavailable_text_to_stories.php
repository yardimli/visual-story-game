<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('stories',function(Blueprint $table){$table->text('unavailable_text')->nullable();}); }
 public function down(): void { Schema::table('stories',function(Blueprint $table){$table->dropColumn('unavailable_text');}); }
};
