@php
    $mobileAppName = config('mobile.app_name', 'Amref training DB');
    $mobileAppDownloadUrl = route('mobile-app.download');
    $mobileAppQrUrl = route('mobile-app.qr');
@endphp

<div class="site-mobile-app-download">
    <div class="site-mobile-app-copy">
        <h3>{{ $mobileAppName }}</h3>
        <p>Scan the QR code to download the Android mobile app.</p>
        <a href="{{ $mobileAppDownloadUrl }}" class="site-mobile-app-link" download>Download APK</a>
    </div>
    <a href="{{ $mobileAppDownloadUrl }}" class="site-mobile-app-qr" download aria-label="Download {{ $mobileAppName }}">
        <img src="{{ $mobileAppQrUrl }}" alt="QR code to download {{ $mobileAppName }}" width="164" height="164">
    </a>
</div>
