@extends('public.layouts.app')
@section('title', '교재 조회')
@section('max_width', '1400px')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-journals"></i> 교재 조회
        <small class="text-muted fs-6">{{ number_format($books->total()) }}권</small>
    </h1>
    <p class="text-muted small mb-0">
        @if($distributor)
            <strong>{{ filled($distributor->business_name) ? $distributor->business_name.'('.$distributor->name.')' : $distributor->name }}</strong>
            총판이 취급하는 교재입니다. 학원을 고르면 그 학원의 <strong>공급가</strong>까지 함께 보여줍니다.
        @else
            소속 총판이 없어 표시할 교재가 없습니다.
        @endif
    </p>
</div>

@if(session('info'))<div class="alert alert-info py-2 small">{{ session('info') }}</div>@endif

@unless($distributor)
    <div class="alert alert-warning py-2 small">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>소속 총판이 배정되지 않았습니다.</strong>
        교재는 총판이 취급하는 것만 판매할 수 있어 목록이 비어 있습니다. 관리자에게 총판 배정을 요청해주세요.
    </div>
@endunless

<form method="GET" action="{{ route('my.books.index') }}" class="card section-card mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            @if($vendors->isNotEmpty())
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">학원 (공급가 기준)</label>
                    <select name="vendor_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">정가만 보기</option>
                        @foreach($vendors as $v)
                            <option value="{{ $v->id }}" @selected($selectedVendor && $selectedVendor->id == $v->id)>{{ $v->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">출판사</label>
                <select name="publisher" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    @foreach($filterOptions['publisher'] as $p)
                        <option value="{{ $p->id }}" @selected($activeFilters['publisher'] == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">분류</label>
                <select name="school" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    @foreach($filterOptions['school'] as $s)
                        <option value="{{ $s->code }}" @selected($activeFilters['school'] === $s->code)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">과목</label>
                <select name="subject" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    @foreach($filterOptions['subject'] as $s)
                        <option value="{{ $s->code }}" @selected($activeFilters['subject'] === $s->code)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">검색 (제목·ISBN·시리즈·저자)</label>
                <input type="text" name="q" value="{{ $activeFilters['q'] }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search"></i></button>
                <a href="{{ route('my.books.index') }}" class="btn btn-sm btn-outline-secondary" title="초기화"><i class="bi bi-x-lg"></i></a>
            </div>
        </div>
    </div>
</form>

@if($selectedVendor)
    <div class="alert alert-light border py-2 small mb-3">
        <i class="bi bi-percent"></i>
        <strong>{{ $selectedVendor->name }}</strong> 기준 공급가입니다.
        @if($generalRate !== null)
            기본 할인율 <strong class="navy">{{ rtrim(rtrim($generalRate, '0'), '.') }}%</strong>
            @if($bookDiscounts->isNotEmpty())
                · 도서별 할인율이 지정된 교재는 그 값이 우선 적용됩니다.
            @endif
        @endif
    </div>
@endif

<div class="card section-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    <th>교재</th>
                    <th>출판사</th>
                    <th>ISBN</th>
                    <th class="text-end">정가</th>
                    @if($selectedVendor)
                        <th class="text-end">할인율</th>
                        <th class="text-end">공급가</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($books as $b)
                    @php
                        $rate = $selectedVendor
                            ? (float) ($bookDiscounts[$b->id] ?? $generalRate ?? 0)
                            : null;
                        $supply = $rate !== null ? (int) round($b->price * (100 - $rate) / 100) : null;
                    @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                {{-- 표지 썸네일 — 마우스 올리면 확대. 없으면 책 아이콘 (주문화면과 동일) --}}
                                @php $coverUrl = $b->cover_path ? (str_starts_with($b->cover_path, 'http') ? $b->cover_path : asset('storage/'.$b->cover_path)) : null; @endphp
                                <div class="book-thumb">
                                    @if($coverUrl)
                                        <img src="{{ $coverUrl }}" alt="{{ $b->title }}" loading="lazy"
                                             onerror="this.parentElement.classList.add('no-cover');this.remove();">
                                    @else
                                        <i class="bi bi-book"></i>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <strong>{{ $b->title }}</strong>
                                    @if($b->series_name)
                                        <span class="text-muted small ms-1">{{ $b->series_name }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="text-muted text-nowrap">{{ $b->publisher_name ?: '-' }}</td>
                        <td class="text-muted text-nowrap"><code>{{ $b->isbn ?: '-' }}</code></td>
                        <td class="text-end text-nowrap">{{ number_format($b->price) }}원</td>
                        @if($selectedVendor)
                            <td class="text-end text-nowrap">
                                {{ rtrim(rtrim(number_format($rate, 2), '0'), '.') }}%
                                @if($bookDiscounts->has($b->id))
                                    <span class="badge bg-secondary ms-1">개별</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap"><strong class="navy">{{ number_format($supply) }}원</strong></td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $selectedVendor ? 6 : 4 }}" class="text-center text-muted py-5">
                            @if($distributor)
                                조건에 맞는 교재가 없습니다.<br>
                                <small>총판이 취급 교재(재고)를 등록하면 여기에 표시됩니다.</small>
                            @else
                                소속 총판이 배정되어야 교재가 표시됩니다.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($books->hasPages())
    <div class="mt-3">{{ $books->links() }}</div>
@endif
@endsection

@push('head')
<style>
/* 표지 썸네일 + hover 확대 — 주문화면(order_new)과 같은 규격 */
.book-thumb {
    position: relative; flex-shrink: 0;
    width: 40px; height: 54px; border-radius: 4px; overflow: visible;
    background: #f1f4f9; display: flex; align-items: center; justify-content: center;
}
.book-thumb img {
    width: 40px; height: 54px; object-fit: cover; border-radius: 4px;
    border: 1px solid #e6e9ef; background: #fff; cursor: zoom-in;
}
.book-thumb.no-cover, .book-thumb:not(:has(img)) { color: #b6c0cd; font-size: 1.2rem; }
.min-w-0 { min-width: 0; }
/* 확대 미리보기 — 표가 overflow 컨테이너라 fixed 팝업으로 띄운다(안 잘림) */
#coverZoom {
    position: fixed; z-index: 2000; pointer-events: none; display: none;
    padding: 6px; background: #fff; border: 1px solid #d7dee8; border-radius: 8px;
    box-shadow: 0 12px 40px rgba(0,0,0,.3);
}
#coverZoom img { display: block; width: 240px; height: auto; border-radius: 4px; }
@media (max-width: 991px) { #coverZoom img { width: 180px; } }
</style>
@endpush

@push('scripts')
<script>
// 표지 확대 미리보기 — 썸네일에 마우스 올리면 fixed 팝업으로 크게 (overflow 잘림 회피)
(function () {
    var box = document.getElementById('coverZoom');
    if (! box) {
        box = document.createElement('div');
        box.id = 'coverZoom';
        box.innerHTML = '<img alt="">';
        document.body.appendChild(box);
    }
    var img = box.querySelector('img');

    function place(e) {
        var pad = 16, w = box.offsetWidth || 252, h = box.offsetHeight || 340;
        var x = e.clientX + pad, y = e.clientY - h / 2;
        if (x + w > window.innerWidth)  x = e.clientX - w - pad;   // 오른쪽 넘치면 왼쪽에
        if (y < 8) y = 8;
        if (y + h > window.innerHeight) y = window.innerHeight - h - 8;
        box.style.left = x + 'px';
        box.style.top  = y + 'px';
    }
    // 이벤트 위임 — 목록이 다시 그려져도 동작
    document.addEventListener('mouseover', function (e) {
        var t = e.target.closest('.book-thumb img');
        if (! t) return;
        img.src = t.currentSrc || t.src;
        box.style.display = 'block';
        place(e);
    });
    document.addEventListener('mousemove', function (e) {
        if (box.style.display === 'block') place(e);
    });
    document.addEventListener('mouseout', function (e) {
        if (e.target.closest('.book-thumb img')) box.style.display = 'none';
    });
})();
</script>
@endpush