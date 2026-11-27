<?php
namespace Tests\Feature;

use App\Models\{User, Story, CompanionMessage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatHistoryPollingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unchanged_history_returns_no_body_and_edits_or_deletions_invalidate_it(): void
    {
        $user=User::factory()->create();
        $story=Story::create(['user_id'=>$user->id,'title'=>'House','premise'=>'Test']);
        $message=$story->messages()->create(['role'=>'assistant','content'=>str_repeat('Long response ',1000)]);
        $url=route('chat.history',$story);
        $first=$this->actingAs($user)->getJson($url)->assertOk();
        $etag=$first->headers->get('ETag');
        $this->assertNotEmpty($etag);
        $unchanged=$this->getJson($url,['If-None-Match'=>$etag])->assertStatus(304);
        $this->assertSame('',$unchanged->getContent());
        $message->update(['proposal'=>['story'=>['title'=>'Changed']]]);
        $changed=$this->getJson($url,['If-None-Match'=>$etag])->assertOk();
        $this->assertNotSame($etag,$changed->headers->get('ETag'));
        $message->delete();
        $this->getJson($url,['If-None-Match'=>$changed->headers->get('ETag')])->assertOk()->assertJsonCount(0,'messages');
        $this->actingAs(User::factory()->create())->getJson($url,['If-None-Match'=>$etag])->assertNotFound();
    }

    public function test_pending_reply_updates_and_stale_recovery_invalidate_even_without_new_messages(): void
    {
        $user=User::factory()->create();
        $message=CompanionMessage::create(['user_id'=>$user->id,'role'=>'user','content'=>'Help','response_status'=>'running','response_partial'=>'One']);
        $url=route('companion.history');
        $first=$this->actingAs($user)->getJson($url)->assertOk();
        $message->update(['response_partial'=>'One two']);
        $updated=$this->getJson($url,['If-None-Match'=>$first->headers->get('ETag')])->assertOk()->assertJsonPath('pending.0.content','One two');
        $this->getJson($url,['If-None-Match'=>$updated->headers->get('ETag')])->assertStatus(304);
        $this->travel(6)->minutes();
        $this->getJson($url,['If-None-Match'=>$updated->headers->get('ETag')])->assertOk()->assertJsonCount(0,'pending')->assertJsonPath('messages.0.response_status','failed');
    }

    public function test_embedded_snapshot_version_matches_first_poll(): void
    {
        $user=User::factory()->create();
        $history=app(\App\Services\ChatHistory::class);
        $snapshot=$history->snapshot(CompanionMessage::where('user_id',$user->id),$user);
        $this->actingAs($user)->getJson(route('companion.history'),['If-None-Match'=>$history->version($snapshot)])->assertStatus(304);
    }
}
