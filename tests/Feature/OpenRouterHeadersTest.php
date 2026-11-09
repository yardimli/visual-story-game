<?php
namespace Tests\Feature;
use App\Services\OpenRouter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class OpenRouterHeadersTest extends TestCase {
 public function test_authenticated_writing_and_image_requests_use_configured_attribution(): void {
  config(['story.openrouter_referer'=>'https://vsg.playground.computer','app.name'=>'Visual Story Game']);Http::fake(['*'=>Http::response([],200)]);
  foreach(['platform-test-key','personal-test-key'] as $key){
   app(OpenRouter::class)->complete($key,['model'=>'test/text','messages'=>[]]);
   app(OpenRouter::class)->generateImage($key,['model'=>'test/image','prompt'=>'A room']);
  }
  Http::assertSentCount(4);
  foreach(Http::recorded() as [$request,$response]){
   $this->assertTrue($request->hasHeader('HTTP-Referer','https://vsg.playground.computer'));
   $this->assertTrue($request->hasHeader('X-OpenRouter-Title','Visual Story Game'));
   $this->assertFalse($request->hasHeader('X-Title'));
   $this->assertTrue(in_array($request->header('Authorization')[0],['Bearer platform-test-key','Bearer personal-test-key'],true));
  }
 }
}
