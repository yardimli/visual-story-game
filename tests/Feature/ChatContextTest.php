<?php
namespace Tests\Feature;

use App\Services\ChatContext;
use Tests\TestCase;

class ChatContextTest extends TestCase
{
    public function test_neighbors_follow_page_and_scene_order_and_survive_compaction(): void
    {
        $scene=fn($id)=>['id'=>$id,'kind'=>'scene','description'=>'Description '.$id,
            'hotspots'=>[['id'=>'door-'.$id,'flag_conditions'=>[['key'=>'unlocked','value'=>true]],'state_description'=>'Open']]];
        $context=['pages'=>[
            ['id'=>30,'position'=>3,'visual_document'=>['visuals'=>[$scene('next'),$scene('distant')]]],
            ['id'=>10,'position'=>1,'visual_document'=>['visuals'=>[$scene('earlier'),$scene('previous'),['id'=>'item','kind'=>'object']]]],
            ['id'=>20,'position'=>2,'visual_document'=>['visuals'=>[$scene('current')]]],
        ]];
        $service=app(ChatContext::class);
        $scoped=$service->scope($context,20,'current',null);
        $result=$service->compact($scoped,['scene'],'Change this description');
        $this->assertSame(['previous','next'],array_column($result['neighbor_scenes'],'relation'));
        $this->assertSame([10,30],array_column($result['neighbor_scenes'],'page_id'));
        $this->assertSame([$scene('previous'),$scene('next')],array_column($result['neighbor_scenes'],'scene'));
        $this->assertSame([true,true],array_column($result['neighbor_scenes'],'read_only'));
        $this->assertSame($scoped['neighbor_scenes'],$result['neighbor_scenes']);
        $this->assertArrayNotHasKey('visual_document',$result['pages'][0]);
        // A neighbor already included on the current page is not duplicated.
        $first=$service->scope($context,10,'earlier',null);
        $this->assertSame([],$first['neighbor_scenes']);
        $last=$service->scope($context,30,'distant',null);
        $this->assertSame([],$last['neighbor_scenes']);
        $none=$service->scope($context,20,null,null);
        $this->assertSame([],$none['neighbor_scenes']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->assertSceneChange(['scope'=>'visual','page_id'=>10,'visual_id'=>'previous'], $result['selection'], collect());
    }

    public function test_recent_images_survive_page_scope_and_compaction_without_importing_other_scenes(): void
    {
        $service=app(ChatContext::class);
        $context=['pages'=>[
            ['id'=>18,'visual_document'=>['visuals'=>[['id'=>'open','kind'=>'scene','asset_id'=>165]]]],
            ['id'=>17,'visual_document'=>['visuals'=>[['id'=>'closed','kind'=>'scene','asset_id'=>34]]]],
        ],'recent_image_ids'=>[176],
            'references'=>[['id'=>165],['id'=>176,'name'=>'Staircase chest — sockets slotted and lock is open','role'=>'state_composite'],['id'=>34]],
            'image_jobs'=>[['id'=>112,'story_page_id'=>17,'status'=>'completed','asset_ids'=>[176]]]];
        $result=$service->compact($service->scope($context,18,'open',null),['video'],'Create a video');
        $this->assertSame([165,176,34],array_column($result['references'],'id'));
        $this->assertSame($context['references'][1],$result['references'][1]);
        $this->assertSame([],$result['image_jobs']);
        $this->assertArrayNotHasKey('visual_document',$result['pages'][1]);
    }

    public function test_video_library_survives_page_filtering_and_compaction_with_saved_last_frame(): void
    {
        $context=['active_tab'=>'video','pages'=>[['id'=>1,'visual_document'=>['visuals'=>[]]]],
            'video_jobs'=>[['id'=>'chest-video','name'=>'Approach chest','status'=>'draft','prompt'=>'Match the final frame','first_asset_id'=>172,'last_asset_id'=>48,'reference_asset_ids'=>[99]]],
            'references'=>[['id'=>172],['id'=>48],['id'=>99]]];
        $service=app(ChatContext::class);
        $result=$service->compact($service->scope($context,1,null,null),['video'],'Check the last frame');
        $this->assertSame($context['video_jobs'],$result['video_jobs']);
        $this->assertSame($context['references'],$result['references']);
        $context['active_tab']='visuals';
        $this->assertSame([],$service->scope($context,1,null,null)['video_jobs']);
    }
    public function test_compaction_keeps_live_game_data_and_drops_only_redundancy_and_unused_renders(): void
    {
        $scene=['id'=>'a','kind'=>'scene','name'=>'Room','asset_id'=>1,'hotspots'=>[['id'=>'door','description'=>'Exact clue','flag_conditions'=>[['key'=>'key_found','value'=>true]]]]];
        $input=[
            'pages'=>[['id'=>1,'narrative'=>'<p><strong>Exact prose</strong></p>','narrative_delta'=>['ops'=>[['insert'=>'Exact prose']]],'interaction_plan_delta'=>['ops'=>[]],'visual_document'=>['visuals'=>[$scene]]]],
            'extraction_plans'=>[['story_page_id'=>1,'outputs'=>[['id'=>'out','accepted_asset_id'=>2,'versions'=>[20,21],'previous_backgrounds'=>[['accepted_asset_id'=>3]]]]]],
            'image_jobs'=>[['id'=>1,'story_page_id'=>1,'status'=>'completed','asset_ids'=>[1]],['id'=>3,'story_page_id'=>1,'status'=>'completed','asset_ids'=>[3]],['id'=>4,'story_page_id'=>1,'status'=>'running','asset_ids'=>[]]],
            'references'=>[['id'=>1],['id'=>2],['id'=>3]],
            'characters'=>[['name'=>'Visitor','views'=>[['asset_id'=>2,'label'=>'Front','url'=>'/image/2']]]],
            'inventory_items'=>[['id'=>'key','description'=>'Keep this']], 'game_flags'=>[['key'=>'key_found','initial'=>false]],
        ];
        $service=app(ChatContext::class);
        $scoped=$service->scope($input,1,'a',null);
        $scoped['scene_directory'][]=['page_id'=>2,'id'=>'b','name'=>'Other room'];
        $result=$service->compact($scoped,['scene'],'Clarify the door clue');
        $this->assertSame($scene,$result['pages'][0]['visual_document']['visuals'][0]);
        $this->assertSame($input['inventory_items'],$result['inventory_items']);
        $this->assertSame($input['game_flags'],$result['game_flags']);
        $this->assertSame($scoped['scene_directory'],$result['scene_directory']);
        $this->assertSame('<p><strong>Exact prose</strong></p>',$result['pages'][0]['narrative']);
        $this->assertArrayNotHasKey('narrative_delta',$result['pages'][0]);
        $this->assertSame([1,4],array_column($result['image_jobs'],'id'));
        $this->assertSame([1,2],array_column($result['references'],'id'));
        $this->assertSame(2,$result['extraction_plans'][0]['outputs'][0]['accepted_asset_id']);
        $this->assertArrayNotHasKey('versions',$result['extraction_plans'][0]['outputs'][0]);
        $historical=$service->compact($scoped,['history'],'Restore an earlier background');
        $this->assertSame([20,21],$historical['extraction_plans'][0]['outputs'][0]['versions']);
        $this->assertCount(3,$historical['image_jobs']);
    }

    public function test_instruction_routing_preserves_cross_tab_requests_and_ambiguous_followups(): void
    {
        $service=app(\App\Services\ChatInstructions::class);
        $scene=$service->build(['active_tab'=>'visuals','message'=>'Change this door description']);
        $this->assertStringNotContainsString('Video engines:',$scene);
        $this->assertStringNotContainsString('"audio.generate":',$scene);
        $this->assertStringContainsString('"scene.save":',$scene);
        $video=$service->build(['active_tab'=>'visuals','message'=>'Generate a video with a character reference']);
        $this->assertStringContainsString('Video engines:',$video);
        $this->assertStringContainsString('"video.generate":',$video);
        $followup=$service->build(['active_tab'=>'visuals','message'=>'Yes do that'],[['role'=>'assistant','content'=>'Prepare a video draft?']]);
        $this->assertStringContainsString('Video engines:',$followup);
        $unknown=$service->build(['active_tab'=>'story','message'=>'はい']);
        $this->assertStringContainsString('"audio.generate":',$unknown);
        $this->assertStringNotContainsString('Description cursor (cursor)',$scene);
    }

    public function test_page_scope_keeps_story_prose_and_shared_media_but_filters_other_scene_details(): void
    {
        $context = [
            'pages'=>[
                ['id'=>1,'title'=>'Porch','narrative'=>'Porch prose','visual_document'=>['visuals'=>[['id'=>'porch','kind'=>'scene','name'=>'Porch','asset_id'=>1,'intro_video_id'=>'v1','description'=>'Porch narration','hotspots'=>[]]]]],
                ['id'=>2,'title'=>'Hall','narrative'=>'Hall prose','visual_document'=>['visuals'=>[['id'=>'hall','kind'=>'scene','name'=>'Hall','asset_id'=>2,'description'=>'Hall narration']]]],
            ],
            'inventory_items'=>[['asset_id'=>3,'description'=>'Key narration']],
            'characters'=>[['views'=>[['asset_id'=>4]]]],
            'audio_settings'=>['defaults'=>['examine'=>['click'=>8]]],
            'references'=>array_map(fn($id)=>['id'=>$id],range(1,7)),
            'selected_reference_ids'=>[7],
            'image_jobs'=>[['story_page_id'=>1,'asset_ids'=>[5]],['story_page_id'=>2,'asset_ids'=>[6]]],
            'extraction_plans'=>[['story_page_id'=>1],['story_page_id'=>2]],
            'container_plans'=>[['story_page_id'=>1],['story_page_id'=>2]],
            'sounds'=>[['id'=>1,'source_text'=>'Porch narration'],['id'=>2,'source_text'=>'Hall narration'],['id'=>3,'source_text'=>'Key narration'],['id'=>8]],
            'video_jobs'=>[['id'=>'v1'],['id'=>'v2','first_asset_id'=>2],['id'=>'unrelated-character-video','first_asset_id'=>4]],
        ];
        $result = app(ChatContext::class)->scope($context,1,'porch',null);
        $this->assertArrayNotHasKey('visual_document',$result['pages'][1]);
        $this->assertSame('Hall prose',$result['pages'][1]['narrative']);
        $this->assertCount(2,$result['scene_directory']);
        $this->assertSame([1,2,3,4,5,7],array_column($result['references'],'id'));
        $this->assertSame([1,2,3,8],array_column($result['sounds'],'id'));
        $this->assertSame(['v1','v2'],array_column($result['video_jobs'],'id'));
        $this->assertCount(1,$result['extraction_plans']);
        $this->assertCount(1,$result['container_plans']);
        $this->assertSame('porch',$result['selection']['visual_id']);
    }

    public function test_navigation_to_another_scene_is_allowed_but_editing_it_is_not(): void
    {
        $service=app(ChatContext::class);
        $selection=['page_id'=>1,'visual_id'=>'porch'];
        $service->assertSceneChange(['scope'=>'hotspot','page_id'=>1,'visual_id'=>'porch','fields'=>['target_page_id'=>2,'target_scene_id'=>'hall']],$selection,collect());
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->assertSceneChange(['scope'=>'visual','page_id'=>1,'visual_id'=>'other'],$selection,collect());
    }

    public function test_full_page_edit_cannot_change_an_unselected_scene(): void
    {
        $page=new \App\Models\StoryPage(['id'=>1,'visual_document'=>['visuals'=>[['id'=>'a','name'=>'A'],['id'=>'b','name'=>'B']]]]);
        $page->id=1;
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ChatContext::class)->assertSceneChange(['scope'=>'scene_document','page_id'=>1,'fields'=>['visuals'=>[['id'=>'a','name'=>'A'],['id'=>'b','name'=>'Changed']]]],['page_id'=>1,'visual_id'=>'a'],collect([$page]));
    }
}
