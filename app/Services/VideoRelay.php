<?php

namespace App\Services;

use App\Models\VideoGeneration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class VideoRelay
{
    public function sync(VideoGeneration $video): void
    {
        if (config('video.mode') !== 'relay') {
            return;
        }
        Cache::store('file')->lock('video-sync-'.$video->id, 600)->get(function () use ($video) {
            $job = $video->fresh();
            if (in_array($job->status, ['draft', 'completed', 'failed'])) {
                return;
            }
            try {
                $url = rtrim(config('video.hub_url'), '/').'/api/video/relay';
                throw_unless(str_starts_with($url, 'https://') && config('video.relay_token'), new \RuntimeException('Configure an HTTPS VIDEO_HUB_URL and VIDEO_RELAY_TOKEN.'));
                $client = Http::withToken(config('video.relay_token'))->acceptJson()->connectTimeout(15)->timeout(300)->withoutRedirecting();
                if (! $job->remote_id) {
                    $streams = [];
                    try {
                        foreach (['first', 'last'] as $frame) {
                            if (! $job->{$frame.'_path'}) {
                                continue;
                            }
                            $streams[] = $stream = Storage::disk('local')->readStream($job->{$frame.'_path'});
                            throw_unless(is_resource($stream), new \RuntimeException('Queued frame file is missing.'));
                            $client->attach($frame, $stream, basename($job->{$frame.'_path'}));
                        }
                        $response = $client->post($url, ['job_id' => $job->id, 'metadata' => json_encode($job->only(['name', 'prompt', 'engine', 'engine_version', 'parameters', 'seed']))])->throw()->json();
                        validator($response, ['id' => 'required|uuid'])->validate();
                        $job->update(['remote_id' => $response['id']]);
                    } finally {
                        foreach ($streams as $stream) {
                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                        }
                    }
                }
                $client = Http::withToken(config('video.relay_token'))->acceptJson()->connectTimeout(15)->timeout(300)->withoutRedirecting();
                $remote = $client->get($url.'/'.$job->remote_id)->throw()->json();
                validator($remote, ['status' => 'required|in:queued,claimed,submitting,running,uploading,completed,failed', 'progress' => 'required|integer|min:0|max:100'])->validate();
                $fields = array_intersect_key($remote, array_flip(['status', 'progress', 'started_at', 'heartbeat_at', 'error', 'completed_at']));
                if ($remote['status'] === 'completed') {
                    // Keep the local job pending until the verified MP4 is stored locally.
                    $job->update(['status' => 'downloading', 'sync_error' => null]);
                    $temp = tempnam(sys_get_temp_dir(), 'video-');
                    try {
                        $client->withOptions(['sink' => $temp, 'progress' => function ($total, $downloaded) {
                            if (max($total, $downloaded) > config('video.max_upload_kb') * 1024) {
                                throw new \RuntimeException('Video exceeds the upload limit.');
                            }
                        }])->get($url.'/'.$job->remote_id.'/file')->throw();
                        throw_unless(hash_equals($remote['output_sha256'] ?? '', hash_file('sha256', $temp)), new \RuntimeException('Downloaded video checksum mismatch.'));
                        app(VideoFiles::class)->complete($job, $temp);
                    } finally {
                        @unlink($temp);
                    }
                } else {
                    $job->update($fields + ['sync_error' => null]);
                }
            } catch (\Throwable $exception) {
                // Retry transport failures on the next sync, retaining the same remote job identity.
                $message = $exception instanceof \Illuminate\Http\Client\RequestException
                    ? ($exception->response->json('message') ?? 'Video hub request failed (HTTP '.$exception->response->status().').')
                    : $exception->getMessage();
                $job->update(['sync_error' => mb_substr($message, 0, 2000)]);
            }
        });
    }
}
