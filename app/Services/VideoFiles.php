<?php

namespace App\Services;

use App\Models\VideoGeneration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VideoFiles
{
    public function complete(VideoGeneration $video, string $source): void
    {
        $handle = fopen($source, 'rb');
        $header = fread($handle, 32);
        fclose($handle);
        abort_unless(strlen($header) >= 12 && substr($header, 4, 4) === 'ftyp'
            && filesize($source) <= config('video.max_upload_kb') * 1024, 422, 'The output must be an MP4 video within the upload limit.');
        DB::transaction(function () use ($video, $source) {
            $job = VideoGeneration::whereKey($video->id)->lockForUpdate()->firstOrFail();
            if ($job->status === 'completed') {
                return;
            }
            abort_if($job->status === 'failed', 409, 'This job has failed.');
            $path = 'videos/'.$job->id.'/result.mp4';
            $stream = fopen($source, 'rb');
            try {
                abort_unless(Storage::disk('local')->put($path, $stream), 500, 'Could not store video.');
            } finally {
                fclose($stream);
            }
            $job->update(['status' => 'completed', 'progress' => 100, 'output_path' => $path,
                'output_sha256' => hash_file('sha256', $source), 'output_bytes' => filesize($source),
                'completed_at' => now(), 'error' => null, 'sync_error' => null]);
        });
    }
}
