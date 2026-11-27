<?php

namespace App\Http\Controllers;

use App\Models\VideoGeneration;
use App\Services\VideoEngines;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VideoRelayController extends Controller
{
    public function store(Request $request, VideoEngines $engines)
    {
        $data = $request->validate([
            'job_id' => 'required|uuid', 'metadata' => 'required|json',
            'first' => 'required|image|mimes:png,jpg,jpeg,webp|max:20480',
            'last' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:20480',
            'references' => 'sometimes|array|max:9',
            'references.*' => 'required|image|mimes:png,jpg,jpeg,webp|max:20480',
        ]);
        $metadata = json_decode($data['metadata'], true);
        abort_unless(is_array($metadata), 422, 'Invalid job metadata.');
        $fields = $engines->validate($metadata);
        $engine = $engines->all()[$fields['engine']];
        $references = array_values($request->file('references', []));
        abort_if(count($references) > ($engine['max_references'] ?? 0), 422, 'This engine does not support this many reference images.');
        abort_unless(($metadata['reference_count'] ?? 0) === count($references), 422, 'Reference image upload is incomplete.');
        abort_unless(($metadata['engine_version'] ?? null) === $engine['version'], 422, 'Update the engine catalog on both installations.');
        abort_if($request->hasFile('last') && ! $engine['supports_last_frame'], 422, 'This engine does not support a last frame.');
        $requestSeed = validator($metadata, ['seed' => 'required|integer|min:0|max:9007199254740991'])->validate();
        $token = $request->attributes->get('video_token');

        return DB::transaction(function () use ($request, $data, $fields, $requestSeed, $token, $references) {
            DB::table('video_access_tokens')->where('id', $token->id)->lockForUpdate()->first();
            $existing = VideoGeneration::where('origin_token_id', $token->id)->where('origin_job_id', $data['job_id'])->first();
            if ($existing) {
                return response()->json($existing->presentation());
            }
            $job = VideoGeneration::create($fields + $requestSeed + [
                'origin_token_id' => $token->id, 'origin_job_id' => $data['job_id'],
                'status' => 'queued', 'queued_at' => now(),
            ]);
            foreach (['first', 'last'] as $frame) {
                if ($file = $request->file($frame)) {
                    $job->{$frame.'_path'} = $file->storeAs('videos/'.$job->id, $frame.'.'.$file->extension(), 'local');
                    abort_unless($job->{$frame.'_path'}, 500, 'Could not store frame.');
                }
            }
            $paths = [];
            foreach ($references as $index => $file) {
                $path = $file->storeAs('videos/'.$job->id, 'reference_'.$index.'.'.$file->extension(), 'local');
                abort_unless($path, 500, 'Could not store reference image.');
                $paths[] = $path;
            }
            $job->reference_paths = $paths;
            $job->save();

            return response()->json($job->presentation(), 201);
        });
    }

    private function owner(Request $request, VideoGeneration $job): void
    {
        abort_unless($job->origin_token_id === $request->attributes->get('video_token')->id, 404);
    }

    public function show(Request $request, VideoGeneration $video)
    {
        $this->owner($request, $video);

        return response()->json($video->presentation());
    }

    public function file(Request $request, VideoGeneration $video)
    {
        $this->owner($request, $video);
        abort_unless($video->status === 'completed' && $video->output_path, 404);

        return response()->file(Storage::disk('local')->path($video->output_path), ['Content-Type' => 'video/mp4']);
    }
}
