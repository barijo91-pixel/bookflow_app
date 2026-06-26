<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ setting('company_name', 'BookSys') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --navy:#1f3a5f; }
        body { background:#f4f6f9; color:#333; }
        .legal-wrap { max-width:840px; margin:0 auto; padding:2rem 1rem 4rem; }
        .legal-nav { font-size:.88rem; }
        .legal-nav a { color:var(--navy); text-decoration:none; }
        .legal-nav a:hover { text-decoration:underline; }
        .legal-card { background:#fff; border:1px solid #e9ecef; border-radius:.6rem; padding:2rem 2.2rem; }
        .legal-card h1 { font-size:1.5rem; font-weight:700; color:var(--navy); margin-bottom:.4rem; }
        .legal-card .lead-date { color:#888; font-size:.85rem; margin-bottom:1.6rem; }
        .legal-card h2 { font-size:1.08rem; font-weight:700; color:var(--navy); margin:1.8rem 0 .6rem; padding-top:.4rem; }
        .legal-card p, .legal-card li { font-size:.92rem; line-height:1.75; color:#444; }
        .legal-card ul, .legal-card ol { padding-left:1.3rem; }
        .legal-card table { width:100%; font-size:.88rem; border-collapse:collapse; margin:.6rem 0; }
        .legal-card th, .legal-card td { border:1px solid #e3e7ec; padding:.5rem .7rem; text-align:left; }
        .legal-card th { background:#f1f4f8; color:var(--navy); }
    </style>
</head>
<body>
    <div class="legal-wrap">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <a href="{{ route('home') }}" class="legal-nav"><i class="bi bi-arrow-left"></i> 홈으로</a>
            <div class="legal-nav">
                <a href="{{ route('legal.terms') }}" class="me-3">이용약관</a>
                <a href="{{ route('legal.privacy') }}" class="me-3">개인정보처리방침</a>
                <a href="{{ route('legal.refund') }}">취소·환불정책</a>
            </div>
        </div>
        <div class="legal-card">
            @yield('content')
        </div>
        <p class="text-center text-muted small mt-4 mb-0">&copy; {{ date('Y') }} {{ setting('company_name', 'BookSys') }}. All rights reserved.</p>
    </div>
</body>
</html>
