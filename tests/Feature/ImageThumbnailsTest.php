<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\User;
use App\Models\VideoGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageThumbnailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_thumbnails_are_small_cached_private_and_leave_original_intact(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $story = Story::create(['user_id'=>$user->id,'title'=>'Images','premise'=>'']);
        $file = UploadedFile::fake()->image('large.png', 1600, 800);
        $path = $file->store('images', 'local');
        $asset = $story->assets()->create(['name'=>'Large','path'=>$path,'mime'=>'image/png','bytes'=>$file->getSize(),'role'=>'scene']);
        $original = Storage::disk('local')->get($path);
        $url = route('assets.show', [$asset, 'thumbnail'=>1]);
        $this->actingAs($user)->get($url)->assertOk()->assertHeader('Content-Type','image/webp');
        $files = Storage::disk('local')->allFiles('thumbnails');
        $this->assertCount(1,$files);
        $dimensions = getimagesize(Storage::disk('local')->path($files[0]));
        $this->assertSame([640,320],array_slice($dimensions,0,2));
        $this->get($url)->assertOk();
        $this->assertSame($files,Storage::disk('local')->allFiles('thumbnails'));
        $this->assertSame($original,Storage::disk('local')->get($path));
        $this->get(route('assets.show',$asset))->assertHeader('Content-Type','image/png');
        $video=VideoGeneration::create(['story_id'=>$story->id,'user_id'=>$user->id,'name'=>'Clip','prompt'=>'Test','engine'=>'fastvideo_h3','engine_version'=>1,'parameters'=>[],'seed'=>1,'first_path'=>$path]);
        $frame=route('videos.frame',[$story,$video,'first','thumbnail'=>1]);
        $this->get($frame)->assertOk()->assertHeader('Content-Type','image/webp');
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
        $this->get($frame)->assertNotFound();
    }

    public function test_transparent_images_keep_alpha_and_small_images_are_not_upscaled(): void
    {
        Storage::fake('local');
        $image=imagecreatetruecolor(40,20);
        imagealphablending($image,false);
        imagesavealpha($image,true);
        imagefill($image,0,0,imagecolorallocatealpha($image,0,0,0,127));
        ob_start(); imagepng($image); $bytes=ob_get_clean(); imagedestroy($image);
        Storage::disk('local')->put('transparent.png',$bytes);
        app(\App\Services\ImageThumbnails::class)->response('transparent.png');
        $file=Storage::disk('local')->allFiles('thumbnails')[0];
        $thumbnail=imagecreatefromwebp(Storage::disk('local')->path($file));
        $this->assertSame(40,imagesx($thumbnail));
        $this->assertSame(127,imagecolorsforindex($thumbnail,imagecolorat($thumbnail,0,0))['alpha']);
        imagedestroy($thumbnail);
    }
}
