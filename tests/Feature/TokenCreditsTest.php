<?php
namespace Tests\Feature;
use App\Models\User;
use App\Services\LlmBudget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class TokenCreditsTest extends TestCase {
 use RefreshDatabase;
 public function test_signup_grants_one_dollar_once(): void {
  $this->post('/register',['name'=>'New user','email'=>'new@example.com','password'=>'password','password_confirmation'=>'password'])->assertRedirect();
  $u=User::where('email','new@example.com')->firstOrFail();$this->assertSame(1000000,$u->creditTotal());$this->assertSame(1,$u->tokenCredits()->count());
  $u->update(['name'=>'Updated']);$this->assertSame(1,$u->tokenCredits()->count());
 }
 public function test_admin_grant_extends_credit_and_preserves_spending_and_holds(): void {
  $u=User::factory()->create(['email'=>'credit-test@example.com']);$u->forceFill(['llm_spent'=>1000000,'llm_reserved'=>100000])->save();
  $this->artisan('ai:grant-credit',['email'=>$u->email,'amount'=>'5','--description'=>'Testing grant','--reference'=>'test-grant'])->assertSuccessful();
  $this->assertSame(6000000,$u->creditTotal());$this->assertSame(4900000,$u->fresh()->availableCredit());
  $this->artisan('ai:grant-credit',['email'=>$u->email,'amount'=>'5','--reference'=>'test-grant'])->assertFailed();$this->assertSame(2,$u->tokenCredits()->count());
  $r=app(LlmBudget::class)->reserve($u->fresh(),null,(string)Str::uuid(),'test/model',2000000);$this->assertSame('platform',$r->funding);$this->assertSame(2900000,$u->fresh()->availableCredit());
  $this->actingAs($u)->get(route('ai.edit'))->assertOk()->assertSee('Credits received')->assertSee('Welcome credit')->assertSee('Testing grant');
  $other=User::factory()->create();$this->actingAs($other)->get(route('ai.edit'))->assertDontSee('Testing grant');
 }
 public function test_invalid_grants_do_not_change_balance(): void {
  $u=User::factory()->create();foreach(['0','-5','abc','1.0000001'] as $amount)$this->artisan('ai:grant-credit',['email'=>$u->email,'amount'=>$amount])->assertFailed();$this->assertSame(1000000,$u->creditTotal());
 }
}
