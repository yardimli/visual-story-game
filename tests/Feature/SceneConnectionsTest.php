<?php
namespace Tests\Feature;

use App\Services\{ChatContext,SceneConnections};
use Tests\TestCase;

class SceneConnectionsTest extends TestCase
{
    private function pages(): array
    {
        $scenes=[
            ['id'=>'start','kind'=>'scene','asset_id'=>1,'description'=>'Beginning','hotspots'=>[
                ['id'=>'return','label'=>'Return to finale','action'=>'page','target_page_id'=>5,'target_scene_id'=>'end','flag_conditions'=>[['key'=>'ready','value'=>true]]],
                ['id'=>'unrelated','action'=>'page','target_page_id'=>3,'target_scene_id'=>'remote'],
            ]],
            ['id'=>'branch','kind'=>'scene','asset_id'=>2,'hotspots'=>[]],
            ['id'=>'remote','kind'=>'scene','asset_id'=>3,'hotspots'=>[]],
            ['id'=>'previous','kind'=>'scene','asset_id'=>4,'hotspots'=>[]],
            ['id'=>'end','kind'=>'scene','asset_id'=>5,'hotspots'=>[
                ['id'=>'restart','label'=>'Begin again','action'=>'examine','move_after_examine'=>true,'restart_game'=>true,'target_page_id'=>1,'target_scene_id'=>'start'],
                ['id'=>'switch','action'=>'state_v2','auto_state'=>['enabled'=>true,'original_target'=>'start','changed_target'=>'branch','flag_conditions'=>[['key'=>'open','value'=>true]]]],
                ['id'=>'item','action'=>'inventory','inventory_rules'=>[['object_id'=>'key','target_id'=>'branch','flag_conditions'=>[['key'=>'open','value'=>true]]]]],
                ['id'=>'choice','action'=>'choices','choices'=>[['label'=>'Back','target_id'=>'previous']]],
                ['id'=>'page-only','action'=>'page','target_page_id'=>1],
                ['id'=>'bad','action'=>'page','target_page_id'=>999,'target_scene_id'=>'missing'],
            ]],
        ];
        return array_map(fn($i,$s)=>['id'=>$i+1,'position'=>$i+1,'visual_document'=>['visuals'=>[$s]]],array_keys($scenes),$scenes);
    }

    public function test_direct_connections_survive_compaction_with_conditions_and_media_without_recursive_expansion(): void
    {
        $input=['pages'=>$this->pages(),'references'=>array_map(fn($id)=>['id'=>$id],range(1,5))];
        $service=app(ChatContext::class);
        $scoped=$service->scope($input,5,'end',null);
        $result=$service->compact($scoped,['video'],'Create a return video');
        $this->assertSame(['start','branch'],array_column(array_column($result['connected_scenes'],'scene'),'id'));
        $this->assertSame([1,2,4,5],array_column($result['references'],'id'));
        $this->assertSame(['previous'],array_column(array_column($result['neighbor_scenes'],'scene'),'id'));
        $this->assertCount(7,$result['scene_connections']);
        $incoming=$result['scene_connections'][0];
        $this->assertSame('incoming',$incoming['direction']);
        $this->assertSame('return',$incoming['area_id']);
        $this->assertSame('hotspots.0',$incoming['source_path']);
        $this->assertSame('ready',$result['connected_scenes'][0]['scene']['hotspots'][0]['flag_conditions'][0]['key']);
        $this->assertSame($scoped['connected_scenes'],$result['connected_scenes']);
        $this->assertSame($scoped['scene_connections'],$result['scene_connections']);
        $this->assertTrue($result['connected_scenes'][0]['read_only']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->assertSceneChange(['scope'=>'visual','page_id'=>1,'visual_id'=>'start'], $result['selection'],collect());
    }

    public function test_skip_legacy_and_same_page_links_do_not_duplicate_scene_data(): void
    {
        $pages=[['id'=>1,'visual_document'=>['visuals'=>[
            ['id'=>'a','kind'=>'scene','skip_rules'=>[['target_page_id'=>1,'target_scene_id'=>'b','flag_conditions'=>[['key'=>'skip','value'=>true]]]],'hotspots'=>[
                ['id'=>'legacy','action'=>'transition','target_id'=>'b'],
                ['id'=>'self','action'=>'transition','target_id'=>'a'],
                ['id'=>'object','action'=>'inspect','target_id'=>'object'],
            ]],['id'=>'b','kind'=>'scene'],['id'=>'object','kind'=>'object'],
        ]]]];
        $service=app(SceneConnections::class);
        $result=$service->context($pages,1,'a',[]);
        $this->assertCount(3,$result['scene_connections']);
        $this->assertSame('skip_rules.0',$result['scene_connections'][0]['source_path']);
        $this->assertSame([],$result['connected_scenes']);
        $this->assertSame([], $service->context($pages,1,null,[])['scene_connections']);
    }
}
