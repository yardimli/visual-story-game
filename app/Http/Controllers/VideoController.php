<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Story;
use App\Models\VideoGeneration;
use App\Services\VideoDrafts;
use App\Services\VideoEngines;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    private function owner(Request $request, Story $story, ?VideoGeneration $video = null): void
    {
        abort_unless($story->user_id === $request->user()->id, 404);
        if ($video) {
            abort_unless($video->story_id === $story->id, 404);
        }
    }

    public function index(Request $request, Story $story)
    {
        $this->owner($request, $story);

        return response()->json([
            'engines' => app(VideoEngines::class)->all(),
            'mode' => config('video.mode'),
            'assets' => Asset::withGenerationNames($story->assets()->whereNull('archived_at')->latest()->get(), $story->id)
                ->filter(fn ($asset) => str_starts_with($asset->mime, 'image/') && ! str_contains($asset->role ?? '', 'reference'))
                ->map(fn ($asset) => ['id' => $asset->id, 'name' => $asset->name, 'url' => route('assets.show', $asset)])->values(),
            'jobs' => VideoGeneration::where('story_id', $story->id)->latest()->get()->map->presentation(),
        ]);
    }

    public function store(Request $request, Story $story, VideoDrafts $drafts)
    {
        $this->owner($request, $story);

        return response()->json($drafts->save($story, $request->all())->presentation(), 201);
    }

    public function update(Request $request, Story $story, VideoGeneration $video, VideoDrafts $drafts)
    {
        $this->owner($request, $story, $video);

        return DB::transaction(function () use ($request, $story, $video, $drafts) {
            $job = VideoGeneration::whereKey($video->id)->lockForUpdate()->firstOrFail();
            abort_unless($job->status === 'draft', 409, 'Only pending drafts can be edited.');
            $job->update($drafts->validate($story, $request->all()));

            return response()->json($job->presentation());
        });
    }

    public function queue(Request $request, Story $story, VideoGeneration $video, VideoDrafts $drafts)
    {
        $this->owner($request, $story, $video);
        DB::transaction(function () use ($video, $drafts) {
            $drafts->queue(VideoGeneration::whereKey($video->id)->lockForUpdate()->firstOrFail());
        });

        return response()->json($video->fresh()->presentation(), 202);
    }

    public function destroy(Request $request, Story $story, VideoGeneration $video)
    {
        $this->owner($request, $story, $video);
        DB::transaction(function () use ($video) {
            $job = VideoGeneration::whereKey($video->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($job->status, ['draft', 'completed', 'failed']), 409, 'Wait for this job to finish before deleting it.');
            foreach (\App\Models\StoryPage::where('story_id', $job->story_id)->lockForUpdate()->get() as $page) {
                $document = $page->visual_document;
                $changed = false;
                foreach ($document['visuals'] ?? [] as $index => $visual) {
                    if (($visual['intro_video_id'] ?? null) === $job->id) {
                        $document['visuals'][$index]['intro_video_id'] = null;
                        $changed = true;
                    }
                }
                if ($changed) $page->update(['visual_document' => $document, 'visual_revision' => $page->visual_revision + 1]);
            }
            $job->delete();
            Storage::disk('local')->deleteDirectory('videos/'.$job->id);
        });

        return response()->noContent();
    }

    public function frame(Request $request, Story $story, VideoGeneration $video, string $frame)
    {
        $this->owner($request, $story, $video);
        abort_unless(in_array($frame, ['first', 'last']), 404);
        $path = $frame === 'first' ? $video->first_path : $video->last_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return response()->file(Storage::disk('local')->path($path), [
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function file(Request $request, Story $story, VideoGeneration $video)
    {
        $this->owner($request, $story, $video);
        abort_unless($video->status === 'completed' && $video->output_path, 404);

        return response()->file(Storage::disk('local')->path($video->output_path), [
            'Content-Type' => 'video/mp4', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
