{{-- BookSys PWA 메타 (welcome / public layout / admin layout 공용)
     디자인 일관성 정책에 따라 항상 같이 적용. --}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#1a1d2e">
<meta name="application-name" content="BookSys">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="BookSys">
<meta name="mobile-web-app-capable" content="yes">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('icons/apple-touch-icon-180.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/icon-192.png') }}">
<link rel="icon" type="image/png" sizes="512x512" href="{{ asset('icons/icon-512.png') }}">

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('{{ asset('service-worker.js') }}').catch(() => {});
    });
}
</script>
