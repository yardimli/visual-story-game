<?php
namespace App\Services;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Models\Story;
class StoryFlags {
 public function generatedKey(string $name,array $flags):string {
  $base=trim(str_replace('-', '_',\Illuminate\Support\Str::slug($name)), '_');
  if($base===''||!preg_match('/^[a-z]/',$base))$base='flag_'.$base;
  $base=rtrim(substr($base,0,64),'_');$keys=array_column($flags,'key');$key=$base;$n=1;
  while(in_array($key,$keys,true)){$suffix='_'.($n++);$key=rtrim(substr($base,0,64-strlen($suffix)),'_').$suffix;}
  return $key;
 }
 public function renameKeys(array $value,array $mapping):array {
  // Older collectible areas relied on the UUID-derived effect instead of an explicit link.
  if(($value['action']??null)==='pickup_v2'&&empty($value['collectible_flag'])&&!empty($value['id'])){
   $legacy='collected_'.str_replace('-','',$value['id']);
   if(isset($mapping[$legacy])&&collect($value['flag_effects']??[])->contains('key',$legacy))$value['collectible_flag']=$mapping[$legacy];
  }
  foreach($value as $key=>$item){if(is_array($item))$value[$key]=$this->renameKeys($item,$mapping);elseif(is_string($item)&&isset($mapping[$item])&&in_array($key,['key','state_flag','collectible_flag'],true))$value[$key]=$mapping[$item];}
  return $value;
 }
 public function validate(array $flags): array {return Validator::make(['flags'=>$flags],['flags'=>'array|max:100','flags.*'=>'array:key,label,initial','flags.*.key'=>['required','string','distinct','regex:/^[a-z][a-z0-9_]{0,63}$/'],'flags.*.label'=>'required|string|max:160','flags.*.initial'=>'required|boolean'])->validate()['flags'];}
 public function inspectDocument(array $doc,string $flag,string $prefix,bool $remove=false):array {
  $uses=[];
  $walk=function(array $node,string $path)use(&$walk,&$uses,$flag,$remove):array {
   if(isset($node['name']))$path.=' / '.$node['name'];elseif(isset($node['label']))$path.=' / '.$node['label'];
   if($remove&&isset($node['auto_state'])&&(($node['state_flag']??null)===$flag||in_array($flag,array_column($node['auto_state']['flag_conditions']??[],'key'),true)))$node['auto_state']['enabled']=false;
   foreach($node as $key=>$value){
    if(in_array($key,['state_flag','collectible_flag'],true)&&$value===$flag){$uses[]=['location'=>$path,'type'=>$key==='state_flag'?'Local switch state':'Collected item state'];if($remove)$node[$key]=null;}
    elseif(in_array($key,['flag_conditions','flag_effects','disabled_when','visible_when'],true)&&is_array($value)){
     foreach($value as $row)if(($row['key']??null)===$flag)$uses[]=['location'=>$path,'type'=>match($key){'flag_conditions'=>'Required flag state','disabled_when'=>'Disable area when','visible_when'=>'Show area when',default=>'Set flag after action'},'value'=>$row['value']??false];
     if($remove)$node[$key]=array_values(array_filter($value,fn($r)=>($r['key']??null)!==$flag));
    }elseif(is_array($value)){
     $label=match($key){'skip_rules'=>'Scene skip rules','inventory_rules'=>'Inventory rules','choices'=>'Choices','actions'=>'Inventory actions',default=>$key};
     $suffix=in_array($key,['visuals','hotspots'],true)||(is_int($key)&&(isset($value['name'])||isset($value['label'])))?'':' / '.(is_int($key)?($key+1):$label);$node[$key]=$walk($value,$path.$suffix);
     if($remove&&$key==='skip_rules')$node[$key]=array_values(array_filter($node[$key],fn($r)=>!empty($r['flag_conditions'])||!empty($r['object_ids'])));
    }
   }
   return $node;
  };
  return ['document'=>$walk($doc,$prefix),'uses'=>$uses];
 }
 public function usage(Story $story,string $flag,$pages):array {
  $uses=[];$versions=[];
  foreach($pages as $p){$uses=array_merge($uses,$this->inspectDocument($p->visual_document??[],$flag,'Page '.$p->position.' · '.$p->title)['uses']);$versions[$p->id]=$p->visual_revision;}
  $uses=array_merge($uses,$this->inspectDocument($story->inventory_document??[],$flag,'Inventory')['uses']);
  return ['uses'=>$uses,'revision'=>$story->flags_revision,'fingerprint'=>hash('sha256',json_encode([$versions,$story->inventory_revision,$story->flags_revision,$uses]))];
 }
 public function assertReferences(Story $story,array $flags):void {
  $keys=array_column($flags,'key');
  $walk=function($node)use(&$walk,$keys){
   if(!is_array($node))return;
   foreach($node as $key=>$value){
    if(in_array($key,['state_flag','collectible_flag'],true) && $value && !in_array($value,$keys,true))throw ValidationException::withMessages(['flags'=>'A local switch still uses flag '.$value.'.']);
    if(in_array($key,['flag_conditions','flag_effects','disabled_when','visible_when'],true)){
     foreach($value??[] as $flag)if(!in_array($flag['key']??null,$keys,true))throw ValidationException::withMessages(['flags'=>'A scene still uses flag '.($flag['key']??'').'. Remove its conditions/effects before deleting or renaming the flag.']);
    }elseif(is_array($value))$walk($value);
   }
  };
  foreach($story->pages()->get() as $p)$walk($p->visual_document);$walk($story->inventory_document);
 }
}
