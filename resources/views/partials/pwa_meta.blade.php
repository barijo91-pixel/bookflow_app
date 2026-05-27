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

<button id="pwaInstallBtn" class="pwa-install-btn" type="button" aria-label="북시스 앱설치">
    <i class="bi bi-download"></i> <span id="pwaInstallBtnText">북시스 앱설치</span>
    <span class="close" id="pwaInstallClose" title="이번엔 안 함">×</span>
</button>

<div id="pwaInstallModal" class="pwa-install-modal" role="dialog" aria-modal="true">
    <div class="box">
        <h3><i class="bi bi-download"></i> BookSys 설치 안내</h3>
        <p class="text-start mb-2"><strong>크롬·엣지 (PC)</strong></p>
        <div class="step text-start">주소창 우측 <strong>⊕ 아이콘</strong> 또는 ⋮ 메뉴 → <strong>"BookSys 설치"</strong> 클릭</div>
        <p class="text-start mb-2 mt-3"><strong>안드로이드 크롬</strong></p>
        <div class="step text-start">⋮ 메뉴 → <strong>"홈 화면에 추가"</strong> 선택</div>
        <p class="text-start mb-2 mt-3"><strong>아이폰 사파리</strong></p>
        <div class="step text-start">하단 공유 <strong>⬆️</strong> → <strong>"홈 화면에 추가"</strong></div>
        <p class="small text-muted mt-3 mb-0">이미 설치되어 있다면 주소창 우측 "앱에서 열기" 사용</p>
        <button type="button" class="mt-3" onclick="document.getElementById('pwaInstallModal').classList.remove('show')">닫기</button>
    </div>
</div>

<script>
// Service worker 등록은 즉시 가능
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('{{ asset('service-worker.js') }}').catch(() => {});
    });
}

// 나머지 DOM 요소 의존 로직은 DOMContentLoaded 후 실행
// (이 스크립트는 <head>에 있어서 body의 heroInstallBtn이 아직 없는 시점)
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('pwaInstallBtn');
    const modal = document.getElementById('pwaInstallModal');
    const closeBtn = document.getElementById('pwaInstallClose');
    // welcome 페이지 hero에 있는 버튼 (없을 수도 있음)
    const heroBtn = document.getElementById('heroInstallBtn');
    const heroInstalledHint = document.getElementById('heroInstalledHint');

    // 2. 이미 standalone(설치된 상태) 감지
    const isStandalone =
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true ||
        document.referrer.startsWith('android-app://');

    // 3. iOS Safari 감지
    const ua = navigator.userAgent.toLowerCase();
    const isIos = /iphone|ipad|ipod/.test(ua);
    const isSafari = /safari/.test(ua) && !/chrome|crios|fxios|edgios/.test(ua);

    let deferredPrompt = null;
    const DISMISS_KEY = 'pwa-install-dismissed-at';

    function showButtons() {
        if (heroBtn) heroBtn.style.display = 'inline-flex';
        // floating은 dismiss 24시간 룰 적용
        const dismissed = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
        if (!dismissed || Date.now() - dismissed >= 24 * 60 * 60 * 1000) {
            if (btn) btn.style.display = 'inline-flex';
        }
    }

    function markAsInstalled() {
        // 이미 설치된 사용자: 설치 버튼 둘 다 숨기고 안내 텍스트만 노출
        hideButtons();
        if (heroInstalledHint) heroInstalledHint.style.display = 'block';
    }

    function hideButtons() {
        if (btn) btn.style.display = 'none';
        if (heroBtn) heroBtn.style.display = 'none';
    }

    // 4. 이미 standalone(PWA로 실행 중) → 둘 다 숨김
    if (isStandalone) {
        hideButtons();
        return;
    }

    // 4-1. 페이지 로드 시 floating은 dismiss 룰 무관하게 일단 표시 (엣지·크롬 일관성)
    if (btn) btn.style.display = 'inline-flex';

    // 5. Chrome/Edge/Android: beforeinstallprompt 캡처
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        showButtons();
    });

    // 6. iOS Safari는 beforeinstallprompt 없으니 바로 노출
    if (isIos && isSafari) {
        showButtons();
    }

    // 7. 1.5초 후에도 beforeinstallprompt 안 오면 → 이미 설치된 상태로 간주
    setTimeout(() => {
        if (!deferredPrompt && !isStandalone && !(isIos && isSafari)) {
            markAsInstalled();
        }
    }, 1500);

    // 8. 클릭 핸들러 (floating, hero 공용)
    async function triggerInstall() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const choice = await deferredPrompt.userChoice;
            deferredPrompt = null;
            hideButtons();
            if (choice.outcome === 'dismissed') {
                localStorage.setItem(DISMISS_KEY, String(Date.now()));
            }
        } else if (isIos && isSafari) {
            modal.classList.add('show');
        } else {
            // beforeinstallprompt가 안 오는 경우 — 이미 설치되어 있거나 브라우저가 PWA 미지원
            modal.classList.add('show');
        }
    }

    if (btn) {
        btn.addEventListener('click', (e) => {
            if (e.target === closeBtn) return;
            triggerInstall();
        });
    }
    if (heroBtn) {
        heroBtn.addEventListener('click', triggerInstall);
    }

    // 9. 닫기(X)
    if (closeBtn) {
        closeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (btn) btn.style.display = 'none';
            localStorage.setItem(DISMISS_KEY, String(Date.now()));
        });
    }

    // 10. 설치 완료 시 자동 숨김
    window.addEventListener('appinstalled', () => {
        hideButtons();
        deferredPrompt = null;
        if (heroInstalledHint) heroInstalledHint.style.display = 'block';
    });

    // 11. 모달 바깥 클릭 시 닫기
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.classList.remove('show');
        });
    }
});
</script>
