<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {Schema::table('llm_requests',function(Blueprint $table){$table->longText('request_content')->nullable();$table->longText('response_content')->nullable();});}
 public function down(): void {Schema::table('llm_requests',fn(Blueprint $table)=>$table->dropColumn(['request_content','response_content']));}
};
