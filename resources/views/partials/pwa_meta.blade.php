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

<style>
    /* 앱 설치 안내 floating 버튼 */
    .pwa-install-btn {
        position: fixed; right: 20px; bottom: 20px;
        background: #1a1d2e; color: #fff;
        border: none; border-radius: 999px;
        padding: 12px 20px; font-size: 14px; font-weight: 600;
        box-shadow: 0 4px 16px rgba(0,0,0,0.25);
        cursor: pointer; z-index: 9999;
        display: none; align-items: center; gap: 8px;
        font-family: 'Noto Sans KR', sans-serif;
        transition: transform .15s, box-shadow .15s;
    }
    .pwa-install-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.35); }
    .pwa-install-btn .close { margin-left: 4px; opacity: .7; font-size: 16px; line-height: 1; }
    .pwa-install-btn .close:hover { opacity: 1; }
    .pwa-install-modal {
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.5);
        display: none; align-items: center; justify-content: center;
        z-index: 10000; padding: 20px;
        font-family: 'Noto Sans KR', sans-serif;
    }
    .pwa-install-modal.show { display: flex; }
    .pwa-install-modal .box {
        background: #fff; border-radius: 16px;
        max-width: 360px; width: 100%;
        padding: 24px; text-align: center;
    }
    .pwa-install-modal h3 { color: #1f3a5f; margin: 0 0 12px; font-size: 18px; font-weight: 700; }
    .pwa-install-modal p { color: #495057; font-size: 14px; line-height: 1.6; margin: 0 0 16px; }
    .pwa-install-modal .step { background: #f6f7fb; border-radius: 8px; padding: 10px; margin: 8px 0; font-size: 13px; color: #495057; }
    .pwa-install-modal button { background: #1a1d2e; color: #fff; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; }
</style>

<button id="pwaInstallBtn" class="pwa-install-btn" type="button" aria-label="앱 설치">
    <i class="bi bi-download"></i> 앱 설치
    <span class="close" id="pwaInstallClose" title="이번엔 안 함">×</span>
</button>

<div id="pwaInstallModal" class="pwa-install-modal" role="dialog" aria-modal="true">
    <div class="box">
        <h3><i class="bi bi-phone"></i> 홈 화면에 BookSys 추가</h3>
        <p>아이폰/사파리에서는 직접 추가해주세요.</p>
        <div class="step">1) 하단 공유 버튼 <strong>⬆️</strong> 누르기</div>
        <div class="step">2) <strong>"홈 화면에 추가"</strong> 선택</div>
        <div class="step">3) 이름 확인 후 <strong>"추가"</strong></div>
        <button type="button" onclick="document.getElementById('pwaInstallModal').classList.remove('show')">닫기</button>
    </div>
</div>

<script>
(function () {
    // 1. Service worker 등록
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('{{ asset('service-worker.js') }}').catch(() => {});
        });
    }

    // 2. 이미 standalone(설치된 상태)면 아무것도 표시 안 함
    const isStandalone =
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true ||
        document.referrer.startsWith('android-app://');
    if (isStandalone) return;

    // 3. 사용자가 24시간 내 닫았으면 표시 안 함
    const DISMISS_KEY = 'pwa-install-dismissed-at';
    const dismissed = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
    if (dismissed && Date.now() - dismissed < 24 * 60 * 60 * 1000) return;

    const btn = document.getElementById('pwaInstallBtn');
    const modal = document.getElementById('pwaInstallModal');
    const closeBtn = document.getElementById('pwaInstallClose');

    // 4. iOS Safari 감지 — beforeinstallprompt 없으니 모달로 안내
    const ua = navigator.userAgent.toLowerCase();
    const isIos = /iphone|ipad|ipod/.test(ua);
    const isSafari = /safari/.test(ua) && !/chrome|crios|fxios|edgios/.test(ua);

    let deferredPrompt = null;

    // 5. Chrome/Edge/Android: beforeinstallprompt 캡처
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        btn.style.display = 'inline-flex';
    });

    // 6. iOS Safari는 beforeinstallprompt 없으니 바로 노출
    if (isIos && isSafari) {
        btn.style.display = 'inline-flex';
    }

    // 7. 버튼 클릭
    btn.addEventListener('click', async (e) => {
        if (e.target === closeBtn) return; // 닫기 X 클릭은 별도 처리
        if (deferredPrompt) {
            // Chrome/Edge/Android
            deferredPrompt.prompt();
            const choice = await deferredPrompt.userChoice;
            deferredPrompt = null;
            btn.style.display = 'none';
            if (choice.outcome === 'dismissed') {
                localStorage.setItem(DISMISS_KEY, String(Date.now()));
            }
        } else if (isIos && isSafari) {
            // iOS Safari — 안내 모달
            modal.classList.add('show');
        }
    });

    // 8. 닫기(X) 클릭 — 24시간 표시 안 함
    closeBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        btn.style.display = 'none';
        localStorage.setItem(DISMISS_KEY, String(Date.now()));
    });

    // 9. 설치 완료 시 자동 숨김
    window.addEventListener('appinstalled', () => {
        btn.style.display = 'none';
        deferredPrompt = null;
    });

    // 10. 모달 바깥 클릭 시 닫기
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.remove('show');
    });
})();
</script>
