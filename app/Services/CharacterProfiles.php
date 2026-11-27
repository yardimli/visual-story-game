<?php
namespace App\Services;
use App\Models\{Story, StoryCharacter, ImageGeneration};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CharacterProfiles {
    public function validate(Story $story, array $input): array {
        $data = validator($input, [
            'revision' => 'required|integer|min:0', 'name' => 'required|string|max:160', 'description' => 'nullable|string|max:5000',
            'views' => 'present|array|max:16', 'views.*' => 'array:label,asset_id,image_id', 'views.*.label' => 'required|string|max:160',
            'views.*.asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('story_id', $story->id)->where(fn ($query) => $query->where('mime', 'like', 'image/%'))->whereNull('archived_at')],
            'views.*.image_id' => ['nullable', 'integer', Rule::exists('image_generations', 'id')->where('story_id', $story->id)],
        ])->validate();
        foreach ($data['views'] as $view) if(empty($view['asset_id']) && empty($view['image_id'])) throw \Illuminate\Validation\ValidationException::withMessages(['views'=>'Choose an image for each view.']);
        return $data;
    }
    public function save(Story $story, array $input, ?StoryCharacter $character = null): StoryCharacter {
        abort_unless(!$character || $character->story_id === $story->id, 404);
        $data = $this->validate($story, $input);
        return DB::transaction(function () use ($story, $character, $data) {
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
    }
}
