@extends('public.layouts.app')
@section('title', '할인율 관리')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-percent"></i> 할인율 관리</h1>
    <p class="text-muted small mb-0">학원·도서별 할인율 설정 (개별 설정 우선)</p>
</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="row g-3">
    {{-- LEFT: 학원별 일반 할인율 --}}
    <div class="col-lg-5">
        <div class="card section-card">
            <div class="card-header"><strong><i class="bi bi-building"></i> 학원별 일반 할인율</strong></div>
            @if($vendors->isEmpty())
                <div class="card-body text-center text-muted py-4">담당 학원이 없습니다.</div>
            @else
                <style>
                    .vendor-row-selected {
                        background: #d4e0ee !important;
                        border-left: 4px solid #1f3a5f !important;
                        padding-left: calc(1rem - 4px) !important;
                    }
                    .vendor-row-selected .navy { color: #15294a !important; font-weight: 700; }
                </style>
                <div class="list-group list-group-flush">
                    @foreach($vendors as $v)
                        <div class="list-group-item {{ $v->vendor_id == $selectedVendorId ? 'vendor-row-selected' : '' }}">
                            <form method="POST" action="{{ route('my.discounts.vendor.update', $v->avd_id) }}" class="row g-2 align-items-center">
                                @csrf @method('PUT')
                                <input type="hidden" name="is_active" value="1">
                                <div class="col-12 col-sm-5 mb-1 mb-sm-0">
                                    <a href="{{ route('my.discounts.index', ['vendor_id' => $v->vendor_id]) }}"
                                       class="text-decoration-none d-block py-1 navy {{ $v->vendor_id == $selectedVendorId ? 'fw-bold' : '' }}">
                                        @if($v->vendor_id == $selectedVendorId)
                                            <i class="bi bi-check-circle-fill"></i>
                                        @else
                                            <i class="bi bi-circle text-muted"></i>
                                        @endif
                                        {{ $v->vendor_name }}
                                    </a>
                                </div>
                                @php $isBoth = \App\Services\TradeService::usesSplitRate($v->trade_type ?? null); @endphp
                                <div class="{{ $isBoth ? 'col-8 col-sm-3' : 'col-8 col-sm-5' }}">
                                    @if($isBoth)<div class="small fw-bold navy mb-1">소매</div>@endif
                                    <div class="input-group input-group-sm rate-stepper">
                                        <button type="button" class="btn btn-outline-secondary rate-down" tabindex="-1">−</button>
                                        <input type="text" name="discount_rate" data-step="0.5" data-min="0" data-max="100" inputmode="decimal" autocomplete="off"
                                               value="{{ rtrim(rtrim($v->general_rate, '0'), '.') }}"
                                               class="form-control text-end rate-input">
                                        <button type="button" class="btn btn-outline-secondary rate-up" tabindex="-1">+</button>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                @if($isBoth)
                                    {{-- 도·소매 학원만 — 학원 일괄 배송(도매) 주문에 쓰인다. 비우면 소매율을 그대로 쓴다 --}}
                                    <div class="col-8 col-sm-2">
                                        <div class="small fw-bold navy mb-1">도매</div>
                                        <div class="input-group input-group-sm rate-stepper">
                                            <input type="text" name="wholesale_discount_rate" data-step="0.5" data-min="0" data-max="100"
                                                   inputmode="decimal" autocomplete="off" data-allow-empty="1"
                                                   value="{{ $v->wholesale_rate !== null ? rtrim(rtrim($v->wholesale_rate, '0'), '.') : '' }}"
                                                   placeholder="소매율" class="form-control text-end rate-input">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                @endif
                                <div class="col-4 col-sm-2 d-grid">
                                    <button class="btn btn-sm btn-outline-navy">저장</button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- RIGHT: 선택된 학원의 도서별 개별 할인율 --}}
    <div class="col-lg-7">
        @if($selectedVendor)
            <div class="card section-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-book"></i> {{ $selectedVendor->vendor_name }} — 도서별 개별 할인율</strong>
                    <small class="text-muted">일반: {{ rtrim(rtrim($selectedVendor->general_rate, '0'), '.') }}%</small>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>도서</th>
                                <th class="text-end">정가</th>
                                <th style="width:230px" class="text-end">할인율</th>
                                <th style="width:120px" class="text-end">할인가</th>
                                <th style="width:60px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bookDiscounts as $bd)
                                @php $disc = (int) round($bd->price * (100 - $bd->discount_rate) / 100); @endphp
                                <tr>
                                    <td class="small">
                                        <strong>{{ $bd->title }}</strong>
                                        <div class="text-muted small"><code>{{ $bd->isbn }}</code></div>
                                    </td>
                                    <td class="text-end small text-muted">{{ number_format($bd->price) }}원</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('my.discounts.book.upsert') }}" class="d-flex gap-1 justify-content-end">
                                            @csrf
                                            <input type="hidden" name="vendor_id" value="{{ $selectedVendorId }}">
                                            <input type="hidden" name="book_id" value="{{ $bd->book_id }}">
                                            <div class="input-group input-group-sm rate-stepper ms-auto">
                                                <button type="button" class="btn btn-outline-secondary rate-down" tabindex="-1">−</button>
                                                <input type="text" name="discount_rate" data-step="0.5" data-min="0" data-max="100" inputmode="decimal" autocomplete="off"
                                                       value="{{ rtrim(rtrim($bd->discount_rate, '0'), '.') }}"
                                                       class="form-control text-end rate-input">
                                                <button type="button" class="btn btn-outline-secondary rate-up" tabindex="-1">+</button>
                                                <button class="btn btn-outline-navy" title="저장"><i class="bi bi-save"></i></button>
                                            </div>
                                        </form>
                                    </td>
                                    <td class="text-end small fw-bold navy">{{ number_format($disc) }}원</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('my.discounts.book.destroy', $bd->avbd_id) }}"
                                              onsubmit="return confirm('개별 할인율을 제거하시겠어요? (일반 할인율로 돌아감)')" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3 small">개별 설정된 도서 없음 · 일반 할인율 적용</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- 새 도서 개별 할인율 추가 (검색형 combobox) --}}
                @if($availableBooks->isNotEmpty())
                    <div class="card-footer">
                        <form method="POST" action="{{ route('my.discounts.book.upsert') }}" class="row g-2 align-items-end" id="addBookDiscountForm">
                            @csrf
                            <input type="hidden" name="vendor_id" value="{{ $selectedVendorId }}">
                            <div class="col-md-7 position-relative">
                                <label class="form-label small text-muted mb-1">개별 할인율 추가할 도서</label>
                                <input type="text" id="bookSearchInput" class="form-control form-control-sm"
                                       placeholder="도서명 또는 ISBN 입력..." autocomplete="off">
                                <input type="hidden" name="book_id" id="bookIdInput" required>
                                <div id="bookSearchResults" class="position-absolute bg-white border rounded shadow-sm w-100 d-none"
                                     style="max-height:300px; overflow-y:auto; z-index:1000; top:100%; left:0;">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">할인율 %</label>
                                <div class="input-group input-group-sm rate-stepper">
                                    <button type="button" class="btn btn-outline-secondary rate-down" tabindex="-1">−</button>
                                    <input type="text" name="discount_rate" data-step="0.5" data-min="0" data-max="100" inputmode="decimal" autocomplete="off"
                                           value="{{ rtrim(rtrim($selectedVendor->general_rate, '0'), '.') }}"
                                           class="form-control text-end rate-input">
                                    <button type="button" class="btn btn-outline-secondary rate-up" tabindex="-1">+</button>
                                </div>
                            </div>
                            <div class="col-md-2 d-grid">
                                <button class="btn btn-sm btn-navy" type="submit"><i class="bi bi-plus-lg"></i></button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        @else
            <div class="card section-card">
                <div class="card-body text-center text-muted py-5">
                    좌측에서 학원을 선택해주세요.
                </div>
            </div>
        @endif
    </div>
</div>
@push('head')
<style>
/* 할인율 stepper — 좁은 폭에서도 한 줄 유지 (% 줄바꿈 방지) */
.rate-stepper { flex-wrap: nowrap; }
.rate-stepper input { min-width: 44px; }
.rate-stepper .btn, .rate-stepper .input-group-text { padding-left: .5rem; padding-right: .5rem; }
</style>
@endpush
@push('scripts')
<script>
// 할인율 +/- 버튼 (모바일 친화)
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.rate-stepper .rate-up, .rate-stepper .rate-down');
    if (!btn) return;
    const wrap = btn.closest('.rate-stepper');
    const input = wrap.querySelector('.rate-input');
    if (!input) return;
    let v = parseFloat(input.value) || 0;
    const step = parseFloat(input.dataset.step) || 0.5;
    const min = isNaN(parseFloat(input.dataset.min)) ? -Infinity : parseFloat(input.dataset.min);
    const max = isNaN(parseFloat(input.dataset.max)) ? Infinity : parseFloat(input.dataset.max);
    v = btn.classList.contains('rate-up') ? v + step : v - step;
    v = Math.max(min, Math.min(max, v));
    input.value = (Math.round(v * 10) / 10).toString().replace(/\.0$/, '');
});
</script>
@if($selectedVendor && $availableBooks->isNotEmpty())
@php
    $booksJsArray = $availableBooks->map(function ($b) {
        return ['id' => $b->id, 'title' => $b->title, 'isbn' => $b->isbn, 'price' => $b->price];
    })->values()->all();
@endphp
<script>
(function () {
    const books = {!! json_encode($booksJsArray, JSON_UNESCAPED_UNICODE) !!};
    const input    = document.getElementById('bookSearchInput');
    const hidden   = document.getElementById('bookIdInput');
    const results  = document.getElementById('bookSearchResults');
    if (!input || !results) return;

    function render(filtered) {
        if (filtered.length === 0) {
            results.innerHTML = '<div class="px-3 py-2 small text-muted">검색 결과 없음</div>';
        } else {
            results.innerHTML = filtered.slice(0, 50).map(b =>
                `<a href="#" class="d-block px-3 py-2 small text-decoration-none text-dark border-bottom book-pick" data-id="${b.id}" data-label="${b.title}">
                    <strong>${escapeHtml(b.title)}</strong>
                    <div class="text-muted small">
                        <code>${b.isbn}</code> · ${Number(b.price).toLocaleString()}원
                    </div>
                </a>`
            ).join('');
        }
        results.classList.remove('d-none');
    }
    function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    function filter(query) {
        const q = query.trim().toLowerCase();
        if (q === '') return books;
        return books.filter(b =>
            b.title.toLowerCase().includes(q) ||
            String(b.isbn).toLowerCase().includes(q)
        );
    }

    input.addEventListener('focus', () => render(filter(input.value)));
    input.addEventListener('input', () => {
        hidden.value = ''; // 검색 시 선택값 초기화
        render(filter(input.value));
    });

    results.addEventListener('click', (e) => {
        const link = e.target.closest('.book-pick');
        if (!link) return;
        e.preventDefault();
        hidden.value = link.dataset.id;
        input.value  = link.dataset.label;
        results.classList.add('d-none');
    });

    // 외부 클릭 시 닫기
    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !results.contains(e.target)) {
            results.classList.add('d-none');
        }
    });

    // submit 전 hidden 검증
    document.getElementById('addBookDiscountForm').addEventListener('submit', (e) => {
        if (!hidden.value) {
            e.preventDefault();
            alert('목록에서 도서를 선택해주세요.');
            input.focus();
        }
    });
})();

// 숫자·소수점만 허용 ("2." 중간 상태는 유지)
document.addEventListener('input', function (e) {
    const input = e.target.closest('.rate-input');
    if (!input) return;
    let v = input.value.replace(/[^0-9.]/g, '');
    const first = v.indexOf('.');
    if (first !== -1) v = v.slice(0, first + 1) + v.slice(first + 1).replace(/\./g, '');
    if (v !== input.value) input.value = v;
});
document.addEventListener('blur', function (e) {
    const input = e.target.closest('.rate-input');
    if (!input) return;
    const min = parseFloat(input.dataset.min), max = parseFloat(input.dataset.max);
    let v = parseFloat(input.value);
    // 비워둘 수 있는 칸(도매율)은 0 으로 바꾸지 않는다 — 0%% 할인으로 굳어버린다
    if (isNaN(v)) { input.value = input.dataset.allowEmpty === '1' ? '' : '0'; return; }
    if (!isNaN(min)) v = Math.max(min, v);
    if (!isNaN(max)) v = Math.min(max, v);
    input.value = (Math.round(v * 100) / 100).toString();
}, true);
</script>
@endif
@endpush
@endsection
