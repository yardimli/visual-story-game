<?php
namespace App\Services;
use Illuminate\Validation\ValidationException;

/** Catalog-based accounting with upstream inference details for zero-priced BYOK responses. */
class ImagePricing {
 public function quote(array $lines, array $settings, string $prompt, array $references): int {
  if(isset($lines['endpoints'])) return max(array_map(fn($e)=>$this->quote($e['pricing']??[],$settings,$prompt,$references),$lines['endpoints']));
  $rates=$this->rates($lines,$settings,true);
  if($rates===null) throw ValidationException::withMessages(['model'=>'Image pricing is unavailable for these settings. Choose another model or setting.']);
  // Fixed reservation estimates, not provider-enforced token limits.
  $pixels=$this->outputMegapixels($settings);
  $input=strlen($prompt)+1024+count($references)*2048;
  $output=2048;
  $cost=$this->calculate($rates,['prompt_tokens'=>$input,'completion_tokens'=>$output,'prompt_tokens_details'=>['image_tokens'=>count($references)*2048]],$references,1,$pixels);
  if($cost===null) throw ValidationException::withMessages(['model'=>'Image pricing could not be estimated. Choose another model.']);
  return (int)ceil($cost*1.05);
 }
 public function cost(array $lines,array $settings,array $usage,array $references,array $outputs, ?string $provider=null): ?int {
  if(($upstream=$this->upstreamCost($usage))!==null)return $upstream;
  if(isset($lines['endpoints'])) {
   $endpoints=$lines['endpoints'];
   if($provider) { $matched=array_filter($endpoints,fn($e)=>in_array(strtolower($provider),array_map('strtolower',array_filter([$e['provider_tag']??null,$e['provider_name']??null,$e['provider_slug']??null])),true));if($matched)$endpoints=$matched; }
   $costs=array_map(fn($e)=>$this->cost($e['pricing']??[],$settings,$usage,$references,$outputs),$endpoints);
   return $costs && !in_array(null,$costs,true) && count(array_unique($costs))===1 ? array_values($costs)[0] : null;
  }
  $rates=$this->rates($lines,$settings,false);if($rates===null)return null;
  $mp=0;foreach($outputs as $o)$mp+=($o['width']*$o['height'])/1000000;
  return $this->calculate($rates,$usage,$references,count($outputs),$mp);
 }
 public function upstreamCost(array $usage): ?int {
  if(!isset($usage['cost']) || !is_numeric($usage['cost']) || (float)$usage['cost']!==0.0)return null;
  $details=$usage['cost_details']??[];if(!is_array($details))return null;
  $valid=fn($value)=>!is_bool($value) && is_numeric($value) && is_finite((float)$value) && (float)$value>=0;
  // The total already includes prompt and completion; never sum all three.
  $total=$details['upstream_inference_cost']??null;
  if(!$valid($total) || (float)$total===0.0){
   $input=$details['upstream_inference_prompt_cost']??null;$output=$details['upstream_inference_completions_cost']??null;
   if(!$valid($input)||!$valid($output))return null;
   $total=(float)$input+(float)$output;
  }
  $micro=round((float)$total*1000000,8);
  return is_finite($micro) && $micro>0 && $micro<PHP_INT_MAX?(int)ceil($micro):null;
 }
 private function rates(array $lines,array $settings,bool $reserve): ?array {
  if(!$lines)return null;$groups=[];
  $settings=array_filter(array_intersect_key($settings,array_flip(['quality','resolution','aspect_ratio','background'])),'is_string');
  foreach($lines as $line) {
   if(!is_array($line)||!isset($line['billable'],$line['unit'],$line['cost_usd'])||!is_numeric($line['cost_usd'])||!is_finite((float)$line['cost_usd'])||$line['cost_usd']<0)return null;
   if(!in_array($line['billable'],['input_text','input_image','input_reference','output_image'])||!in_array($line['unit'],['token','image','megapixel']))return null;
   $groups[$line['billable'].'/'.$line['unit']][]=$line;
  }
  $rates=[];
  foreach($groups as $key=>$group) {
   if(count($group)>1) {
    $matches=array_filter($group,fn($l)=>isset($l['variant'])&&in_array(strtolower((string)$l['variant']),array_map('strtolower',$settings),true));
    if($matches)$group=$matches;
    else {$base=array_filter($group,fn($l)=>empty($l['variant']));if(!$base)return null;$group=$base;}
   } elseif(!empty($group[0]['variant']) && !in_array(strtolower((string)$group[0]['variant']),array_map('strtolower',$settings),true)) return null;
   $rates[$key]=max(array_column($group,'cost_usd'));
  }
  if(!array_filter(array_keys($rates),fn($key)=>str_starts_with($key,'output_image/')))return null;
  return $rates;
 }
 private function calculate(array $rates,array $usage,array $references,int $count,float $outputMp): ?int {
  $cost=0;$inputRates=[];
  foreach($rates as $key=>$rate) {
   [$billable,$unit]=explode('/',$key);$isInput=str_starts_with($billable,'input_');
   if($isInput && $billable!=='input_text' && !$references)continue;
   if($unit==='token') {
    if($isInput){$inputRates[$billable]=(float)$rate;continue;}
    if(!$this->validTokens($usage['completion_tokens']??null))return null;
    $quantity=$usage['completion_tokens'];
   } elseif($unit==='image') $quantity=$isInput?count($references):$count;
   else {
    if($isInput){$quantity=0;foreach($references as $ref){if(!isset($ref['width'],$ref['height']))return null;$quantity+=$ref['width']*$ref['height']/1000000;}}
    else $quantity=$outputMp;
   }
   $cost+=$quantity*$rate;
  }
  if($inputRates) {
   if(!$this->validTokens($usage['prompt_tokens']??null))return null;
   $total=$usage['prompt_tokens'];$image=$usage['prompt_tokens_details']['image_tokens']??null;
   if($references && count(array_unique($inputRates))>1) {
    // Never guess mixed text/image token quantities or charge input tokens twice.
    if(!$this->validTokens($image)||$image>$total)return null;
    $cost+=($total-$image)*($inputRates['input_text']??0)+$image*max($inputRates['input_image']??0,$inputRates['input_reference']??0);
   } else $cost+=$total*max($inputRates);
  }
  $micro=$cost*1000000;return is_finite($micro)&&$micro<=PHP_INT_MAX?(int)ceil(round($micro,8)):null;
 }
 private function validTokens($n): bool {return (is_int($n)||(is_string($n)&&ctype_digit($n)))&&$n>=0&&$n<=1000000000;}
 private function outputMegapixels(array $settings): float {
  $side=['512'=>512,'768'=>768,'1K'=>1024,'1.5K'=>1536,'2K'=>2048,'4K'=>4096][$settings['resolution']??'']??1024;
  return $side*$side/1000000;
 }
}
