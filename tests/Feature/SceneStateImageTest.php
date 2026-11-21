<?php

namespace Tests\Feature;

use App\Models\ImageGeneration;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SceneStateImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_capture_previews_then_saves_visible_layers_in_order_with_name_and_description(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user);
        $story = Story::create(['user_id' => $user->id, 'title' => 'House', 'premise' => 'Test']);
        $make = function ($name, $r, $g, $b) use ($story) {
            $image = imagecreatetruecolor(20, 20);
            imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));
            ob_start();
            imagepng($image);
            $bytes = ob_get_clean();
            imagedestroy($image);
            $path = $name.'.png';
            Storage::disk('local')->put($path, $bytes);

            return $story->assets()->create(['name' => $name, 'path' => $path, 'mime' => 'image/png', 'bytes' => strlen($bytes), 'role' => 'scene']);
        };
        $base = $make('Empty chest', 255, 0, 0);
        $patch = $make('Open chest', 0, 255, 0);
        $item = $make('Crystal', 0, 0, 255);
        $area = ['id' => (string) Str::uuid(), 'label' => 'Chest lid', 'action' => 'state_v2', 'state_asset_id' => $patch->id,
            'state_flag' => 'lid', 'overlay' => true, 'overlay_always' => true, 'x' => .3, 'y' => .3, 'width' => .2, 'height' => .2,
            'overlay_bounds' => ['x' => .25, 'y' => .25, 'width' => .5, 'height' => .5]];
        $slot = array_merge($area, ['id' => (string) Str::uuid(), 'label' => 'Crystal socket', 'action' => 'pickup_v2', 'initial_empty' => true,
            'overlay_asset_id' => $item->id, 'state_flag' => null, 'overlay_bounds' => ['x' => .4, 'y' => .4, 'width' => .2, 'height' => .2]]);
        $hidden = array_merge($slot, ['id' => (string) Str::uuid(), 'label' => 'Hidden item', 'overlay' => false]);
        $scene = ['id' => (string) Str::uuid(), 'kind' => 'scene', 'name' => 'Staircase chest', 'asset_id' => $base->id, 'hotspots' => [$area, $slot, $hidden]];
        $page = $story->pages()->create(['position' => 1, 'title' => 'Hall', 'visual_document' => ['visuals' => [$scene]]]);
        $url = route('images.states', $story);
        $request = ['scene_id' => $scene['id'], 'revision' => $page->fresh()->visual_revision, 'area_ids' => [$area['id'], $slot['id']]];
        $preview = $this->postJson($url, $request + ['preview' => true])->assertOk()->json();
        $this->assertStringContainsString('Crystal socket (image shown; Show always on)', $preview['description']);
        $this->assertStringContainsString('Chest lid (changed state)', $preview['description']);
        $this->assertStringNotContainsString('Hidden item', $preview['description']);
        $this->assertSame(0, ImageGeneration::count());
        $image = imagecreatefromstring(base64_decode(explode(',', $preview['preview'], 2)[1]));
        $this->assertSame(0xFF0000, imagecolorat($image, 0, 0));
        $this->assertSame(0x00FF00, imagecolorat($image, 6, 6));
        $this->assertSame(0x0000FF, imagecolorat($image, 10, 10));
        imagedestroy($image);
        $this->postJson($url, $request + ['name' => 'Chest with crystal'])->assertOk();
        $job = ImageGeneration::first();
        $this->assertSame('Chest with crystal', $job->name);
        $this->assertSame($preview['description'], $job->payload['_composition']['description']);
        $this->assertArrayNotHasKey('prompt', $job->payload);
        $this->assertSame([$base->id, $patch->id, $item->id], array_column($job->payload['_composition']['sources'], 'id'));
        $this->getJson(route('videos.index', $story))->assertOk()->assertJsonPath('assets.0.composition.description', $preview['description']);
        $this->postJson($url, array_merge($request, ['revision' => 999, 'name' => 'Stale']))->assertStatus(409);
        $this->postJson($url, array_merge($request, ['area_ids' => [(string) Str::uuid()], 'preview' => true]))->assertUnprocessable();
        // A scene without switches or shown layers can also be captured.
        $scene['hotspots'] = [];
        $page->update(['visual_document' => ['visuals' => [$scene]]]);
        $this->postJson($url, array_merge($request, ['area_ids' => [], 'name' => 'Empty chest']))->assertOk();
        $this->assertSame('scene', ImageGeneration::latest('id')->first()->category);
        $this->actingAs(User::factory()->create())->postJson($url, $request + ['preview' => true])->assertNotFound();
    }
}
