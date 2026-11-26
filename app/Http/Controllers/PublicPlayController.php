<?php

namespace App\Http\Controllers;

use App\Models\{Asset, Story, StorySound, VideoGeneration};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};

class PublicPlayController extends Controller
{
    public function enable(Request $request, Story $story)
    {
        abort_unless($story->user_id === $request->user()->id, 404);
        $token = DB::transaction(function () use ($story) {
            $locked = Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
            if (!$locked->public_play_token) {
                $locked->public_play_token = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
                $locked->save();
            }
            return $locked->public_play_token;
        });
        return response()->json(['url' => route('public.play', $token)]);
    }

    public function disable(Request $request, Story $story)
    {
        abort_unless($story->user_id === $request->user()->id, 404);
        $story->update(['public_play_token' => null]);
        return response()->json(['url' => null]);
    }

    public function media(string $token, string $kind, string $id)
    {
        $story = Story::where('public_play_token', $token)->firstOrFail();
        $model = match ($kind) {
            'image' => Asset::class,
            'audio' => StorySound::class,
            'video' => VideoGeneration::class,
        };
        $record = $model::where('story_id', $story->id)->whereKey($id)->firstOrFail();
        if ($kind === 'video') {
            abort_unless($record->status === 'completed', 404);
        }
        $path = $kind === 'video' ? $record->output_path : $record->getRawOriginal('path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $kind === 'video' ? 'video/mp4' : $record->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
