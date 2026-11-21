<?php

namespace App\Http\Controllers;

use App\Models\Story;
use App\Services\SceneStateImages;
use Illuminate\Http\Request;

class SceneStateImageController extends Controller
{
    public function store(Request $request, Story $story, SceneStateImages $images)
    {
        abort_unless($story->user_id === $request->user()->id, 404);
        $data = $request->validate(['scene_id' => 'required|uuid', 'area_ids' => 'present|array|max:100', 'area_ids.*' => 'uuid|distinct',
            'revision' => 'required|integer', 'name' => 'sometimes|required|string|max:160', 'preview' => 'sometimes|boolean']);
        foreach ($story->pages as $page) {
            foreach ($page->visual_document['visuals'] ?? [] as $scene) {
                if (($scene['kind'] ?? '') !== 'scene' || $scene['id'] !== $data['scene_id']) {
                    continue;
                }
                abort_unless($page->visual_revision === $data['revision'], 409, 'The scene changed. Close this dialog and capture it again.');
                abort_unless(! empty($data['preview']) || ! empty($data['name']), 422, 'Give the image a name.');

                return response()->json($images->capture($story, $page, $scene, $data['area_ids'], ! empty($data['preview']) ? null : $data['name']));
            }
        }
        abort(422, 'Scene not found.');
    }
}
