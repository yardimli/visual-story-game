<?php

namespace App\Http\Controllers;

use App\Models\{Story, StorySound, VideoGeneration};
use App\Services\GameplayMessages;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlayController extends Controller
{
    public function show(Request $request, Story $story)
    {
        abort_unless($story->user_id === $request->user()->id, 404);
        return $this->renderGame($request, $story);
    }

    public function publicShow(Request $request, string $token)
    {
        return $this->renderGame($request, Story::where('public_play_token', $token)->firstOrFail(), $token);
    }

    private function renderGame(Request $request, Story $story, ?string $token = null)
    {
        $pages = $story->pages()->get()->map(fn ($page) => [
            'id' => $page->id,
            'position' => $page->position,
            'title' => $page->title,
            'visuals' => $page->visual_document['visuals'] ?? [],
        ])->values();
        $items = $story->inventory_document['visuals'] ?? [];

        if ($token) {
            // Public links always start fresh; owner-only test overrides are ignored.
            $first = $pages->first(fn ($page) => collect($page['visuals'])->contains(fn ($visual) => $visual['kind'] === 'scene'));
            $selected = ['page' => $first['id'] ?? null];
        } else {
            $itemIds = collect($items)->pluck('id')->merge(
                $pages->flatMap(fn ($page) => collect($page['visuals'])->where('kind', 'object')->pluck('id'))
            )->unique()->all();
            $selected = $request->validate([
                'skip_first_video' => 'sometimes|boolean',
                'page' => ['required', 'integer', Rule::in($pages->pluck('id')->all())],
                'scene' => 'nullable|string|max:100',
                'inventory' => 'sometimes|array|max:200',
                'inventory.*' => ['string', Rule::in($itemIds)],
                'flags' => 'sometimes|array|max:100',
                'flags.*' => ['string', Rule::in(array_column($story->game_flags ?? [], 'key'))],
            ]);
        }

        $page = $pages->firstWhere('id', (int) $selected['page']);
        abort_unless($page, 422, 'This story has no playable scenes yet.');
        $scene = collect($page['visuals'])->first(fn ($visual) => $visual['kind'] === 'scene'
            && (!isset($selected['scene']) || $visual['id'] === $selected['scene']));
        abort_unless($scene, 422, 'Choose a scene on the selected page.');

        $game = [
            'videos' => VideoGeneration::where('story_id', $story->id)->where('status', 'completed')->get()->map(fn ($video) => [
                'id' => $video->id,
                'name' => $video->name,
                'url' => $token ? route('public.play.media', [$token, 'video', $video->id]) : route('videos.file', [$story, $video]),
            ]),
            'messages' => GameplayMessages::forStory($story),
            'pages' => $pages,
            'inventory' => $items,
            'assets' => $story->assets()->get(['id'])->map(fn ($asset) => [
                'id' => $asset->id,
                'url' => $token ? route('public.play.media', [$token, 'image', $asset->id]) : route('assets.show', $asset),
            ]),
            'flags' => $story->game_flags ?? [],
            'uiTheme' => app(\App\Services\GuiThemes::class)->forStory($story),
            'cursorTooltips' => $story->cursor_tooltips ?? [],
            'unavailableText' => $story->unavailable_text ?? 'You cannot do that yet.',
            'unavailableTimeout' => (int) ($story->unavailable_timeout_ms ?? 1000),
            'audioSettings' => $story->audio_settings ?? [],
            'sounds' => StorySound::where('story_id', $story->id)->latest()->orderByDesc('id')->get()->map(fn ($sound) => [
                'id' => $sound->id,
                'name' => $sound->name,
                'url' => $token ? route('public.play.media', [$token, 'audio', $sound->id]) : $sound->url,
                'source_text' => $sound->source_text,
            ]),
            'start' => [
                'skipFirstVideo' => $token ? false : $request->boolean('skip_first_video'),
                'pageId' => $page['id'],
                'sceneId' => $scene['id'],
                'inventory' => $selected['inventory'] ?? [],
                'flags' => $selected['flags'] ?? [],
            ],
        ];

        return response()->view('game.play', ['story' => $story, 'game' => $game, 'publicPlay' => (bool) $token])
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
