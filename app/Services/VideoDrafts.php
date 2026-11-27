<?php

namespace App\Services;

use App\Models\Story;
use App\Models\VideoGeneration;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VideoDrafts
{
    public function validate(Story $story, array $data): array
    {
        $fields = app(VideoEngines::class)->validate($data);
        $image = Rule::exists('assets', 'id')->where('story_id', $story->id);
        $frames = Validator::make($data, [
            'first_asset_id' => ['required', 'integer', $image],
            'last_asset_id' => ['nullable', 'integer', $image],
            'reference_asset_ids' => ['nullable', 'array', 'max:'.(app(VideoEngines::class)->all()[$fields['engine']]['max_references'] ?? 0)],
            'reference_asset_ids.*' => ['required', 'integer', 'distinct', $image],
        ])->validate();
        $frames['reference_asset_ids'] = array_values($frames['reference_asset_ids'] ?? []);
        foreach (array_filter([$frames['first_asset_id'], $frames['last_asset_id'] ?? null, ...$frames['reference_asset_ids']]) as $id) {
            $asset = $story->assets()->findOrFail($id);
            abort_unless(in_array($asset->mime, ['image/png', 'image/jpeg', 'image/webp']), 422, 'Choose PNG, JPEG or WebP frames.');
        }
        abort_if(! empty($frames['last_asset_id']) && ! app(VideoEngines::class)->all()[$fields['engine']]['supports_last_frame'], 422, 'This engine does not support a last frame.');

        return $fields + $frames;
    }

    public function save(Story $story, array $data): VideoGeneration
    {
        return VideoGeneration::create($this->validate($story, $data) + [
            'story_id' => $story->id, 'user_id' => $story->user_id,
            'seed' => random_int(0, 9007199254740991), 'status' => 'draft',
        ]);
    }

    public function queue(VideoGeneration $job): void
    {
        abort_unless(in_array(config('video.mode'), ['hub', 'relay']), 422, 'Set VIDEO_MODE to hub or relay.');
        if ($job->status !== 'draft') {
            return;
        }
        $story = Story::findOrFail($job->story_id);
        $this->validate($story, $job->toArray());
        foreach (['first', 'last'] as $frame) {
            if (! $job->{$frame.'_asset_id'}) {
                continue;
            }
            $asset = $story->assets()->findOrFail($job->{$frame.'_asset_id'});
            $path = 'videos/'.$job->id.'/'.$frame.'.'.['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$asset->mime];
            abort_unless(Storage::disk('local')->copy($asset->path, $path), 422, 'Could not snapshot the frame image.');
            $job->{$frame.'_path'} = $path;
        }
        $references = [];
        foreach ($job->reference_asset_ids ?? [] as $index => $id) {
            $asset = $story->assets()->findOrFail($id);
            $path = 'videos/'.$job->id.'/reference_'.$index.'.'.['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$asset->mime];
            abort_unless(Storage::disk('local')->copy($asset->path, $path), 422, 'Could not snapshot the reference image.');
            $references[] = $path;
        }
        $job->reference_paths = $references;
        $job->status = config('video.mode') === 'relay' ? 'forwarding' : 'queued';
        $job->queued_at = now();
        $job->save();
        if (config('video.mode') === 'relay') {
            \App\Jobs\SyncVideo::dispatch($job->id);
        }
    }
}
