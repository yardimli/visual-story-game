<?php
namespace App\Services;
use Illuminate\Support\Facades\{Cache,Http};
use Illuminate\Validation\ValidationException;
class SpeechCatalog {
 const MODELS=['heygen/heygen-voice-1-turbo','elevenlabs/eleven-v4','google/gemini-3.8-flash-tts'];
 public function models():array {
  return Cache::remember('speech.catalog.v1',3600,function(){
   $r=Http::timeout(25)->get('https://openrouter.ai/api/v1/models',['output_modalities'=>'speech']);
   if(!$r->successful())throw ValidationException::withMessages(['model'=>'Speech catalog unavailable. Try again.']);
   return collect($r->json('data')??[])->whereIn('id',self::MODELS)->map(fn($m)=>array_intersect_key($m,array_flip(['id','name','pricing','supported_voices'])))->values()->all();
  });
 }
 public function model(string $id):array {return collect($this->models())->firstWhere('id',$id)??throw ValidationException::withMessages(['model'=>'This speech model is unavailable.']);}
 public function reservation(array $model,string $text,string $instructions=''):int {
  $p=$model['pricing'];if(!isset($p['prompt'],$p['completion'])||!is_numeric($p['prompt'])||!is_numeric($p['completion'])||$p['prompt']<0||$p['completion']<0)throw ValidationException::withMessages(['model'=>'Speech pricing is unavailable.']);
  $characters=mb_strlen($text);
  // Character-priced voices have no output charge. Gemini has token-priced output.
  return (int)ceil(($characters*(float)$p['prompt']+($model['id']==='google/gemini-3.8-flash-tts'?(strlen($instructions)*(float)$p['prompt']+16384*(float)$p['completion']):0))*1000000);
 }
 public static function wav(string $pcm,int $rate):string {return 'RIFF'.pack('V',strlen($pcm)+36).'WAVEfmt '.pack('VvvVVvv',16,1,1,$rate,$rate*2,2,16).'data'.pack('V',strlen($pcm)).$pcm;}
}
