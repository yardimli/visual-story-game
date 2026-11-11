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
  $source=$story->assets()->findOrFail($visual['asset_id']);
  $bytes=Storage::disk('local')->get($source->path);$info=@getimagesizefromstring($bytes);
  if(!$info || $info[0]*$info[1]>40000000)$this->fail('Use a source image under 40 megapixels.');
  $image=@imagecreatefromstring($bytes);if(!$image)$this->fail('The source image could not be read.');
  $mode=$data['mode']??'collectibles';$outputs=[];$removals=[];$paths=[];
  try{return DB::transaction(function()use($story,$page,$data,$visual,$source,$image,$info,$mode,&$outputs,&$removals,&$paths){
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
    $b=$selection['bounds'];if($b['x']+$b['width']>1.00001 || $b['y']+$b['height']>1.00001)$this->fail('Extraction boundaries must fit inside the scene.');
    $x=(int)floor($b['x']*$info[0]);$y=(int)floor($b['y']*$info[1]);$w=max(1,min($info[0]-$x,(int)round($b['width']*$info[0])));$height=max(1,min($info[1]-$y,(int)round($b['height']*$info[1])));
    $crop=imagecrop($image,['x'=>$x,'y'=>$y,'width'=>$w,'height'=>$height]);if(!$crop)$this->fail('Could not crop '.$h['label']);
    ob_start();imagepng($crop);$png=ob_get_clean();imagedestroy($crop);
    $path='stories/'.$story->id.'/extraction/'.Str::uuid().'.png';if(!Storage::disk('local')->put($path,$png))$this->fail('Could not save extraction reference.');$paths[]=$path;
    $asset=Asset::create(['story_id'=>$story->id,'name'=>$h['label'].' extraction reference','path'=>$path,'mime'=>'image/png','bytes'=>strlen($png),'role'=>'reference']);
    if($mode==='switches'){
     $base=['area_id'=>$h['id'],'bounds'=>$b,'references'=>[$asset->id],'versions'=>[]];
     $outputs[]=array_merge($base,['id'=>(string)Str::uuid(),'type'=>'switch_off','name'=>$h['label'].' original state','prompt'=>'Original cropped area.','accepted_asset_id'=>$asset->id]);
     $outputs[]=array_merge($base,['id'=>(string)Str::uuid(),'type'=>'switch_on','name'=>$h['label'].' changed state','accepted_asset_id'=>null,'prompt'=>'Edit only the local state of '.$h['label'].' in this cropped reference: '.($h['state_prompt']??'Show it pressed or activated.').'. Preserve the exact framing, dimensions, surrounding background, lighting and perspective. Nothing is removed. Do not change anything outside the mechanism. Output a full rectangular patch with matching edges, not a transparent cutout.']);
     continue;
    }
    $base=['area_id'=>$h['id'],'object_id'=>$item['id'],'bounds'=>$b,'references'=>[$asset->id],'versions'=>[],'accepted_asset_id'=>null];
    $removals[]=(count($removals)+1).'. '.$h['label'];
    $outputs[]=array_merge($base,['id'=>(string)Str::uuid(),'type'=>'layer','name'=>$h['label'].' scene layer','prompt'=>'Extract only '.$item['name'].' from this cropped reference. Preserve its exact pose, perspective, scale, placement and lighting within the crop. Output a transparent PNG with true alpha, not a checkerboard or solid background. Remove the surrounding background and unrelated objects, retain natural soft contact shadow only. Keep the exact '.$w.' by '.$height.' canvas framing. Do not center, enlarge or redesign the object.']);
    $outputs[]=array_merge($base,['id'=>(string)Str::uuid(),'type'=>'inventory','name'=>$item['name'].' inventory','prompt'=>'Create an isolated inventory render of '.$item['name'].' from this reference. '.($item['description']??$h['description']??'').' Preserve its identity, material and details. Center the entire object with comfortable margins, clearly readable at small inventory size, transparent PNG with true alpha. No scenery, text, hands or extra objects.']);
   }
   if($mode==='collectibles'){
    // Draw on a separate copy; source pixels and per-item crops remain untouched.
    $marked=imagecreatetruecolor($info[0],$info[1]);imagecopy($marked,$image,0,0,0,0,$info[0],$info[1]);
    $yellow=imagecolorallocate($marked,255,210,0);
    $scale=max(1,(int)round(min($info[0],$info[1])/500));imagesetthickness($marked,max(2,$scale));
    foreach($data['areas'] as $n=>$selection){
     $b=$selection['bounds'];$x=(int)floor($b['x']*$info[0]);$y=(int)floor($b['y']*$info[1]);
     $right=min($info[0]-1,(int)round(($b['x']+$b['width'])*$info[0]));$bottom=min($info[1]-1,(int)round(($b['y']+$b['height'])*$info[1]));
     imagerectangle($marked,$x,$y,$right,$bottom,$yellow);
     $label=(string)($n+1);$lw=imagefontwidth(5)*strlen($label)+8;$lh=imagefontheight(5)+6;
     $tag=imagecreatetruecolor($lw,$lh);imagefill($tag,0,0,imagecolorallocate($tag,255,210,0));imagestring($tag,5,4,3,$label,imagecolorallocate($tag,0,0,0));
     $tx=max(0,min($x,$info[0]-$lw*$scale));$ty=max(0,$y-$lh*$scale);
     imagecopyresized($marked,$tag,$tx,$ty,0,0,$lw*$scale,$lh*$scale,$lw,$lh);imagedestroy($tag);
    }
    ob_start();imagepng($marked);$png=ob_get_clean();imagedestroy($marked);
    $path='stories/'.$story->id.'/extraction/'.Str::uuid().'.png';if(!Storage::disk('local')->put($path,$png))$this->fail('Could not save annotated scene.');$paths[]=$path;
    $annotated=Asset::create(['story_id'=>$story->id,'name'=>$visual['name'].' numbered extraction reference','path'=>$path,'mime'=>'image/png','bytes'=>strlen($png),'role'=>'reference']);
    $referenceMode=$data['background_references']??'original_and_annotated';
    $intro=$referenceMode==='annotated_only'?'Edit the supplied annotated scene image. Numbered boxes identify the target objects.':'Edit image 1 (the clean original). Image 2 is an annotated reference only; its numbered boxes identify the target objects.';
    array_unshift($outputs,['id'=>(string)Str::uuid(),'type'=>'background','name'=>$visual['name'].' clean background','reference_mode'=>$referenceMode,'references'=>$referenceMode==='annotated_only'?[$annotated->id]:[$source->id,$annotated->id],'versions'=>[],'accepted_asset_id'=>null,'prompt'=>$intro." Remove ONLY these numbered collectible objects and their shadows:\n".implode("\n",$removals)."\nRemove the objects, not the entire contents of each rectangle. Reconstruct the background naturally behind each removed item. Preserve all other objects, camera, framing, lighting, colors and exact image dimensions. Do not remove furniture or any item not listed. Do not add new objects. The final image must contain no annotation rectangles, numbers or labels; restore the underlying scene wherever annotations appeared."]);
   }
   if($created){
    $story->update(['inventory_document'=>$inventory,'inventory_revision'=>$story->inventory_revision+1]);
    $page->update(['visual_document'=>$doc,'visual_revision'=>$page->visual_revision+1]);
   }
   $batch=SceneExtraction::create(['mode'=>$mode,'story_id'=>$story->id,'story_page_id'=>$page->id,'visual_id'=>$visual['id'],'source_asset_id'=>$source->id,'outputs'=>$outputs]);
   return $batch;
  });}catch(\Throwable $e){foreach($paths as $path)Storage::disk('local')->delete($path);throw $e;}finally{imagedestroy($image);}
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
     $h['state_flag']=$key;$h['overlay']=true;$h['overlay_always']=true;$h['overlay_bounds']=$output['bounds'];$h['target_id']=null;$h['object_id']=null;
     $h[$output['type']==='switch_off'?'overlay_asset_id':'state_asset_id']=$assetId;unset($h);continue;
    }
    $ii=array_search($output['object_id'],array_column($inventory['visuals'],'id'),true);
    if($hi===false || $ii===false)$this->fail('An extraction area or inventory item was removed. Prepare a new extraction.');
    $h=&$doc['visuals'][$vi]['hotspots'][$hi];if($h['action']!=='pickup_v2' || $h['object_id']!==$output['object_id'])$this->fail('A selected area no longer picks up the original item.');
    if($output['type']==='inventory'){$inventory['visuals'][$ii]['asset_id']=$assetId;unset($h);continue;}
    $key='collected_'.str_replace('-','',$h['id']);
    if(!collect($flags)->contains('key',$key))$flags[]=['key'=>$key,'label'=>mb_substr('Collected '.$h['label'],0,160),'initial'=>false];
    $h['overlay']=true;$h['overlay_always']=true;$h['overlay_asset_id']=$assetId;$h['overlay_bounds']=$output['bounds'];$h['target_id']=null;
    $h['availability_enabled']=true;$h['unavailable_behavior']='hide';
    $h['flag_conditions']=array_values(array_filter($h['flag_conditions']??[],fn($f)=>$f['key']!==$key));$h['flag_conditions'][]=['key'=>$key,'value'=>false];
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
