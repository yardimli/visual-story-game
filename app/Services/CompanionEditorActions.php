<?php
namespace App\Services;

use App\Models\Story;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Explicit bridge to the same story-owned commands used by the editor forms. */
class CompanionEditorActions
{
    private const COMMANDS = [
        'scene.save' => ['SceneController', 'update', 'page', 'document:{visuals:[complete page visuals]}; preserves the manual editor deletion/link cleanup'],
        'inventory.save' => ['InventoryController', 'update', '', 'items (complete inventory objects including actions)'],
        'page.structure' => ['StudioController', 'structure', '', 'action:add|insert|up|down|remove, page_id for all except add'],
        'character.view' => ['CharacterController', 'draft', 'character', 'page_id,label; prepare and attach an unrendered character view'],
        'character.delete' => ['CharacterController', 'destroy', 'character', 'Delete this character profile'],
        'flags.rename' => ['StoryStateController', 'rename', 'flag', 'key,label; renames references throughout the game'],
        'flags.delete' => ['StoryStateController', 'destroy', 'flag', 'Delete flag and clean its references'],
        'video.edit' => ['VideoController', 'update', 'video', 'name,prompt,engine,parameters,first_asset_id,last_asset_id,reference_asset_ids; full draft form'],
        'video.generate' => ['VideoController', 'queue', 'video', 'Queue this saved draft; consumes generation credits'],
        'video.delete' => ['VideoController', 'destroy', 'video', 'Delete video and clear scene assignments'],
        'image.draft' => ['SceneController', 'draft', 'page', 'name,prompt,video_prompt,model,references,settings; draft_id when editing an existing draft'],
        'image.generate' => ['SceneController', 'generate', 'page', 'name,visual_id or draft_id,model,references,settings; starts paid generation'],
        'image.retry' => ['SceneController', 'retry', 'page,image', 'Retry failed image; starts paid generation'],
        'image.delete' => ['SceneController', 'deleteDraft', 'image', 'Delete image draft using the editor deletion rules'],
        'image.move' => ['SceneController', 'moveImage', 'page,image', 'visual_id; assign completed generation to a visual'],
        'image.categories' => ['ImageCategoryController', 'save', '', 'categories: array of category names'],
        'image.category' => ['ImageCategoryController', 'assign', 'job:ImageGeneration', 'category: existing category key'],
        'image.order' => ['StoryStateController', 'order', '', 'order: image generation IDs'],
        'asset.edit' => ['AssetController', 'update', 'asset', 'name, archived; rename or archive/restore an existing image'],
        'story.restore' => ['StudioController', 'restore', 'revision:StoryRevision', 'Restore a saved story revision from History'],
        'audio.settings' => ['AudioController', 'settings', '', 'music_id,music_loop,defaults:{ACTION:{click,action}},voices:{scene|area|inventory:{model,voice,instructions,temperature,language}}'],
        'audio.edit' => ['AudioManageController', 'update', 'job:AudioGeneration', 'name,prompt,source_text,category,model,settings,seconds; full draft form'],
        'audio.generate' => ['AudioManageController', 'start', 'job:AudioGeneration', 'Start saved audio draft; consumes generation credits'],
        'audio.delete_draft' => ['AudioManageController', 'destroyJob', 'job:AudioGeneration', 'Delete pending audio request'],
        'audio.delete' => ['AudioManageController', 'destroySound', 'sound', 'confirm:true; delete sound and clear assignments'],
        'audio.categories' => ['AudioController', 'saveCategories', '', 'categories: array of names'],
        'audio.category' => ['AudioController', 'categorize', 'sound', 'category: existing category key'],
        'container.rename' => ['ContainerController', 'rename', 'page,plan', 'name'],
        'container.prepare' => ['ContainerController', 'prepare', 'page,plan', 'preview_asset_id,contents:[{area_id,bounds:{x,y,width,height},padding:{top,right,bottom,left},equal_padding}]'],
        'container.activate' => ['ContainerController', 'activate', 'page,plan', 'Apply accepted container outputs to scene'],
        'container.delete' => ['ContainerController', 'destroy', 'page', 'visual_id,area_id; delete container and its plans'],
        'extraction.prepare' => ['SceneExtractionController', 'store', 'page', 'visual_id,mode:collectibles|switches,areas:[{id,bounds:{x,y,width,height},padding:{top,right,bottom,left},equal_padding,initial_empty}],background_references,optional update_plan_id'],
        'extraction.rename' => ['SceneExtractionController', 'rename', 'page,extraction', 'name'],
        'extraction.output' => ['SceneExtractionController', 'output', 'page,extraction,output', 'action:generate|accept|unaccept|align|references|switch_references; prompt,model,asset_id,bounds,reference_mode,extra_references as applicable. generate starts paid rendering'],
        'extraction.activate' => ['SceneExtractionController', 'activate', 'page,extraction', 'Apply accepted outputs to scene'],
        'extraction.delete' => ['SceneExtractionController', 'destroy', 'page,extraction', 'Delete plan; keep scene settings and images'],
        'scene.capture' => ['SceneStateImageController', 'store', 'page', 'scene_id,area_ids,name; capture combined scene image for image/video reference'],
        'story.publish' => ['PublicPlayController', 'enable', '', 'Enable public play link'],
        'story.unpublish' => ['PublicPlayController', 'disable', '', 'Disable public play link'],
    ];

    public function catalog(): array
    {
        return array_map(fn ($c) => ['targets' => $c[2], 'input' => $c[3]], self::COMMANDS);
    }

    public function reviewTitle(array $data): string
    {
        $operation=$data['operation'];
        $paid=str_ends_with($operation,'.generate') || $operation==='image.retry' || ($operation==='extraction.output' && ($data['input']['action']??null)==='generate');
        $prefix=$paid?'Start generation (uses credits)':(str_contains($operation,'delete')?'Delete':(str_contains($operation,'publish')?'Change public access':'Update editor'));
        return $prefix.' / '.$operation;
    }

    private function resolve(Story $story, string $operation, array $targets, bool $lock = false): array
    {
        $command = self::COMMANDS[$operation] ?? null;
        if (!$command) $this->invalid('Unsupported editor command.');
        $models = ['page'=>'StoryPage','character'=>'StoryCharacter','video'=>'VideoGeneration','image'=>'ImageGeneration','sound'=>'StorySound','plan'=>'ContainerPlan','extraction'=>'SceneExtraction','asset'=>'Asset'];
        $resolved = [];
        foreach (array_filter(explode(',', $command[2])) as $target) {
            [$name, $type] = array_pad(explode(':', $target), 2, null);
            $value = $targets[$name] ?? null;
            if (in_array($name, ['flag', 'output'])) {
                if (!is_string($value) || strlen($value)>160) $this->invalid('Missing editor target: '.$name);
                if ($name === 'flag' && !in_array($value, array_column($story->game_flags ?? [], 'key'), true)) $this->invalid('Unknown story flag.');
                $resolved[$name] = $value;
                continue;
            }
            $class = 'App\\Models\\'.($type ?? $models[$name]);
            $query = $class::where('story_id', $story->id)->whereKey($value);
            $record = ($lock ? $query->lockForUpdate() : $query)->first();
            if (!$record) $this->invalid('Editor target is missing or belongs to another story: '.$name);
            $resolved[$name] = $record;
        }
        return $resolved;
    }

    private function fingerprint(Story $story, array $targets): string
    {
        return hash('sha256', json_encode([$story->getAttributes(),
            $story->pages()->get()->map->getAttributes()->all(),
            array_map(fn ($value) => $value instanceof Model ? $value->getAttributes() : $value, $targets)]));
    }

    public function prepare(Story $story, array $fields): array
    {
        $data = validator($fields, ['operation'=>'required|string','targets'=>'present|array','input'=>'present|array'])->validate();
        $resolved = $this->resolve($story, $data['operation'], $data['targets']);
        if ($data['operation'] === 'scene.save') {
            $input = validator($data['input'], ['document'=>'required|array'])->validate();
            // The manual controller cleans links to removed scenes before full validation.
            validator($input['document'], ['visuals'=>'present|array|max:100','visuals.*.id'=>'required|uuid|distinct','visuals.*.hotspots'=>'present|array|max:100'])->validate();
        }
        if ($data['operation'] === 'inventory.save') {
            $input = validator($data['input'], ['items'=>'present|array|max:100'])->validate();
            $candidate=clone $story;$candidate->inventory_document=['visuals'=>$input['items']];
            $data['input']['items'] = app(SceneDocument::class)->validate($candidate, ['visuals'=>$input['items']])['visuals'];
        }
        $data['fingerprint'] = $this->fingerprint($story, $resolved);
        // A retry of the same approved generation uses the same request key.
        $data['input']['request_key'] ??= (string) Str::uuid();
        return $data;
    }

    public function apply(Story $story, array $data): array
    {
        $resolved = $this->resolve($story, $data['operation'], $data['targets'], true);
        if (!hash_equals($data['fingerprint'], $this->fingerprint($story, $resolved))) $this->invalid('This editor form changed. Ask for a fresh proposal.');
        [$controller, $method] = self::COMMANDS[$data['operation']];
        $input = $data['input'];
        $input['revision'] ??= match (true) {
            str_starts_with($data['operation'], 'flags.') => $story->flags_revision,
            $data['operation'] === 'inventory.save' => $story->inventory_revision,
            isset($resolved['character']) => $resolved['character']->revision,
            isset($resolved['page']) => $resolved['page']->visual_revision,
            default => $story->revision,
        };
        $input['inventory_revision'] ??= $story->inventory_revision;
        if ($data['operation'] === 'flags.delete') $input['fingerprint'] = app(StoryFlags::class)->usage($story, $resolved['flag'], $story->pages()->get())['fingerprint'];
        $request = Request::create('/companion/editor-action', 'POST', $input);
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => auth()->user());
        if (request()->hasSession()) $request->setLaravelSession(request()->session());
        $instance = app('App\\Http\\Controllers\\'.$controller);
        $arguments = [];
        foreach ((new \ReflectionMethod($instance, $method))->getParameters() as $parameter) {
            $name = $parameter->getName();
            if ((string)$parameter->getType() === Request::class) $arguments[$name] = $request;
            elseif ((string)$parameter->getType() === Story::class) $arguments[$name] = $story;
            elseif (array_key_exists($name, $resolved)) $arguments[$name] = $resolved[$name];
        }
        $response = app()->call([$instance, $method], $arguments);
        if ($response instanceof \Symfony\Component\HttpFoundation\Response && $response->getStatusCode() >= 400) $this->invalid('Editor command failed: '.$response->getContent());
        // Controllers may have changed revisions themselves. Refresh before the caller increments.
        $story->refresh();
        return $response instanceof \Illuminate\Http\JsonResponse ? $response->getData(true) : [];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['editor_action' => $message]);
    }
}
