<?php
namespace App\Services;
use App\Models\Story;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
class ChatSceneImage {
 public function prepare(Story $story, ?array $visual, array $model): array {
  if(($visual['kind']??null)!=='scene' || empty($visual['asset_id']))$this->fail('Select a scene with an image before including it in chat.');
  return $this->prepareAsset($story, (int)$visual['asset_id'], $model);
 }
 public function prepareAsset(Story $story, int $assetId, array $model): array {
  if(!in_array('image',$model['architecture']['input_modalities']??[],true))$this->fail('Choose a writing model that supports image input, or deselect the image attachment.');
  $asset=$story->assets()->find($assetId);
  if(!$asset || !in_array($asset->mime,['image/png','image/jpeg','image/webp'],true))$this->fail('The selected image is unavailable or unsupported.');
  $disk=Storage::disk('local');
  if(!$disk->exists($asset->path) || $disk->size($asset->path)>20*1024*1024)$this->fail('The image is missing or exceeds the 20 MB chat attachment limit.');
  $bytes=$disk->get($asset->path);$size=@getimagesizefromstring($bytes);
  if(!$size)$this->fail('The image could not be read.');
  return ['part'=>['type'=>'image_url','image_url'=>['url'=>'data:'.$asset->mime.';base64,'.base64_encode($bytes)]],
   'estimated_tokens'=>max(8192,(int)ceil($size[0]*$size[1]/256))];
 }
 private function fail(string $message): never {throw ValidationException::withMessages(['attachment_ids'=>$message]);}
}
