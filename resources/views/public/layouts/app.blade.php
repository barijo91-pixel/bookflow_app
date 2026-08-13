<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <title>@yield('title', 'BookSys') · BookSys</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --navy: #1f3a5f;
            --navy-dark: #15294a;
            --navy-soft: #eaf0fa;
            --btn-blue: #2c5282;        /* 버튼 공통 — 딥네이비보다 밝은 톤 */
            --btn-blue-dark: #24446e;   /* 버튼 호버 */
            --link-blue: #1b6ac9;       /* 리스트 이름 링크 — 클릭 가능함이 보이게 한 톤 밝게 */
            --link-blue-dark: #12508f;  /* 링크 호버 */
            --sidebar-bg: #14264a;            /* 짙은 네이비 (coolenglish.kr 라이브 톤) */
            --sidebar-border: #22345c;
            --sidebar-text: #e8eefb;
            --sidebar-text-strong: #ffffff;
            --sidebar-section: #93c5fd;
            --sidebar-active-bg: #2d4b70;
            --sidebar-hover-bg: rgba(255,255,255,0.08);
            --content-bg: #f6f7fb;
            --text-muted-2: #6c757d;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body { font-family: 'Noto Sans KR', sans-serif; background: var(--content-bg); color: #212529; margin: 0; }
        .navy { color: var(--navy); }
        /* 버튼 톤 — 딥네이비(#1f3a5f)는 어두워 한 단계 밝은 네이비블루로 통일.
           btn-navy/btn-primary 를 같은 색으로 맞춰 화면 간 버튼색 차이 제거 */
        .btn-navy, .btn-primary { background: var(--btn-blue); border-color: var(--btn-blue); color: #fff; }
        .btn-navy:hover, .btn-navy:focus,
        .btn-primary:hover, .btn-primary:focus { background: var(--btn-blue-dark); border-color: var(--btn-blue-dark); color: #fff; }
        .btn-outline-navy { color: var(--btn-blue); border-color: var(--btn-blue); }
        .btn-outline-navy:hover { background: var(--btn-blue); color: #fff; }
        a { color: var(--navy); }
        /* 리스트에서 상세로 들어가는 이름 링크 — 클릭 가능한 걸 색으로 알림 */
        .link-name { color: var(--link-blue); text-decoration: none; font-weight: 700; }
        .link-name:hover, .link-name:focus { color: var(--link-blue-dark); text-decoration: underline; }
        code { font-size: .85em; color: var(--navy); background: var(--navy-soft); padding: .1em .4em; border-radius: 4px; }
        .badge.bg-navy { background: var(--navy); color: #fff; }
        .card { border: 0; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        /* 폼 포커스 — 테마 네이비/하늘색 링으로 통일 */
        .form-control:focus, .form-select:focus {
            border-color: #93c5fd;
            box-shadow: 0 0 0 .2rem rgba(20, 38, 74, .12);
        }
        .card-header.bg-white strong { color: var(--navy); }

        /* ---------- Public shell with sidebar (인증 사용자) ---------- */
        .public-shell { display: flex; min-height: 100vh; }
        .public-sidebar {
            width: 240px;
            background: linear-gradient(180deg, #1e365f 0%, #16294c 26%, #0e1c39 100%);
            border-right: 1px solid var(--sidebar-border);
            position: fixed; top: 0; left: 0; bottom: 0;
            display: flex; flex-direction: column; z-index: 100;
        }
        .public-sidebar-brand { padding: 1.2rem 1.3rem; border-bottom: 1px solid var(--sidebar-border); }
        .public-sidebar-brand a {
            display: flex; align-items: center; gap: .6rem;
            color: var(--sidebar-text-strong); text-decoration: none; font-weight: 700; font-size: 1.15rem;
        }
        .public-sidebar-brand i { font-size: 1.4rem; }
        .public-nav { flex: 1; overflow-y: auto; padding: .6rem 0 2rem; }
        .public-nav::-webkit-scrollbar { width: 6px; }
        .public-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 3px; }
        .public-nav::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.25); }
        .public-nav .nav-section {
            padding: .9rem 1.3rem .35rem; font-size: .7rem; color: var(--sidebar-section);
            text-transform: uppercase; letter-spacing: .06em; font-weight: 600;
        }
        .public-nav .nav-item {
            display: flex; align-items: center; gap: .7rem;
            padding: .6rem 1.3rem; color: var(--sidebar-text); text-decoration: none;
            font-size: .92rem; transition: background .15s, color .15s;
            border-left: 3px solid transparent;
        }
        /* 아이콘 — 하늘색 액센트(섹션 라벨과 동일 톤) */
        .public-nav .nav-item i { width: 18px; text-align: center; color: #93c5fd; opacity: .95; }
        .public-nav .nav-item:hover { background: var(--sidebar-hover-bg); color: var(--sidebar-text-strong); }
        .public-nav .nav-item:hover i { opacity: 1; }
        .public-nav .nav-item.active {
            background: var(--sidebar-active-bg); color: var(--sidebar-text-strong); font-weight: 600;
            border-left-color: #93c5fd;
        }
        .public-nav .nav-item.active i { opacity: 1; }
        .public-main { flex: 1; margin-left: 240px; display: flex; flex-direction: column; }
        .public-topbar {
            background: var(--sidebar-bg); border-bottom: 1px solid var(--sidebar-border);
            padding: .8rem 1.5rem; display: flex; align-items: center; justify-content: space-between;
            color: var(--sidebar-text);
        }
        .public-topbar .user-info { font-size: .85rem; color: var(--sidebar-text); }
        .public-topbar .btn-outline-secondary {
            color: var(--sidebar-text); border-color: rgba(255,255,255,0.25);
        }
        .public-topbar .btn-outline-secondary:hover,
        .public-topbar .btn-outline-secondary:focus {
            background: rgba(255,255,255,0.1); color: var(--sidebar-text-strong);
            border-color: rgba(255,255,255,0.4);
        }
        .public-topbar .user-info .badge { margin-left: .3rem; }
        .public-content { flex: 1; padding: 1.5rem 1.5rem 2rem; }

        /* ---------- 모바일 하단 탭바 (앱 스타일) ---------- */
        .mobile-bottom-nav { display: none; }
        .sidebar-overlay { display: none; }

        /* 모바일 (768px 이하): 사이드바 오프캔버스 + 하단 탭바 */
        @media (max-width: 768px) {
            .public-sidebar {
                transform: translateX(-100%); transition: transform .22s ease;
                box-shadow: 4px 0 12px rgba(0,0,0,.1); width: 270px;
            }
            .public-sidebar.show { transform: translateX(0); }
            .public-main { margin-left: 0; }
            .public-topbar .hamburger { display: inline-flex; }

            /* 배경 오버레이 (사이드바 열렸을 때) */
            .sidebar-overlay {
                position: fixed; inset: 0; background: rgba(0,0,0,.45);
                z-index: 99; opacity: 0; transition: opacity .22s;
            }
            .sidebar-overlay.show { display: block; opacity: 1; }

            /* 하단 탭바 */
            .mobile-bottom-nav {
                display: flex; position: fixed; bottom: 0; left: 0; right: 0; z-index: 150;
                background: #fff; border-top: 1px solid #e5e7eb;
                box-shadow: 0 -2px 12px rgba(0,0,0,.07);
                padding-bottom: env(safe-area-inset-bottom, 0);
            }
            .mbn-item {
                position: relative; flex: 1; display: flex; flex-direction: column;
                align-items: center; justify-content: center; gap: 2px;
                padding: .45rem 0 .4rem; min-height: 56px;
                color: #8a93a2; text-decoration: none; font-size: .68rem; font-weight: 500;
                background: none; border: 0; cursor: pointer;
            }
            .mbn-item i { font-size: 1.35rem; line-height: 1; }
            .mbn-item.active { color: var(--navy); }
            .mbn-item.active i { transform: translateY(-1px); }
            .mbn-badge {
                position: absolute; top: 5px; left: 50%; margin-left: 4px;
                background: #dc3545; color: #fff; font-size: .6rem; font-weight: 700;
                min-width: 16px; height: 16px; line-height: 16px; text-align: center;
                border-radius: 999px; padding: 0 4px;
            }

            /* 콘텐츠/푸터가 하단 탭바에 가리지 않게 */
            .public-content { padding: 1rem 1rem 5rem; }
            .public-footer { margin-bottom: 3.6rem; }

            /* 모바일 터치 타겟 확대 */
            .public-content .btn { min-height: 42px; }
        }
        @media (min-width: 769px) {
            .public-topbar .hamburger { display: none; }
        }

        /* ---------- Guest layout (사이드바 없음) ---------- */
        .guest-shell { display: flex; flex-direction: column; min-height: 100vh; }
        .guest-topbar { background: #fff; border-bottom: 1px solid var(--sidebar-border); }
        .guest-topbar .brand { color: var(--navy); font-weight: 700; font-size: 1.3rem; text-decoration: none; }
        .guest-main { flex: 1; padding: 2rem 1rem; }
        footer.public-footer {
            background: #fff; border-top: 1px solid var(--sidebar-border);
            padding: 1.5rem 1rem; color: var(--text-muted-2); font-size: .85rem; text-align: center;
        }
    </style>
    @include('partials.pwa_meta')
    @stack('head')
</head>
<body>

@auth
{{-- 인증 사용자: 사이드바 + 메인 --}}
<div class="public-shell">
    @include('public.partials.sidebar')
    <div class="sidebar-overlay" onclick="closeMobileMenu()"></div>
    <div class="public-main">
        <header class="public-topbar">
            <button type="button" class="btn btn-sm btn-outline-secondary hamburger" onclick="toggleMobileMenu()">
                <i class="bi bi-list"></i>
            </button>
            <span class="user-info">
                <i class="bi bi-person-circle"></i>
                {{ auth()->user()->name }}
                <span class="badge bg-light text-dark">{{ match(auth()->user()->role_code) {
                    'admin' => '관리자',
                    'distributor' => '총판',
                    'agent' => '영업자',
                    'academy' => '학원',
                    default => auth()->user()->role_code
                } }}</span>
                <code class="ms-1">{{ auth()->user()->login_id }}</code>
            </span>

            {{-- 대행 로그인 중 표시 + 복귀 (관리자가 다른 사용자 화면을 볼 때) --}}
            @if(session()->has(\App\Http\Controllers\Admin\ImpersonateController::SESSION_KEY))
                <form method="POST" action="{{ route('impersonate.stop') }}" class="ms-auto d-flex align-items-center gap-2">
                    @csrf
                    <span class="badge" style="background:#f59e0b; color:#1f2937; font-weight:700;">
                        <i class="bi bi-person-badge"></i> 대행 로그인 중
                    </span>
                    <button class="btn btn-sm btn-light" style="font-weight:600;">
                        <i class="bi bi-box-arrow-left"></i> 관리자로 복귀
                    </button>
                </form>
            @endif
        </header>
        <main class="public-content">
            <div style="max-width: @yield('max_width', '1400px');">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
                @endif
                @if(session('info'))
                    <div class="alert alert-info alert-dismissible fade show">{{ session('info') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
                @endif
                @yield('content')
            </div>
        </main>
        <footer class="public-footer">
            &copy; {{ date('Y') }} BookSys · {{ setting('company_name', 'e-Learn') }}
        </footer>
    </div>
    {{-- 모바일 하단 탭바 --}}
    @include('public.partials.bottom_nav')
</div>
@else
{{-- 비인증 사용자: 상단 헤더 + 본문 (기존 layout) --}}
<div class="guest-shell">
    <header class="guest-topbar">
        <div class="container d-flex align-items-center justify-content-between py-3">
            <a href="/" class="brand">
                <i class="bi bi-book-half"></i> BookSys
            </a>
            <nav class="d-flex align-items-center gap-2">
                <a href="{{ route('public.login') }}" class="btn btn-outline-navy btn-sm">
                    <i class="bi bi-box-arrow-in-right"></i> 로그인
                </a>
                <a href="{{ route('public.register') }}" class="btn btn-navy btn-sm">
                    <i class="bi bi-person-plus"></i> 가입
                </a>
            </nav>
        </div>
    </header>
    <main class="guest-main">
        <div class="container" style="max-width: @yield('max_width', '960px');">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
            @endif
            @yield('content')
        </div>
    </main>
    <footer class="public-footer">
        &copy; {{ date('Y') }} BookSys · {{ setting('company_name', 'e-Learn') }}
    </footer>
</div>
@endauth

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // 모바일 메뉴(오프캔버스 사이드바) 토글 — 하단 '더보기' + 상단 햄버거 공용
    function toggleMobileMenu() {
        document.querySelector('.public-sidebar')?.classList.toggle('show');
        document.querySelector('.sidebar-overlay')?.classList.toggle('show');
    }
    function closeMobileMenu() {
        document.querySelector('.public-sidebar')?.classList.remove('show');
        document.querySelector('.sidebar-overlay')?.classList.remove('show');
    }
    // 사이드바 내 링크 클릭 시 자동 닫기 (모바일)
    document.querySelectorAll('.public-sidebar .nav-item').forEach(el => {
        el.addEventListener('click', () => { if (window.innerWidth <= 768) closeMobileMenu(); });
    });
</script>
@stack('scripts')
</body>
</html>
