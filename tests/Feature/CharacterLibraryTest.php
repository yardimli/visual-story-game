<?php

namespace Tests\Feature;

use App\Models\{User, Story, StoryCharacter, ImageGeneration};
use App\Services\{CharacterLibrary, ImageDrafts, OpenRouter};
use App\Jobs\GenerateSceneImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class CharacterLibraryTest extends TestCase
{
    use RefreshDatabase;
    private function project(): array
    {
        $user = User::factory()->create(); $user->forceFill(['openrouter_key' => 'test'])->save(); $this->actingAs($user);
        $story = Story::create(['user_id' => $user->id, 'title' => 'Characters', 'premise' => 'Story']);
        $page = $story->pages()->create(['position' => 1, 'title' => 'Start']);
        Storage::fake('local'); Storage::disk('local')->put('front.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN9sAAAAASUVORK5CYII='));
        $asset = $story->assets()->create(['name' => 'Front', 'path' => 'front.png', 'mime' => 'image/png', 'bytes' => 68, 'role' => 'reference']);
        return [$story, $page, $asset];
    }
    private function profile($story, $asset): array
    {
        return $this->postJson(route('characters.index', $story), ['revision' => 0, 'name' => 'Mira', 'description' => 'Silver braid, green eyes, scar on left cheek.', 'views' => [['label' => 'Front', 'asset_id' => $asset->id]]])->assertCreated()->json();
    }
    public function test_profiles_save_multiple_views_with_revision_checks_and_private_access(): void
    {
        [$story, $page, $asset] = $this->project(); $profile = $this->profile($story, $asset);
        $url = route('characters.index', $story);
        $this->getJson($url)->assertOk()->assertJsonPath('characters.0.views.0.asset_id', $asset->id)->assertJsonPath('characters.0.views.0.label', 'Front');
        $data = ['revision' => 1, 'name' => 'Mira', 'description' => 'Silver braid', 'views' => [['label' => 'Close-up', 'asset_id' => $asset->id]]];
        $this->putJson($url.'/'.$profile['id'], $data)->assertOk()->assertJsonPath('revision', 2);
        $this->putJson($url.'/'.$profile['id'], $data)->assertConflict();
        $this->assertSame('character', $asset->fresh()->role);
        $this->getJson(route('visuals.show', [$story, $page]))->assertJsonPath('image_categories.characters', 'Characters')->assertJsonPath('characters.0.name', 'Mira');
        $this->actingAs(User::factory()->create());
        $this->getJson($url)->assertNotFound();
        $this->putJson($url.'/'.$profile['id'], $data)->assertNotFound();
        $this->deleteJson($url.'/'.$profile['id'], ['revision' => 2])->assertNotFound();
    }
    public function test_reference_views_reject_assets_and_jobs_from_other_stories(): void
    {
        [$story, $page, $asset] = $this->project();
        $other = Story::create(['user_id' => $story->user_id, 'title' => 'Other', 'premise' => 'Other']);
        $this->postJson(route('characters.index', $other), ['revision' => 0, 'name' => 'Wrong', 'views' => [['label' => 'Front', 'asset_id' => $asset->id]]])->assertUnprocessable();
        $job = app(ImageDrafts::class)->save($story, $page, ['name' => 'Front', 'prompt' => 'Front view']);
        $this->postJson(route('characters.index', $other), ['revision' => 0, 'name' => 'Wrong', 'views' => [['label' => 'Front', 'image_id' => $job->id]]])->assertUnprocessable();
    }
    public function test_reference_view_drafts_are_unpaid_and_resolve_to_finished_images(): void
    {
        [$story, $page, $asset] = $this->project(); $profile = $this->profile($story, $asset); Queue::fake();
        $url = route('characters.index', $story).'/'.$profile['id'];
        $this->postJson($url.'/draft', ['revision' => 1, 'page_id' => $page->id, 'label' => 'Left profile'])->assertCreated();
        $job = ImageGeneration::firstOrFail();
        $this->assertSame('draft', $job->status); $this->assertSame('characters', $job->category); $this->assertNull($job->llm_request_id);
        $this->assertStringContainsString('Left profile', $job->payload['prompt']); $this->assertSame([$asset->id], array_column($job->references, 'id')); Queue::assertNothingPushed();
        $job->update(['status' => 'completed', 'asset_ids' => [$asset->id]]);
        $this->getJson(route('characters.index', $story))->assertJsonPath('characters.0.views.1.status', 'ready')->assertJsonPath('characters.0.views.1.asset_id', $asset->id);
        $this->deleteJson($url, ['revision' => 2])->assertOk();
        $this->assertDatabaseHas('assets', ['id' => $asset->id]); $this->assertDatabaseHas('image_generations', ['id' => $job->id]);
    }
    public function test_identity_instructions_use_only_selected_views_and_keep_reference_order(): void
    {
        [$story, $page, $asset] = $this->project(); $this->profile($story, $asset);
        $library = app(CharacterLibrary::class);
        $this->assertSame('', $library->referenceInstructions($story, []));
        $prompt = $library->referenceInstructions($story, [999, $asset->id]);
        $this->assertStringContainsString('reference image 2 (Front)', $prompt);
        $this->assertStringContainsString('Silver braid', $prompt);
        $this->assertStringContainsString('not additional people', $prompt);
    }
    public function test_generation_snapshots_identity_and_keeps_editable_prompt_separate(): void
    {
        [$story, $page, $asset] = $this->project(); $this->profile($story, $asset); Queue::fake();
        $this->mock(OpenRouter::class, function ($mock) { $mock->shouldReceive('imageEndpoints')->andReturn([['supported_parameters' => ['input_references' => ['max' => 16]], 'pricing' => []]]); });
        $draft = app(ImageDrafts::class)->save($story, $page, ['prompt' => 'Mira at the lighthouse', 'references' => [$asset->id]]);
        $this->postJson(route('images.generate', [$story, $page]), ['revision' => $page->fresh()->visual_revision, 'draft_id' => $draft->id, 'request_key' => (string) Str::uuid(), 'model' => 'test/image', 'references' => [$asset->id], 'settings' => []])->assertAccepted();
        $payload = $draft->fresh()->payload;
        $this->assertStringContainsString('Silver braid', $payload['prompt']);
        $this->assertSame('Mira at the lighthouse', $payload['_base_prompt']);
        $this->getJson(route('visuals.show', [$story, $page]))->assertJsonPath('jobs.0.prompt', 'Mira at the lighthouse');
        Queue::assertPushed(GenerateSceneImage::class);
    }
}
