<?php

namespace App\Services;

class ChatContext
{
    public function compact(array $context, array $families, string $message): array
    {
        // Keep canonical HTML (including formatting); editor deltas repeat it.
        foreach ($context['pages'] ?? [] as $i=>$page) {
            unset($context['pages'][$i]['narrative_delta'], $context['pages'][$i]['interaction_plan_delta']);
        }
        if (!in_array('history', $families)) {
            $stripVersions = function ($value) use (&$stripVersions) {
                if (!is_array($value)) return $value;
                unset($value['previous_backgrounds'], $value['versions']);
                return array_map($stripVersions, $value);
            };
            $context['extraction_plans'] = $stripVersions($context['extraction_plans'] ?? []);
        }
        if (!in_array('image', $families) && !in_array('history', $families)) {
            $used = [];
            $collect = function ($value, $key = '') use (&$collect, &$used) {
                if (is_array($value)) {
                    if (in_array($key, ['asset_ids','reference_asset_ids','references','extra_references','selected_reference_ids'])) {
                        foreach ($value as $id) if (is_numeric($id)) $used[(int)$id] = true;
                    }
                    foreach ($value as $k=>$v) $collect($v, (string)$k);
                } elseif (str_ends_with($key, 'asset_id') && is_numeric($value)) $used[(int)$value] = true;
            };
            foreach (['pages','inventory_items','characters','extraction_plans','container_plans','image_drafts','video_jobs','current_image_draft','selected_reference_ids'] as $key) $collect($context[$key] ?? [], $key);
            $context['image_jobs'] = array_values(array_filter($context['image_jobs'] ?? [], function ($job) use ($used, $message) {
                return in_array($job['status'] ?? '', ['draft','queued','running'])
                    || array_intersect($job['asset_ids'] ?? [], array_keys($used))
                    || (!empty($job['name']) && mb_stripos($message, $job['name']) !== false)
                    || (!empty($job['id']) && preg_match('/(?<!\d)'.preg_quote((string)$job['id'], '/').'(?!\d)/', $message));
            }));
        }
        // Recompute dependencies after removing old render versions/history. Keep
        // the original cross-page directory, since other full documents are absent.
        $directory = $context['scene_directory'] ?? [];
        $selection = $context['selection'];
        $context = $this->scope($context, $selection['page_id'], $selection['visual_id'], $selection['hotspot_id']);
        $context['scene_directory'] = $directory;
        foreach ($context['characters'] ?? [] as $i=>$character) {
            foreach ($character['views'] ?? [] as $j=>$view) unset($context['characters'][$i]['views'][$j]['url'], $context['characters'][$i]['views'][$j]['image_url']);
        }
        foreach ($context['sounds'] ?? [] as $i=>$sound) unset($context['sounds'][$i]['url']);
        return $context;
    }

    public function assertSceneChange(array $request, array $selection, $pages): void
    {
        $scope = $request['scope'] ?? '';
        $pageId = $request['page_id'] ?? null;
        $visualId = $request['visual_id'] ?? null;
        $document = null;
        if ($scope === 'editor_action') {
            $fields = $request['fields'] ?? [];
            $operation = $fields['operation'] ?? '';
            $input = $fields['input'] ?? [];
            $pageId = $fields['targets']['page'] ?? null;
            $visualId = $input['visual_id'] ?? $input['scene_id'] ?? null;
            if ($operation === 'scene.save') $document = $input['document']['visuals'] ?? [];
            if (in_array($operation, ['container.activate','container.prepare','container.rename'])) {
                $plan = \App\Models\ContainerPlan::find($fields['targets']['plan'] ?? null);
                $visualId = $plan?->visual_id;
            }
            if (str_starts_with($operation, 'extraction.') && isset($fields['targets']['extraction'])) {
                $plan = \App\Models\SceneExtraction::find($fields['targets']['extraction']);
                $visualId = $plan?->visual_id;
            }
            if (!$visualId && $document === null) return;
        } elseif ($scope === 'scene_document') {
            $document = $request['fields']['visuals'] ?? [];
        } elseif (!in_array($scope, ['visual','hotspot','create_hotspot','copy_areas','object','create_container_plan'])) {
            return;
        }
        $fail = fn () => throw \Illuminate\Validation\ValidationException::withMessages([
            'scene'=>'Open the scene you want to edit before requesting changes in chat. Cross-scene navigation destinations are allowed.',
        ]);
        if (!$selection['page_id'] || !$selection['visual_id'] || (int)$pageId !== (int)$selection['page_id']) $fail();
        if ($document !== null) {
            $before = collect($pages)->firstWhere('id', (int)$pageId)?->visual_document['visuals'] ?? [];
            $others = fn ($visuals) => array_values(array_filter($visuals, fn ($v) => ($v['id'] ?? null) !== $selection['visual_id']));
            if ($others($before) !== $others($document)) $fail();
        } elseif ($visualId !== $selection['visual_id']) $fail();
    }

    public function scope(array $context, ?int $pageId, ?string $visualId, ?string $hotspotId): array
    {
        $current = collect($context['pages'] ?? [])->firstWhere('id', $pageId);
        $visuals = $current['visual_document']['visuals'] ?? [];
        $context['scene_directory'] = collect($context['pages'] ?? [])->flatMap(fn ($page) =>
            collect($page['visual_document']['visuals'] ?? [])->where('kind', 'scene')->map(fn ($scene) =>
                ['page_id'=>$page['id'], 'id'=>$scene['id'], 'name'=>$scene['name'] ?? '']))->values()->all();
        $context['pages'] = array_map(function ($page) use ($pageId) {
            if ($page['id'] !== $pageId) unset($page['visual_document'], $page['visual_revision']);
            return $page;
        }, $context['pages'] ?? []);
        $context['selection'] = ['page_id'=>$pageId, 'visual_id'=>$visualId, 'hotspot_id'=>$hotspotId];
        foreach (['extraction_plans','container_plans','image_jobs','image_drafts'] as $key) {
            $context[$key] = array_values(array_filter($context[$key] ?? [], fn ($row) =>
                $pageId !== null && ($row['story_page_id'] ?? $row['page_id'] ?? null) === $pageId));
        }
        // Media has no page foreign key. Follow actual references and narration text,
        // including shared inventory/characters/default sounds and explicit attachments.
        $assets = []; $sounds = []; $videos = []; $texts = [];
        $walk = function ($value, $key = '') use (&$walk, &$assets, &$sounds, &$videos, &$texts) {
            if (is_array($value)) {
                if (in_array($key, ['asset_ids','reference_asset_ids','references','extra_references','background_references','selected_reference_ids'])) {
                    foreach ($value as $id) if (is_numeric($id)) $assets[(int)$id] = true;
                }
                foreach ($value as $k=>$v) $walk($v, (string)$k);
            } elseif (is_string($value) || is_numeric($value)) {
                if (str_ends_with($key, 'asset_id') && is_numeric($value)) $assets[(int)$value] = true;
                if ((str_ends_with($key, 'sound_id') || in_array($key, ['music_id','click','action'])) && is_numeric($value)) $sounds[(int)$value] = true;
                if ($key === 'intro_video_id') $videos[(string)$value] = true;
                if (is_string($value)) $texts[trim(preg_replace('/\s+/u', ' ', $value))] = true;
            }
        };
        $walk($visuals);
        foreach (['extraction_plans','container_plans','image_jobs','image_drafts'] as $key) $walk($context[$key] ?? [], $key);
        $pageAssets = $assets;
        foreach (['inventory_items','characters','audio_settings','story','current_image_draft','selected_reference_ids'] as $key) $walk($context[$key] ?? [], $key);
        foreach ($context['attached_images'] ?? [] as $image) $assets[$image['id']] = true;
        $context['video_jobs'] = array_values(array_filter($context['video_jobs'] ?? [], fn ($video) =>
            isset($videos[$video['id']]) || isset($pageAssets[$video['first_asset_id'] ?? '']) || isset($pageAssets[$video['last_asset_id'] ?? ''])));
        $walk($context['video_jobs']);
        $context['references'] = array_values(array_filter($context['references'] ?? [], fn ($asset) => isset($assets[$asset['id']])));
        foreach (['sounds','audio_drafts'] as $key) $context[$key] = array_values(array_filter($context[$key] ?? [], fn ($sound) =>
            ($key === 'sounds' && isset($sounds[$sound['id']])) ||
            (!empty($sound['source_text']) && isset($texts[trim(preg_replace('/\s+/u',' ', $sound['source_text']))]))));
        return $context;
    }
}
