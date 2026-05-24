<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>{{ $vendor->name ?? 'BookSys' }} 교재 안내</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans KR', sans-serif; background: #f6f7fb; }
        .hero { background: linear-gradient(135deg, #1f3a5f 0%, #15294a 100%); color: #fff; padding: 2rem 1rem; }
        .book-card { background: #fff; border-radius: 10px; padding: 1rem; margin-bottom: .8rem; display: flex; gap: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .book-card img { width: 70px; height: 100px; object-fit: cover; border-radius: 4px; flex-shrink: 0; }
        .book-card .meta { color: #6c757d; font-size: .85rem; }
        .price { font-weight: bold; color: #1f3a5f; font-size: 1.1rem; }
    </style>
</head>
<body>
<div class="hero text-center">
    <i class="bi bi-mortarboard" style="font-size:2rem"></i>
    <h1 class="h4 mt-2">{{ $vendor->name ?? '' }}</h1>
    <p class="mb-0 small opacity-75">{{ $class->name ?? '' }} · {{ $student->name ?? '' }} 학부모님</p>
</div>

<div class="container mt-3" style="max-width: 600px;">
    <div class="mb-3">
        <h2 class="h5"><i class="bi bi-journals"></i> 교재 목록 ({{ $books->count() }}권)</h2>
        <p class="text-muted small">학원에서 안내드린 교재 목록입니다. 결제 기능은 곧 오픈 예정입니다.</p>
    </div>

    @foreach($books as $b)
        <div class="book-card">
            <div>
                @if($b->cover_path)
                    <img src="{{ str_starts_with($b->cover_path, 'http') ? $b->cover_path : asset('storage/'.$b->cover_path) }}" alt="">
                @else
                    <div style="width:70px;height:100px;background:#eee;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#999"><i class="bi bi-book" style="font-size:2rem"></i></div>
                @endif
            </div>
            <div class="flex-grow-1">
                <div class="fw-bold">{{ $b->title }}</div>
                @if($b->subtitle)<div class="text-muted small">{{ $b->subtitle }}</div>@endif
                <div class="meta">
                    {{ $b->author }} · {{ $b->publisher_name }}
                </div>
                <div class="meta"><code>{{ $b->isbn }}</code></div>
                <div class="mt-2 d-flex justify-content-between align-items-center">
                    <span class="price">{{ number_format($b->price) }}원</span>
                    <span class="text-muted small">수량 {{ $b->qty }}권</span>
                </div>
            </div>
        </div>
    @endforeach

    <div class="text-center text-muted small mt-4 mb-5">
        문의: {{ $vendor->mobile ?? $vendor->tel ?? '' }}<br>
        <span style="opacity:.6">Powered by BookSys</span>
    </div>
</div>
</body>
</html>
