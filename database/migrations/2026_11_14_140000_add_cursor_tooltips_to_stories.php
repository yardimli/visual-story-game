<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('stories',fn(Blueprint $table)=>$table->json('cursor_tooltips')->nullable());}
 public function down():void {Schema::table('stories',fn(Blueprint $table)=>$table->dropColumn('cursor_tooltips'));}
};
