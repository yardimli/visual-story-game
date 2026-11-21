<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\User;
use App\Models\VideoGeneration;
use App\Services\CompanionChanges;
use App\Services\VideoDrafts;
use App\Services\VideoRelay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VideoGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Story $story;

    private array $fields;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['video.mode' => 'hub']);
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->story = Story::create(['user_id' => $user->id, 'title' => 'Video test', 'premise' => 'Test']);
        $file = UploadedFile::fake()->image('frame.png', 64, 64);
        $path = $file->store('frames', 'local');
        $asset = $this->story->assets()->create(['name' => 'First', 'path' => $path, 'mime' => 'image/png', 'bytes' => $file->getSize(), 'role' => 'scene']);
        $this->fields = ['name' => 'Door opens', 'prompt' => 'Open the door', 'engine' => 'fastvideo_h3',
            'parameters' => ['aspect_ratio' => '16:9', 'seconds' => 5, 'megapixels' => 0.4], 'first_asset_id' => $asset->id];
    }

    public function test_completed_videos_bill_duration_once_and_relay_hub_copy_is_not_billed(): void
    {
        foreach ([5, 15] as $seconds) {
            $fields=$this->fields; $fields['parameters']['seconds']=$seconds;
            $job=app(VideoDrafts::class)->save($this->story,$fields);
            $job->update(['status'=>'running','remote_id'=>(string) Str::uuid()]);
            $file=UploadedFile::fake()->createWithContent('video.mp4',pack('N',24).'ftypisom'.str_repeat("x",32));
            app(\App\Services\VideoFiles::class)->complete($job,$file->getPathname());
            app(\App\Services\VideoFiles::class)->complete($job,$file->getPathname());
            $this->assertDatabaseHas('llm_requests',['request_key'=>$job->id,'cost'=>$seconds*50000]);
        }
        $this->assertSame(1000000,User::find($this->story->user_id)->llm_spent);
        $hub=VideoGeneration::create(['name'=>'Relay copy','engine'=>'fastvideo_h3','engine_version'=>1,'prompt'=>'Test','parameters'=>['seconds'=>5],'seed'=>1,'status'=>'running']);
        app(\App\Services\VideoFiles::class)->complete($hub,$file->getPathname());
        $this->assertDatabaseCount('llm_requests',2);
        $this->assertNull($hub->fresh()->llm_request_id);
    }

    public function test_pending_video_cards_include_source_frame_urls(): void
    {
        $fields = $this->fields + ['last_asset_id' => $this->fields['first_asset_id']];
        $job = app(VideoDrafts::class)->save($this->story, $fields);
        $this->getJson(route('videos.index', $this->story))->assertOk()
            ->assertJsonPath('jobs.0.first_frame_url', route('assets.show', $fields['first_asset_id']))
            ->assertJsonPath('jobs.0.last_frame_url', route('assets.show', $fields['last_asset_id']));
        $job->update(['last_asset_id' => null]);
        $this->assertNull($job->presentation()['last_frame_url']);
    }

    public function test_relay_queues_on_existing_worker_and_hub_does_not(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $hub = $this->queued();
        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\SyncVideo::class);
        config(['video.mode' => 'relay']);
        $local = $this->queued();
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SyncVideo::class,
            fn ($job) => $job->videoId === $local->id && $job->queue === 'video'
                && $job->connection === 'database' && $job->tries === 0 && $job->timeout < 240);
        $this->assertSame('forwarding', $local->status);
    }

    public function test_relay_poll_releases_until_complete_and_stops_for_deleted_jobs(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        config(['video.mode' => 'relay']);
        $video = $this->queued();
        $relay = \Mockery::mock(VideoRelay::class);
        $relay->shouldReceive('sync')->once()->andReturnUsing(fn ($job) => $job->update(['status' => 'running']));
        $queued = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $queued->shouldReceive('release')->once()->with(15);
        $poll = new \App\Jobs\SyncVideo($video->id);
        $poll->setJob($queued);
        $poll->handle($relay);

        $relay = \Mockery::mock(VideoRelay::class);
        $relay->shouldReceive('sync')->once()->andReturnUsing(fn ($job) => $job->update(['status' => 'completed']));
        $poll->setJob(\Mockery::mock(\Illuminate\Contracts\Queue\Job::class));
        $poll->handle($relay);
        $video->delete();
        $poll->handle($relay);
    }

    public function test_video_worker_recovers_preexisting_relay_jobs(): void
    {
        $video = $this->queued();
        config(['video.mode' => 'relay']);
        \Illuminate\Support\Facades\Queue::fake();
        event(new \Illuminate\Queue\Events\Looping('database', 'images,audio'));
        \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\SyncVideo::class);
        event(new \Illuminate\Queue\Events\Looping('database', 'images,audio,video'));
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SyncVideo::class,
            fn ($job) => $job->videoId === $video->id && $job->queue === 'video');
    }

    public function test_frame_picker_uses_image_card_order_and_categories(): void
    {
        $page = $this->story->pages()->create(['position' => 1, 'title' => 'Hall']);
        $first = $this->story->assets()->first();
        $second = $first->replicate();
        $second->name = 'Second';
        $second->save();
        $drafts = app(\App\Services\ImageDrafts::class);
        $one = $drafts->save($this->story, $page, ['prompt' => 'One']);
        $two = $drafts->save($this->story, $page, ['prompt' => 'Two']);
        $one->update(['status' => 'completed', 'asset_ids' => [$first->id], 'category' => 'inventory']);
        $two->update(['status' => 'completed', 'asset_ids' => [$second->id], 'category' => 'change_state']);
        $this->story->update(['image_order' => [$one->id, $two->id]]);
        $this->getJson(route('videos.index', $this->story))->assertOk()
            ->assertJsonPath('assets.0.id', $first->id)
            ->assertJsonPath('assets.0.category', 'inventory')
            ->assertJsonPath('assets.1.id', $second->id)
            ->assertJsonPath('assets.1.category', 'change_state')
            ->assertJsonPath('image_categories.inventory', 'Inventory');
    }

    public function test_scene_video_selection_playback_payload_frames_and_delete_cleanup(): void
    {
        $video = $this->queued();
        $video->update(['status' => 'completed', 'output_path' => 'videos/'.$video->id.'/result.mp4']);
        $visual = ['id' => (string) Str::uuid(), 'kind' => 'scene', 'name' => 'Hall', 'asset_id' => null,
            'prompt' => '', 'hotspots' => [], 'intro_video_id' => $video->id, 'intro_video_repeat' => true];
        $document = app(\App\Services\SceneDocument::class)->validate($this->story, ['visuals' => [$visual]]);
        $this->assertTrue($document['visuals'][0]['intro_video_repeat']);
        $page = $this->story->pages()->create(['position' => 1, 'title' => 'Hall', 'visual_document' => $document]);
        $this->get(route('stories.play', [$this->story, 'page' => $page->id, 'skip_first_video' => 1]))
            ->assertOk()->assertViewHas('game', fn ($game) => $game['start']['skipFirstVideo'] === true
                && $game['videos']->first()['id'] === $video->id);
        $this->get(route('videos.frame', [$this->story, $video, 'first']))->assertOk();
        $this->get(route('videos.frame', [$this->story, $video, 'last']))->assertNotFound();
        $this->deleteJson('/stories/'.$this->story->id.'/videos/'.$video->id)->assertNoContent();
        $this->assertNull($page->fresh()->visual_document['visuals'][0]['intro_video_id']);
    }

    public function test_scene_rejects_unfinished_and_foreign_videos(): void
    {
        $video = $this->queued();
        $visual = ['id' => (string) Str::uuid(), 'kind' => 'scene', 'name' => 'Hall', 'asset_id' => null,
            'prompt' => '', 'hotspots' => [], 'intro_video_id' => $video->id];
        foreach (['queued', 'foreign'] as $case) {
            if ($case === 'foreign') $video->update(['status' => 'completed', 'story_id' => Story::create([
                'user_id' => $this->story->user_id, 'title' => 'Other', 'premise' => 'Other'])->id]);
            try {
                app(\App\Services\SceneDocument::class)->validate($this->story, ['visuals' => [$visual]]);
                $this->fail('Invalid video was accepted');
            } catch (\Illuminate\Validation\ValidationException $error) {
                $this->assertArrayHasKey('visuals.0.intro_video_id', $error->errors());
            }
        }
        $this->get(route('videos.frame', [$this->story, $video, 'first']))->assertNotFound();
    }

    private function token(string $role, string $plain): int
    {
        return DB::table('video_access_tokens')->insertGetId(['role' => $role, 'name' => $plain, 'token_hash' => hash('sha256', $plain), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function queued(): VideoGeneration
    {
        $job = app(VideoDrafts::class)->save($this->story, $this->fields);
        app(VideoDrafts::class)->queue($job);

        return $job->fresh();
    }

    public function test_credentials_can_be_issued_and_rotated_without_any_user_account(): void
    {
        User::query()->delete();
        $this->artisan('videos:token worker windows-gpu')->assertSuccessful();
        $record = DB::table('video_access_tokens')->sole();
        $this->assertNull($record->user_id);
        $this->artisan('videos:token worker windows-gpu')->assertSuccessful();
        $rotated = DB::table('video_access_tokens')->sole();
        $this->assertSame($record->id, $rotated->id);
        $this->assertNotSame($record->token_hash, $rotated->token_hash);
        $this->artisan('videos:token relay local-development')->assertSuccessful();
        $this->assertDatabaseCount('video_access_tokens', 2);
    }

    public function test_migration_preserves_existing_credentials_and_imported_jobs_without_account_dependency(): void
    {
        $tokenId = $this->token('relay', 'legacy-relay');
        DB::table('video_access_tokens')->where('id', $tokenId)->update(['user_id' => $this->story->user_id]);
        $job = $this->queued();
        $job->update(['story_id' => null, 'origin_token_id' => $tokenId, 'origin_job_id' => (string) Str::uuid()]);
        $migration = require database_path('migrations/2026_11_20_000002_make_video_credentials_installation_scoped.php');
        $migration->up();
        User::whereKey($this->story->user_id)->delete();
        $this->assertNull($job->fresh()->user_id);
        $this->assertDatabaseHas('video_access_tokens', ['id' => $tokenId, 'token_hash' => hash('sha256', 'legacy-relay'), 'user_id' => null]);
        $this->getJson('/api/video/relay/'.$job->id, ['Authorization' => 'Bearer legacy-relay'])->assertOk();
    }

    public function test_drafts_validate_ownership_and_queue_snapshots_once(): void
    {
        $url = '/stories/'.$this->story->id.'/videos';
        $job = $this->postJson($url, $this->fields)->assertCreated()->json();
        $this->assertSame('draft', $job['status']);
        $this->postJson($url.'/'.$job['id'].'/queue')->assertAccepted()->assertJsonPath('status', 'queued');
        $this->postJson($url.'/'.$job['id'].'/queue')->assertAccepted();
        $this->assertDatabaseCount('video_generations', 1);
        $model = VideoGeneration::find($job['id']);
        Storage::disk('local')->assertExists($model->first_path);
        $this->patchJson($url.'/'.$job['id'], $this->fields)->assertConflict();
        $bad = $this->fields;
        $bad['parameters']['seconds'] = 16;
        $this->postJson($url, $bad)->assertUnprocessable();
        $bad = $this->fields;
        $bad['parameters']['unknown'] = 1;
        $this->postJson($url, $bad)->assertUnprocessable();
        $this->actingAs(User::factory()->create());
        $this->getJson($url)->assertNotFound();
        $this->postJson($url.'/'.$job['id'].'/queue')->assertNotFound();
    }

    public function test_worker_claim_is_exclusive_and_upload_is_idempotent(): void
    {
        $this->token('worker', 'gpu');
        $job = $this->queued();
        $headers = ['Authorization' => 'Bearer gpu'];
        $claim = $this->postJson('/api/video/worker/claim', ['worker_id' => 'pc', 'engines' => ['fastvideo_h3' => 1]], $headers)->assertOk()->json();
        $this->postJson('/api/video/worker/claim', ['worker_id' => 'other', 'engines' => ['fastvideo_h3' => 1]], $headers)->assertNoContent();
        $this->postJson('/api/video/worker/claim', ['worker_id' => 'pc', 'engines' => ['fastvideo_h3' => 1]], $headers)->assertJsonPath('id', $job->id);
        $base = '/api/video/worker/'.$job->id;
        $this->getJson($base.'/frames/first', $headers)->assertNotFound();
        $headers['X-Video-Claim'] = $claim['claim_key'];
        $this->get($base.'/frames/first', $headers)->assertOk();
        $this->postJson($base.'/heartbeat', ['status' => 'running', 'prompt_id' => 'comfy-id'], $headers)->assertOk();
        $this->assertNotNull($job->fresh()->started_at);
        $this->postJson($base.'/heartbeat', ['status' => 'claimed'], $headers)->assertConflict();
        $bytes = pack('N', 24).'ftypisom'.str_repeat("\0", 32);
        $file = UploadedFile::fake()->createWithContent('result.mp4', $bytes);
        $this->post($base.'/complete', ['video' => $file, 'sha256' => hash('sha256', $bytes)], $headers)->assertOk()->assertJsonPath('status', 'completed');
        $this->post($base.'/complete', [], $headers)->assertOk();
        $this->assertSame(250000, User::find($job->user_id)->llm_spent);
        $this->assertDatabaseCount('llm_requests', 1);
        $this->assertDatabaseHas('llm_requests', ['id'=>$job->fresh()->llm_request_id, 'cost'=>250000, 'funding'=>'platform']);
        $this->get(route('ai.edit'))->assertOk()->assertSee('Video · Door opens');

        $this->get(route('videos.file', [$this->story, $job]))->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->postJson($base.'/heartbeat', ['status' => 'failed', 'error' => 'late callback'], $headers)->assertJsonPath('status', 'completed');
    }

    public function test_relay_import_is_scoped_idempotent_and_hub_only(): void
    {
        $this->token('relay', 'local');
        $this->token('relay', 'another');
        $metadata = $this->fields + ['engine_version' => 1, 'seed' => 123];
        $data = ['job_id' => (string) Str::uuid(), 'metadata' => json_encode($metadata), 'first' => UploadedFile::fake()->image('first.png')];
        $headers = ['Authorization' => 'Bearer local', 'Accept' => 'application/json'];
        $job = $this->post('/api/video/relay', $data, $headers)->assertCreated()->json();
        $this->post('/api/video/relay', $data, $headers)->assertOk()->assertJsonPath('id', $job['id']);
        $this->assertDatabaseCount('video_generations', 1);
        $this->getJson('/api/video/relay/'.$job['id'], ['Authorization' => 'Bearer another'])->assertNotFound();
        $this->postJson('/api/video/worker/claim', ['worker_id' => 'pc', 'engines' => ['fastvideo_h3' => 1]], $headers)->assertUnauthorized();
        config(['video.mode' => 'relay']);
        $this->post('/api/video/relay', $data, $headers)->assertConflict();
    }

    public function test_local_relay_downloads_verified_result_without_reimporting(): void
    {
        config(['video.mode' => 'relay', 'video.relay_token' => 'local', 'video.hub_url' => 'https://hub.example']);
        $job = $this->queued();
        $this->assertSame('forwarding', $job->status);
        $remoteId = (string) Str::uuid();
        $bytes = pack('N', 24).'ftypisom'.str_repeat("\0", 32);
        Http::fake([
            'https://hub.example/api/video/relay' => Http::response(['id' => $remoteId]),
            'https://hub.example/api/video/relay/'.$remoteId => Http::response(['status' => 'completed', 'progress' => 100, 'output_sha256' => hash('sha256', $bytes)]),
            'https://hub.example/api/video/relay/'.$remoteId.'/file' => Http::response($bytes),
        ]);
        app(VideoRelay::class)->sync($job);
        $this->assertSame('completed', $job->fresh()->status, $job->fresh()->sync_error ?? '');
        Storage::disk('local')->assertExists($job->fresh()->output_path);
        app(VideoRelay::class)->sync($job);
        Http::assertSentCount(3);
    }

    public function test_chat_video_operation_only_prepares_a_draft(): void
    {
        $review = app(CompanionChanges::class)->prepare($this->story, ['changes' => [['scope' => 'video', 'fields' => $this->fields]]]);
        $this->assertSame('video', $review['items'][0]['scope']);
        $this->assertDatabaseCount('video_generations', 0);
        $job = app(VideoDrafts::class)->save($this->story, $review['items'][0]['after']);
        $this->assertSame('draft', $job->status);
    }

    public function test_failed_relay_request_reuses_the_import_identity_and_reports_error(): void
    {
        config(['video.mode' => 'relay', 'video.relay_token' => 'local', 'video.hub_url' => 'https://hub.example']);
        $job = $this->queued();
        $remoteId = (string) Str::uuid();
        $importIds = [];
        Http::fake(function ($request) use (&$importIds, $job, $remoteId) {
            if ($request->method() === 'POST') {
                $importIds[] = str_contains($request->body(), $job->id);

                return count($importIds) === 1
                    ? Http::response(['message' => 'Temporary failure'], 503)
                    : Http::response(['id' => $remoteId]);
            }

            return Http::response(['status' => 'queued', 'progress' => 0]);
        });
        app(VideoRelay::class)->sync($job);
        $this->assertSame('Temporary failure', $job->fresh()->sync_error);
        $this->assertSame('forwarding', $job->fresh()->status);
        app(VideoRelay::class)->sync($job);
        $this->assertSame($remoteId, $job->fresh()->remote_id);
        $this->assertNull($job->fresh()->sync_error);
        $this->assertSame([true, true], $importIds);
        config(['video.mode' => 'hub']);
        app(VideoRelay::class)->sync($job);
        Http::assertSentCount(3);
    }

    public function test_invalid_upload_and_engine_version_do_not_complete_or_claim_job(): void
    {
        $this->token('worker', 'gpu');
        $job = $this->queued();
        $headers = ['Authorization' => 'Bearer gpu', 'Accept' => 'application/json'];
        $this->postJson('/api/video/worker/claim', ['worker_id' => 'pc', 'engines' => ['fastvideo_h3' => 2]], $headers)->assertNoContent();
        $claim = $this->postJson('/api/video/worker/claim', ['worker_id' => 'pc', 'engines' => ['fastvideo_h3' => 1]], $headers)->json();
        $headers['X-Video-Claim'] = $claim['claim_key'];
        $bytes = 'This is not a video';
        $this->post('/api/video/worker/'.$job->id.'/complete', [
            'video' => UploadedFile::fake()->createWithContent('bad.mp4', $bytes), 'sha256' => hash('sha256', $bytes),
        ], $headers)->assertUnprocessable();
        $this->assertSame('claimed', $job->fresh()->status);
        $this->assertNull($job->fresh()->output_path);
    }
}
