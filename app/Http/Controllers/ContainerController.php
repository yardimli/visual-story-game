<?php
namespace App\Http\Controllers;
use App\Models\{Story,StoryPage,ContainerPlan};
use App\Services\ContainerPlans;
use Illuminate\Http\Request;
class ContainerController extends Controller {
 public function destroy(Request $r,Story $story,StoryPage $page,ContainerPlans $service){$this->owner($r,$story,$page);$d=$r->validate(['revision'=>'required|integer','visual_id'=>'required|uuid','area_id'=>'required|uuid']);$service->deleteContainer($story,$page,$d);return response()->json(['message'=>'Container and its plans deleted. Generated images were kept.']);}
 private function owner(Request $r,Story $s,StoryPage $p,?ContainerPlan $plan=null){abort_unless($r->user()->id===$s->user_id&&$p->story_id===$s->id&&(!$plan||($plan->story_id===$s->id&&$plan->story_page_id===$p->id)),404);}
 public function rename(Request $r,Story $story,StoryPage $page,ContainerPlan $plan){$this->owner($r,$story,$page,$plan);$plan->update($r->validate(['name'=>'required|string|max:160']));return response()->json(['name'=>$plan->name]);}
 public function index(Request $r,Story $story,StoryPage $page,ContainerPlans $service){$this->owner($r,$story,$page);return response()->json(['plans'=>ContainerPlan::where('story_page_id',$page->id)->latest()->get()->map(fn($p)=>$service->present($p)),'inventory_revision'=>$story->inventory_revision]);}
 public function store(Request $r,Story $story,StoryPage $page,ContainerPlans $service){$this->owner($r,$story,$page);return response()->json($service->present($service->create($story,$page,$r->all())),201);}
 public function prepare(Request $r,Story $story,StoryPage $page,ContainerPlan $plan,ContainerPlans $service){$this->owner($r,$story,$page,$plan);return response()->json($service->present($service->prepare($story,$plan,$r->all())));}
 public function activate(Request $r,Story $story,StoryPage $page,ContainerPlan $plan,ContainerPlans $service){$this->owner($r,$story,$page,$plan);$d=$r->validate(['revision'=>'required|integer','inventory_revision'=>'required|integer']);$service->activate($story,$page,$plan,$d['revision'],$d['inventory_revision']);return response()->json(['message'=>'Container activated.']);}
}
