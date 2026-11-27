<?php

namespace App\Http\Controllers;

use App\Models\VideoGeneration;
use App\Services\VideoFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoWorkerController extends Controller
{
    public function claim(Request $request)
    {
        $data = $request->validate(['worker_id' => 'required|string|max:100', 'engines' => 'required|array|min:1', 'engines.*' => 'integer|min:1']);
        $token = $request->attributes->get('video_token');

        return DB::transaction(function () use ($data, $token) {
            // Serialize claims for this credential, including retries after a lost response.
            DB::table('video_access_tokens')->where('id', $token->id)->lockForUpdate()->first();
            $job = VideoGeneration::where('worker_token_id', $token->id)->where('worker_id', $data['worker_id'])
                ->whereIn('status', ['claimed', 'submitting', 'running', 'uploading'])->lockForUpdate()->first();
            if (! $job) {
                $job = VideoGeneration::where('status', 'queued')->where(function ($query) use ($data) {
                    foreach ($data['engines'] as $id => $version) {
                        $query->orWhere(fn ($q) => $q->where('engine', $id)->where('engine_version', $version));
                    }
                })->orderBy('queued_at')->lockForUpdate()->first();
                if (! $job) {
                    return response()->noContent();
                }
                $job->update(['status' => 'claimed', 'worker_token_id' => $token->id,
                    'worker_id' => $data['worker_id'], 'claim_key' => (string) Str::uuid(), 'heartbeat_at' => now()]);
            }

            return response()->json($job->presentation() + ['claim_key' => $job->claim_key]);
        });
    }

    private function authorizeJob(Request $request, VideoGeneration $job): void
    {
        abort_unless($job->worker_token_id === $request->attributes->get('video_token')->id
            && $job->claim_key && hash_equals($job->claim_key, $request->header('X-Video-Claim', '')), 404);
    }

    public function frame(Request $request, VideoGeneration $video, string $frame)
    {
        $this->authorizeJob($request, $video);
        $path = in_array($frame, ['first', 'last']) ? $video->{$frame.'_path'} : null;
        if (preg_match('/^reference_([0-8])$/', $frame, $matches)) {
            $path = ($video->reference_paths ?? [])[(int) $matches[1]] ?? null;
        }
        abort_unless($path, 404);
        return response()->file(Storage::disk('local')->path($path));
    }

    public function heartbeat(Request $request, VideoGeneration $video)
    {
        $this->authorizeJob($request, $video);
        $data = $request->validate([
            'status' => 'required|in:claimed,submitting,running,uploading,failed',
            'progress' => 'sometimes|integer|min:0|max:99', 'prompt_id' => 'nullable|string|max:100',
            'started_at' => 'nullable|date',
            'error' => 'nullable|string|max:4000',
        ]);

        return DB::transaction(function () use ($video, $data) {
            $job = VideoGeneration::whereKey($video->id)->lockForUpdate()->firstOrFail();
            if (in_array($job->status, ['completed', 'failed'])) {
                return response()->json($job->presentation());
            }
            $ranks = ['claimed' => 0, 'submitting' => 1, 'running' => 2, 'uploading' => 3, 'failed' => 4];
            abort_if($ranks[$data['status']] < $ranks[$job->status], 409, 'Job state cannot move backwards.');
            abort_if($job->prompt_id && ! empty($data['prompt_id']) && $job->prompt_id !== $data['prompt_id'], 409, 'This job already has a ComfyUI request.');
            if ($job->started_at) {
                unset($data['started_at']);
            } elseif (in_array($data['status'], ['running', 'uploading'])) {
                $data['started_at'] ??= now();
            }
            $job->update(array_filter($data, fn ($v) => $v !== null) + ['heartbeat_at' => now()]);

            return response()->json($job->presentation());
        });
    }

    public function complete(Request $request, VideoGeneration $video, VideoFiles $files)
    {
        $this->authorizeJob($request, $video);
        if ($video->status === 'completed') {
            return response()->json($video->presentation());
        }
        abort_if($video->status === 'failed', 409, 'This job has failed.');
        $request->validate(['video' => 'required|file|max:'.config('video.max_upload_kb'), 'sha256' => 'required|string|size:64']);
        $path = $request->file('video')->getRealPath();
        abort_unless(hash_equals($request->sha256, hash_file('sha256', $path)), 422, 'Video checksum mismatch.');
        $files->complete($video, $path);

        return response()->json($video->fresh()->presentation());
    }
}
