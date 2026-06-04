<?php

namespace App\Http\Controllers;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class MobileAppDownloadController extends Controller
{
    public function download(): BinaryFileResponse
    {
        $path = public_path(config('mobile.apk_public_path'));

        abort_unless(is_file($path), 404, 'Mobile app APK file was not found.');

        return response()->download($path, config('mobile.app_name').'.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }

    public function qr(Request $request): Response
    {
        $svg = (new QRCode(new QROptions([
            'addQuietzone' => true,
            'cssClass' => 'mobile-app-download-qr',
            'eccLevel' => EccLevel::M,
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'quietzoneSize' => 4,
            'scale' => 8,
            'svgAddXmlHeader' => false,
        ])))->render(route('mobile-app.download'));

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
