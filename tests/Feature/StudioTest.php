<?php
namespace Tests\Feature;
use App\Models\{User,Story,ChatMessage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Storage,DB};
use Tests\TestCase;
class StudioTest extends TestCase {
    use RefreshDatabase;
    private function story(User $u): Story {
        $story=Story::create(['user_id'=>$u->id,'title'=>'Workshop','premise'=>'Repair a radio.']);
        $story->pages()->create(['position'=>1,'title'=>'Arrival']);
        return $story;
    }
    public function test_editor_tabs_and_dashboard_history_are_scoped_to_owner(): void {
        $u=User::factory()->create();$s=$this->story($u);$p=$s->pages()->first();$this->actingAs($u);
        $this->get(route('stories.edit',$s))->assertOk()->assertSeeInOrder(['Page plan','Interactions','Image generation ↗','References','Story bible'])->assertSeeInOrder(['Completion condition','Move earlier'])->assertDontSee('Linear progression');
        $this->get(route('images.studio',[$s,$p]))->assertOk()->assertSeeInOrder(['Page plan','Interactions','aria-current="page"','Image generation ↗','References','Story bible'],false);
        $this->get(route('dashboard',['history'=>$s->id]))->assertOk()->assertSee('History · Workshop')->assertSee('Your first save will create a revision.');
        $this->actingAs(User::factory()->create())->get(route('dashboard',['history'=>$s->id]))->assertNotFound();
    }
    public function test_inline_reference_upload_returns_library_thumbnails_without_generating(): void {
        Storage::fake('local');$u=User::factory()->create();$s=$this->story($u);
        $response=$this->actingAs($u)->postJson(route('assets.store',$s),['images'=>[UploadedFile::fake()->image('note.png'),UploadedFile::fake()->image('stone.png')],'role'=>'reference']);
        $response->assertCreated()->assertJsonCount(2,'assets')->assertJsonPath('assets.0.name','note.png');
        $assets=$s->assets()->get();$this->assertCount(2,$assets);
        foreach($assets as $asset){Storage::disk('local')->assertExists($asset->path);$this->get(route('assets.show',$asset))->assertOk();}
        $this->assertDatabaseCount('image_generations',0);
        $this->actingAs(User::factory()->create())->postJson(route('assets.store',$s),['images'=>[UploadedFile::fake()->image('other.png')],'role'=>'reference'])->assertNotFound();
    }
    public function test_creates_single_short_and_novel_drafts(): void {
        $u=User::factory()->create();$this->actingAs($u);
        foreach([1,3,50] as $count) {
            $this->post(route('stories.store'),['title'=>'New story','premise'=>'A mystery','genre'=>'Mystery','page_count'=>$count])->assertRedirect();
            $this->assertSame($count,Story::latest('id')->first()->pages()->count());
        }
        $this->post(route('stories.store'),['title'=>'Bad','premise'=>'X','genre'=>'Mystery','page_count'=>0])->assertSessionHasErrors('page_count');
    }
    public function test_editor_and_settings_render(): void {
        $u=User::factory()->create();$s=$this->story($u);
        $this->actingAs($u)->get(route('dashboard'))->assertOk()->assertSee('Workshop');
        $this->get(route('stories.edit',$s))->assertOk()->assertSee('Creative companion');
        $this->get(route('ai.edit'))->assertOk()->assertSee('AI & usage',false);
    }
    public function test_other_users_cannot_read_or_edit_a_story(): void {
        $s=$this->story(User::factory()->create());
        $this->actingAs(User::factory()->create())->get(route('stories.edit',$s))->assertNotFound();
        $this->put(route('stories.update',$s),[])->assertNotFound();
        $this->post(route('chat.store',$s),[])->assertNotFound();
    }
    public function test_page_save_versions_and_rejects_stale_writes(): void {
        $u=User::factory()->create();$s=$this->story($u);
        $this->actingAs($u)->put(route('pages.update',[$s,$s->pages->first()]),['revision'=>1,'title'=>'Changed'])->assertRedirect();
        $this->assertSame(2,$s->fresh()->revision);
        $this->assertSame('Arrival',$s->revisions->first()->snapshot['pages'][0]['title']);
        $this->put(route('pages.update',[$s,$s->pages->first()]),['revision'=>1,'title'=>'Stale'])->assertSessionHasErrors('revision');
    }
    public function test_editor_has_no_full_plan_approval_gate(): void {
        $u=User::factory()->create();$s=$this->story($u);
        $this->actingAs($u)->get(route('stories.edit',$s))->assertOk()->assertDontSee('Approve full plan');
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Plan approved');
        $this->post('/stories/'.$s->id.'/approve',['revision'=>1])->assertNotFound();
    }
    public function test_key_is_encrypted_hidden_and_not_flashed_on_validation_error(): void {
        $u=User::factory()->create();$key='sk-or-v1-personal-secret';
        $this->actingAs($u)->put(route('ai.update'),['openrouter_key'=>$key,'current_password'=>'password'])->assertRedirect();
        $this->assertSame($key,$u->fresh()->openrouter_key);
        $this->assertNotSame($key,DB::table('users')->where('id',$u->id)->value('openrouter_key'));
        $this->assertArrayNotHasKey('openrouter_key',$u->fresh()->toArray());
        $this->put(route('ai.update'),['openrouter_key'=>$key,'current_password'=>'wrong'])->assertSessionHasErrors('current_password')->assertSessionMissing('_old_input.openrouter_key');
        $this->delete(route('ai.destroy'),['current_password'=>'password'])->assertRedirect();
        $this->assertNull($u->fresh()->openrouter_key);
    }
    public function test_uploaded_references_are_private(): void {
        Storage::fake('local');$u=User::factory()->create();$s=$this->story($u);
        $this->actingAs($u)->post(route('assets.store',$s),['images'=>[UploadedFile::fake()->image('reference.png')],'role'=>'character'])->assertRedirect();
        $asset=$s->assets()->first();Storage::disk('local')->assertExists($asset->path);
        $this->get(route('assets.show',$asset))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('assets.show',$asset))->assertNotFound();
    }
    public function test_assistant_proposal_is_reviewed_and_rejects_stale_apply(): void {
        $u=User::factory()->create();$s=$this->story($u);
        $m=$s->messages()->create(['role'=>'assistant','content'=>'Draft','revision'=>1,'proposal'=>['bible'=>'New bible']]);
        $this->assertNull($s->bible);
        $this->actingAs($u)->post(route('stories.apply',[$s,$m]))->assertRedirect();
        $this->assertSame('New bible',$s->fresh()->bible);
        $this->post(route('stories.apply',[$s,$m]))->assertSessionHasErrors('revision');
        $rev=$s->revisions()->first();
        $this->post(route('stories.restore',[$s,$rev]),['revision'=>2])->assertRedirect();
        $this->assertNull($s->fresh()->bible);
    }
    public function test_proposal_cannot_move_a_page_into_another_story(): void {
        $u=User::factory()->create();$s=$this->story($u);$other=$this->story(User::factory()->create());
        $fields=array_fill_keys(['narrative','scene_prompt','video_prompt','interaction_plan','completion'],'Draft');
        $m=$s->messages()->create(['role'=>'assistant','content'=>'Draft','revision'=>1,'proposal'=>['pages'=>[array_merge($fields,['position'=>1,'title'=>'Moved','story_id'=>$other->id])]]]);
        $this->actingAs($u)->post(route('stories.apply',[$s,$m]))->assertSessionHasErrors('pages.0');
        $this->assertSame($s->id,$s->pages->first()->fresh()->story_id);
    }
    public function test_page_route_cannot_modify_another_story_page(): void {
        $u=User::factory()->create();$s=$this->story($u);$other=$this->story(User::factory()->create());
        $this->actingAs($u)->put(route('pages.update',[$s,$other->pages->first()]),['revision'=>1,'title'=>'Wrong'])->assertNotFound();
    }
    public function test_email_control_characters_are_rejected(): void {
        $this->post('/register',['name'=>'Test','email'=>"test@example.test\r\nBcc: other@example.test",'password'=>'password','password_confirmation'=>'password'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users',0);
    }
}
