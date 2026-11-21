<?php

namespace Tests\Feature;

use App\Http\Controllers\ImageCategoryController;
use App\Models\Story;
use App\Models\User;
use App\Services\ImageDrafts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImageCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_empty_categories_can_be_deleted_in_image_and_audio_libraries(): void
    {
        $user = User::factory()->create();
        $story = Story::create(['user_id' => $user->id, 'title' => 'House', 'premise' => 'Explore']);
        $page = $story->pages()->create(['position' => 1, 'title' => 'Hall']);
        $this->actingAs($user);
        $imageUrl = route('images.categories', $story);
        $this->putJson($imageUrl, ['categories' => []])->assertOk();
        app(ImageDrafts::class)->save($story, $page, ['prompt' => 'A hall']);
        $this->getJson(route('visuals.show', [$story, $page]))->assertJsonPath('image_categories.scene', 'Scene');
        $this->putJson($imageUrl, ['categories' => []])->assertUnprocessable();
        $this->putJson($imageUrl, ['categories' => ['scene' => 'Backgrounds']])->assertOk();
        $audioUrl = '/stories/'.$story->id.'/audio/categories';
        $this->putJson($audioUrl, ['categories' => []])->assertOk();
        \App\Models\StorySound::create(['story_id' => $story->id, 'name' => 'Door', 'category' => 'effects', 'provider' => 'freesound', 'path' => 'door.wav', 'mime' => 'audio/wav']);
        $this->getJson(route('audio.index', $story))->assertJsonPath('categories.effects', 'Effects');
        $this->putJson($audioUrl, ['categories' => []])->assertUnprocessable();
        $this->putJson($audioUrl, ['categories' => ['effects' => 'Sound effects']])->assertOk();
    }

    public function test_categories_and_assignments_persist_and_are_owner_scoped(): void
    {
        $user = User::factory()->create();
        $story = Story::create(['user_id' => $user->id, 'title' => 'House', 'premise' => 'Explore']);
        $page = $story->pages()->create(['position' => 1, 'title' => 'Hall']);
        $job = app(ImageDrafts::class)->save($story, $page, ['prompt' => 'A hall']);
        $url = route('images.categories', $story);
        $categories = ImageCategoryController::DEFAULTS + ['custom_test' => 'Clues'];
        $this->actingAs($user)->putJson($url, ['categories' => $categories])->assertOk();
        $this->putJson($url.'/'.$job->id, ['category' => 'custom_test'])->assertOk();
        $categories['custom_test'] = 'Puzzle clues';
        $this->putJson($url, ['categories' => $categories])->assertOk();
        $this->getJson(route('visuals.show', [$story, $page]))->assertOk()
            ->assertJsonPath('image_categories.custom_test', 'Puzzle clues')
            ->assertJsonPath('jobs.0.category', 'custom_test');
        $this->get(route('images.studio', [$story, $page]))->assertOk()->assertSee('imageCategoriesDialog');
        $this->putJson($url.'/'.$job->id, ['category' => 'missing'])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->putJson($url, ['categories' => $categories])->assertNotFound();
        $this->putJson($url.'/'.$job->id, ['category' => 'scene'])->assertNotFound();
    }
}
