<?php
namespace App\Services;
use Illuminate\Support\Collection;
class ModelCatalogFilter {
    public function apply(Collection $models): Collection {
        $providers = config('story.openrouter_providers', []);
        $models = $models->reject(function ($model) use ($providers) {
            $id = strtolower($model['id']);
            $label = strtolower(($model['name'] ?? '').' '.$id);
            if (!in_array(explode('/', $id)[0], $providers, true)) return true;
            if (!in_array('text', $model['architecture']['output_modalities'] ?? [], true)) return true;
            if (preg_match('/\((batch|free)\)|:(batch|free)$/i', $label)) return true;
            if (preg_match('/\b(lite|code|coder|codex)\b/i', $label)) return true;
            if (preg_match('/\bnano[\s_-]*banana\b/i', $label)) return true;
            if (str_starts_with($id, 'qwen/')) {
                if (preg_match('/\b(thinking|instruct)\b/i', $label)) return true;
                $name = $model['name'] ?? '';
                if (preg_match('/(?<![a-z0-9.])(\d+(?:\.\d+)?)\s*([bt])\b/i', $name, $size)
                    || preg_match('/(?<![a-z0-9.])(\d+(?:\.\d+)?)\s*([bt])\b/i', $id, $size)) {
                    $billions = (float)$size[1] * (strtolower($size[2]) === 't' ? 1000 : 1);
                    if ($billions < 100) return true;
                }
            }
            return (bool)preg_match('~^(?:openai/(?:gpt-[34](?:[.o-]|$)|o1(?:-|$))|google/gemma-2(?:-|$)|qwen/qwen-?2(?:[.-]|$)|deepseek/(?:deepseek-chat(?:$|-v3)|deepseek-v3))~', $id);
        });
        $winners = [];
        foreach ($models as $model) {
            [$family,$version,$snapshot] = $this->identity($model);
            $rank = [$version,$snapshot,(int)($model['created'] ?? 0)];
            if (!isset($winners[$family]) || $this->newer($rank,$winners[$family]['rank']))
                $winners[$family] = ['model'=>$model,'rank'=>$rank];
        }
        return collect(array_column($winners,'model'))->values();
    }
    private function identity(array $model): array {
        $id = strtolower($model['id']); $snapshot = 0;
        $id = preg_replace_callback('/[-:](20\d{2})-?(\d{2})-?(\d{2})(?=-|$)/', function ($m) use (&$snapshot) {
            $snapshot = (int)($m[1].$m[2].$m[3]); return '';
        }, $id);
        $version = '0';
        // Keep size (235B/A22B), capability and product suffixes as distinct families.
        $family = preg_replace_callback('/(?<=[a-z-])(\d+(?:[.]\d+)*)(?=-|$)/', function ($m) use (&$version) {
            if (version_compare($m[1],$version,'>')) $version=$m[1];
            return '{version}';
        }, $id);
        return [$family,$version,$snapshot];
    }
    private function newer(array $candidate, array $current): bool {
        $version = version_compare($candidate[0],$current[0]);
        return $version > 0 || ($version === 0 && [$candidate[1],$candidate[2]] > [$current[1],$current[2]]);
    }
}
