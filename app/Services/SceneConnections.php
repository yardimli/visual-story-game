<?php
namespace App\Services;

class SceneConnections
{
    public function context(array $pages, ?int $pageId, ?string $sceneId, array $neighbors): array
    {
        $scenes=[];$first=[];
        foreach ($pages as $page) foreach ($page['visual_document']['visuals'] ?? [] as $scene) {
            if (($scene['kind'] ?? '') !== 'scene') continue;
            $scenes[$scene['id']]=['page_id'=>$page['id'],'scene'=>$scene];
            $first[$page['id']] ??= $scene['id'];
        }
        if (!isset($scenes[$sceneId]) || $scenes[$sceneId]['page_id'] !== $pageId)
            return ['scene_connections'=>[], 'connected_scenes'=>[]];
        $links=[];$included=[];
        foreach ($neighbors as $neighbor) $included[$neighbor['scene']['id']]=true;
        $connected=[];
        foreach ($scenes as $sourceId=>$source) {
            $add=function ($target, $targetPage, string $path, ?array $area=null) use (&$links,&$connected,&$included,$scenes,$first,$sceneId,$pageId,$sourceId,$source) {
                $target=$target ?: ($first[$targetPage] ?? null);
                if (!$target || !isset($scenes[$target])) return;
                if ($targetPage && $scenes[$target]['page_id'] !== $targetPage) return;
                if ($sourceId !== $sceneId && $target !== $sceneId) return;
                $links[]=['direction'=>$sourceId === $sceneId ? 'outgoing' : 'incoming',
                    'source_page_id'=>$source['page_id'],'source_scene_id'=>$sourceId,
                    'target_page_id'=>$scenes[$target]['page_id'],'target_scene_id'=>$target,
                    'source_path'=>$path,'area_id'=>$area['id'] ?? null,'area_label'=>$area['label'] ?? null];
                $other=$sourceId === $sceneId ? $target : $sourceId;
                if ($scenes[$other]['page_id'] !== $pageId && !isset($included[$other])) {
                    $connected[]=$scenes[$other]+['read_only'=>true];
                    $included[$other]=true;
                }
            };
            foreach ($source['scene']['skip_rules'] ?? [] as $i=>$rule)
                $add($rule['target_scene_id'] ?? null,$rule['target_page_id'] ?? null,"skip_rules.$i");
            foreach ($source['scene']['hotspots'] ?? [] as $i=>$area) {
                $path="hotspots.$i";$action=$area['action'] ?? '';
                if ($action === 'page' || ($action === 'examine' && !empty($area['move_after_examine'])) || !empty($area['restart_game']))
                    $add($area['target_scene_id'] ?? null,$area['target_page_id'] ?? null,$path,$area);
                if (in_array($action,['pickup','inspect','transition']))
                    $add($area['target_id'] ?? null,null,$path,$area);
                if ($action === 'state_v2' && !empty($area['auto_state']['enabled']))
                    foreach (['original_target','changed_target'] as $state)
                        $add($area['auto_state'][$state] ?? null,null,"$path.auto_state.$state",$area);
                foreach (['inventory_rules','choices'] as $rules)
                    foreach ($area[$rules] ?? [] as $j=>$rule)
                        $add($rule['target_id'] ?? null,null,"$path.$rules.$j",$area);
            }
        }
        return ['scene_connections'=>$links,'connected_scenes'=>$connected];
    }
}
