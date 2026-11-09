<?php
return [
    'openrouter_providers' => array_values(array_filter(array_map(fn ($value) => strtolower(trim($value)), explode(',', env('OPENROUTER_PROVIDERS', 'deepseek,anthropic,openai,moonshotai,google,qwen'))))),
    'openrouter_key' => env('OPEN_ROUTER_KEY'),
    'openrouter_referer' => env('OPENROUTER_HTTP_REFERER', 'https://vsg.playground.computer'),
    'openrouter_url' => 'https://openrouter.ai/api/v1',
    'allowance' => (int) env('LLM_ALLOWANCE_MICRO_USD', 1000000),
    'max_output_tokens' => 2048,
];
