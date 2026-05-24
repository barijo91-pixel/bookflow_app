<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <title>관리자 로그인 · BookSys</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}" rel="stylesheet">
</head>
<body class="login-body">
<div class="login-wrap">
    <div class="login-card">
        <div class="login-brand">
            <i class="bi bi-book-half"></i>
            <h1>BookSys</h1>
            <p>관리자 콘솔</p>
        </div>
        @if ($errors->any())
            <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('admin.login.attempt') }}" autocomplete="on">
            @csrf
            <div class="mb-3">
                <label class="form-label small text-muted">아이디</label>
                <input type="text" name="login_id" value="{{ old('login_id') }}"
                       class="form-control form-control-lg" required autofocus
                       autocomplete="username">
            </div>
            <div class="mb-3">
                <label class="form-label small text-muted">비밀번호</label>
                <input type="password" name="password" class="form-control form-control-lg" required>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="remember" id="remember" class="form-check-input" value="1" checked>
                <label for="remember" class="form-check-label small">로그인 유지 (30일)</label>
            </div>
            <button type="submit" class="btn btn-primary w-100 btn-lg">
                <i class="bi bi-box-arrow-in-right"></i> 로그인
            </button>
        </form>
        <div class="login-footer">
            <small class="text-muted">e-Learn · BookSys Admin</small>
        </div>
    </div>
</div>
</body>
</html>
