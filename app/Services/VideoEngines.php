<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VideoEngines
{
    public function all(): array
    {
        return json_decode(file_get_contents(config('video.engines_file')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function validate(array $data): array
    {
        $engines = $this->all();
        $fields = Validator::make($data, [
            'name' => 'required|string|max:160', 'prompt' => 'required|string|max:10000',
            'engine' => ['required', Rule::in(array_keys($engines))],
            'parameters' => 'present|array',
        ])->validate();
        $engine = $engines[$fields['engine']];
        // Old saved drafts may carry a custom step count. Modes now fix it in
        // the workflow, so ignore that legacy field when resaving/queueing.
        if ($fields['engine'] === 'minimax_h3_reference') unset($fields['parameters']['steps']);
        $rules = [];
        foreach ($engine['parameters'] as $key => $parameter) {
            $rules[$key] = $parameter['type'] === 'boolean' ? ['required', 'boolean'] : ($parameter['type'] === 'select'
                ? ['required', Rule::in(array_keys($parameter['options']))]
                : ['required', $parameter['type'] === 'integer' ? 'integer' : 'numeric',
                    'min:'.$parameter['min'], 'max:'.$parameter['max']]);
            $fields['parameters'][$key] ??= $parameter['default'];
        }
        Validator::make(['parameters' => $fields['parameters']], [
            'parameters' => 'array:'.implode(',', array_keys($rules)),
        ])->validate();
        $fields['parameters'] = Validator::make($fields['parameters'], $rules)->validate();
        foreach ($engine['parameters'] as $key=>$parameter) if ($parameter['type'] === 'boolean') $fields['parameters'][$key] = (bool)$fields['parameters'][$key];
        $fields['engine_version'] = $engine['version'];

        return $fields;
    }
}
