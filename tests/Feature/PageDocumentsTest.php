<?php
namespace Tests\Feature;
use App\Models\{User,Story};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
class PageDocumentsTest extends TestCase {
 use RefreshDatabase;
 private function story(){ $user=User::factory()->create();$this->actingAs($user);$s=Story::create(['user_id'=>$user->id,'title'=>'Documents','premise'=>'Draft']);$s->pages()->create(['position'=>1,'title'=>'First']);$s->pages()->create(['position'=>2,'title'=>'Second']);return $s; }
 public function test_rich_text_saves_and_plain_companion_edits_clear_old_formatting(): void {
  $s=$this->story();$p=$s->pages()->first();$delta=['ops'=>[['insert'=>'Marked','attributes'=>['bold'=>true,'background'=>'#fef08a']],['insert'=>"\n"]]];
  $this->putJson(route('pages.update',[$s,$p]),['revision'=>1,'title'=>'First','narrative'=>'stale','narrative_delta'=>json_encode($delta)])->assertOk();
  $this->assertSame('Marked',$p->fresh()->narrative);$this->assertSame($delta,$p->fresh()->narrative_delta);
  $p->refresh()->update(['narrative'=>'New prose']);$this->assertNull($p->fresh()->narrative_delta);
  $this->assertFalse(Schema::hasColumn('story_pages','scene_prompt'));$this->assertFalse(Schema::hasColumn('story_pages','video_prompt'));
  $this->putJson(route('pages.update',[$s,$p]),['revision'=>2,'title'=>'First','scene_prompt'=>'Old field'])->assertUnprocessable();
 }
 public function test_rich_documents_reject_embeds_and_unsafe_attributes(): void {
  $s=$this->story();$p=$s->pages()->first();
  foreach([['insert'=>['image'=>'https://example.com/x']],['insert'=>'bad','attributes'=>['link'=>'javascript:alert(1)']]] as $op)$this->putJson(route('pages.update',[$s,$p]),['revision'=>1,'title'=>'First','narrative_delta'=>json_encode(['ops'=>[$op]])])->assertUnprocessable();
 }
 public function test_insert_after_selected_page_preserves_order_and_rejects_foreign_page(): void {
  $s=$this->story();[$first,$second]=$s->pages()->pluck('id')->all();
  $this->post(route('pages.manage',$s),['revision'=>1,'action'=>'insert','page_id'=>$first])->assertSessionHasNoErrors();
  $pages=$s->pages()->get();$this->assertSame([$first,$pages[1]->id,$second],$pages->pluck('id')->all());$this->assertNull($pages[1]->narrative);$this->assertSame([1,2,3],$pages->pluck('position')->all());
  $count=$pages->count();$this->post(route('pages.manage',$s),['revision'=>2,'action'=>'insert','page_id'=>99999])->assertNotFound();$this->assertSame($count,$s->pages()->count());
 }
}
