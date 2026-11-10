<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void{Schema::table('stories',function(Blueprint $t){$t->json('game_flags')->nullable();$t->unsignedInteger('flags_revision')->default(1);$t->json('image_order')->nullable();});}public function down():void{Schema::table('stories',fn(Blueprint $t)=>$t->dropColumn(['game_flags','flags_revision','image_order']));}};
