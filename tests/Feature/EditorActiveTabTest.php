<?php
namespace Tests\Feature;
use App\Models\{User,Story};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorActiveTabTest extends TestCase {
    use RefreshDatabase;
    public function test_only_selected_tab_and_page_are_rendered(): void {
        $user=User::factory()->create();
        $story=Story::create(['user_id'=>$user->id,'title'=>'Editor','premise'=>'Test']);
        $first=$story->pages()->create(['position'=>1,'title'=>'First','narrative'=>'First page prose']);
        $second=$story->pages()->create(['position'=>2,'title'=>'Second','narrative'=>'Other page prose must not be mounted']);
        $this->actingAs($user)->get(route('stories.edit',[$story,'page'=>$first->id,'tab'=>'visuals']))
            ->assertOk()->assertSee('x-data="sceneEditor(',false)
            ->assertDontSee('x-data="storyVideo(',false)->assertDontSee('x-data="inventoryEditor(',false)
            ->assertDontSee('data-companion-form="story"',false)->assertDontSee('Other page prose must not be mounted');
        $this->get(route('stories.edit',[$story,'page'=>$first->id,'tab'=>'page']))->assertOk()
            ->assertSee('First page prose')->assertDontSee('Other page prose must not be mounted')
            ->assertDontSee('x-data="sceneEditor(',false);
        foreach(['story','inventory','audio','video'] as $tab)
            $this->get(route('stories.edit',[$story,'page'=>$second->id,'tab'=>$tab]))->assertOk()->assertDontSee('x-data="sceneEditor(',false);
    }
}
