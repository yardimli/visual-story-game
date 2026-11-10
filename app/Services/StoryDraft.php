<?php
namespace App\Services;
use App\Models\Story;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class StoryDraft {
    public function change(Story $story, int $revision, string $reason, callable $callback): void {
        DB::transaction(function () use ($story,$revision,$reason,$callback) {
            $locked = Story::whereKey($story->id)->lockForUpdate()->firstOrFail();
            if ($locked->revision !== $revision) throw ValidationException::withMessages(['revision'=>'This story changed in another tab. Reload before saving.']);
            $locked->revisions()->create(['revision'=>$locked->revision,'reason'=>$reason,'snapshot'=>[
                'story'=>$locked->only(['title','premise','genre','language','style','bible','llm_model','image_model','inventory_document','game_flags']),
                'pages'=>$locked->pages()->get()->map->only(['id','position','title','narrative','interaction_plan','narrative_delta','interaction_plan_delta'])->all(),
            ]]);
            $callback($locked);
            $locked->forceFill(['revision'=>$locked->revision+1])->save();
        });
    }
}
