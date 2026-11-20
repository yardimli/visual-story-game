<?php

namespace App\Console\Commands;

use App\Models\VideoGeneration;
use App\Services\VideoRelay;
use Illuminate\Console\Command;

class SyncVideos extends Command
{
    protected $signature = 'videos:sync';

    protected $description = 'Forward local video jobs to the hub and download completed videos';

    public function handle(VideoRelay $relay): int
    {
        if (config('video.mode') !== 'relay') {
            return self::SUCCESS;
        }
        VideoGeneration::whereNotIn('status', ['draft', 'completed', 'failed'])->orderBy('created_at')
            ->each(fn ($job) => $relay->sync($job), 20);

        return self::SUCCESS;
    }
}
