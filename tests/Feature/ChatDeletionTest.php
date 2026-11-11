<?php
namespace Tests\Feature;
use App\Models\{User,Story,CompanionMessage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ChatDeletionTest extends TestCase {
 use RefreshDatabase;
 public function test_owner_can_delete_both_roles_without_deleting_story_or_other_messages():void {
  $u=User::factory()->create();$s=Story::create(['user_id'=>$u->id,'title'=>'House','premise'=>'Keep this']);$other=Story::create(['user_id'=>$u->id,'title'=>'Other','premise'=>'Other']);
  foreach(['user','assistant'] as $role){
   $m=$s->messages()->create(['role'=>$role,'content'=>'Remove me']);
   $this->actingAs($u)->deleteJson(route('chat.destroy',[$other,$m]))->assertNotFound();
   $this->actingAs(User::factory()->create())->deleteJson(route('chat.destroy',[$s,$m]))->assertNotFound();
   $this->actingAs($u)->deleteJson(route('chat.destroy',[$s,$m]))->assertNoContent();
   $this->assertDatabaseMissing('chat_messages',['id'=>$m->id]);
  }
  $this->assertSame('Keep this',$s->fresh()->premise);
 }
 public function test_personal_chat_deletion_checks_ownership():void {
  $u=User::factory()->create();$m=CompanionMessage::create(['user_id'=>$u->id,'role'=>'user','content'=>'Private']);
  $this->actingAs(User::factory()->create())->deleteJson(route('companion.destroy',$m))->assertNotFound();
  $this->actingAs($u)->deleteJson(route('companion.destroy',$m))->assertNoContent();
  $this->assertDatabaseMissing('companion_messages',['id'=>$m->id]);
 }
}
