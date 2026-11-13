<?php
namespace App\Services;
use App\Models\{Story,AudioGeneration,StorySound};
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
class AudioDrafts {
 public static function text($text):string {return trim(preg_replace('/\s+/u',' ',preg_replace('/<[^>]*>/',' ',(string)$text)));}
 public function validate(array $fields):array {return Validator::make($fields,['name'=>'required|string|max:160','prompt'=>'required|string|max:5000','source_text'=>'nullable|string|max:10000','category'=>'required|string|max:80','model'=>'nullable|string|in:'.implode(',',array_merge(SpeechCatalog::MODELS,['fal-ai/stable-audio','cassetteai/sound-effects-generator'])),'seconds'=>'sometimes|integer|min:0|max:47','settings'=>'sometimes|array:voice,instructions,temperature,language','settings.voice'=>'nullable|string|max:200','settings.instructions'=>'nullable|string|max:1000','settings.temperature'=>'nullable|numeric|min:0|max:2','settings.language'=>'nullable|string|max:10'])->validate();}
 public function missing(Story $story):array {
  $known=StorySound::where('story_id',$story->id)->pluck('source_text')->merge(AudioGeneration::where('story_id',$story->id)->whereIn('status',['draft','queued','submitting','running'])->pluck('source_text'))->filter()->map(fn($t)=>self::text($t))->flip()->all();$items=[];
  $add=function($name,$text,$category)use(&$known,&$items){$text=self::text($text);if(!$text||isset($known[$text]))return;$known[$text]=true;$items[]=['name'=>mb_substr($name,0,160),'prompt'=>$text,'source_text'=>$text,'category'=>$category];};
  $walk=function($node,$name,$category)use(&$walk,$add){if(!is_array($node))return;foreach($node as $k=>$v){if(is_array($v))$walk($v,$name,$category);elseif(in_array($k,['description','state_description','interaction_text','fallback_text','unavailable_text','response'])&&is_string($v))$add($name.' / '.str_replace('_',' ',$k),$v,$category);}};
  foreach($story->pages as $page){$add($page->title.' / Narrative',$page->narrative,'scenes');foreach($page->visual_document['visuals']??[] as $visual){$add($page->title.' / '.$visual['name'],$visual['description']??'','scenes');foreach($visual['hotspots']??[] as $area)$walk($area,$page->title.' / '.$visual['name'].' / '.($area['label']??'Area'),'click_areas');}}
  foreach($story->inventory_document['visuals']??[] as $item)$walk($item,'Inventory / '.$item['name'],'inventory');return $items;
 }
 public function save(Story $story,array $fields):AudioGeneration {
  $fields=$this->validate($fields);$fields['source_text']=self::text($fields['source_text']??$fields['prompt']);
  $existing=AudioGeneration::where('story_id',$story->id)->whereIn('status',['draft','queued','running','submitting'])->where('source_text',$fields['source_text'])->first();if($existing)return $existing;
  $key=['scenes'=>'scene','click_areas'=>'area','inventory'=>'inventory'][$fields['category']]??null;$preset=$story->audio_settings['voices'][$key]??[];
  $fields['model']=$fields['model']??($key?($preset['model']??SpeechCatalog::MODELS[0]):'fal-ai/stable-audio');if(!$fields['model'])$fields['model']=SpeechCatalog::MODELS[0];
  $fields['settings']=array_merge(array_intersect_key($preset,array_flip(['voice','instructions','temperature','language'])),$fields['settings']??[]);
  return AudioGeneration::create($fields+['story_id'=>$story->id,'request_key'=>(string)Str::uuid(),'status'=>'draft','seconds'=>3]);
 }
}
