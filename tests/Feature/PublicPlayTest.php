<?php

namespace Tests\Feature;

use App\Models\{Asset, Story, StorySound, User, VideoGeneration};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicPlayTest extends TestCase
{
    use RefreshDatabase;

    private function project(): array
    {
        $owner = User::factory()->create();
        $story = Story::create(['user_id' => $owner->id, 'title' => 'Public house', 'premise' => 'Explore']);
        $story->pages()->create(['position' => 1, 'title' => 'Blank']);
        $page = $story->pages()->create(['position' => 2, 'title' => 'First scene', 'visual_document' => ['visuals' => [
            ['id' => 'first', 'kind' => 'scene', 'name' => 'First', 'hotspots' => []],
            ['id' => 'later', 'kind' => 'scene', 'name' => 'Later', 'hotspots' => []],
        ]]]);
        return [$owner, $story, $page];
    }

    public function test_owner_can_share_revoke_and_create_a_new_link(): void
    {
        [$owner, $story] = $this->project();
        $endpoint = route('public.play.enable', $story);
        $this->postJson($endpoint)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson($endpoint)->assertNotFound();
        $this->actingAs($owner)->postJson($endpoint)->assertOk();
        $token = $story->fresh()->public_play_token;
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{22}$/', $token);
        $this->postJson($endpoint)->assertJsonPath('url', route('public.play', $token));
        $this->actingAs(User::factory()->create())->deleteJson($endpoint)->assertNotFound();
        $this->actingAs($owner)->deleteJson($endpoint)->assertOk();
        $this->get(route('public.play', $token))->assertNotFound();
        $this->postJson($endpoint)->assertOk();
        $this->assertNotSame($token, $story->fresh()->public_play_token);
    }

    public function test_guest_starts_at_first_playable_scene_and_cannot_override_start(): void
    {
        [, $story, $page] = $this->project();
        $token = str_repeat('a', 22);
        $story->update(['public_play_token' => $token]);
        $url = route('public.play', $token).'?page=999&scene=later&inventory[]=key&flags[]=open&skip_first_video=1';
        $this->get($url)->assertOk()->assertSee('Start game')->assertDontSee('sceneEditor(')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertViewHas('game', fn ($game) => $game['start'] === [
                'skipFirstVideo' => false, 'pageId' => $page->id, 'sceneId' => 'first', 'inventory' => [], 'flags' => [],
            ]);
        $this->get(route('stories.edit', $story))->assertRedirect(route('login'));
        $this->get(route('public.play', str_repeat('b', 22)))->assertNotFound();
    }

    public function test_guest_media_is_story_scoped_and_revocable(): void
    {
        Storage::fake('local');
        [, $story] = $this->project();
        [, $other] = $this->project();
        $token = str_repeat('c', 22);
        $story->update(['public_play_token' => $token]);
        foreach ([$story, $other] as $project) {
            $path = 'media/'.$project->id;
            Storage::disk('local')->put($path, 'media bytes');
            $assets[$project->id] = Asset::create(['story_id' => $project->id, 'name' => 'Image', 'path' => $path, 'mime' => 'image/png', 'bytes' => 11]);
            $sounds[$project->id] = StorySound::create(['story_id' => $project->id, 'name' => 'Audio', 'provider' => 'upload', 'path' => $path, 'mime' => 'audio/mpeg']);
            $videos[$project->id] = VideoGeneration::create([
                'id' => (string) Str::uuid(), 'user_id' => $project->user_id, 'story_id' => $project->id,
                'name' => 'Intro', 'prompt' => 'Intro', 'engine' => 'test', 'engine_version' => 1,
                'parameters' => [], 'seed' => 1, 'status' => 'completed', 'output_path' => $path,
            ]);
        }
        foreach (['image' => $assets, 'audio' => $sounds, 'video' => $videos] as $kind => $records) {
            $this->get(route('public.play.media', [$token, $kind, $records[$story->id]->id]))->assertOk();
            $this->get(route('public.play.media', [$token, $kind, $records[$other->id]->id]))->assertNotFound();
        }
        $this->get(route('public.play', $token))->assertViewHas('game', fn ($game) =>
            str_contains($game['assets']->first()['url'], '/p/'.$token.'/image/') &&
            str_contains($game['sounds']->first()['url'], '/p/'.$token.'/audio/') &&
            str_contains($game['videos']->first()['url'], '/p/'.$token.'/video/'));
        $videos[$story->id]->update(['status' => 'draft']);
        $this->get(route('public.play.media', [$token, 'video', $videos[$story->id]->id]))->assertNotFound();
        $story->update(['public_play_token' => null]);
        $this->get(route('public.play.media', [$token, 'image', $assets[$story->id]->id]))->assertNotFound();
    }
}
