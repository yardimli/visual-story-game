<?php
namespace App\Services;
use Illuminate\Support\Facades\Validator;
class PageRichText {
 public static function companion(string $field, ?string $value): array {
  if(!in_array($field,['narrative','interaction_plan'],true) || !preg_match('/<\/?[a-z][^>]*>/i',$value??''))return [$field=>$value,$field.'_delta'=>null];
  $dom=new \DOMDocument();$previous=libxml_use_internal_errors(true);
  try{$dom->loadHTML('<?xml encoding="UTF-8"><body>'.$value.'</body>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
  $ops=[];
  $append=function(string $text,array $attributes=[])use(&$ops){if($text==='')return;$op=['insert'=>$text];if($attributes)$op['attributes']=$attributes;$ops[]=$op;};
  $walk=function($node,array $inline=[],array $block=[])use(&$walk,&$ops,$append){
   if($node instanceof \DOMText){$text=$node->nodeValue;if(trim($text)==='' && str_contains($text,"\n"))return;$append($text,$inline);return;}
   if(!($node instanceof \DOMElement))return;
   $tag=strtolower($node->tagName);
   if(in_array($tag,['script','style','iframe','object','embed','svg','math','template','img','video','audio','input'],true))return;
   if($tag==='br'){$append("\n",$block);return;}
   if(in_array($tag,['strong','b']))$inline['bold']=true;
   if(in_array($tag,['em','i']))$inline['italic']=true;
   if($tag==='u')$inline['underline']=true;
   if(in_array($tag,['s','strike','del']))$inline['strike']=true;
   if($tag==='mark')$inline['background']='#fef08a';
   if(in_array($tag,['h1','h2','h3']))$block=['header'=>(int)substr($tag,1)];
   if($tag==='blockquote')$block=['blockquote'=>true];
   if($tag==='li')$block=['list'=>strtolower($node->parentNode->nodeName)==='ol'?'ordered':'bullet'];
   foreach($node->childNodes as $child)$walk($child,$inline,$block);
   if(in_array($tag,['p','div','h1','h2','h3','blockquote','li'])){
    $last=array_key_last($ops);
    if($last===null || !str_ends_with($ops[$last]['insert'],"\n"))$append("\n",$block);
   }
  };
  $body=$dom->getElementsByTagName('body')->item(0);if($body)$walk($body);
  if(!$ops || !str_ends_with($ops[array_key_last($ops)]['insert'],"\n"))$append("\n");
  return self::normalize([$field.'_delta'=>['ops'=>$ops]]);
 }
 public static function normalize(array $data): array {
  foreach(['narrative','interaction_plan'] as $field){$key=$field.'_delta';if(!isset($data[$key]))continue;
   $delta=is_string($data[$key])?json_decode($data[$key],true):$data[$key];if($delta===null){$data[$key]=null;continue;}
   $valid=Validator::make([$key=>$delta],[$key=>'array:ops',$key.'.ops'=>'required|array|max:10000',$key.'.ops.*'=>'array:insert,attributes',$key.'.ops.*.insert'=>'present|string',
    $key.'.ops.*.attributes'=>'sometimes|array:bold,italic,underline,strike,background,header,list,blockquote',
    $key.'.ops.*.attributes.bold'=>'sometimes|boolean',$key.'.ops.*.attributes.italic'=>'sometimes|boolean',$key.'.ops.*.attributes.underline'=>'sometimes|boolean',$key.'.ops.*.attributes.strike'=>'sometimes|boolean',$key.'.ops.*.attributes.blockquote'=>'sometimes|boolean',
    $key.'.ops.*.attributes.background'=>'sometimes|regex:/^#[0-9a-fA-F]{6}$/',$key.'.ops.*.attributes.header'=>'sometimes|integer|between:1,3',$key.'.ops.*.attributes.list'=>'sometimes|in:ordered,bullet'])->validate();
   $data[$key]=$valid[$key];$data[$field]=preg_replace('/\n$/','',implode('',array_column($delta['ops'],'insert')));
   Validator::make([$field=>$data[$field]],[$field=>'nullable|string|max:10000'])->validate();
  }return $data;
 }
}

