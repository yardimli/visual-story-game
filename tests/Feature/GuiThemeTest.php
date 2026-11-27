<?php

namespace Tests\Feature;

use App\Models\{Story, User};
use App\Services\GuiThemes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GuiThemeTest extends TestCase
{
    use RefreshDatabase;
    private string $themeRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->themeRoot = storage_path('framework/testing/gui-'.bin2hex(random_bytes(6)));
        config(['game.ui_theme_path' => $this->themeRoot]);
        File::makeDirectory($this->themeRoot.'/sample', 0755, true);
        file_put_contents($this->themeRoot.'/themes.json', json_encode(['themes' => [['id'=>'sample','label'=>'Sample']]]));
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        file_put_contents($this->themeRoot.'/sample/panel.png', $png);
        file_put_contents($this->themeRoot.'/sample/private.psd', 'not public');
        file_put_contents($this->themeRoot.'/sample/theme.json', json_encode([
            'components'=>['dialogue'=>['layers'=>[['file'=>'panel.png','rect'=>[0,0,1,1]]]]],
            'assets'=>[['file'=>'panel.png','width'=>1,'height'=>1,'sha256'=>hash('sha256',$png)]],
        ]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->themeRoot);
        parent::tearDown();
    }

    public function test_theme_saves_and_reaches_owner_and_public_players(): void
    {
        $user=User::factory()->create();
        $story=Story::create(['user_id'=>$user->id,'title'=>'Themes','premise'=>'Explore','public_play_token'=>str_repeat('a',22)]);
        $page=$story->pages()->create(['position'=>1,'title'=>'Hall','visual_document'=>['visuals'=>[['id'=>'hall','kind'=>'scene','name'=>'Hall','hotspots'=>[]]]]]);
        $this->assertSame('text', $story->fresh()->ui_theme);
        $payload=['revision'=>$story->fresh()->revision,'title'=>'Themes','language'=>'English','ui_theme'=>'sample'];
        $this->actingAs($user)->putJson(route('stories.update',$story),$payload)->assertOk()->assertJsonPath('ui_theme.id','sample');
        $this->assertSame('sample',$story->fresh()->ui_theme);
        $this->getJson(route('visuals.show',[$story,$page]))->assertOk()->assertJsonPath('ui_theme.id','sample');
        $this->get(route('stories.play',[$story,'page'=>$page->id]))->assertOk()->assertViewHas('game',fn($game)=>$game['uiTheme']['id']==='sample');
        $this->get(route('public.play',str_repeat('a',22)))->assertOk()->assertViewHas('game',fn($game)=>$game['uiTheme']['id']==='sample');
        $payload['revision']=$story->fresh()->revision;$payload['ui_theme']='missing';
        $this->putJson(route('stories.update',$story),$payload)->assertUnprocessable();
        $payload['ui_theme']='text';
        $this->putJson(route('stories.update',$story),$payload)->assertOk()->assertJsonPath('ui_theme.renderer','existing_css');
    }

    public function test_only_manifest_component_images_are_public_and_missing_pack_falls_back(): void
    {
        $this->get(route('game.ui.asset',['sample',sha1('panel.png')]))->assertOk()->assertHeader('content-type','image/png');
        $this->get(route('game.ui.asset',['sample',sha1('private.psd')]))->assertNotFound();
        $this->get(route('game.ui.asset',['missing',sha1('panel.png')]))->assertNotFound();
        $service=app(GuiThemes::class);
        $story=new Story(['ui_theme'=>'sample']);
        $data=$service->forStory($story);
        $this->assertArrayNotHasKey('file',$data['components']['dialogue']['layers'][0]);
        unlink($this->themeRoot.'/sample/panel.png');
        $this->assertSame('text',$service->forStory($story)['id']);
    }
}
