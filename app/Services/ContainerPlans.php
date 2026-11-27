<?php
namespace App\Services;
use App\Models\{Story,StoryPage,Asset,ContainerPlan,SceneExtraction};
use Illuminate\Support\Facades\{DB,Storage,Validator};
use Illuminate\Support\Str;
class ContainerPlans {
 public function deleteContainer(Story $story,StoryPage $page,array $data):void {
  DB::transaction(function()use($story,$page,$data){
   $s=Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
   $p=StoryPage::whereKey($page->id)->lockForUpdate()->firstOrFail();
   if($p->visual_revision!==$data['revision'])$this->fail('Scene changed. Reload before deleting this container.');
   $doc=$p->visual_document;$vi=array_search($data['visual_id'],array_column($doc['visuals']??[],'id'),true);
   abort_if($vi===false,404);$areas=&$doc['visuals'][$vi]['hotspots'];$hi=array_search($data['area_id'],array_column($areas,'id'),true);abort_if($hi===false,404);
   $plans=ContainerPlan::where('story_page_id',$p->id)->where('visual_id',$data['visual_id'])->where('area_id',$data['area_id'])->lockForUpdate()->get();
   abort_if($plans->isEmpty()&&empty($areas[$hi]['is_container']),404);
   $restore=null;foreach($plans as $savedPlan){$preview=collect(SceneExtraction::find($savedPlan->extraction_id)?->outputs??[])->firstWhere('type','container_preview');if(!empty($preview['source_parent'])&&empty($preview['source_parent']['is_container'])){$restore=$preview['source_parent'];break;}}
   $candidates=[];
   if(!empty($areas[$hi]['is_container'])){
    $expected=$areas[$hi]['state_flag']??null;if($expected)$candidates[]=$expected;
    foreach($areas as $area)if(($area['container_parent_id']??null)===$data['area_id']){$expected=$area['collectible_flag']??null;if($expected)$candidates[]=$expected;}
    foreach(['is_container','container_plan_id','state_flag','state_asset_id','state_prompt','state_description','overlay_asset_id','overlay_bounds','default_bounds','default_overlay_bounds'] as $key)unset($areas[$hi][$key]);
    if($restore){$areas[$hi]=array_merge($areas[$hi],array_intersect_key($restore,array_flip(['action','state_flag','state_asset_id','overlay','overlay_always','overlay_asset_id','overlay_bounds','default_bounds','default_overlay_bounds','state_description','state_prompt'])));}else{$areas[$hi]['action']='examine';$areas[$hi]['overlay']=false;}
    $areas=array_values(array_filter($areas,fn($area)=>($area['container_parent_id']??null)!==$data['area_id']));
   }
   foreach($plans as $plan){$batchId=$plan->extraction_id;$plan->delete();SceneExtraction::whereKey($batchId)->delete();}
   $p->update(['visual_document'=>$doc,'visual_revision'=>$p->visual_revision+1]);
   // Only discard generated state flags that no remaining scene or item uses.
   $unused=[];$flags=app(StoryFlags::class);$pages=$s->pages()->get();
   foreach(array_filter(array_unique($candidates)) as $flag)if(!$flags->usage($s,$flag,$pages)['uses'])$unused[]=$flag;
   if($unused)$s->update(['game_flags'=>array_values(array_filter($s->game_flags??[],fn($f)=>!in_array($f['key'],$unused,true))),'flags_revision'=>$s->flags_revision+1]);
  });
 }
 private function fail($text):never {throw \Illuminate\Validation\ValidationException::withMessages(['container'=>$text]);}
 public function validate(Story $story,StoryPage $page,array $data):array {
  $d=Validator::make($data,['output_prompts'=>'sometimes|array|max:25','output_prompts.*'=>'array:type,object_id,prompt','output_prompts.*.type'=>'required|in:switch_on,layer,inventory','output_prompts.*.object_id'=>'nullable|uuid','output_prompts.*.prompt'=>'required|string|max:10000','reuse_changed_state'=>'sometimes|boolean','visual_id'=>'required|uuid','area_id'=>'required|uuid','name'=>'required|string|max:160','prompt'=>'required_unless:reuse_changed_state,true|nullable|string|max:10000','contents'=>'required|array|min:1|max:12','contents.*'=>'required|array:object_id,name,description','contents.*.object_id'=>'required|uuid|distinct','contents.*.name'=>'sometimes|string|max:160','contents.*.description'=>'nullable|string|max:3000','padding'=>'sometimes|array:top,right,bottom,left','padding.*'=>'integer|min:0|max:500','equal_padding'=>'sometimes|boolean','user_prompt'=>'nullable|string|max:10000','prompt_model'=>'nullable|string|max:200'])->validate();
  $v=collect($page->visual_document['visuals']??[])->firstWhere('id',$d['visual_id']);$a=collect($v['hotspots']??[])->firstWhere('id',$d['area_id']);
  if(!$v||!$v['asset_id']||!$a||!empty($a['container_parent_id']))$this->fail('Draw a container area on this scene first, then select it.');
  foreach($d['contents'] as &$c){$item=collect($story->inventory_document['visuals']??[])->firstWhere('id',$c['object_id']);if(!$item)$this->fail('Choose an Inventory item from this game.');$c=['object_id'=>$item['id'],'name'=>$item['name'],'description'=>$item['description']??''];}unset($c);return $d;
 }
 public function paddedBounds(Asset $source,array $b,array $padding):array {
  $size=@getimagesizefromstring(Storage::disk('local')->get($source->path));if(!$size||$size[0]*$size[1]>40000000)$this->fail('Use an image under 40 megapixels.');
  [$w,$h]=$size;$x=max(0,(int)floor($b['x']*$w)-($padding['left']??0));$y=max(0,(int)floor($b['y']*$h)-($padding['top']??0));
  $right=min($w,(int)ceil(($b['x']+$b['width'])*$w)+($padding['right']??0));$bottom=min($h,(int)ceil(($b['y']+$b['height'])*$h)+($padding['bottom']??0));
  return ['x'=>$x/$w,'y'=>$y/$h,'width'=>($right-$x)/$w,'height'=>($bottom-$y)/$h];
 }
 public function cropBytes(Asset $source,array $b):string {
  $bytes=Storage::disk('local')->get($source->path);$size=@getimagesizefromstring($bytes);if(!$size||$size[0]*$size[1]>40000000)$this->fail('Use an image under 40 megapixels.');$im=@imagecreatefromstring($bytes);if(!$im)$this->fail('Could not read source image.');
  try{$x=(int)floor($b['x']*$size[0]);$y=(int)floor($b['y']*$size[1]);$crop=imagecrop($im,['x'=>$x,'y'=>$y,'width'=>max(1,min($size[0]-$x,(int)round($b['width']*$size[0]))),'height'=>max(1,min($size[1]-$y,(int)round($b['height']*$size[1])))]);if(!$crop)$this->fail('Could not crop area.');ob_start();imagepng($crop);$png=ob_get_clean();imagedestroy($crop);}finally{imagedestroy($im);}
  return $png;
 }
 private function crop(Story $story,Asset $source,array $b,string $name):Asset {
  $png=$this->cropBytes($source,$b);
  $path='stories/'.$story->id.'/containers/'.Str::uuid().'.png';if(!Storage::disk('local')->put($path,$png))$this->fail('Could not save crop.');return Asset::create(['story_id'=>$story->id,'name'=>$name,'path'=>$path,'mime'=>'image/png','bytes'=>strlen($png),'role'=>'extraction_reference']);
 }
 private function output($type,$name,$prompt,$references,$accepted=null):array {return ['id'=>(string)Str::uuid(),'type'=>$type,'name'=>$name,'prompt'=>$prompt,'references'=>$references,'accepted_asset_id'=>$accepted,'versions'=>[]];}
 public function create(Story $story,StoryPage $page,array $data):ContainerPlan {
  return DB::transaction(function()use($story,$page,$data){$page=StoryPage::whereKey($page->id)->lockForUpdate()->firstOrFail();$d=$this->validate($story,$page,$data);$v=collect($page->visual_document['visuals'])->firstWhere('id',$d['visual_id']);$a=collect($v['hotspots'])->firstWhere('id',$d['area_id']);$b=array_intersect_key($a,array_flip(['x','y','width','height']));
   $source=$story->assets()->findOrFail($v['asset_id']);$padding=array_merge(['top'=>0,'right'=>0,'bottom'=>0,'left'=>0],$d['padding']??[]);$reuse=!empty($d['reuse_changed_state']);
   if($reuse){if(($a['action']??null)!=='state_v2'||empty($a['state_flag'])||empty($a['state_asset_id'])||empty($a['overlay_asset_id']))$this->fail('Choose an activated local switch with original and changed images.');$crop=$story->assets()->findOrFail($a['overlay_asset_id']);$cropBounds=$a['overlay_bounds']??$b;$story->assets()->findOrFail($a['state_asset_id']);}
   else{$cropBounds=$this->paddedBounds($source,$b,$padding);$crop=$this->crop($story,$source,$cropBounds,$d['name'].' closed container reference');}
   $refs=[$crop->id];$contents=[];
   foreach($d['contents'] as $c){$item=collect($story->inventory_document['visuals']??[])->firstWhere('id',$c['object_id']);if(!empty($item['asset_id']))$refs[]=$item['asset_id'];$contents[]=$c+['new_item'=>false,'area_id'=>(string)Str::uuid()];}
   $prompt=$d['prompt']??'Extract the selected items from the existing changed state.';
   $batch=SceneExtraction::create(['story_id'=>$story->id,'story_page_id'=>$page->id,'visual_id'=>$v['id'],'source_asset_id'=>$crop->id,'mode'=>'container','outputs'=>[$this->output('switch_off',$d['name'].' closed','Original cropped container.',[$crop->id],$crop->id),array_merge($this->output('container_preview',$d['name'].' open with contents',$prompt,array_values(array_unique($refs)),$reuse?$a['state_asset_id']:null),$reuse?['source_parent'=>$a]:[],['inherited_prompts'=>$d['output_prompts']??[]])]]);
   return ContainerPlan::create(['story_id'=>$story->id,'story_page_id'=>$page->id,'visual_id'=>$v['id'],'area_id'=>$a['id'],'name'=>$d['name'],'prompt'=>$prompt,'bounds'=>$b,'crop_bounds'=>$cropBounds,'padding'=>$padding,'equal_padding'=>$d['equal_padding']??true,'user_prompt'=>$d['user_prompt']??null,'prompt_model'=>$d['prompt_model']??null,'contents'=>$contents,'source_asset_id'=>$crop->id,'extraction_id'=>$batch->id]);
  });
 }
 public function present(ContainerPlan $plan):array {return $plan->toArray()+['batch'=>app(SceneExtractionService::class)->present(SceneExtraction::findOrFail($plan->extraction_id))];}
 public function prepare(Story $story,ContainerPlan $plan,array $data):ContainerPlan {
  $d=Validator::make($data,['preview_asset_id'=>'required|integer','contents'=>'required|array|min:1|max:12','contents.*.area_id'=>'required|uuid|distinct','contents.*.bounds'=>'required|array:x,y,width,height','contents.*.bounds.x'=>'required|numeric|min:0|max:1','contents.*.bounds.y'=>'required|numeric|min:0|max:1','contents.*.bounds.width'=>'required|numeric|min:0.005|max:1','contents.*.bounds.height'=>'required|numeric|min:0.005|max:1'])->validate();
  return DB::transaction(function()use($story,$plan,$d){$p=ContainerPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();if($p->stage!=='preview')$this->fail('Contents are already prepared. Create a new plan to change their areas.');$b=SceneExtraction::whereKey($p->extraction_id)->lockForUpdate()->firstOrFail();$preview=collect($b->outputs)->firstWhere('type','container_preview');if(\App\Models\ImageGeneration::whereIn('id',$preview['versions']??[])->whereIn('status',['queued','running'])->exists())$this->fail('Wait for the open-container render to finish before approving it.');if(($preview['accepted_asset_id']??null)!==$d['preview_asset_id'])$this->fail('Choose and approve the completed open-container preview first.');if(count($p->contents)!==count($d['contents']))$this->fail('Mark an area for every item.');
   $source=$story->assets()->findOrFail($d['preview_asset_id']);$outputs=$b->outputs;$contents=[];foreach($d['contents'] as $selection){$item=collect($p->contents)->firstWhere('area_id',$selection['area_id']);if(!$item)$this->fail('Unknown content area.');$contents[]=$item;}
   $outputs[]=$this->output('switch_on',$p->name.' open and empty','Remove only '.implode(', ',array_column($contents,'name')).' from this changed-state image and reconstruct the background behind them. Keep the mechanism in its changed state, all surroundings, framing, lighting and exact dimensions unchanged.',[$source->id]);
   foreach($contents as &$c){$sel=collect($d['contents'])->firstWhere('area_id',$c['area_id']);if(!$sel)$this->fail('Missing content area.');$r=$sel['bounds'];if($r['x']+$r['width']>1.00001||$r['y']+$r['height']>1.00001)$this->fail('Keep item areas inside the container crop.');if($r['width']*($p->crop_bounds??$p->bounds)['width']<.005||$r['height']*($p->crop_bounds??$p->bounds)['height']<.005)$this->fail('Item areas must be at least 0.5% of the full scene width and height. Enlarge the item boundary.');$c['bounds']=$r;$crop=$this->crop($story,$source,$r,$c['name'].' container item reference');$item=collect($story->inventory_document['visuals']??[])->firstWhere('id',$c['object_id']);
    $outputs[]=array_merge($this->output('layer',$c['name'].' container layer','Extract only '.$c['name'].' on transparent PNG with true alpha. Preserve exact position, scale, pose, lighting and crop dimensions. Remove surrounding background, not item details. Do not center or enlarge it.',[$crop->id]),['area_id'=>$c['area_id'],'bounds'=>$r]);
    $outputs[]=array_merge($this->output('inventory',$c['name'].' inventory','Isolated inventory render of '.$c['name'].'. '.($c['description']??'').' Center the entire item, transparent PNG, no scenery or text.',[$crop->id],$item['asset_id']??null),['area_id'=>$c['area_id']]);
   }unset($c);
   // Match item prompts by inventory identity; regenerated areas have fresh IDs.
   foreach($outputs as &$output){
    if(!in_array($output['type'],['switch_on','layer','inventory'],true))continue;
    $objectId=collect($contents)->firstWhere('area_id',$output['area_id']??null)['object_id']??null;
    $inherited=collect($preview['inherited_prompts']??[])->first(fn($row)=>$row['type']===$output['type']&&($row['object_id']??null)===$objectId);
    if($inherited)$output['prompt']=$inherited['prompt'];
   }unset($output);
   $b->update(['outputs'=>$outputs]);$p->update(['contents'=>$contents,'preview_asset_id'=>$source->id,'stage'=>'contents']);return $p;
  });
 }
 public function activate(Story $story,StoryPage $page,ContainerPlan $plan,int $revision,int $inventoryRevision):void {
  DB::transaction(function()use($story,$page,$plan,$revision,$inventoryRevision){$s=Story::whereKey($story->id)->lockForUpdate()->firstOrFail();$p=StoryPage::whereKey($page->id)->lockForUpdate()->firstOrFail();$plan=ContainerPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();if($p->visual_revision!==$revision||$s->inventory_revision!==$inventoryRevision)$this->fail('Scene or inventory changed. Reopen the plan before activating.');if($plan->stage==='preview')$this->fail('Approve the preview and prepare contents first.');$batch=SceneExtraction::findOrFail($plan->extraction_id);$outputs=collect($batch->outputs);if(\App\Models\ImageGeneration::whereIn('id',$outputs->flatMap(fn($o)=>$o['versions']??[]))->whereIn('status',['queued','running'])->exists())$this->fail('Wait for container renders to finish before activating.');foreach($outputs as $o)if(!$o['accepted_asset_id']||!$s->assets()->whereKey($o['accepted_asset_id'])->exists())$this->fail('Accept all outputs before activating.');if($outputs->firstWhere('type','container_preview')['accepted_asset_id']!==$plan->preview_asset_id)$this->fail('The open preview changed after extraction. Create a new plan to use it.');
   $doc=$p->visual_document;$vi=array_search($plan->visual_id,array_column($doc['visuals'],'id'),true);if($vi===false)$this->fail('Scene removed.');$areas=&$doc['visuals'][$vi]['hotspots'];$hi=array_search($plan->area_id,array_column($areas,'id'),true);if($hi===false)$this->fail('Container area removed.');$parent=$areas[$hi];foreach($plan->bounds as $key=>$value)if(abs($parent[$key]-$value)>.00001)$this->fail('Container bounds changed. Create a new plan from its current crop.');
   $flags=$s->game_flags??[];$inventory=$s->inventory_document??['visuals'=>[]];$open=$parent['state_flag']??app(StoryFlags::class)->generatedKey($parent['label'].' open',$flags);if(!collect($flags)->contains('key',$open))$flags[]=['key'=>$open,'label'=>mb_substr($plan->name.' open',0,160),'initial'=>false];
   $parent=array_merge($parent,['is_container'=>true,'container_plan_id'=>$plan->id,'action'=>'state_v2','state_flag'=>$open,'overlay'=>true,'overlay_always'=>true,'overlay_asset_id'=>$plan->source_asset_id,'state_asset_id'=>$outputs->firstWhere('type','switch_on')['accepted_asset_id'],'target_id'=>null,'object_id'=>null,'overlay_bounds'=>$plan->crop_bounds??$plan->bounds,'default_bounds'=>$plan->bounds,'default_overlay_bounds'=>$plan->crop_bounds??$plan->bounds]);
   $existingChildren=collect($areas)->keyBy('id');
   $areas=array_values(array_filter($areas,fn($a)=>($a['container_parent_id']??null)!==$parent['id']));$hi=array_search($parent['id'],array_column($areas,'id'),true);$areas[$hi]=$parent;
   foreach($plan->contents as $c){$layer=$outputs->first(fn($o)=>$o['type']==='layer'&&$o['area_id']===$c['area_id']);$image=$outputs->first(fn($o)=>$o['type']==='inventory'&&$o['area_id']===$c['area_id']);$ii=array_search($c['object_id'],array_column($inventory['visuals'],'id'),true);if($ii===false){if(empty($c['new_item']))$this->fail('An Inventory item was removed.');$inventory['visuals'][]=['id'=>$c['object_id'],'kind'=>'object','name'=>$c['name'],'description'=>$c['description']??'','asset_id'=>$image['accepted_asset_id'],'prompt'=>'','hotspots'=>[],'actions'=>[]];}else $inventory['visuals'][$ii]['asset_id']=$image['accepted_asset_id'];
    $r=$c['bounds'];$base=$plan->crop_bounds??$plan->bounds;$bounds=['x'=>$base['x']+$r['x']*$base['width'],'y'=>$base['y']+$r['y']*$base['height'],'width'=>$r['width']*$base['width'],'height'=>$r['height']*$base['height']];$flag=$existingChildren->get($c['area_id'])['collectible_flag']??app(StoryFlags::class)->generatedKey('Collected '.$c['name'],$flags);if(!collect($flags)->contains('key',$flag))$flags[]=['key'=>$flag,'label'=>mb_substr('Collected '.$c['name'],0,160),'initial'=>false];
    $areas[]=array_merge($bounds,['id'=>$c['area_id'],'label'=>$c['name'],'description'=>$c['description']??'','action'=>'pickup_v2','object_id'=>$c['object_id'],'target_id'=>null,'container_parent_id'=>$parent['id'],'container_bounds'=>$r,'overlay'=>true,'overlay_always'=>true,'overlay_asset_id'=>$layer['accepted_asset_id'],'overlay_bounds'=>$bounds,'default_bounds'=>$bounds,'default_overlay_bounds'=>$bounds,'collectible_flag'=>$flag,'initial_empty'=>false,'placement_enabled'=>false,'show_hover_text'=>false]);
   }
   $s->game_flags=app(StoryFlags::class)->validate($flags);$s->inventory_document=$inventory;$doc=app(SceneDocument::class)->validate($s,$doc,$p->id);$s->flags_revision++;$s->inventory_revision++;$s->save();$p->update(['visual_document'=>$doc,'visual_revision'=>$p->visual_revision+1]);ContainerPlan::where('story_page_id',$p->id)->where('area_id',$parent['id'])->where('stage','active')->update(['stage'=>'contents']);$plan->update(['stage'=>'active']);$s->messages()->create(['role'=>'assistant','content'=>'Container plan "'.$plan->name.'" applied to '.$p->title.'. Its contents are visible only while open; collected items stay removed.']);
  });
 }
}
