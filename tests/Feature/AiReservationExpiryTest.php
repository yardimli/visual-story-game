<?php
namespace Tests\Feature;
use App\Models\{User,Story,ImageGeneration};
use App\Services\{LlmBudget,OpenRouter};
use App\Jobs\GenerateSceneImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;
class AiReservationExpiryTest extends TestCase {
 use RefreshDatabase;
 public function test_four_minute_expiry_releases_once_and_ignores_late_settlement(): void {
  $this->freezeTime();$u=User::factory()->create();$b=app(LlmBudget::class);
  $r=$b->reserve($u,null,(string)Str::uuid(),'image',650000);
  $this->travel(239)->seconds();$this->assertSame(0,$b->expireOverdue());
  $this->travel(1)->seconds();$this->assertSame(1,$b->expireOverdue());
  $this->assertSame('failed',$r->fresh()->status);$this->assertSame(0,$u->fresh()->llm_reserved);
  $b->settle($r,500000,'completed');$this->assertSame(0,$u->fresh()->llm_spent);
  $this->assertSame(0,$b->expireOverdue());
 }
 public function test_uncertain_expiry_preserves_completed_charges_and_expires_queued_images(): void {
  $this->freezeTime();$u=User::factory()->create();$b=app(LlmBudget::class);
  $paid=$b->reserve($u,null,(string)Str::uuid(),'text',100000);$b->settle($paid,50000,'completed');
  $s=Story::create(['user_id'=>$u->id,'title'=>'Test','premise'=>'Test']);$p=$s->pages()->create(['position'=>1,'title'=>'Page']);
  $r=$b->reserve($u,$s,(string)Str::uuid(),'image',600000);$b->settle($r,null,'uncertain');
  $job=ImageGeneration::create(['llm_request_id'=>$r->id,'user_id'=>$u->id,'story_id'=>$s->id,'story_page_id'=>$p->id,'visual_id'=>(string)Str::uuid(),'name'=>'Scene','kind'=>'scene','request_key'=>(string)Str::uuid(),'payload'=>[],'references'=>[],'pricing'=>[]]);
  $this->travel(241)->seconds();Http::fake();(new GenerateSceneImage($job->id))->handle(app(OpenRouter::class));
  Http::assertNothingSent();$this->assertSame('failed',$job->fresh()->status);
  $this->assertSame(0,$u->fresh()->llm_reserved);$this->assertSame(50000,$u->fresh()->llm_spent);
  $this->assertSame('completed',$paid->fresh()->status);
 }
 public function test_authenticated_page_refresh_releases_expired_credit(): void {
  $this->freezeTime();$u=User::factory()->create();$b=app(LlmBudget::class);$b->reserve($u,null,(string)Str::uuid(),'text',100000);
  $this->travel(241)->seconds();$this->actingAs($u)->get(route('ai.edit'))->assertOk();
  $this->assertSame(0,$u->fresh()->llm_reserved);
 }
}
