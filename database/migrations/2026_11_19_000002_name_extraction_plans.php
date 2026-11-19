<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('scene_extractions',function(Blueprint $t){$t->string('name',160)->nullable();$t->timestamp('applied_at')->nullable();});}
 public function down():void {Schema::table('scene_extractions',fn(Blueprint $t)=>$t->dropColumn(['name','applied_at']));}
};
