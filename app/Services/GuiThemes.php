<?php

namespace App\Services;

class GuiThemes
{
    public function root(): string { return config('game.ui_theme_path', public_path('gui')); }

    public function options(): array
    {
        $catalog = $this->json($this->root().'/themes.json');
        $options = [['id' => 'text', 'label' => 'Current text UI (default)']];
        foreach ($catalog['themes'] ?? [] as $theme) {
            $id = $theme['id'] ?? '';
            if ($id !== 'text' && preg_match('/^[a-z0-9_-]+$/', $id) && is_file($this->root()."/$id/theme.json"))
                $options[] = ['id' => $id, 'label' => $theme['label']];
        }
        return $options;
    }

    private function json(string $path): array
    {
        return is_file($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
    }

    public function manifest(string $id): array
    {
        if ($id === 'text' || !in_array($id, array_column($this->options(), 'id'), true)) return [];
        return $this->json($this->root()."/$id/theme.json");
    }

    /** Only images referenced by gameplay components may be served publicly. */
    public function files(array $manifest): array
    {
        $files = [];
        $walk = function ($node) use (&$walk, &$files) {
            if (!is_array($node)) return;
            if (isset($node['file']) && is_string($node['file'])) $files[sha1($node['file'])] = $node['file'];
            foreach ($node as $value) if (is_array($value)) $walk($value);
        };
        $walk($manifest['components'] ?? []);
        return $files;
    }

    public function assetPath(string $id, string $asset): ?string
    {
        $file = $this->files($this->manifest($id))[$asset] ?? null;
        if (!$file || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'png') return null;
        $base = realpath($this->root()."/$id");
        $path = realpath($this->root()."/$id/$file");
        if (!$base || !$path || !str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $base).'/')) return null;
        return $path;
    }

    public function forStory($story): array
    {
        $id = $story->ui_theme ?: 'text';
        $manifest = $this->manifest($id);
        $fallback = ['id' => 'text', 'renderer' => 'existing_css', 'components' => []];
        if (!$manifest) return $fallback;
        $dimensions = array_column($manifest['assets'] ?? [], null, 'file');
        $components = $manifest['components'] ?? [];
        $valid = true;
        $walk = function (&$node) use (&$walk, &$valid, $id, $dimensions) {
            if (!is_array($node)) return;
            if (isset($node['file'])) {
                $file = $node['file'];
                $hash = sha1($file);
                $size = $dimensions[$file] ?? null;
                if (!$size || !$this->assetPath($id, $hash)) { $valid = false; return; }
                $node['url'] = route('game.ui.asset', [$id, $hash, 'v' => substr($size['sha256'], 0, 16)]);
                $node['width'] = $size['width'];
                $node['height'] = $size['height'];
                unset($node['file']);
            }
            foreach ($node as &$value) if (is_array($value)) $walk($value);
        };
        $walk($components);
        return $valid ? ['id' => $id, 'renderer' => 'png', 'components' => $components] : $fallback;
    }
}
