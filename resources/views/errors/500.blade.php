<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>일시적인 오류 · BookSys</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans KR', sans-serif; background: #f6f7fb; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .wrap { text-align: center; padding: 2rem; max-width: 480px; }
        .code { font-size: 6rem; font-weight: 900; color: #1f3a5f; line-height: 1; margin-bottom: 1rem; }
        .btn-navy { background: #1f3a5f; border-color: #1f3a5f; color: #fff; }
        .btn-navy:hover { background: #15294a; border-color: #15294a; color: #fff; }
    </style>
</head>
<body>
<div class="wrap">
    <i class="bi bi-exclamation-triangle text-warning" style="font-size:3.5rem"></i>
    <div class="code">500</div>
    <h1 class="h4" style="color:#1f3a5f">일시적인 오류가 발생했습니다</h1>
    <p class="text-muted">잠시 후 다시 시도해주세요. 문제가 계속되면 관리자에게 문의해주세요.</p>
    <a href="/" class="btn btn-navy btn-lg px-4 mt-2">
        <i class="bi bi-house"></i> 홈으로
    </a>
</div>
</body>
</html>
