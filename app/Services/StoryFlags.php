<?php
namespace App\Services;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Models\Story;
class StoryFlags {
 public function validate(array $flags): array {return Validator::make(['flags'=>$flags],['flags'=>'array|max:100','flags.*'=>'array:key,label,initial','flags.*.key'=>['required','string','distinct','regex:/^[a-z][a-z0-9_]{0,63}$/'],'flags.*.label'=>'required|string|max:160','flags.*.initial'=>'required|boolean'])->validate()['flags'];}
 public function assertReferences(Story $story,array $flags):void {
  $keys=array_column($flags,'key');
  $walk=function($node)use(&$walk,$keys){
   if(!is_array($node))return;
   foreach($node as $key=>$value){
    if(in_array($key,['flag_conditions','flag_effects'],true)){
     foreach($value??[] as $flag)if(!in_array($flag['key']??null,$keys,true))throw ValidationException::withMessages(['flags'=>'A scene still uses flag '.($flag['key']??'').'. Remove its conditions/effects before deleting or renaming the flag.']);
    }elseif(is_array($value))$walk($value);
   }
  };
  foreach($story->pages()->get() as $p)$walk($p->visual_document);
 }
}
