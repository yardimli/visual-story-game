<?php

namespace App\Jobs;

use App\Models\VideoGeneration;
use App\Services\VideoRelay;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SyncVideo implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Polling is expected to take many attempts, even when the worker uses --tries=1.
    public $tries = 0;
    public $timeout = 210;

    public function __construct(public string $videoId)
    {
        $this->onConnection('database')->onQueue('video')->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->videoId;
    }

    public function uniqueVia()
    {
        return Cache::store('file');
    }

    public function handle(VideoRelay $relay): void
    {
        if (config('video.mode') !== 'relay') return;
        $video = VideoGeneration::find($this->videoId);
        if (! $video || in_array($video->status, ['draft', 'completed', 'failed'])) return;

        $relay->sync($video);
        $video = $video->fresh();
        if ($video && ! in_array($video->status, ['draft', 'completed', 'failed'])) {
            // Release the worker for image/audio work while the GPU renders remotely.
            $this->release($video->sync_error ? 60 : 15);
        }
    }
}
