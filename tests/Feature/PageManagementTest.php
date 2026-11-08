<?php
namespace Tests\Feature;
use App\Models\{User,Story,StoryPage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PageManagementTest extends TestCase {
    use RefreshDatabase;
    private function story(int $count=2): Story {
        $user=User::factory()->create(); $this->actingAs($user);
        $story=Story::create(['user_id'=>$user->id,'title'=>'Story','premise'=>'Premise']);
        for($i=1;$i<=$count;$i++) $story->pages()->create(['position'=>$i,'title'=>'Page '.$i,'narrative'=>'Text '.$i]);
        return $story;
    }
    public function test_reorder_remove_and_restore_preserve_content_and_ids(): void {
        $s=$this->story(); [$first,$second]=$s->pages()->pluck('id')->all();
        $this->post(route('pages.manage',$s),['revision'=>1,'action'=>'up','page_id'=>$second])->assertSessionHasNoErrors();
        $this->assertSame([$second,$first],$s->pages()->pluck('id')->all());
        $this->post(route('pages.manage',$s),['revision'=>2,'action'=>'remove','page_id'=>$first])->assertSessionHasNoErrors();
        $this->assertSoftDeleted('story_pages',['id'=>$first]);
        $this->assertSame([1],$s->pages()->pluck('position')->all());
        $original=$s->revisions()->where('revision',1)->firstOrFail();
        $this->post(route('stories.restore',[$s,$original]),['revision'=>3])->assertRedirect(route('stories.edit',$s));
        $this->assertSame([$first,$second],$s->pages()->pluck('id')->all());
        $this->assertSame('Text 1',StoryPage::findOrFail($first)->narrative);
        $this->assertSame(4,$s->fresh()->revision);
    }
    public function test_addition_can_be_undone_and_redone_using_history(): void {
        $s=$this->story(1);
        $this->post(route('pages.manage',$s),['revision'=>1,'action'=>'add'])->assertSessionHasNoErrors();
        $added=$s->pages()->get()->last()->id;
        $this->assertSame([1,2],$s->pages()->pluck('position')->all());
        $before=$s->revisions()->where('revision',1)->firstOrFail();
        $this->post(route('stories.restore',[$s,$before]),['revision'=>2])->assertSessionHasNoErrors();
        $this->assertSoftDeleted('story_pages',['id'=>$added]);
        $withAdded=$s->revisions()->where('revision',2)->firstOrFail();
        $this->post(route('stories.restore',[$s,$withAdded]),['revision'=>3])->assertSessionHasNoErrors();
        $this->assertSame($added,$s->pages()->get()->last()->id);
    }
    public function test_limits_stale_revision_and_foreign_pages_are_rejected(): void {
        $s=$this->story(1); $id=$s->pages()->first()->id;
        $this->post(route('pages.manage',$s),['revision'=>1,'action'=>'remove','page_id'=>$id])->assertSessionHasErrors('pages');
        $this->post(route('pages.manage',$s),['revision'=>1,'action'=>'up','page_id'=>$id])->assertSessionHasErrors('pages');
        $this->post(route('pages.manage',$s),['revision'=>0,'action'=>'add'])->assertSessionHasErrors('revision');
        $other=Story::create(['user_id'=>User::factory()->create()->id,'title'=>'Other','premise'=>'Other']);
        $foreign=$other->pages()->create(['position'=>1,'title'=>'Other']);
        $this->post(route('pages.manage',$s),['revision'=>1,'action'=>'remove','page_id'=>$foreign->id])->assertNotFound();
        $this->post(route('pages.manage',$other),['revision'=>1,'action'=>'add'])->assertNotFound();
        $this->assertSame(1,$s->fresh()->revision);
        $this->assertSame(0,$s->revisions()->count());
    }
    public function test_hundred_page_limit(): void {
        $s=$this->story(100);
        $this->post(route('pages.manage',$s),['revision'=>1,'action'=>'add'])->assertSessionHasErrors('pages');
        $this->assertSame(100,$s->pages()->count());
    }
}
