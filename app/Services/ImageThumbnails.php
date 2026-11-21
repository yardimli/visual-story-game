<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ImageThumbnails
{
    public function response(string $source)
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($source), 404);
        $key = hash('sha256', 'v1:640:'.$source.':'.$disk->lastModified($source).':'.$disk->size($source));
        $path = 'thumbnails/'.substr($key, 0, 2).'/'.$key.'.webp';
        if (! $disk->exists($path)) {
            Cache::store('file')->lock('thumbnail-'.$key, 60)->block(30, function () use ($disk, $source, $path) {
                if ($disk->exists($path)) {
                    return;
                }
                $image = @imagecreatefromstring($disk->get($source));
                abort_unless($image, 422, 'This image cannot be thumbnailed.');
                $scale = min(1, 640 / max(imagesx($image), imagesy($image)));
                $width = max(1, (int) round(imagesx($image) * $scale));
                $height = max(1, (int) round(imagesy($image) * $scale));
                $thumbnail = imagecreatetruecolor($width, $height);
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
                imagefill($thumbnail, 0, 0, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
                imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
                ob_start();
                imagewebp($thumbnail, null, 78);
                $bytes = ob_get_clean();
                imagedestroy($thumbnail);
                imagedestroy($image);
                abort_unless($bytes && $disk->put($path, $bytes), 500, 'Could not save thumbnail.');
            });
        }

        return response()->file($disk->path($path), [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
