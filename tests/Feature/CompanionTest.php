<?php
namespace Tests\Feature;
use App\Models\{User,CompanionMessage,Story,LlmRequest};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http,Cache};
use Illuminate\Support\Str;
use Tests\TestCase;
class CompanionTest extends TestCase {
    use RefreshDatabase;
    public function test_image_prompt_proposal_is_bound_to_the_selected_visual_without_saving_it(): void {
        $u=$this->userAndProvider('Prompt draft. ```json {"pages":[{"position":1,"scene_prompt":"Remove the note from the porch."}]} ```');
        $s=Story::create(['user_id'=>$u->id,'title'=>'House','premise'=>'Find a key']);$id=(string)Str::uuid();
        $p=$s->pages()->create(['position'=>1,'title'=>'Porch','visual_document'=>['visuals'=>[['id'=>$id,'name'=>'Porch edit','prompt'=>'Original image prompt']]]]);
        $this->actingAs($u)->postJson(route('chat.store',$s),array_merge($this->payload(),['page_id'=>$p->id,'image_visual_id'=>$id]))
            ->assertOk()->assertJsonPath('message.proposal._image.id',$id)->assertJsonPath('message.proposal._image.revision',1);
        $this->assertSame('Original image prompt',$p->fresh()->visual_document['visuals'][0]['prompt']);
        Http::assertSent(fn($r)=>str_contains(json_encode($r['messages']),'Original image prompt'));
        $this->postJson(route('chat.store',$s),array_merge($this->payload(),['page_id'=>$p->id,'image_visual_id'=>(string)Str::uuid()]))->assertUnprocessable();
        Http::assertSentCount(1);
    }
    public function test_interaction_proposal_uses_selected_area_context_and_is_revision_bound(): void {
        $u=$this->userAndProvider('Envelope draft. ```json {"interaction":{"description":"A sealed envelope waits by the door.","action":"inspect","object":{"name":"Envelope letter","prompt":"An opened envelope with a readable clue."}}} ```');
        $s=Story::create(['user_id'=>$u->id,'title'=>'House','premise'=>'Find a key']);$visual=(string)Str::uuid();$area=(string)Str::uuid();
        $p=$s->pages()->create(['position'=>1,'title'=>'Porch','visual_document'=>['visuals'=>[['id'=>$visual,'kind'=>'scene','name'=>'Porch','hotspots'=>[['id'=>$area,'label'=>'Envelope','description'=>'Old description']]]]]]);
        $s->pages()->create(['position'=>2,'title'=>'Other page context','video_prompt'=>'Rain on the hallway window','interaction_plan'=>'Use the key']);
        $payload=array_merge($this->payload(),['page_id'=>$p->id,'active_tab'=>'visuals','visual_id'=>$visual,'hotspot_id'=>$area]);
        $this->actingAs($u)->postJson(route('chat.store',$s),$payload)->assertOk()
            ->assertJsonPath('message.proposal._interaction.hotspot_id',$area)->assertJsonPath('message.proposal._interaction.revision',1)
            ->assertJsonPath('message.proposal.interaction.object.name','Envelope letter');
        $this->assertSame('Old description',$p->fresh()->visual_document['visuals'][0]['hotspots'][0]['description']);
        Http::assertSent(fn($r)=>str_contains(json_encode($r['messages']),'Selected area') && str_contains(json_encode($r['messages']),'Envelope') && str_contains(json_encode($r['messages']),'Rain on the hallway window') && str_contains(json_encode($r['messages']),'Use the key'));
        $payload['hotspot_id']=(string)Str::uuid();$payload['request_key']=(string)Str::uuid();
        $this->postJson(route('chat.store',$s),$payload)->assertUnprocessable();Http::assertSentCount(1);
    }
    private function userAndProvider(string $content): User {
        config(['story.openrouter_providers'=>['test','openai'], 'story.openrouter_key'=>'test-key']);
        Cache::put('openrouter.models',[['id'=>'test/model','name'=>'Test','context_length'=>100000,'architecture'=>['output_modalities'=>['text']],'pricing'=>['prompt'=>'0.000001','completion'=>'0.000002']]],600);
        Http::fake(['*/chat/completions'=>Http::response(['id'=>'gen-demo','usage'=>['cost'=>0,'prompt_tokens'=>1000,'completion_tokens'=>500],'choices'=>[['message'=>['content'=>$content]]]])]);
        return User::factory()->create();
    }
    private function payload(): array { return ['message'=>'Plan a mystery','model'=>'test/model','request_key'=>(string)Str::uuid()]; }
    public function test_dashboard_proposal_is_private_billed_and_does_not_create_a_story(): void {
        $u=$this->userAndProvider('Try this. ```json {"form":{"title":"The Lantern","premise":"Find the light","genre":"Mystery","page_count":3}} ```');
        $data=$this->payload();
        $this->actingAs($u)->postJson(route('companion.store'),$data)->assertOk()->assertJsonPath('message.proposal.form.title','The Lantern');
        $this->assertDatabaseCount('stories',0);
        $this->assertSame(2000,$u->fresh()->llm_spent);
        $this->assertNull(LlmRequest::first()->story_id);
        $this->assertSame('Plan a mystery',collect(LlmRequest::first()->request_content['messages'])->last()['content']);
        $this->assertStringContainsString('The Lantern',LlmRequest::first()->response_content['body']['choices'][0]['message']['content']);
        $this->postJson(route('companion.store'),$data)->assertUnprocessable();
        Http::assertSentCount(1);
        $this->getJson(route('companion.history'))->assertJsonCount(2,'messages');
        $this->actingAs(User::factory()->create())->getJson(route('companion.history'))->assertJsonCount(0,'messages');
    }
    public function test_unsafe_form_fields_are_not_exposed_as_a_proposal(): void {
        $u=$this->userAndProvider('```json {"form":{"title":"Test","openrouter_key":"bad"}} ```');
        $this->actingAs($u)->postJson(route('companion.store'),$this->payload())->assertOk()->assertJsonPath('message.proposal',null);
        $this->assertNull($u->fresh()->openrouter_key);
    }
    public function test_exhausted_account_companion_does_not_call_provider(): void {
        $u=$this->userAndProvider('Hi');$u->forceFill(['llm_spent'=>1000000])->save();
        $this->actingAs($u)->postJson(route('companion.store'),$this->payload())->assertUnprocessable();
        Http::assertNothingSent();
    }
    public function test_companion_requires_authentication(): void {
        $this->getJson(route('companion.history'))->assertUnauthorized();
        $this->postJson(route('companion.store'),$this->payload())->assertUnauthorized();
    }
    public function test_shared_panel_and_draft_forms_render_without_direct_apply_buttons(): void {
        $u=User::factory()->create();
        foreach (['dashboard','profile.edit','ai.edit'] as $route) $this->actingAs($u)->get(route($route))->assertOk()->assertDontSee('Creative companion')->assertDontSee('studio-with-companion');
        $story=Story::create(['user_id'=>$u->id,'title'=>'Draft','premise'=>'An idea']);
        $story->pages()->create(['position'=>1,'title'=>'Opening']);
        $this->get(route('stories.edit',$story))->assertOk()->assertSee('Creative companion')->assertSee('data-companion-form="page"',false)->assertDontSee('Apply proposed changes');
        $this->get(route('dashboard'))->assertSee('0 / 1 page plans complete');
    }
    public function test_story_chat_returns_suggestion_without_saving_page(): void {
        $u=$this->userAndProvider('```json {"bible":"A revised world"} ```');
        $story=Story::create(['user_id'=>$u->id,'title'=>'Draft','premise'=>'An idea']);
        $this->actingAs($u)->postJson(route('chat.store',$story),$this->payload())->assertOk()->assertJsonPath('message.proposal.bible','A revised world');
        $this->assertNull($story->fresh()->bible);
        $this->assertSame(1,$story->fresh()->revision);
    }
    public function test_story_detail_proposal_is_returned_without_saving_premise(): void {
        $u=$this->userAndProvider('Formatted premise. ```json {"story":{"premise":"PAGE 1\\nA mystery."}} ```');
        $story=Story::create(['user_id'=>$u->id,'title'=>'Draft','premise'=>'Original']);
        $this->actingAs($u)->postJson(route('chat.store',$story),$this->payload())->assertOk()->assertJsonPath('message.proposal.story.premise',"PAGE 1\nA mystery.");
        $this->assertSame('Original',$story->fresh()->premise);
    }
    public function test_autosave_returns_revision_and_rejects_stale_writes(): void {
        $u=User::factory()->create();
        $story=Story::create(['user_id'=>$u->id,'title'=>'Draft','premise'=>'Original']);
        $page=$story->pages()->create(['position'=>1,'title'=>'Opening']);
        $this->actingAs($u)->putJson(route('pages.update',[$story,$page]),['revision'=>1,'title'=>'Edited'])->assertOk()->assertJsonPath('revision',2);
        $this->putJson(route('stories.update',$story),['revision'=>2,'title'=>'Story','premise'=>'Updated','genre'=>'Mystery','language'=>'English'])->assertOk()->assertJsonPath('revision',3);
        $this->putJson(route('pages.update',[$story,$page]),['revision'=>1,'title'=>'Stale'])->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->assertSame('Edited',$page->fresh()->title);
        $this->get(route('stories.edit',$story))->assertDontSee('Save page')->assertDontSee('Save story bible')->assertSee('Changes save automatically.');
    }
}
