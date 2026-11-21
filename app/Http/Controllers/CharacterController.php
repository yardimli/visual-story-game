<?php

namespace App\Http\Controllers;

use App\Models\{Story, StoryCharacter, ImageGeneration};
use App\Services\{CharacterLibrary, ImageDrafts};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CharacterController extends Controller
{
    private function owner(Request $request, Story $story, ?StoryCharacter $character = null): void
    {
        abort_unless($story->user_id === $request->user()->id && (!$character || $character->story_id === $story->id), 404);
    }
    public function index(Request $request, Story $story, CharacterLibrary $library)
    {
        $this->owner($request, $story);
        return response()->json(['characters' => $library->present($story), 'assets' => $story->assets()->whereNull('archived_at')->where('role', '!=', 'extraction_reference')->latest()->get()->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'url' => route('assets.show', $a)])]);
    }
    public function save(Request $request, Story $story, ?StoryCharacter $character = null)
    {
        $this->owner($request, $story, $character);
        $data = $request->validate([
            'revision' => 'required|integer|min:0', 'name' => 'required|string|max:160', 'description' => 'nullable|string|max:5000',
            'views' => 'present|array|max:16', 'views.*' => 'array:label,asset_id,image_id', 'views.*.label' => 'required|string|max:160',
            'views.*.asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('story_id', $story->id)->whereNull('archived_at')],
            'views.*.image_id' => ['nullable', 'integer', Rule::exists('image_generations', 'id')->where('story_id', $story->id)],
        ]);
        foreach ($data['views'] as $view) abort_unless(!empty($view['asset_id']) || !empty($view['image_id']), 422, 'Choose an image for each view.');
        $saved = DB::transaction(function () use ($story, $character, $data) {
            $record = $character ? StoryCharacter::whereKey($character->id)->lockForUpdate()->firstOrFail() : new StoryCharacter(['id' => (string) Str::uuid(), 'story_id' => $story->id]);
            abort_if($character && $record->revision !== $data['revision'], 409, 'This character changed elsewhere. Close and reopen it before editing.');
            $record->fill($data); $record->revision = ($record->revision ?? 0) + 1; $record->save();
            foreach ($data['views'] as $view) {
                if (!empty($view['asset_id'])) {
                    $story->assets()->whereKey($view['asset_id'])->update(['role' => 'character']);
                    ImageGeneration::where('story_id', $story->id)->whereJsonContains('asset_ids', (int) $view['asset_id'])->update(['category' => 'characters']);
                }
                if (!empty($view['image_id'])) ImageGeneration::where('story_id', $story->id)->whereKey($view['image_id'])->update(['category' => 'characters']);
            }
            return $record;
        });
        return response()->json(['id' => $saved->id, 'revision' => $saved->revision], $character ? 200 : 201);
    }
    public function destroy(Request $request, Story $story, StoryCharacter $character)
    {
        $this->owner($request, $story, $character);
        $data = $request->validate(['revision' => 'required|integer']);
        abort_unless(StoryCharacter::whereKey($character->id)->where('revision', $data['revision'])->delete(), 409, 'This character changed elsewhere. Reload before deleting.');
        return response()->json(['deleted' => $character->id]);
    }
    public function draft(Request $request, Story $story, StoryCharacter $character, ImageDrafts $drafts)
    {
        $this->owner($request, $story, $character);
        $data = $request->validate(['revision' => 'required|integer', 'page_id' => ['required', 'integer', Rule::exists('story_pages', 'id')->where('story_id', $story->id)], 'label' => 'required|string|max:160']);
        return DB::transaction(function () use ($story, $character, $drafts, $data) {
            $record = StoryCharacter::whereKey($character->id)->lockForUpdate()->firstOrFail();
            abort_if($record->revision !== $data['revision'], 409, 'This character changed elsewhere. Reload before preparing a view.');
            $views = $record->views ?? []; abort_if(count($views) >= 16, 422, 'A character can have up to 16 views.');
            $resolved = collect(app(CharacterLibrary::class)->present($story))->firstWhere('id', $record->id);
            $references = array_values(array_unique(array_filter(array_column($resolved['views'], 'asset_id'))));
            $page = $story->pages()->findOrFail($data['page_id']);
            $image = $drafts->save($story, $page, ['name' => mb_substr($record->name.' · '.$data['label'], 0, 160), 'model' => $story->image_model ?? '', 'prompt' => 'Create a character reference view of '.$record->name.'. View: '.$data['label'].'. Appearance: '.$record->description.'. Use a simple neutral background and preserve the identity in the supplied reference views. Show only this character with clearly readable features. Story visual direction: '.$story->style, 'references' => $references]);
            $image->update(['category' => 'characters']);
            $views[] = ['label' => $data['label'], 'image_id' => $image->id];
            $record->update(['views' => $views, 'revision' => $record->revision + 1]);
            return response()->json(['url' => route('images.studio', [$story, $page, 'image' => $image->id, 'open' => 1])], 201);
        });
    }
}
