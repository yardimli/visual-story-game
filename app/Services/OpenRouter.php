<?php
namespace App\Services;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
class OpenRouter {
    public function models(): array {
        $models = Cache::remember('openrouter.models', 900, function () {
            $response = Http::acceptJson()->timeout(20)->get(config('story.openrouter_url').'/models');
            if (!$response->successful()) throw ValidationException::withMessages(['model'=>'Model catalog is unavailable. Try again shortly.']);
            return $response->json('data') ?? [];
        });
        return app(ModelCatalogFilter::class)->apply(collect($models))->all();
    }
    public function imageModels(): array {
        $models = Cache::remember('openrouter.image-models', 900, function () {
            $response=Http::acceptJson()->timeout(20)->get(config('story.openrouter_url').'/images/models');
            $response->throw();
            return $response->json('data') ?? [];
        });
        // Keep author allowlist and batch/free policy, not text-editor exclusions.
        return collect($models)->filter(fn ($m) =>
            in_array(explode('/', $m['id'])[0], config('story.openrouter_providers'), true)
            && in_array('image', $m['architecture']['output_modalities'] ?? [], true)
            && !preg_match('/\((batch|free)\)|:(batch|free)$/i', ($m['name'] ?? '').' '.$m['id'])
        )->map(function ($m) {
            if (!str_contains($m['name'], ':')) $m['name']=ucfirst(explode('/', $m['id'])[0]).': '.$m['name'];
            $m['catalog_type']='image';
            return $m;
        })->values()->all();
    }
    public function imageEndpoints(string $id): array {
        if (!collect($this->imageModels())->contains('id',$id))
            throw ValidationException::withMessages(['model'=>'Choose an available image model.']);
        return Cache::remember('openrouter.image-endpoints.'.hash('sha256',$id),900,function () use ($id) {
            // Build the URL from a validated catalog ID, never a supplied remote URL.
            $path=implode('/',array_map('rawurlencode',explode('/',$id)));
            $response=Http::acceptJson()->timeout(20)->get(config('story.openrouter_url').'/images/models/'.$path.'/endpoints');
            $response->throw();
            return $response->json('endpoints') ?? $response->json('data.endpoints') ?? [];
        });
    }
    public function allImageEndpoints(): array {
        $models=$this->imageModels();$endpoints=[];$missing=[];$unavailable=[];
        foreach($models as $model) {
            $id=$model['id'];$cached=Cache::get('openrouter.image-endpoints.'.hash('sha256',$id));
            if($cached!==null)$endpoints[$id]=$cached;else $missing[]=$id;
        }
        if($missing) {
            // Only request uncached, allowlisted IDs, concurrently on the server.
            $responses=Http::pool(function ($pool) use ($missing) {
                return array_map(function ($id) use ($pool) {
                    $path=implode('/',array_map('rawurlencode',explode('/',$id)));
                    return $pool->as($id)->acceptJson()->connectTimeout(5)->timeout(20)
                        ->get(config('story.openrouter_url').'/images/models/'.$path.'/endpoints');
                },$missing);
            });
            foreach($missing as $id) {
                $response=$responses[$id]??null;
                if(!$response instanceof \Illuminate\Http\Client\Response || !$response->successful()) {
                    $endpoints[$id]=[];$unavailable[]=$id;continue;
                }
                $endpoints[$id]=$response->json('endpoints')??$response->json('data.endpoints')??[];
                Cache::put('openrouter.image-endpoints.'.hash('sha256',$id),$endpoints[$id],900);
            }
        }
        return ['models'=>$models,'endpoints'=>(object)$endpoints,'unavailable'=>$unavailable];
    }
    public function generateImage(string $key, array $payload) {
        // Image API has a separate schema; do not route through chat completions.
        // Keep data-URL MIME slashes literal for upstream image parsers.
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return Http::withToken($key)->acceptJson()->connectTimeout(10)->timeout(240)
            ->withBody($body, 'application/json')
            ->post(config('story.openrouter_url').'/images');
    }
    public function model(string $id): array {
        $model = collect($this->models())->firstWhere('id',$id);
        if (!$model || !in_array('text', $model['architecture']['output_modalities'] ?? ['text'])) {
            throw ValidationException::withMessages(['model'=>'Choose an available text-output model.']);
        }
        return $model;
    }
    public function complete(string $key, array $payload) {
        // Never retry a billable request automatically.
        return Http::withToken($key)->acceptJson()->withHeaders([
            'HTTP-Referer'=>config('app.url'), 'X-Title'=>'Visual Story Game',
        ])->connectTimeout(10)->timeout(240)->post(config('story.openrouter_url').'/chat/completions',$payload);
    }
}
