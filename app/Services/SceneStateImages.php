<?php

namespace App\Services;

use App\Models\ImageGeneration;
use App\Models\Story;
use App\Models\StoryPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SceneStateImages
{
    public function capture(Story $story, StoryPage $page, array $scene, array $visibleAreas, ?string $name = null): array
    {
        $areas = collect($scene['hotspots'] ?? [])->keyBy('id');
        abort_unless(count(array_diff($visibleAreas, $areas->keys()->all())) === 0, 422, 'A scene area no longer exists.');
        $flags = collect($story->game_flags ?? [])->mapWithKeys(fn ($f) => [$f['key'] => (bool) ($f['initial'] ?? false)])->all();
        foreach ($areas as $h) {
            if (! empty($h['state_flag'])) {
                $flags[$h['state_flag']] = ! empty($h['overlay']) && ($h['overlay_always'] ?? true) !== false;
            }
        }
        $layers = [['id' => $scene['asset_id'] ?? null, 'bounds' => [0, 0, 1, 1], 'fill' => true, 'anchor' => 0.5]];
        $shown = [];
        foreach ($areas as $h) {
            if (! in_array($h['id'], $visibleAreas) || empty($h['overlay'])) {
                continue;
            }
            if (collect($h['visible_when'] ?? [])->contains(fn ($c) => (bool) ($flags[$c['key']] ?? false) !== $c['value'])) {
                continue;
            }
            if (! empty($h['disabled_when']) && collect($h['disabled_when'])->every(fn ($c) => (bool) ($flags[$c['key']] ?? false) === $c['value'])) {
                continue;
            }
            if (! empty($h['container_parent_id'])) {
                $parent = $areas->get($h['container_parent_id']);
                if (empty($parent['is_container']) || empty($parent['overlay']) || ($parent['overlay_always'] ?? true) === false) {
                    continue;
                }
            }
            $changed = ($h['action'] ?? '') === 'state_v2';
            if ($changed) {
                $id = ($h['overlay_always'] ?? true) !== false ? ($h['state_asset_id'] ?? null) : (! empty($h['is_container']) ? ($h['overlay_asset_id'] ?? null) : null);
            } else {
                // Initial-empty describes gameplay, not the currently shown editor image.
                $id = $h['overlay_asset_id'] ?? collect($story->inventory_document['visuals'] ?? [])->firstWhere('id', $h['object_id'] ?? null)['asset_id'] ?? null;
            }
            if (! $id) {
                continue;
            }
            $b = $h['overlay_bounds'] ?? $h['image_bounds'] ?? $h;
            $scale = ($h['image_size'] ?? 100) / 100;
            $anchor = ['top' => 0, 'middle' => 0.5, 'bottom' => 1][$h['overlay_alignment'] ?? 'middle'] ?? 0.5;
            $layers[] = ['id' => $id, 'bounds' => [$b['x'] + $b['width'] * (1 - $scale) / 2, $b['y'] + $b['height'] * (1 - $scale) * $anchor, $b['width'] * $scale, $b['height'] * $scale], 'fill' => isset($h['overlay_bounds']), 'anchor' => $anchor];
            $shown[] = ['id' => $h['id'], 'name' => $h['label'] ?? 'Unnamed area', 'show_image' => true,
                'show_always' => ($h['overlay_always'] ?? true) !== false,
                'state' => $changed ? (($h['overlay_always'] ?? true) !== false ? 'changed state' : 'original state') : 'image shown'];
        }
        $assets = $story->assets()->whereIn('id', array_column($layers, 'id'))->get()->keyBy('id');
        abort_unless($assets->has($scene['asset_id'] ?? null), 422, 'The scene needs a source image.');
        foreach ($layers as $layer) {
            abort_unless($assets->has($layer['id']) && Storage::disk('local')->exists($assets[$layer['id']]->path), 422, 'A source image is missing.');
        }
        $base = $assets[$layers[0]['id']];
        $canvas = $this->decode(Storage::disk('local')->get($base->path));
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);
        try {
            foreach (array_slice($layers, 1) as $layer) {
                $image = $this->decode(Storage::disk('local')->get($assets[$layer['id']]->path));
                try {
                    [$x, $y, $w, $h] = $layer['bounds'];
                    $x *= $width;
                    $w *= $width;
                    $y *= $height;
                    $h *= $height;
                    if (! $layer['fill']) {
                        $ratio = min($w / imagesx($image), $h / imagesy($image));
                        $newW = imagesx($image) * $ratio;
                        $newH = imagesy($image) * $ratio;
                        $x += ($w - $newW) / 2;
                        $y += ($h - $newH) * $layer['anchor'];
                        $w = $newW;
                        $h = $newH;
                    }
                    imagecopyresampled($canvas, $image, (int) round($x), (int) round($y), 0, 0, max(1, (int) round($w)), max(1, (int) round($h)), imagesx($image), imagesy($image));
                } finally {
                    imagedestroy($image);
                }
            }
            ob_start();
            imagepng($canvas);
            $bytes = ob_get_clean();
        } finally {
            imagedestroy($canvas);
        }
        $description = 'Scene: '.$scene['name'].'. Background: '.$base->name.'. ';
        $description .= $shown ? 'Visible layers: '.collect($shown)->map(fn ($h) => $h['name'].' ('.$h['state'].($h['state'] === 'image shown' ? ($h['show_always'] ? '; Show always on' : '; Show always off') : '').')')->implode('; ').'.' : 'No area images shown.';
        $metadata = ['scene_id' => $scene['id'], 'description' => $description, 'areas' => $shown,
            'sources' => collect($layers)->map(fn ($l) => ['id' => $l['id'], 'name' => $assets[$l['id']]->name])->values()->all()];
        if ($name === null) {
            return ['preview' => 'data:image/png;base64,'.base64_encode($bytes), 'name' => Str::limit($scene['name'].' — scene image', 150, ''), 'description' => $description];
        }
        $path = 'stories/'.$story->id.'/state-images/'.Str::uuid().'.png';
        Storage::disk('local')->put($path, $bytes);
        try {
            $job = DB::transaction(function () use ($story, $page, $scene, $name, $metadata, $path, $bytes) {
                $asset = $story->assets()->create(['name' => $name, 'path' => $path, 'mime' => 'image/png', 'bytes' => strlen($bytes), 'role' => 'state_composite']);

                return ImageGeneration::create(['user_id' => $story->user_id, 'story_id' => $story->id, 'story_page_id' => $page->id,
                    'visual_id' => $scene['id'], 'name' => $name, 'kind' => 'scene', 'category' => collect($metadata['areas'])->contains('state', 'changed state') ? 'change_state' : 'scene', 'request_key' => (string) Str::uuid(),
                    'status' => 'completed', 'payload' => ['_composition' => $metadata], 'references' => $metadata['sources'], 'pricing' => [], 'asset_ids' => [$asset->id]]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return ['id' => $job->id, 'created' => true, 'description' => $description];
    }

    private function decode(string $bytes): \GdImage
    {
        $image = @imagecreatefromstring($bytes);
        abort_unless($image, 422, 'A source image could not be decoded.');
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $image;
    }
}
