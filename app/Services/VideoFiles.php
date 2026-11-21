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
            // Imported hub jobs have no account; charge the originating installation only.
            // The locked job and completion guard prevent duplicate charges on retries.
            if ($job->user_id && ! $job->llm_request_id) {
                $user = \App\Models\User::whereKey($job->user_id)->lockForUpdate()->firstOrFail();
                $seconds = (int) $job->parameters['seconds'];
                $rate = (int) config('video.cost_per_second_micro_usd', 50000);
                $cost = $seconds * $rate;
                $usage = \App\Models\LlmRequest::create([
                    'user_id' => $user->id, 'story_id' => $job->story_id,
                    'request_key' => $job->id, 'model' => $job->engine,
                    'funding' => 'platform', 'status' => 'completed', 'reserved' => 0, 'cost' => $cost,
                    'pricing_snapshot' => ['per_second_micro_usd' => $rate],
                    'request_content' => ['name' => $job->name, 'prompt' => $job->prompt, 'parameters' => $job->parameters],
                    'response_content' => ['video_id' => $job->id, 'seconds' => $seconds, 'cost_micro_usd' => $cost],
                ]);
                $user->increment('llm_spent', $cost);
                $job->llm_request_id = $usage->id;
            }
            $job->update(['status' => 'completed', 'progress' => 100, 'output_path' => $path,
                'output_sha256' => hash_file('sha256', $source), 'output_bytes' => filesize($source),
                'completed_at' => now(), 'error' => null, 'sync_error' => null]);
        });
    }
}
