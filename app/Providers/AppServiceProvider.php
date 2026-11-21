<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $lastVideoScan = 0;
        \Illuminate\Support\Facades\Queue::looping(function (\Illuminate\Queue\Events\Looping $event) use (&$lastVideoScan) {
            if (config('video.mode') !== 'relay' || $event->connectionName !== 'database'
                || ! in_array('video', explode(',', $event->queue)) || time() - $lastVideoScan < 60) return;
            $lastVideoScan = time();
            // Recover pre-existing transfers when a worker starts, even without a scheduler.
            \App\Models\VideoGeneration::whereNotIn('status', ['draft', 'completed', 'failed'])
                ->each(fn ($video) => \App\Jobs\SyncVideo::dispatch($video->id), 100);
        });
    }
}
