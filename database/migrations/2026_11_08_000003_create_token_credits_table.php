<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up(): void {
  Schema::create('token_credits',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->unsignedBigInteger('amount');$t->string('source',30);$t->string('description');$t->string('grant_key')->nullable();$t->timestamps();$t->unique(['user_id','grant_key']);});
  // Convert the original allowance into a grant without changing prior spending or holds.
  DB::table('users')->orderBy('id')->chunkById(200,function($users){foreach($users as $u)DB::table('token_credits')->insert(['user_id'=>$u->id,'amount'=>1000000,'source'=>'signup','description'=>'Welcome credit','grant_key'=>'signup','created_at'=>$u->created_at??now(),'updated_at'=>now()]);});
 }
 public function down(): void {Schema::dropIfExists('token_credits');}
};
