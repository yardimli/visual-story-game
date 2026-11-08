<?php
namespace App\Services;
class AiCallContents {
 public static function clean(mixed $value): mixed {
  if(is_array($value)) {foreach($value as $key=>$item) $value[$key]=preg_match('/^(authorization|api_key|openrouter_key|password|b64_json)$/i',(string)$key)?'[redacted]':self::clean($item);return $value;}
  if(is_string($value))return preg_replace('/sk-(?:or-)?[a-zA-Z0-9_-]+|Bearer\s+[^\s"<>]+|data:image\/[^;]+;base64,[a-zA-Z0-9+\/=]+/i','[redacted]',$value);
  return $value;
 }
}
