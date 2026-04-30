<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PublicVendorAssetController extends Controller
{
    private const ASSETS = [
        'bootstrap-5.3.3.min.css' => [
            'url' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
            'content_type' => 'text/css; charset=UTF-8',
        ],
        'bootstrap-5.3.3.bundle.min.js' => [
            'url' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
            'content_type' => 'application/javascript; charset=UTF-8',
        ],
        'tom-select-2.3.1.bootstrap5.min.css' => [
            'url' => 'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css',
            'content_type' => 'text/css; charset=UTF-8',
        ],
        'tom-select-2.3.1.complete.min.js' => [
            'url' => 'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js',
            'content_type' => 'application/javascript; charset=UTF-8',
        ],
        'chart.js-4.4.3.umd.min.js' => [
            'url' => 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js',
            'content_type' => 'application/javascript; charset=UTF-8',
        ],
    ];

    public function show(Request $request, string $asset): Response
    {
        abort_unless(isset(self::ASSETS[$asset]), 404);

        $config = self::ASSETS[$asset];
        $cachePath = 'vendor-assets/'.$asset;
        $disk = Storage::disk('local');

        if (! $disk->exists($cachePath)) {
            $response = Http::timeout(20)->retry(2, 250)->get($config['url']);

            abort_unless($response->successful(), 404);

            $disk->put($cachePath, $response->body());
        }

        return response($disk->get($cachePath), 200, [
            'Content-Type' => $config['content_type'],
            'Cache-Control' => 'public, max-age=604800, stale-while-revalidate=86400',
        ]);
    }
}
