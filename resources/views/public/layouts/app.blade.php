<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'BookFlow') · BookFlow</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans KR', sans-serif; background: #f6f7fb; color: #212529; min-height: 100vh; display: flex; flex-direction: column; }
        .navy { color: #1f3a5f; }
        .btn-navy { background: #1f3a5f; border-color: #1f3a5f; color: #fff; }
        .btn-navy:hover, .btn-navy:focus { background: #15294a; border-color: #15294a; color: #fff; }
        .btn-outline-navy { color: #1f3a5f; border-color: #1f3a5f; }
        .btn-outline-navy:hover { background: #1f3a5f; color: #fff; }
        .topbar { background: #fff; border-bottom: 1px solid #e6e9ef; }
        .topbar .brand { color: #1f3a5f; font-weight: 700; font-size: 1.3rem; text-decoration: none; }
        main { flex: 1; padding: 2rem 1rem; }
        .card { border: 0; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        .card-header.bg-white strong { color: #1f3a5f; }
        footer.public-footer {
            background: #fff; border-top: 1px solid #e6e9ef;
            padding: 1.5rem 1rem; color: #6c757d; font-size: .85rem; text-align: center;
        }
        a { color: #1f3a5f; }
        code { font-size: .85em; color: #1f3a5f; background: #eaf0fa; padding: .1em .4em; border-radius: 4px; }
        .badge.bg-navy { background: #1f3a5f; color: #fff; }
    </style>
    @stack('head')
</head>
<body>

<header class="topbar">
    <div class="container d-flex align-items-center justify-content-between py-3">
        <a href="/" class="brand">
            <i class="bi bi-book-half"></i> BookFlow
        </a>
        <nav class="d-flex align-items-center gap-2">
            @auth
                <span class="text-muted small d-none d-md-inline">
                    <i class="bi bi-person-circle"></i>
                    {{ auth()->user()->name }}
                    <span class="badge bg-light text-dark">{{ auth()->user()->role_code }}</span>
                </span>
                @if(auth()->user()->role_code === 'admin')
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-navy btn-sm">
                        <i class="bi bi-speedometer2"></i> 관리자
                    </a>
                @else
                    <a href="{{ route('mypage') }}" class="btn btn-outline-navy btn-sm">
                        <i class="bi bi-person"></i> 마이페이지
                    </a>
                @endif
                <form method="POST" action="{{ route('public.logout') }}" class="m-0">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary">로그아웃</button>
                </form>
            @else
                <a href="{{ route('public.login') }}" class="btn btn-outline-navy btn-sm">
                    <i class="bi bi-box-arrow-in-right"></i> 로그인
                </a>
                <a href="{{ route('public.register') }}" class="btn btn-navy btn-sm">
                    <i class="bi bi-person-plus"></i> 가입
                </a>
            @endauth
        </nav>
    </div>
</header>

<main>
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
    &copy; {{ date('Y') }} BookFlow · {{ setting('company_name', 'e-Learn') }}
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
