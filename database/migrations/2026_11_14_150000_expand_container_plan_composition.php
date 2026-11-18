<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('container_plans',function(Blueprint $t){$t->json('padding')->nullable();$t->json('crop_bounds')->nullable();$t->boolean('equal_padding')->default(true);$t->text('user_prompt')->nullable();$t->string('prompt_model',200)->nullable();});}
 public function down():void {Schema::table('container_plans',fn(Blueprint $t)=>$t->dropColumn(['padding','crop_bounds','equal_padding','user_prompt','prompt_model']));}
};
