<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\{ImageGeneration,LlmRequest,User};
use App\Services\ImagePricing;
class ReconcileImageCosts extends Command {
 protected $signature='ai:reconcile-image-costs';
 protected $description='Recover zero/unknown costs from completed image BYOK usage without charging twice';
 public function handle(ImagePricing $pricing): int {
  $count=0;$added=0;
  ImageGeneration::where('status','completed')->whereNotNull('llm_request_id')->select(['id','llm_request_id','user_id'])->chunkById(100,function($jobs)use($pricing,&$count,&$added){
   foreach($jobs as $job) DB::transaction(function()use($job,$pricing,&$count,&$added){
    $user=User::whereKey($job->user_id)->lockForUpdate()->first();
    $entry=LlmRequest::whereKey($job->llm_request_id)->lockForUpdate()->first();
    $image=ImageGeneration::whereKey($job->id)->lockForUpdate()->first();
    if(!$user||!$entry||$entry->user_id!==$user->id||$image->status!=='completed'||($entry->cost!==null && $entry->cost>0))return;
    $cost=$pricing->upstreamCost($image->usage??[]);if($cost===null)return;
    if($entry->funding==='platform'){
     if(in_array($entry->status,['reserved','uncertain']))$user->llm_reserved-=$entry->reserved;
     $user->llm_spent+=$cost;$user->save();$added+=$cost;
    }
    $entry->update(['cost'=>$cost,'status'=>'completed']);$count++;
   });
  });
  $this->info('Reconciled '.$count.' image calls; added $'.number_format($added/1000000,6).' to trial spend.');return self::SUCCESS;
 }
}
