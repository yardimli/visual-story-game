<?php
namespace App\Services;
use App\Models\{Story,StorySound};
use Illuminate\Support\Facades\{Cache,Http,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class StoryAudio {
 public function search(string $query,int $page,?float $maxDuration=3):array {
  return Cache::store('file')->rememberForever('freesound.search.v2.'.hash('sha256',$query.'|'.$page.'|'.($maxDuration??'all')),function()use($query,$page,$maxDuration){
   $key=config('services.freesound.key');if(!$key)throw ValidationException::withMessages(['audio'=>'FREESOUND_API_KEY is not configured.']);
   $response=Http::withHeaders(['Authorization'=>'Token '.$key])->timeout(20)->get('https://freesound.org/apiv2/search/',['filter'=>$maxDuration===null?'':'duration:[0 TO '.$maxDuration.']','query'=>$query,'page'=>$page,'page_size'=>20,'fields'=>'id,name,username,license,url,duration,previews']);
   if(!$response->successful())throw ValidationException::withMessages(['audio'=>'Freesound search failed (HTTP '.$response->status().'). Try again later.']);
   $data=$response->json();$results=[];
   foreach($data['results']??[] as $r){if($maxDuration!==null && (!is_numeric($r['duration']??null) || (float)$r['duration']>$maxDuration))continue;$url=$r['previews']['preview-hq-mp3']??$r['previews']['preview-lq-mp3']??null;if(!$url)continue;
    $sound=['id'=>$r['id'],'name'=>$r['name'],'creator'=>$r['username'],'license'=>$r['license'],'source_url'=>$r['url'],'duration'=>$r['duration'],'preview_url'=>$url];
    Cache::store('file')->forever('freesound.sound.'.$r['id'],$sound);$results[]=$sound;
   }
   return ['results'=>$results,'count'=>$data['count']??0,'page'=>$page,'has_more'=>!empty($data['next'])];
  });
 }
 public function download(string $url,string $provider):array {
  $host=strtolower(parse_url($url,PHP_URL_HOST)??'');
  $allowed=$provider==='freesound'?($host==='cdn.freesound.org'||$host==='freesound.org'):($host==='fal.media'||str_ends_with($host,'.fal.media'));
  if(parse_url($url,PHP_URL_SCHEME)!=='https'||!$allowed)throw new \RuntimeException('The sound provider returned an unsupported download URL.');
  $response=Http::timeout(60)->withOptions(['allow_redirects'=>false,'progress'=>function($total,$downloaded){if($total>52428800||$downloaded>52428800)throw new \RuntimeException('Sound exceeds the 50 MB limit.');}])->get($url);
  if(!$response->successful())throw new \RuntimeException('Sound download failed (HTTP '.$response->status().').');
  $bytes=$response->body();$mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
  if(!in_array($mime,['audio/mpeg','audio/wav','audio/x-wav','audio/vnd.wave','audio/ogg','application/ogg','audio/flac','audio/x-flac','audio/mp4']))throw new \RuntimeException('The provider did not return a supported audio file.');
  return [$bytes,$mime];
 }
 public function save(Story $story,string $url,array $attributes):StorySound {
  [$bytes,$mime]=$this->download($url,$attributes['provider']);$path='stories/'.$story->id.'/audio/'.Str::uuid();
  if(!Storage::disk('local')->put($path,$bytes))throw new \RuntimeException('Could not save audio.');
  try{return StorySound::create($attributes+['story_id'=>$story->id,'path'=>$path,'mime'=>$mime]);}catch(\Throwable $e){Storage::disk('local')->delete($path);throw $e;}
 }
}
