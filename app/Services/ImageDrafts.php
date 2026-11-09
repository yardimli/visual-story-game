<?php
namespace App\Services;

use App\Models\{Story,StoryPage,ImageGeneration};
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImageDrafts {
 public function validate(Story $story,array $fields): array {
  $data=Validator::make($fields,[
   'name'=>'nullable|string|max:160','prompt'=>'required|string|max:10000','video_prompt'=>'nullable|string|max:5000',
   'model'=>'nullable|string|max:200','references'=>'sometimes|array|max:16','references.*'=>'integer|distinct',
   'settings'=>'sometimes|array:quality,resolution,aspect_ratio,background','settings.*'=>'string|max:50',
  ])->validate();
  if($story->assets()->whereIn('id',$data['references']??[])->count()!==count($data['references']??[]))throw ValidationException::withMessages(['references'=>'An image does not belong to this story.']);
  return $data;
 }
 public function save(Story $story,StoryPage $page,array $fields,?ImageGeneration $draft=null): ImageGeneration {
  $data=$this->validate($story,$fields);
  if($draft && $draft->status!=='draft')throw ValidationException::withMessages(['image'=>'This image is already submitted. Create a new image instead.']);
  $values=['name'=>$data['name']??'Untitled image','payload'=>array_merge(['prompt'=>$data['prompt'],'model'=>$data['model']??'','_video_prompt'=>$data['video_prompt']??''],$data['settings']??[]),'references'=>array_map(fn($id)=>['id'=>$id],$data['references']??[])];
  if($draft){$draft->update($values);return $draft;}
  return ImageGeneration::create($values+['user_id'=>$story->user_id,'story_id'=>$story->id,'story_page_id'=>$page->id,'visual_id'=>(string)Str::uuid(),'kind'=>'scene','request_key'=>(string)Str::uuid(),'status'=>'draft','pricing'=>[]]);
 }
}
