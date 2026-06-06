<?php

namespace App\Http\Controllers;

use App\Support\QrCodeSvg;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MobileAppDownloadController extends Controller
{
    public function download(): BinaryFileResponse
    {
        $path = public_path(config('mobile.apk_public_path'));

        abort_unless(is_file($path), 404, 'Mobile app APK file was not found.');

        $downloadName = basename((string) config('mobile.apk_public_path', 'mobile/amref-training-db.apk'));

        return response()->download($path, $downloadName, [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Content-Length' => (string) filesize($path),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function qr(Request $request): Response
    {
        $svg = $this->downloadQrSvg($this->downloadUrl());

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function downloadQrSvg(string $downloadUrl): string
    {
        try {
            if (
                class_exists(QRCode::class)
                && class_exists(QROptions::class)
                && class_exists(EccLevel::class)
                && class_exists(QRMarkupSVG::class)
            ) {
                return (new QRCode(new QROptions([
                    'addQuietzone' => true,
                    'cssClass' => 'mobile-app-download-qr',
                    'eccLevel' => EccLevel::M,
                    'outputInterface' => QRMarkupSVG::class,
                    'outputBase64' => false,
                    'quietzoneSize' => 4,
                    'scale' => 8,
                    'svgAddXmlHeader' => false,
                ])))->render($downloadUrl);
            }
        } catch (Throwable) {
            // Fall back to the local generator when production dependencies are not installed yet.
        }

        return QrCodeSvg::svg($downloadUrl, 5, 4);
    }

    private function downloadUrl(): string
    {
        $path = public_path(config('mobile.apk_public_path'));
        $version = is_file($path) ? (string) filemtime($path) : (string) time();

        return route('mobile-app.download', ['v' => $version]);
    }
}
