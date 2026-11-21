<?php

namespace App\Console\Commands;

use App\Models\VideoGeneration;
use App\Jobs\SyncVideo;
use Illuminate\Console\Command;

class SyncVideos extends Command
{
    protected $signature = 'videos:sync';

    protected $description = 'Resume pending local video transfers on the video queue';

    public function handle(): int
    {
        if (config('video.mode') !== 'relay') {
            return self::SUCCESS;
        }
        VideoGeneration::whereNotIn('status', ['draft', 'completed', 'failed'])->orderBy('created_at')
            ->each(fn ($job) => SyncVideo::dispatch($job->id), 20);

        return self::SUCCESS;
    }
}
