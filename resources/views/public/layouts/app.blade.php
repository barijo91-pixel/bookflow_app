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
            --sidebar-bg: #1a1d2e;            /* 짙은 네이비-블랙 */
            --sidebar-border: #2a2e44;
            --sidebar-text: #e2e8f0;
            --sidebar-text-strong: #ffffff;
            --sidebar-section: #6b7280;
            --sidebar-active-bg: #262a40;
            --sidebar-hover-bg: rgba(255,255,255,0.06);
            --content-bg: #f6f7fb;
            --text-muted-2: #6c757d;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body { font-family: 'Noto Sans KR', sans-serif; background: var(--content-bg); color: #212529; margin: 0; }
        .navy { color: var(--navy); }
        .btn-navy { background: var(--navy); border-color: var(--navy); color: #fff; }
        .btn-navy:hover, .btn-navy:focus { background: var(--navy-dark); border-color: var(--navy-dark); color: #fff; }
        .btn-outline-navy { color: var(--navy); border-color: var(--navy); }
        .btn-outline-navy:hover { background: var(--navy); color: #fff; }
        a { color: var(--navy); }
        code { font-size: .85em; color: var(--navy); background: var(--navy-soft); padding: .1em .4em; border-radius: 4px; }
        .badge.bg-navy { background: var(--navy); color: #fff; }
        .card { border: 0; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .card-header.bg-white strong { color: var(--navy); }

        /* ---------- Public shell with sidebar (인증 사용자) ---------- */
        .public-shell { display: flex; min-height: 100vh; }
        .public-sidebar {
            width: 240px; background: var(--sidebar-bg); border-right: 1px solid var(--sidebar-border);
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
        .public-nav .nav-item i { width: 18px; text-align: center; opacity: .85; }
        .public-nav .nav-item:hover { background: var(--sidebar-hover-bg); color: var(--sidebar-text-strong); }
        .public-nav .nav-item:hover i { opacity: 1; }
        .public-nav .nav-item.active {
            background: var(--sidebar-active-bg); color: var(--sidebar-text-strong); font-weight: 600;
            border-left-color: #5b8def;
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

        /* 모바일 (768px 이하): 사이드바 숨김 + 햄버거 */
        @media (max-width: 768px) {
            .public-sidebar {
                transform: translateX(-100%); transition: transform .2s;
                box-shadow: 4px 0 12px rgba(0,0,0,.1);
            }
            .public-sidebar.show { transform: translateX(0); }
            .public-main { margin-left: 0; }
            .public-topbar .hamburger { display: inline-flex; }
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
    @stack('head')
</head>
<body>

@auth
{{-- 인증 사용자: 사이드바 + 메인 --}}
<div class="public-shell">
    @include('public.partials.sidebar')
    <div class="public-main">
        <header class="public-topbar">
            <button type="button" class="btn btn-sm btn-outline-secondary hamburger" onclick="document.querySelector('.public-sidebar').classList.toggle('show')">
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
        </header>
        <main class="public-content">
            <div style="max-width: @yield('max_width', '1400px');">
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
@stack('scripts')
</body>
</html>
