<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache,Http};
use Tests\TestCase;
class BulkImageCatalogTest extends TestCase {
 use RefreshDatabase;
 public function test_bulk_catalog_reuses_cache_filters_models_and_preserves_partial_success(): void {
  $this->getJson('/ai/image-endpoints')->assertUnauthorized();
  $this->actingAs(User::factory()->create());
  config(['story.openrouter_providers'=>['openai']]);
  $models=array_map(fn($id)=>['id'=>$id,'name'=>$id,'architecture'=>['output_modalities'=>['image']]],['openai/cached','openai/new','openai/down','other/hidden']);
  Cache::put('openrouter.image-models',$models,900);
  $cached=[['provider_name'=>'Cached','pricing'=>[['unit'=>'image','cost_usd'=>0.03]]]];
  $fresh=[['provider_name'=>'Fresh','supported_parameters'=>['quality'=>['type'=>'enum','values'=>['high']]]]];
  Cache::put('openrouter.image-endpoints.'.hash('sha256','openai/cached'),$cached,900);
  Http::fake(['*/images/models/openai/new/endpoints'=>Http::response(['endpoints'=>$fresh]),'*/images/models/openai/down/endpoints'=>Http::response([],503)]);
  $response=$this->getJson('/ai/image-endpoints')->assertOk();
  $this->assertSame(['openai/cached','openai/new','openai/down'],array_column($response->json('models'),'id'));
  $this->assertSame($cached,$response->json('endpoints')['openai/cached']);
  $this->assertSame($fresh,$response->json('endpoints')['openai/new']);
  $this->assertSame([], $response->json('endpoints')['openai/down']);
  $this->assertSame(['openai/down'],$response->json('unavailable'));
  Http::assertSentCount(2);
  $this->assertSame($fresh,Cache::get('openrouter.image-endpoints.'.hash('sha256','openai/new')));
 }
}
