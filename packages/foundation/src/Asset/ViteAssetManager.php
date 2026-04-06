<?php

declare(strict_types=1);

namespace Waaseyaa\Foundation\Asset;

/**
 * Asset manager that reads Vite manifest.json files to resolve asset URLs.
 *
 * Each bundle corresponds to a Vite build output directory containing a
 * manifest.json file. The manifest maps source paths to their hashed outputs.
 *
 * Expected manifest format (Vite 5+):
 * {
 *   "src/main.ts": {
 *     "file": "assets/main-abc123.js",
 *     "css": ["assets/main-def456.css"],
 *     "isEntry": true,
 *     "imports": ["_vendor-ghi789.js"]
 *   }
 * }
 */
final class ViteAssetManager implements AssetManagerInterface
{
    /** @var array<string, array<string, mixed>> Cached parsed manifests per bundle */
    private array $manifests = [];

    /**
     * @param string $basePath Base path to the dist directory (e.g., '/var/www/dist')
     * @param string $baseUrl  Base URL prefix for asset URLs (e.g., '/dist' or 'https://cdn.example.com/dist')
     */
    public function __construct(
        private readonly string $basePath,
        private readonly string $baseUrl = '/dist',
        private readonly ?string $devServerUrl = null,
    ) {}

    public function url(string $path, string $bundle = 'admin'): string
    {
        $manifest = $this->loadManifest($bundle);

        if (!isset($manifest[$path])) {
            // Fall back to the raw path if not found in manifest.
            return rtrim($this->baseUrl, '/') . '/' . $bundle . '/' . ltrim($path, '/');
        }

        $entry = $manifest[$path];
        $file = $entry['file'] ?? $path;

        return rtrim($this->baseUrl, '/') . '/' . $bundle . '/' . ltrim($file, '/');
    }

    public function preloadLinks(string $bundle = 'admin'): array
    {
        $manifest = $this->loadManifest($bundle);
        $links = [];

        foreach ($manifest as $entry) {
            if (!isset($entry['isEntry']) || $entry['isEntry'] !== true) {
                continue;
            }

            // Add the entry file itself.
            if (isset($entry['file'])) {
                $links[] = [
                    'href' => rtrim($this->baseUrl, '/') . '/' . $bundle . '/' . ltrim($entry['file'], '/'),
                    'as' => $this->guessAssetType($entry['file']),
                ];
            }

            // Add CSS files associated with this entry.
            if (isset($entry['css']) && is_array($entry['css'])) {
                foreach ($entry['css'] as $cssFile) {
                    $links[] = [
                        'href' => rtrim($this->baseUrl, '/') . '/' . $bundle . '/' . ltrim($cssFile, '/'),
                        'as' => 'style',
                    ];
                }
            }
        }

        return $links;
    }

    /**
     * Generate HTML script and link tags for a bundle's entry assets.
     *
     * In production (manifest exists): emits hashed asset tags.
     * In dev mode (no manifest, devServerUrl set): emits Vite dev server tags.
     * Otherwise: returns empty string.
     */
    public function assetTags(string $bundle = 'build', string $entrypoint = 'resources/js/app.ts'): string
    {
        $manifest = $this->loadManifest($bundle);

        if ($manifest !== []) {
            return $this->productionTags($manifest, $bundle);
        }

        if ($this->devServerUrl !== null) {
            return $this->devTags($entrypoint);
        }

        return '';
    }

    private function productionTags(array $manifest, string $bundle): string
    {
        $tags = [];

        foreach ($manifest as $entry) {
            if (!isset($entry['isEntry']) || $entry['isEntry'] !== true) {
                continue;
            }

            if (isset($entry['css']) && is_array($entry['css'])) {
                foreach ($entry['css'] as $cssFile) {
                    $href = rtrim($this->baseUrl, '/') . '/' . $bundle . '/' . ltrim($cssFile, '/');
                    $tags[] = '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">';
                }
            }

            if (isset($entry['file'])) {
                $src = rtrim($this->baseUrl, '/') . '/' . $bundle . '/' . ltrim($entry['file'], '/');
                $tags[] = '<script type="module" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>';
            }
        }

        return implode("\n        ", $tags);
    }

    private function devTags(string $entrypoint): string
    {
        $base = rtrim($this->devServerUrl, '/');

        return '<script type="module" src="' . htmlspecialchars($base . '/@vite/client', ENT_QUOTES, 'UTF-8') . '"></script>'
            . "\n        "
            . '<script type="module" src="' . htmlspecialchars($base . '/' . ltrim($entrypoint, '/'), ENT_QUOTES, 'UTF-8') . '"></script>';
    }

    /**
     * @return array<string, mixed>
     */
    private function loadManifest(string $bundle): array
    {
        if (isset($this->manifests[$bundle])) {
            return $this->manifests[$bundle];
        }

        $manifestPath = rtrim($this->basePath, '/') . '/' . $bundle . '/.vite/manifest.json';

        if (!file_exists($manifestPath)) {
            // Try legacy location without .vite prefix.
            $manifestPath = rtrim($this->basePath, '/') . '/' . $bundle . '/manifest.json';
        }

        if (!file_exists($manifestPath)) {
            $this->manifests[$bundle] = [];
            return [];
        }

        $contents = file_get_contents($manifestPath);
        if ($contents === false) {
            $this->manifests[$bundle] = [];
            return [];
        }

        try {
            $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->manifests[$bundle] = [];
            return [];
        }
        if (!is_array($manifest)) {
            $this->manifests[$bundle] = [];
            return [];
        }

        $this->manifests[$bundle] = $manifest;

        return $manifest;
    }

    private function guessAssetType(string $file): string
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return match ($extension) {
            'js', 'mjs', 'ts' => 'script',
            'css' => 'style',
            'woff', 'woff2', 'ttf', 'otf', 'eot' => 'font',
            'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif' => 'image',
            default => 'script',
        };
    }
}
