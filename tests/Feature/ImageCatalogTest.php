<?php
namespace Tests\Feature;
use App\Services\OpenRouter;
use Illuminate\Support\Facades\{Http,Cache};
use Tests\TestCase;
class ImageCatalogTest extends TestCase {
    public function test_image_discovery_is_separate_from_text_editor_policy(): void {
        config(['story.openrouter_providers'=>['openai','google']]);
        Cache::forget('openrouter.image-models');
        Http::fake(['*/images/models'=>Http::response(['data'=>[
            ['id'=>'openai/gpt-image-2.6-sunburst','name'=>'GPT Image 2.6 Sunburst','architecture'=>['output_modalities'=>['image']]],
            ['id'=>'google/nano-banana','name'=>'Google: Nano Banana','architecture'=>['output_modalities'=>['image']]],
            ['id'=>'other/image','name'=>'Other','architecture'=>['output_modalities'=>['image']]],
            ['id'=>'openai/text','name'=>'Text','architecture'=>['output_modalities'=>['text']]],
        ]])]);
        $models=app(OpenRouter::class)->imageModels();
        $this->assertSame(['openai/gpt-image-2.6-sunburst','google/nano-banana'],array_column($models,'id'));
        $this->assertSame('image',$models[0]['catalog_type']);
        Http::assertSent(fn($r)=>str_ends_with($r->url(),'/images/models'));
    }
    public function test_endpoint_pricing_preserves_units_and_tiers(): void {
        config(['story.openrouter_providers'=>['openai']]);
        Cache::put('openrouter.image-models',[['id'=>'openai/image','name'=>'Image','architecture'=>['output_modalities'=>['image']]]],60);
        Cache::forget('openrouter.image-endpoints.'.hash('sha256','openai/image'));
        $pricing=[['billable'=>'output_image','unit'=>'image','cost_usd'=>0.05,'variant'=>'2k'],['billable'=>'input_text','unit'=>'token','cost_usd'=>0.000005]];
        Http::fake(['*/images/models/openai/image/endpoints'=>Http::response(['endpoints'=>[['provider_name'=>'OpenAI','pricing'=>$pricing]]])]);
        $this->assertSame($pricing,app(OpenRouter::class)->imageEndpoints('openai/image')[0]['pricing']);
    }
    public function test_image_requests_use_dedicated_endpoint(): void {
        Http::fake(['*/images'=>Http::response(['data'=>[]])]);
        app(OpenRouter::class)->generateImage('test-key',['model'=>'openai/image','prompt'=>'A scene']);
        Http::assertSent(fn($r)=>str_ends_with($r->url(),'/images') && $r['prompt']==='A scene');
        Http::assertSentCount(1);
    }
    public function test_reference_mime_slashes_are_literal_in_the_wire_json(): void {
        Http::fake(['*/images'=>Http::response(['data'=>[]])]);
        $payload = [
            'model'=>'openai/image',
            'prompt'=>"Keep the \"note\".\nPreserve C:\\scene.",
            'input_references'=>array_map(fn ($mime)=>[
                'type'=>'image_url',
                'image_url'=>['url'=>'data:'.$mime.';base64,/w=='],
            ], ['image/png', 'image/jpeg', 'image/webp']),
        ];
        app(OpenRouter::class)->generateImage('test-key', $payload);
        Http::assertSent(function ($request) use ($payload) {
            $body = $request->body();
            $this->assertSame($payload, json_decode($body, true, 512, JSON_THROW_ON_ERROR));
            $this->assertTrue($request->hasHeader('Content-Type', 'application/json'));
            foreach (['image/png', 'image/jpeg', 'image/webp'] as $mime) {
                $this->assertStringContainsString('data:'.$mime.';base64,/w==', $body);
            }
            $this->assertStringNotContainsString('\\/', $body);
            return true;
        });
        Http::assertSentCount(1);
    }
}
