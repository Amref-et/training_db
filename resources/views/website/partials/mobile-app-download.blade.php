@php
    $mobileAppName = config('mobile.app_name', 'Amref training DB');
    $mobileAppApkPath = public_path(config('mobile.apk_public_path', 'mobile/amref-training-db.apk'));
    $mobileAppDownloadVersion = is_file($mobileAppApkPath) ? filemtime($mobileAppApkPath) : time();
    $mobileAppDownloadUrl = route('mobile-app.download', ['v' => $mobileAppDownloadVersion]);
    $mobileAppQrUrl = route('mobile-app.qr');
@endphp

<div class="site-mobile-app-download">
    <div class="site-mobile-app-copy">
        <h3>{{ $mobileAppName }}</h3>
        <p>Scan the QR code to download the Android mobile app.</p>
        <a href="{{ $mobileAppDownloadUrl }}" class="site-mobile-app-link" download>Download APK</a>
    </div>
    <a href="{{ $mobileAppDownloadUrl }}" class="site-mobile-app-qr" download aria-label="Download {{ $mobileAppName }}">
        <img src="{{ $mobileAppQrUrl }}" alt="QR code to download {{ $mobileAppName }}" width="112" height="112">
    </a>
</div>
