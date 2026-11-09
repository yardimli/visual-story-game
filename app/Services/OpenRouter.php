<?php
namespace App\Services;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
class OpenRouter {
    private function authenticated(string $key): \Illuminate\Http\Client\PendingRequest {
        return Http::withToken($key)->acceptJson()->withHeaders([
            'HTTP-Referer'=>config('story.openrouter_referer'),
            'X-OpenRouter-Title'=>config('app.name'),
        ])->connectTimeout(10)->timeout(240);
    }
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
        return $this->authenticated($key)
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
        return $this->authenticated($key)->post(config('story.openrouter_url').'/chat/completions',$payload);
    }
    public function completeStream(string $key,array $payload,callable $onText) {
        $response=$this->authenticated($key)->withOptions(['stream'=>true,'read_timeout'=>240])
            ->post(config('story.openrouter_url').'/chat/completions',$payload+['stream'=>true,'stream_options'=>['include_usage'=>true]]);
        if(!$response->successful())return $response;
        $stream=$response->toPsrResponse()->getBody();$buffer='';$content='';$body=[];$done=false;$started=microtime(true);
        try {
            while(!$stream->eof() && !$done){
                if(microtime(true)-$started>240)throw new \RuntimeException('Stream deadline exceeded.');
                $buffer.=$stream->read(1024);
                while(($end=strpos($buffer,"\n"))!==false){
                    $line=rtrim(substr($buffer,0,$end),"\r");$buffer=substr($buffer,$end+1);
                    if(!str_starts_with($line,'data:'))continue;
                    $json=trim(substr($line,5));if($json==='[DONE]'){$done=true;break;}
                    $chunk=json_decode($json,true,512,JSON_THROW_ON_ERROR);
                    if(!is_array($chunk))throw new \RuntimeException('Invalid stream event.');
                    if(isset($chunk['id']))$body['id']=$chunk['id'];
                    if(isset($chunk['usage']))$body['usage']=$chunk['usage'];
                    if(isset($chunk['error']) || data_get($chunk,'choices.0.finish_reason')==='error')throw new \RuntimeException('Provider stream failed.');
                    $text=data_get($chunk,'choices.0.delta.content');
                    if(is_string($text) && $text!==''){$content.=$text;if(strlen($content)>1000000)throw new \RuntimeException('Response too large.');$onText($text);}
                }
            }
            if(!$done)throw new \RuntimeException('Stream ended before completion.');
        } finally {$stream->close();}
        $body['choices']=[['message'=>['role'=>'assistant','content'=>$content]]];
        return new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(200,['Content-Type'=>'application/json'],json_encode($body,JSON_THROW_ON_ERROR)));
    }
}
