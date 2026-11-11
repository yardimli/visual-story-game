<?php
namespace App\Http\Controllers;
use App\Models\{Story,StoryPage,SceneExtraction,ImageGeneration};
use App\Services\{SceneExtractionService,ImageDrafts,OpenRouter,ImagePricing};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class SceneExtractionController extends Controller {
 private function owner(Request $r,Story $s,StoryPage $p,?SceneExtraction $e=null):void {abort_unless($s->user_id===$r->user()->id && $p->story_id===$s->id && (!$e || ($e->story_id===$s->id && $e->story_page_id===$p->id)),404);}
 public function index(Request $r,Story $story,StoryPage $page,SceneExtractionService $service){$this->owner($r,$story,$page);return response()->json(['batches'=>SceneExtraction::where('story_id',$story->id)->where('story_page_id',$page->id)->latest()->get()->map(fn($e)=>$service->present($e)),'inventory_revision'=>$story->inventory_revision]);}
 public function store(Request $r,Story $story,StoryPage $page,SceneExtractionService $service){
  $this->owner($r,$story,$page);$data=$r->validate(['background_references'=>'sometimes|in:original_and_annotated,annotated_only','mode'=>'sometimes|in:collectibles,switches','visual_id'=>'required|uuid','revision'=>'required|integer','areas'=>'required|array|min:1|max:20','areas.*.id'=>'required|uuid|distinct','areas.*.bounds'=>'required|array:x,y,width,height','areas.*.bounds.x'=>'required|numeric|min:0|max:0.999','areas.*.bounds.y'=>'required|numeric|min:0|max:0.999','areas.*.bounds.width'=>'required|numeric|min:0.005|max:1','areas.*.bounds.height'=>'required|numeric|min:0.005|max:1']);
  $batch=$service->prepare($story,$page,$data);$page->refresh();$story->refresh();
  return response()->json($service->present($batch)+['document'=>$page->visual_document,'revision'=>$page->visual_revision,'inventory_items'=>$story->inventory_document['visuals']??[],'inventory_revision'=>$story->inventory_revision],201);
 }
 public function output(Request $r,Story $story,StoryPage $page,SceneExtraction $extraction,string $output,SceneExtractionService $service,OpenRouter $router){
  $this->owner($r,$story,$page,$extraction);
  $data=$r->validate(['action'=>'required|in:quote,generate,accept,unaccept,align','prompt'=>'sometimes|required|string|max:10000','model'=>'required_if:action,quote,generate|string|max:200','bounds'=>'required_if:action,align|array:x,y,width,height','bounds.x'=>'required_with:bounds|numeric|min:0|max:1','bounds.y'=>'required_with:bounds|numeric|min:0|max:1','bounds.width'=>'required_with:bounds|numeric|min:0.005|max:1','bounds.height'=>'required_with:bounds|numeric|min:0.005|max:1','asset_id'=>'required_if:action,accept|integer']);
  return DB::transaction(function()use($r,$story,$page,$extraction,$output,$service,$router,$data){
   $batch=SceneExtraction::whereKey($extraction->id)->lockForUpdate()->firstOrFail();$outputs=$batch->outputs;$i=array_search($output,array_column($outputs,'id'),true);abort_if($i===false,404);$o=$outputs[$i];
   if($data['action']==='align'){
    if($o['type']!=='layer' || $data['bounds']['x']+$data['bounds']['width']>1.00001 || $data['bounds']['y']+$data['bounds']['height']>1.00001)$service->fail('Keep the layer inside the scene.');$outputs[$i]['bounds']=$data['bounds'];
   }elseif($data['action']==='unaccept'){
    if($o['type']==='switch_off')$service->fail('The original crop is preserved. Create a new plan to change it.');
    $outputs[$i]['accepted_asset_id']=null;$outputs[$i]['reviewed_versions']=$o['versions'];
   }elseif($data['action']==='accept'){
    $valid=ImageGeneration::where('story_id',$story->id)->whereIn('id',$o['versions'])->where('status','completed')->get()->contains(fn($j)=>in_array($data['asset_id'],$j->asset_ids??[],true));
    if(!$valid)$service->fail('Choose a completed version of this output.');
    $outputs[$i]['accepted_asset_id']=$data['asset_id'];$outputs[$i]['reviewed_versions']=$o['versions'];
   }else{
    if($o['type']==='switch_off')$service->fail('The original switch patch is preserved from the scene. Create a new plan to recrop it.');$prompt=$data['prompt']??$o['prompt'];$endpoints=$router->imageEndpoints($data['model']);if(!$endpoints)$service->fail('Choose an available image-edit model.');
    foreach($endpoints as $endpoint)if(($endpoint['supported_parameters']['input_references']['max']??0)<count($o['references']))$service->fail('This model does not support image references.');
    $settings=[];
    if(in_array($o['type'],['layer','inventory']) && collect($endpoints)->every(fn($e)=>in_array('transparent',$e['supported_parameters']['background']['values']??[],true)))$settings['background']='transparent';
    $refs=$story->assets()->whereIn('id',$o['references'])->get()->map(fn($a)=>$a->only(['id','path','mime','bytes']))->all();
    $price=app(ImagePricing::class)->quote(['endpoints'=>array_map(fn($e)=>['pricing'=>$e['pricing']??[]],$endpoints)],$settings,$prompt,$refs);
    if($data['action']==='quote')return response()->json(['estimate'=>$price/1000000]);
    if(ImageGeneration::whereIn('id',$o['versions'])->whereIn('status',['queued','running'])->exists())$service->fail('This output already has a request in progress.');
    $draft=app(ImageDrafts::class)->save($story,$page,['name'=>$o['name'],'prompt'=>$prompt,'model'=>$data['model'],'references'=>$o['references'],'settings'=>$settings]);
    $request=Request::create('/', 'POST',['name'=>$o['name'],'revision'=>$page->visual_revision,'draft_id'=>$draft->id,'request_key'=>(string)Str::uuid(),'model'=>$data['model'],'references'=>$o['references'],'settings'=>$settings]);$request->setUserResolver(fn()=>$r->user());
    app(SceneController::class)->generate($request,$story,$page,$router);
    $outputs[$i]['versions'][]=$draft->id;$outputs[$i]['prompt']=$prompt;
   }
   $batch->update(['outputs'=>$outputs]);return response()->json($service->present($batch));
  });
 }
 public function activate(Request $r,Story $story,StoryPage $page,SceneExtraction $extraction,SceneExtractionService $service){$this->owner($r,$story,$page,$extraction);$data=$r->validate(['revision'=>'required|integer','inventory_revision'=>'required|integer']);$message=$service->activate($story,$page,$extraction,$data['revision'],$data['inventory_revision']);return response()->json(['message'=>'Accepted extraction activated.','chat_message'=>$message]);}
}
