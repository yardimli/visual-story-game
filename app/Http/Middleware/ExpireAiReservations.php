<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Services\LlmBudget;
class ExpireAiReservations {
 public function handle(Request $request,Closure $next) {
  if($user=$request->user()) {
   if(app(LlmBudget::class)->expireOverdue($user->id))$user->refresh();
  }
  return $next($request);
 }
}
