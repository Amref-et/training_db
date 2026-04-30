<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Request;

class PublicBuildManifest
{
    public static function tags(array $entries): HtmlString
    {
        $manifest = self::manifest();
        $html = [];

        foreach ($entries as $entry) {
            $chunk = $manifest[$entry] ?? null;

            if (! is_array($chunk) || empty($chunk['file'])) {
                continue;
            }

            $file = self::buildUrl($chunk['file']);

            if (str_ends_with($entry, '.css')) {
                $html[] = '<link rel="stylesheet" href="'.e($file).'">';
                continue;
            }

            if (! empty($chunk['css']) && is_array($chunk['css'])) {
                foreach ($chunk['css'] as $cssFile) {
                    $html[] = '<link rel="stylesheet" href="'.e(self::buildUrl($cssFile)).'">';
                }
            }

            $html[] = '<script type="module" src="'.e($file).'"></script>';
        }

        return new HtmlString(implode("\n", $html));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function manifest(): array
    {
        $path = public_path('build/manifest.json');

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function buildUrl(string $file): string
    {
        $configuredPath = parse_url((string) config('app.url'), PHP_URL_PATH);
        $basePath = is_string($configuredPath) ? trim($configuredPath) : '';

        if ($basePath === '') {
            $basePath = Request::instance()->getBasePath();
        }

        $prefix = $basePath !== '' ? rtrim($basePath, '/') : '';

        return $prefix.'/build/'.ltrim($file, '/');
    }
}
