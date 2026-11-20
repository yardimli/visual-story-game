<?php

return [
    // A relay forwards to a hub. Only a hub accepts imports and worker requests.
    'mode' => env('VIDEO_MODE', 'hub'),
    'hub_url' => env('VIDEO_HUB_URL', 'https://vgs.playground.computer'),
    'relay_token' => env('VIDEO_RELAY_TOKEN'),
    'max_upload_kb' => 524288,
    'engines_file' => base_path('comfyui/engines.json'),
];
