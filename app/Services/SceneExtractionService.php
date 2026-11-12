<?php
namespace App\Services;
use App\Models\{Story,StoryPage,SceneExtraction,Asset,ImageGeneration};
use Illuminate\Support\Facades\{Storage,DB};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class SceneExtractionService {
 public function fail(string $message):never {throw ValidationException::withMessages(['extraction'=>$message]);}
 public function prepare(Story $story,StoryPage $page,array $data):SceneExtraction {
  $visual=collect($page->visual_document['visuals']??[])->firstWhere('id',$data['visual_id']);
  if(!$visual || $visual['kind']!=='scene' || empty($visual['asset_id']))$this->fail('Choose a scene with an image first.');
  if($page->visual_revision!==$data['revision'])$this->fail('The scene changed. Reload before preparing extraction.');
  $previous=null;
  if(!empty($data['update_plan_id'])){
   $previous=SceneExtraction::where('story_id',$story->id)->where('story_page_id',$page->id)->where('visual_id',$visual['id'])->where('mode',$data['mode']??'collectibles')->findOrFail($data['update_plan_id']);
   $this->present($previous);$previous->refresh();
   if(ImageGeneration::whereIn('id',collect($previous->outputs)->flatMap(fn($o)=>$o['versions']))->whereIn('status',['queued','running'])->exists())$this->fail('Wait for this plan to finish rendering before adding areas.');
   foreach($previous->outputs as $output){
    if($output['type']==='background')continue;
    $area=collect($visual['hotspots'])->firstWhere('id',$output['area_id']);
    if(!$area || $area['action']!==(($previous->mode==='switches')?'state_v2':'pickup_v2') || (isset($output['object_id']) && ($area['object_id']??null)!==$output['object_id']))$this->fail('An existing extraction area or linked item changed. Review the existing plan or create a new plan.');
    if(isset($output['area_bounds']) && collect(['x','y','width','height'])->contains(fn($key)=>abs(($area[$key]??0)-$output['area_bounds'][$key])>0.00001))$this->fail('The area '.$area['label'].' has moved or resized. Review its alignment before creating a new plan.');
    if(collect($data['areas'])->contains('id',$output['area_id']))$this->fail('Select only new areas when updating an existing plan.');
   }
  }
  $background=$previous?collect($previous->outputs)->firstWhere('type','background'):null;
  if($previous && $previous->mode==='collectibles' && empty($background['accepted_asset_id']))$this->fail('Accept the existing clean background before updating with new areas.');
  $source=$story->assets()->findOrFail($background['accepted_asset_id']??$visual['asset_id']);
  $bytes=Storage::disk('local')->get($source->path);$info=@getimagesizefromstring($bytes);
  if(!$info || $info[0]*$info[1]>40000000)$this->fail('Use a source image under 40 megapixels.');
  $image=@imagecreatefromstring($bytes);if(!$image)$this->fail('The source image could not be read.');
  $mode=$data['mode']??'collectibles';$outputs=[];$removals=[];$paths=[];
  try{return DB::transaction(function()use($story,$page,$data,$visual,$source,$image,$info,$mode,$previous,&$outputs,&$removals,&$paths){
   $story=Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
   $page=StoryPage::whereKey($page->id)->lockForUpdate()->firstOrFail();
   if($page->visual_revision!==$data['revision'])$this->fail('The scene changed. Reload before preparing extraction.');
   $doc=$page->visual_document;$vi=array_search($data['visual_id'],array_column($doc['visuals'],'id'),true);
   if($vi===false)$this->fail('The scene no longer exists.');$visual=$doc['visuals'][$vi];
   $inventory=$story->inventory_document??['visuals'=>[]];$created=[];
   foreach($data['areas'] as $selection){
    $h=collect($visual['hotspots'])->firstWhere('id',$selection['id']);$item=collect($inventory['visuals'])->firstWhere('id',$h['object_id']??null);
    if(!$h || $h['action']!==($mode==='switches'?'state_v2':'pickup_v2'))$this->fail('Select only '.($mode==='switches'?'Change state v2':'Pick up object v2').' areas.');
    if($mode==='collectibles' && !$item){
     $item=['id'=>(string)Str::uuid(),'kind'=>'object','name'=>$h['label'],'description'=>$h['description']??'','description_position'=>in_array($h['description_position']??'top',['top','bottom'])?($h['description_position']??'top'):'top','asset_id'=>null,'prompt'=>'','hotspots'=>[],'actions'=>[]];
     $inventory['visuals'][]=$item;$created[]=$item['name'];
     $hi=array_search($h['id'],array_column($doc['visuals'][$vi]['hotspots'],'id'),true);$doc['visuals'][$vi]['hotspots'][$hi]['object_id']=$item['id'];
    }
    $empty=$mode==='collectibles' && ($selection['initial_empty']??$h['initial_empty']??false);
    if($empty && (empty($item['asset_id']) || !$story->assets()->whereKey($item['asset_id'])->exists()))$this->fail('Choose an Inventory item with an image for the empty area '.$h['label'].'.');
    $hi=array_search($h['id'],array_column($doc['visuals'][$vi]['hotspots'],'id'),true);
    if($mode==='collectibles' && ($empty || array_key_exists('initial_empty',$selection)))$doc['visuals'][$vi]['hotspots'][$hi]['initial_empty']=$empty;
    $b=$selection['bounds'];if($b['x']+$b['width']>1.00001 || $b['y']+$b['height']>1.00001)$this->fail('Extraction boundaries must fit inside the scene.');
    $x=(int)floor($b['x']*$info[0]);$y=(int)floor($b['y']*$info[1]);$w=max(1,min($info[0]-$x,(int)round($b['width']*$info[0])));$height=max(1,min($info[1]-$y,(int)round($b['height']*$info[1])));
    $crop=imagecrop($image,['x'=>$x,'y'=>$y,'width'=>$w,'height'=>$height]);if(!$crop)$this->fail('Could not crop '.$h['label']);
    ob_start();imagepng($crop);$png=ob_get_clean();imagedestroy($crop);
    $path='stories/'.$story->id.'/extraction/'.Str::uuid().'.png';if(!Storage::disk('local')->put($path,$png))$this->fail('Could not save extraction reference.');$paths[]=$path;
    $asset=Asset::create(['story_id'=>$story->id,'name'=>$h['label'].' extraction reference','path'=>$path,'mime'=>'image/png','bytes'=>strlen($png),'role'=>'reference']);
    if($mode==='switches'){
     $base=['area_bounds'=>array_intersect_key($h,array_flip(['x','y','width','height'])),'area_id'=>$h['id'],'bounds'=>$b,'references'=>[$asset->id],'versions'=>[]];
     $outputs[]=array_merge($base,['id'=>(string)Str::uuid(),'type'=>'switch_off','name'=>$h['label'].' original state','prompt'=>'Original cropped area.','accepted_asset_id'=>$asset->id]);
     $outputs[]=array_merge($base,['id'=>(string)Str::uuid(),'type'=>'switch_on','name'=>$h['label'].' changed state','accepted_asset_id'=>null,'prompt'=>'Edit only the local state of '.$h['label'].' in this cropped reference: '.($h['state_prompt']??'Show it pressed or activated.').'. Preserve the exact framing, dimensions, surrounding background, lighting and perspective. Nothing is removed. Do not change anything outside the mechanism. Output a full rectangular patch with matching edges, not a transparent cutout.']);
     continue;
    }
    $base=['area_bounds'=>array_intersect_key($h,array_flip(['x','y','width','height'])),'area_id'=>$h['id'],'object_id'=>$item['id'],'bounds'=>$b,'references'=>[$asset->id],'versions'=>[],'accepted_asset_id'=>null,'initial_empty'=>$empty];
    if(!$empty)$removals[]=(array_search($selection,$data['areas'],true)+1).'. '.$h['label'];
    $outputs[]=array_merge($base,['id'=>(string)Str::uuid(),'type'=>'layer','name'=>$h['label'].' scene layer','prompt'=>'Extract only '.$item['name'].' from this cropped reference. Preserve its exact pose, perspective, scale, placement and lighting within the crop. Output a transparent PNG with true alpha, not a checkerboard or solid background. Remove the surrounding background and unrelated objects, retain natural soft contact shadow only. Keep the exact '.$w.' by '.$height.' canvas framing. Do not center, enlarge or redesign the object.']);
    $outputs[]=array_merge($base,['id'=>(string)Str::uuid(),'type'=>'inventory','name'=>$item['name'].' inventory','prompt'=>'Create an isolated inventory render of '.$item['name'].' from this reference. '.($item['description']??$h['description']??'').' Preserve its identity, material and details. Center the entire object with comfortable margins, clearly readable at small inventory size, transparent PNG with true alpha. No scenery, text, hands or extra objects.']);
    if($empty){
     $layerIndex=count($outputs)-2;$inventoryIndex=count($outputs)-1;
     $outputs[$layerIndex]['references']=[$asset->id,$item['asset_id']];
     $outputs[$layerIndex]['prompt']='Image 1 is the empty location crop; image 2 is the Inventory item '.$item['name'].'. Render that item fitted naturally into this location, matching perspective, scale and lighting. Output ONLY the placed item on transparent PNG with true alpha. Preserve the exact '.$w.' by '.$height.' canvas framing and item position relative to the empty location. Do not include the background, annotations, hands or other objects.';
     $outputs[$inventoryIndex]['references']=[$item['asset_id']];$outputs[$inventoryIndex]['accepted_asset_id']=$item['asset_id'];
     $outputs[$inventoryIndex]['prompt']='Preserve the identity of '.$item['name'].' from this inventory reference. Render the isolated item centered on transparent PNG with true alpha.';
    }
   }
   if($mode==='collectibles'){
    $png=$this->annotatedPng($image,$info,$data['areas']);
    $path='stories/'.$story->id.'/extraction/'.Str::uuid().'.png';if(!Storage::disk('local')->put($path,$png))$this->fail('Could not save annotated scene.');$paths[]=$path;
    $annotated=Asset::create(['story_id'=>$story->id,'name'=>$visual['name'].' numbered extraction reference','path'=>$path,'mime'=>'image/png','bytes'=>strlen($png),'role'=>'reference']);
    $referenceMode=$data['background_references']??'original_and_annotated';
    $intro=$referenceMode==='annotated_only'?'Edit the supplied annotated scene image. Numbered boxes identify the target objects.':'Edit image 1 (the clean original). Image 2 is an annotated reference only; its numbered boxes identify the target objects.';
    array_unshift($outputs,['id'=>(string)Str::uuid(),'type'=>'background','name'=>$visual['name'].' clean background','reference_mode'=>$referenceMode,'annotation_areas'=>$data['areas'],'references'=>$referenceMode==='annotated_only'?[$annotated->id]:[$source->id,$annotated->id],'versions'=>[],'accepted_asset_id'=>null,'prompt'=>$intro." Remove ONLY these numbered collectible objects and their shadows:\n".implode("\n",$removals)."\nRemove the objects, not the entire contents of each rectangle. Reconstruct the background naturally behind each removed item. Preserve all other objects, camera, framing, lighting, colors and exact image dimensions. Do not remove furniture or any item not listed. Do not add new objects. The final image must contain no annotation rectangles, numbers or labels; restore the underlying scene wherever annotations appeared."]);
   }
   if($mode==='collectibles' && !$removals){$outputs[0]['accepted_asset_id']=$source->id;$outputs[0]['references']=[$source->id];$outputs[0]['prompt']='Preserve this empty background unchanged. No objects need removal.';}
   if($created){
    $story->update(['inventory_document'=>$inventory,'inventory_revision'=>$story->inventory_revision+1]);
   }
   if($doc!==$page->visual_document)$page->update(['visual_document'=>$doc,'visual_revision'=>$page->visual_revision+1]);
   if($previous){
    $locked=SceneExtraction::whereKey($previous->id)->lockForUpdate()->firstOrFail();
    if($locked->outputs!==$previous->outputs)$this->fail('The saved plan changed. Reopen it before updating.');
    $kept=array_values(array_filter($locked->outputs,fn($o)=>$o['type']!=='background'));
    foreach($kept as &$o)$o['reused']=true;unset($o);
    if($mode==='collectibles')$outputs[0]['previous_backgrounds']=array_merge(collect($locked->outputs)->firstWhere('type','background')['previous_backgrounds']??[],[array_diff_key(collect($locked->outputs)->firstWhere('type','background'),['previous_backgrounds'=>true])]);
    $outputs=array_merge($outputs,$kept);
    $locked->update(['source_asset_id'=>$source->id,'outputs'=>$outputs]);return $locked;
   }
   $batch=SceneExtraction::create(['mode'=>$mode,'story_id'=>$story->id,'story_page_id'=>$page->id,'visual_id'=>$visual['id'],'source_asset_id'=>$source->id,'outputs'=>$outputs]);
   return $batch;
  });}catch(\Throwable $e){foreach($paths as $path)Storage::disk('local')->delete($path);throw $e;}finally{imagedestroy($image);}
 }
 private function annotatedPng($image,array $info,array $areas):string {
    // Draw on a separate copy; source pixels and per-item crops remain untouched.
    $marked=imagecreatetruecolor($info[0],$info[1]);imagecopy($marked,$image,0,0,0,0,$info[0],$info[1]);
    $yellow=imagecolorallocate($marked,255,210,0);
    $scale=max(0.6,min(1.1,min($info[0],$info[1])/1200));imagesetthickness($marked,2);
    foreach($areas as $n=>$selection){
     $b=$selection['bounds'];$x=(int)floor($b['x']*$info[0]);$y=(int)floor($b['y']*$info[1]);
     $right=min($info[0]-1,(int)round(($b['x']+$b['width'])*$info[0]));$bottom=min($info[1]-1,(int)round(($b['y']+$b['height'])*$info[1]));
     imagerectangle($marked,$x,$y,$right,$bottom,$yellow);
     $label=(string)($n+1);$lw=imagefontwidth(5)*strlen($label)+8;$lh=imagefontheight(5)+6;
     $tag=imagecreatetruecolor($lw,$lh);imagefill($tag,0,0,imagecolorallocate($tag,255,210,0));imagestring($tag,5,4,3,$label,imagecolorallocate($tag,0,0,0));
     $labelWidth=max(10,(int)round($lw*$scale));$labelHeight=max(12,(int)round($lh*$scale));
     $tx=max(0,min($x,$info[0]-$labelWidth));$ty=max(0,$y-$labelHeight);
     imagecopyresized($marked,$tag,$tx,$ty,0,0,$labelWidth,$labelHeight,$lw,$lh);imagedestroy($tag);
    }
    ob_start();imagepng($marked);$png=ob_get_clean();imagedestroy($marked);
  return $png;
 }
 public function backgroundReferences(Story $story,SceneExtraction $batch,array $output,string $mode,string $prompt):array {
  if($output['type']!=='background')$this->fail('Reference mode is only available for background removal.');
  if(ImageGeneration::whereIn('id',$output['versions'])->whereIn('status',['queued','running'])->exists())$this->fail('Wait for the current background render to finish before changing references.');
  $source=$story->assets()->findOrFail($batch->source_asset_id);$bytes=Storage::disk('local')->get($source->path);$info=@getimagesizefromstring($bytes);
  if(!$info || $info[0]*$info[1]>40000000)$this->fail('The original scene image cannot be annotated.');
  $image=@imagecreatefromstring($bytes);if(!$image)$this->fail('Could not read the original scene image.');
  $areas=$output['annotation_areas']??array_values(array_map(fn($o)=>['bounds'=>$o['bounds']],array_filter($batch->outputs,fn($o)=>$o['type']==='layer')));
  try{$png=$this->annotatedPng($image,$info,$areas);}finally{imagedestroy($image);}
  $path='stories/'.$story->id.'/extraction/'.Str::uuid().'.png';if(!Storage::disk('local')->put($path,$png))$this->fail('Could not save annotated scene.');
  $asset=Asset::create(['story_id'=>$story->id,'name'=>$output['name'].' numbered reference','path'=>$path,'mime'=>'image/png','bytes'=>strlen($png),'role'=>'reference']);
  $single='Edit the supplied annotated scene image. Numbered boxes identify the target objects.';
  $dual='Edit image 1 (the clean original). Image 2 is an annotated reference only; its numbered boxes identify the target objects.';
  $output['prompt']=($mode==='annotated_only'?$single:$dual).' '.trim(str_replace([$single,$dual],'',$prompt));
  $output['references']=$mode==='annotated_only'?[$asset->id]:[$source->id,$asset->id];$output['reference_mode']=$mode;
  return $output;
 }
 public function present(SceneExtraction $batch):array {
  $batch=DB::transaction(function()use($batch){
   $locked=SceneExtraction::whereKey($batch->id)->lockForUpdate()->firstOrFail();$outputs=$locked->outputs;$changed=false;
   foreach($outputs as &$o){
    $reviewed=$o['reviewed_versions']??[];
    foreach($o['versions'] as $id){
     if(in_array($id,$reviewed,true))continue;
     $job=ImageGeneration::where('story_id',$locked->story_id)->whereKey($id)->first();
     if(!$job || $job->status!=='completed' || empty($job->asset_ids))continue;
     $asset=Asset::where('story_id',$locked->story_id)->whereKey($job->asset_ids[0])->first();if(!$asset)continue;
     $o['accepted_asset_id']=$asset->id;$reviewed[]=$id;$changed=true;
    }
    $o['reviewed_versions']=$reviewed;
   }unset($o);
   if($changed)$locked->update(['outputs'=>$outputs]);return $locked;
  });
  $jobs=ImageGeneration::where('story_id',$batch->story_id)->whereIn('id',collect($batch->outputs)->flatMap(fn($o)=>$o['versions']))->get()->keyBy('id');
  $out=$batch->toArray();$out['outputs']=array_map(function($o)use($jobs){$o['jobs']=array_values(array_map(function($id)use($jobs){$j=$jobs->get($id);return $j?['id'=>$j->id,'status'=>$j->status,'error'=>$j->error,'asset_ids'=>$j->asset_ids??[]]:['id'=>$id,'status'=>'deleted','asset_ids'=>[]];},$o['versions']));return $o;},$batch->outputs);return $out;
 }
 public function activate(Story $story,StoryPage $page,SceneExtraction $batch,int $revision,int $inventoryRevision):\App\Models\ChatMessage {
  return DB::transaction(function()use($story,$page,$batch,$revision,$inventoryRevision){
   $s=Story::whereKey($story->id)->lockForUpdate()->firstOrFail();$p=StoryPage::whereKey($page->id)->lockForUpdate()->firstOrFail();$b=SceneExtraction::whereKey($batch->id)->lockForUpdate()->firstOrFail();
   if($p->visual_revision!==$revision || $s->inventory_revision!==$inventoryRevision)$this->fail('Scene or inventory changed. Reload extraction before activating.');
   $doc=$p->visual_document;$vi=array_search($b->visual_id,array_column($doc['visuals'],'id'),true);if($vi===false)$this->fail('The source scene no longer exists.');
   $inventory=$s->inventory_document;$flags=$s->game_flags??[];
   foreach($b->outputs as $output){
    $assetId=$output['accepted_asset_id'];if(!$assetId)$this->fail('Accept a version for every output before activating.');
    if(!$s->assets()->whereKey($assetId)->exists())$this->fail('An accepted image is missing.');
    if($output['type']==='background'){$doc['visuals'][$vi]['asset_id']=$assetId;continue;}
    $hi=array_search($output['area_id'],array_column($doc['visuals'][$vi]['hotspots'],'id'),true);
    if($b->mode==='switches'){
     if($hi===false)$this->fail('A switch area was removed.');
     $h=&$doc['visuals'][$vi]['hotspots'][$hi];if($h['action']!=='state_v2')$this->fail('Switch areas must still use Change state v2.');
     $key='switch_'.str_replace('-','',$h['id']);if(!collect($flags)->contains('key',$key))$flags[]=['key'=>$key,'label'=>mb_substr('Switch '.$h['label'],0,160),'initial'=>false];
     $h['state_flag']=$key;$h['overlay']=true;$h['overlay_always']=true;$h['overlay_bounds']=$output['bounds'];$h['default_bounds']=$output['area_bounds']??array_intersect_key($h,array_flip(['x','y','width','height']));$h['default_overlay_bounds']=$output['bounds'];$h['target_id']=null;$h['object_id']=null;
     $h[$output['type']==='switch_off'?'overlay_asset_id':'state_asset_id']=$assetId;unset($h);continue;
    }
    $ii=array_search($output['object_id'],array_column($inventory['visuals'],'id'),true);
    if($hi===false || $ii===false)$this->fail('An extraction area or inventory item was removed. Prepare a new extraction.');
    if(!empty($output['reused']) && (($output['type']==='layer' && ($doc['visuals'][$vi]['hotspots'][$hi]['overlay_asset_id']??null)===$assetId) || ($output['type']==='inventory' && ($inventory['visuals'][$ii]['asset_id']??null)===$assetId)))continue;
    $h=&$doc['visuals'][$vi]['hotspots'][$hi];if($h['action']!=='pickup_v2' || $h['object_id']!==$output['object_id'])$this->fail('A selected area no longer picks up the original item.');
    if($output['type']==='inventory'){$inventory['visuals'][$ii]['asset_id']=$assetId;unset($h);continue;}
    $key='collected_'.str_replace('-','',$h['id']);
    if(!collect($flags)->contains('key',$key))$flags[]=['key'=>$key,'label'=>mb_substr('Collected '.$h['label'],0,160),'initial'=>(bool)($output['initial_empty']??false)];
    $h['overlay']=true;$h['overlay_always']=true;$h['overlay_asset_id']=$assetId;$h['overlay_bounds']=$output['bounds'];$h['default_bounds']=$output['area_bounds']??array_intersect_key($h,array_flip(['x','y','width','height']));$h['default_overlay_bounds']=$output['bounds'];$h['target_id']=null;
    foreach($flags as &$definition)if($definition['key']===$key)$definition['initial']=(bool)($output['initial_empty']??false);unset($definition);
    $h['collectible_flag']=$key;$h['initial_empty']=(bool)($output['initial_empty']??false);$h['placement_enabled']=$h['placement_enabled']??true;
    if(!collect($h['inventory_rules']??[])->contains('object_id',$h['object_id']))$h['inventory_rules'][]=['object_id'=>$h['object_id'],'allowed'=>true,'response'=>'You place the item back.','flag_conditions'=>[],'flag_effects'=>[]];
    $h['flag_conditions']=array_values(array_filter($h['flag_conditions']??[],fn($f)=>$f['key']!==$key));
    $h['flag_effects']=array_values(array_filter($h['flag_effects']??[],fn($f)=>$f['key']!==$key));$h['flag_effects'][]=['key'=>$key,'value'=>true];unset($h);
   }
   $s->game_flags=app(StoryFlags::class)->validate($flags);
   $doc=app(SceneDocument::class)->validate($s,$doc,$p->id);
   $s->update(['game_flags'=>$s->game_flags,'flags_revision'=>$s->flags_revision+1]+($b->mode==='switches'?[]:['inventory_document'=>$inventory,'inventory_revision'=>$s->inventory_revision+1]));
   $p->update(['visual_document'=>$doc,'visual_revision'=>$p->visual_revision+1]);
   $details=[];
   foreach($b->outputs as $output){
    if($output['type']==='background'){$details[]='Clean scene background applied.';continue;}
    if(!in_array($output['type'],['layer','switch_on']))continue;
    $area=collect($doc['visuals'][$vi]['hotspots'])->firstWhere('id',$output['area_id']);
    $details[]=$area['label'].($b->mode==='switches'?': original and changed-state images applied; clicking toggles the local state.':': removable scene layer and Inventory image applied; picking it up hides the layer.');
   }
   return $s->messages()->create(['role'=>'assistant','content'=>($b->mode==='switches'?'Switches':'Collectible extraction').' applied to Page '.$p->position.' - '.$p->title.' / '.$doc['visuals'][$vi]['name'].".\n\n".implode("\n",array_map(fn($line)=>'- '.$line,$details))]);
  });
 }
}
