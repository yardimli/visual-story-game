<?php
namespace App\Services;
use App\Models\{Story,StoryPage};
use Illuminate\Validation\ValidationException;
class PageOrder {
    public function arrange(Story $story, array $ids): void {
        $all=StoryPage::withTrashed()->where('story_id',$story->id)->get();
        if (count($ids)<1 || count($ids)>100 || count(array_unique($ids))!==count($ids) || array_diff($ids,$all->pluck('id')->all()))
            throw ValidationException::withMessages(['pages'=>'Invalid page order. Reload and try again.']);
        // Displace every position first to avoid unique-key collisions while swapping.
        $offset=(int)$all->max('position')+1;
        foreach ($all as $index=>$page) $page->update(['position'=>$offset+$index]);
        foreach ($ids as $index=>$id) {
            $page=$all->firstWhere('id',$id);
            if ($page->trashed()) $page->restore();
            $page->update(['position'=>$index+1]);
        }
        foreach ($all as $page) if (!in_array($page->id,$ids,true) && !$page->trashed()) $page->delete();
    }
}
