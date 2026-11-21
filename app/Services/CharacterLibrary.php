<?php

namespace App\Services;

use App\Models\{Story, StoryCharacter, ImageGeneration};

class CharacterLibrary
{
    public function present(Story $story): array
    {
        $characters = StoryCharacter::where('story_id', $story->id)->orderBy('name')->get();
        $imageIds = $characters->flatMap(fn ($c) => array_column($c->views ?? [], 'image_id'))->filter()->unique();
        $jobs = ImageGeneration::where('story_id', $story->id)->whereIn('id', $imageIds)->get()->keyBy('id');
        $assets = $story->assets()->whereNull('archived_at')->get()->keyBy('id');
        return $characters->map(function ($character) use ($story, $jobs, $assets) {
            $data = $character->only(['id', 'name', 'description', 'revision']);
            $data['views'] = array_map(function ($view) use ($story, $jobs, $assets) {
                $job = $jobs->get($view['image_id'] ?? null);
                $asset = $assets->get($job ? ($job->asset_ids[0] ?? null) : ($view['asset_id'] ?? null));
                return [
                    'label' => $view['label'], 'asset_id' => $asset?->id,
                    'image_id' => $view['image_id'] ?? null, 'url' => $asset ? route('assets.show', $asset) : null,
                    'status' => $asset ? 'ready' : ($job?->status ?? 'unavailable'),
                    'image_url' => $job ? route('images.studio', [$story, $job->story_page_id, 'image' => $job->id, 'open' => 1]) : null,
                ];
            }, $character->views ?? []);
            return $data;
        })->all();
    }

    public function referenceInstructions(Story $story, array $referenceIds): string
    {
        $lines = [];
        foreach ($this->present($story) as $character) {
            $views = [];
            foreach ($character['views'] as $view) {
                $index = array_search($view['asset_id'], $referenceIds, true);
                if ($view['asset_id'] && $index !== false) $views[] = 'reference image '.($index + 1).' ('.$view['label'].')';
            }
            if ($views) $lines[] = $character['name'].': '.implode(', ', $views).'. Appearance: '.($character['description'] ?? 'Match the reference views.');
        }
        return $lines ? "\n\nCharacter identity references:\n".implode("\n", $lines)."\nThese views depict the same character for each named profile, not additional people. Preserve each character's facial features, proportions, distinguishing marks and appearance across views. Follow the scene prompt for pose, action and intentional outfit changes; do not substitute a different character." : '';
    }
}
