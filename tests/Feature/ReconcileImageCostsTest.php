<?php
namespace Tests\Feature;
use App\Models\{User,Story,LlmRequest,ImageGeneration};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class ReconcileImageCostsTest extends TestCase {
 use RefreshDatabase;
 public function test_reconciliation_updates_spend_once_and_keeps_personal_cost_separate(): void {
  $user=User::factory()->create();$user->forceFill(['llm_spent'=>1000,'llm_reserved'=>100000])->save();
  $story=Story::create(['user_id'=>$user->id,'title'=>'Test','premise'=>'Test']);$page=$story->pages()->create(['position'=>1,'title'=>'Page 1']);
  $entries=[];
  foreach([['platform','failed',0],['platform','uncertain',null],['personal','completed',0],['platform','completed',12000]] as [$funding,$status,$cost]){
   $entry=LlmRequest::create(['user_id'=>$user->id,'story_id'=>$story->id,'model'=>'image','funding'=>$funding,'status'=>$status,'cost'=>$cost,'reserved'=>100000,'request_key'=>(string)Str::uuid()]);$entries[]=$entry;
   ImageGeneration::create(['user_id'=>$user->id,'story_id'=>$story->id,'story_page_id'=>$page->id,'llm_request_id'=>$entry->id,'visual_id'=>(string)Str::uuid(),'name'=>'Scene','kind'=>'scene','request_key'=>(string)Str::uuid(),'payload'=>[],'references'=>[],'pricing'=>[],'status'=>'completed','usage'=>['cost'=>0,'cost_details'=>['upstream_inference_cost'=>0.054928]]]);
  }
  $this->artisan('ai:reconcile-image-costs')->assertSuccessful();
  $this->assertSame(110856,(int)$user->fresh()->llm_spent);$this->assertSame(0,(int)$user->fresh()->llm_reserved);
  foreach(array_slice($entries,0,3) as $entry){$this->assertSame(54928,(int)$entry->fresh()->cost);$this->assertSame('completed',$entry->fresh()->status);}
  $this->assertSame(12000,(int)$entries[3]->fresh()->cost);
  $this->artisan('ai:reconcile-image-costs')->assertSuccessful();$this->assertSame(110856,(int)$user->fresh()->llm_spent);
 }
}
