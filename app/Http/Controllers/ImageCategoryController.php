<?php

namespace App\Http\Controllers;

use App\Models\ImageGeneration;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ImageCategoryController extends Controller
{
    public const DEFAULTS = ['scene' => 'Scene', 'inventory' => 'Inventory', 'characters' => 'Characters', 'change_state' => 'Change state'];

    public static function jobCategories(Story $story): array
    {
        $inferred = [];
        foreach (\App\Models\SceneExtraction::where('story_id', $story->id)->get() as $plan) {
            foreach ($plan->outputs ?? [] as $output) {
                foreach ($output['versions'] ?? [] as $id) {
                    $inferred[$id] = in_array($output['type'], ['switch_on', 'switch_off', 'container_preview'])
                        ? 'change_state' : (in_array($output['type'], ['inventory', 'layer']) ? 'inventory' : 'scene');
                }
            }
        }
        return ImageGeneration::where('story_id', $story->id)->get()->mapWithKeys(
            fn ($job) => [$job->id => $job->category ?? $inferred[$job->id] ?? ($job->kind === 'object' ? 'inventory' : 'scene')]
        )->all();
    }

    public static function usedKeys(Story $story): array
    {
        return array_values(array_unique(self::jobCategories($story)));
    }

    public static function categories(Story $story): array
    {
        $categories = $story->image_categories ?? self::DEFAULTS;
        $categories['characters'] ??= 'Characters';
        foreach (self::usedKeys($story) as $key) $categories[$key] ??= self::DEFAULTS[$key] ?? $key;
        return $categories;
    }

    public function save(Request $request, Story $story)
    {
        abort_unless($story->user_id === $request->user()->id, 404);
        $data = $request->validate(['categories' => 'present|array|max:100', 'categories.*' => 'required|string|max:80']);
        foreach (self::usedKeys($story) as $key) {
            abort_unless(isset($data['categories'][$key]), 422, 'This category is in use and cannot be deleted.');
        }
        foreach (array_keys($data['categories']) as $key) {
            abort_unless(preg_match('/^[a-z0-9_-]{1,80}$/', $key), 422, 'Invalid category key.');
        }
        $story->update(['image_categories' => $data['categories']]);
        return response()->json($data);
    }

    public function assign(Request $request, Story $story, ImageGeneration $job)
    {
        abort_unless($story->user_id === $request->user()->id && $job->story_id === $story->id, 404);
        $data = $request->validate(['category' => ['required', Rule::in(array_keys(self::categories($story)))]]);
        $job->update($data);
        return response()->json($data);
    }
}
