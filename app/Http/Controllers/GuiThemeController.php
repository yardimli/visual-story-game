<?php

namespace App\Http\Controllers;

use App\Services\GuiThemes;

class GuiThemeController extends Controller
{
    public function asset(string $theme, string $asset, GuiThemes $themes)
    {
        $path = $themes->assetPath($theme, $asset);
        abort_unless($path, 404);
        return response()->file($path, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }
}
