<?php
namespace Tests\Feature;
use App\Models\{Story,User};
use App\Services\AreaActionSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AreaActionSettingsTest extends TestCase {
 use RefreshDatabase;
 public function test_migration_combines_gates_removes_obsolete_fields_and_is_idempotent():void {
  $story=Story::create(['user_id'=>User::factory()->create()->id,'title'=>'Migration','premise'=>'Test']);
  $area=['action'=>'inventory','description'=>'','interaction_text'=>'A locked door.','unavailable_behavior'=>'hide','availability_enabled'=>true,'flag_conditions'=>[['key'=>'read','value'=>true]],'required_items'=>['key'],'action_requirements'=>['availability_enabled'=>true,'flag_conditions'=>[['key'=>'open','value'=>false]],'required_items'=>['key'],'unavailable_text'=>'Read the letter first.']];
  $page=$story->pages()->create(['position'=>1,'title'=>'Door','visual_document'=>['visuals'=>[['hotspots'=>[$area]]]]]);$revision=$page->fresh()->visual_revision;
  $migration=require database_path('migrations/2026_11_14_120000_consolidate_area_action_conditions.php');$migration->up();$page->refresh();$clean=$page->visual_document['visuals'][0]['hotspots'][0];
  $this->assertSame([['key'=>'read','value'=>true],['key'=>'open','value'=>false]],$clean['flag_conditions']);$this->assertSame(['key'],$clean['required_items']);$this->assertSame('A locked door.',$clean['description']);
  foreach(['action_requirements','unavailable_behavior','interaction_text'] as $key)$this->assertArrayNotHasKey($key,$clean);
  $this->assertSame($revision+1,$page->visual_revision);$migration->up();$this->assertSame($page->visual_revision,$page->fresh()->visual_revision);
 }
 public function test_disabled_legacy_gate_does_not_enable_its_conditions():void {
  $clean=AreaActionSettings::area(['availability_enabled'=>false,'required_items'=>['old'],'action_requirements'=>['availability_enabled'=>true,'required_items'=>['new']]]);
  $this->assertTrue($clean['availability_enabled']);$this->assertSame(['new'],$clean['required_items']);
  $clean=AreaActionSettings::area(['availability_enabled'=>false,'required_items'=>['old'],'action_requirements'=>['availability_enabled'=>false,'required_items'=>['new']]]);
  $this->assertFalse($clean['availability_enabled']);$this->assertSame(['old','new'],$clean['required_items']);
 }
}
