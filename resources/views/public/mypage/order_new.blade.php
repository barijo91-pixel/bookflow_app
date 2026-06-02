@extends('public.layouts.app')
@section('title', '도서 주문하기')
@section('max_width', '1200px')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-bag-plus"></i> 도서 주문하기</h1>
    <p class="text-muted small mb-0">
        @if($vendor)
            <strong>{{ $vendor->name }}</strong> · 영업자 선택 후 도서를 담아 주문하세요.
        @else
            <span class="text-danger">학원 매핑이 없습니다. 관리자에게 문의해주세요.</span>
        @endif
    </p>
</div>

@if(!$vendor)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> 학원이 연결되지 않은 계정입니다. 관리자에게 vendor_users 매핑을 요청해주세요.
    </div>
    @php return; @endphp
@endif

@if($agents->isEmpty())
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> 이 학원에 매핑된 영업자가 없습니다. 관리자에게 문의해주세요.
    </div>
    @php return; @endphp
@endif

@php
    // 현재 쿼리에서 특정 필터만 바꿔 URL 생성 (다른 필터는 유지)
    $buildUrl = function (array $override) use ($activeFilters, $selectedAgent) {
        $base = array_filter([
            'q'        => $activeFilters['q'] ?: null,
            'school'   => $activeFilters['school'] ?: null,
            'subject'  => $activeFilters['subject'] ?: null,
            'grade'    => $activeFilters['grade'] ?: null,
            'semester' => $activeFilters['semester'] ?: null,
            'agent_id' => $selectedAgent->id ?? null,
        ], fn ($v) => $v !== null && $v !== '');
        foreach ($override as $k => $v) {
            if ($v === null || $v === '') unset($base[$k]);
            else $base[$k] = $v;
        }
        return route('my.order_new', $base);
    };
    // 필터 활성 체크
    $isActive = fn ($key, $value) => ($activeFilters[$key] ?? null) === $value;
@endphp

{{-- 영업자 선택 + 검색 --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('my.order_new') }}" class="row g-2 align-items-end">
            {{-- 영업자 --}}
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">영업자 (담당)</label>
                <select name="agent_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($agents as $a)
                        <option value="{{ $a->id }}" @selected($selectedAgent && $a->id == $selectedAgent->id)>
                            {{ $a->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            {{-- 검색 --}}
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1">도서 검색</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" value="{{ $q }}" class="form-control"
                           placeholder="제목 · ISBN · 시리즈 · 저자로 검색">
                </div>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-navy flex-grow-1"><i class="bi bi-search"></i> 검색</button>
                <button type="button" class="btn btn-sm btn-warning" id="toggleBarcodeBtn"
                        title="바코드 스캔 열기/닫기">
                    <i class="bi bi-upc-scan"></i>
                </button>
            </div>
            {{-- 현재 필터 hidden (필터 유지) --}}
            @if($activeFilters['school'])   <input type="hidden" name="school"   value="{{ $activeFilters['school'] }}"> @endif
            @if($activeFilters['subject'])  <input type="hidden" name="subject"  value="{{ $activeFilters['subject'] }}"> @endif
            @if($activeFilters['grade'])    <input type="hidden" name="grade"    value="{{ $activeFilters['grade'] }}"> @endif
            @if($activeFilters['semester']) <input type="hidden" name="semester" value="{{ $activeFilters['semester'] }}"> @endif
        </form>
    </div>
</div>

{{-- 바코드 스캔 입력 — 검색 옆 [📷] 버튼으로 토글 (기본 접힘) --}}
<div class="card border-0 shadow-sm mb-3 border-start border-4 border-warning" id="barcodeCard" style="display:none;">
    <div class="card-body py-3">
        <div class="d-flex align-items-center gap-2 mb-2">
            <strong><i class="bi bi-upc-scan text-warning fs-5"></i> 바코드 스캔으로 빠른 주문</strong>
            <span class="badge bg-warning text-dark">NEW</span>
            <span class="small text-muted d-none d-md-inline">ISBN 바코드를 스캔하면 자동으로 장바구니에 담깁니다</span>
            <button type="button" class="btn btn-sm btn-link text-muted ms-auto p-0" id="closeBarcodeBtn" title="닫기">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="input-group">
            <button type="button" id="scanCameraBtn" class="btn btn-outline-warning" title="카메라로 스캔">
                <i class="bi bi-camera"></i><span class="d-none d-md-inline ms-1">카메라</span>
            </button>
            <input type="text" id="scanIsbnInput" class="form-control"
                   placeholder="ISBN 스캔 또는 입력 후 Enter (예: 9788937834790)"
                   autocomplete="off" inputmode="numeric">
            <input type="number" id="scanQtyInput" class="form-control text-end" min="1" max="99"
                   value="1" style="max-width:70px;" title="수량">
            <button type="button" id="scanAddBtn" class="btn btn-warning">
                <i class="bi bi-plus-circle"></i> <span class="d-none d-sm-inline">담기</span>
            </button>
        </div>

        {{-- 카메라 스캔 모달 (풀스크린) --}}
        <div id="scanCameraModal" class="scan-camera-modal" role="dialog" aria-modal="true">
            <div class="scan-camera-box">
                <div class="scan-camera-header">
                    <strong><i class="bi bi-camera"></i> 카메라로 ISBN 바코드 스캔</strong>
                    <button type="button" id="scanCameraClose" class="btn btn-sm btn-light">
                        <i class="bi bi-x-lg"></i> 닫기
                    </button>
                </div>
                <div style="position:relative; background:#000;">
                    <video id="scanCameraVideo" style="width:100%; display:block; pointer-events:none;" playsinline autoplay muted></video>
                    {{-- 가운데 가이드 라인 (바코드 정렬용) --}}
                    <div style="position:absolute; inset:0; pointer-events:none; display:flex; align-items:center; justify-content:center;">
                        <div style="width:85%; height:120px; border:2px solid rgba(255,193,7,0.9); border-radius:8px; box-shadow:0 0 0 9999px rgba(0,0,0,0.45);"></div>
                    </div>
                </div>
                <div class="scan-camera-footer">
                    <div class="d-grid gap-2 mb-2">
                        <button type="button" id="scanCaptureBtn" class="btn btn-warning btn-lg">
                            <i class="bi bi-camera-fill"></i> 현재 화면 사진으로 인식
                        </button>
                        <label for="scanFileInput" class="btn btn-primary btn-lg">
                            <i class="bi bi-camera"></i> 폰 카메라 앱으로 찍기 (가장 정확)
                        </label>
                        <input type="file" id="scanFileInput" accept="image/*" capture="environment" style="display:none">
                    </div>
                    <div class="small text-muted">
                        <i class="bi bi-info-circle"></i>
                        라이브가 안 잡히면 위 버튼 둘 중 하나 사용. <strong>책에서 약 15-20cm</strong>, 바코드 또렷이 보이게.
                    </div>
                    <div id="scanCameraStatus" class="small mt-2"></div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-2">
            <small class="text-muted d-md-none">ISBN 바코드 스캔 또는 직접 입력</small>
            <div id="scanFocusToggle" class="form-check form-switch ms-auto small">
                <input type="checkbox" id="scanAutoFocus" class="form-check-input" checked>
                <label for="scanAutoFocus" class="form-check-label small text-muted">자동 포커스</label>
            </div>
        </div>
        <div id="scanFeedback" class="small mt-2" style="display:none;"></div>
    </div>
</div>

{{-- 필터 카드 - Progressive Disclosure --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        {{-- 1단계: 분류 (항상 표시) --}}
        <div class="d-flex flex-wrap align-items-start mb-2 gap-2">
            <div class="text-muted small fw-bold" style="width:50px;padding-top:.35rem">분류</div>
            <div class="d-flex flex-wrap gap-2 flex-grow-1">
                {{-- 분류 변경 시 학년/학기 초기화 --}}
                <a href="{{ $buildUrl(['school' => null, 'grade' => null, 'semester' => null]) }}"
                   class="btn btn-sm rounded-pill {{ ! $activeFilters['school'] ? 'btn-navy' : 'btn-outline-secondary' }}">
                    전체
                </a>
                @foreach($filterOptions['school'] as $o)
                    <a href="{{ $buildUrl(['school' => $o->code, 'grade' => null, 'semester' => null]) }}"
                       class="btn btn-sm rounded-pill {{ $isActive('school', $o->code) ? 'btn-navy' : 'btn-outline-secondary' }}">
                        {{ $o->name }}
                    </a>
                @endforeach
            </div>
        </div>

        @if($showSubFilters)
            {{-- 2단계: 과목 --}}
            <div class="d-flex flex-wrap align-items-start mb-2 gap-2">
                <div class="text-muted small fw-bold" style="width:50px;padding-top:.35rem">과목</div>
                <div class="d-flex flex-wrap gap-2 flex-grow-1">
                    <a href="{{ $buildUrl(['subject' => null]) }}"
                       class="btn btn-sm rounded-pill {{ ! $activeFilters['subject'] ? 'btn-navy' : 'btn-outline-secondary' }}">
                        전체
                    </a>
                    @foreach($filterOptions['subject'] as $o)
                        <a href="{{ $buildUrl(['subject' => $o->code]) }}"
                           class="btn btn-sm rounded-pill {{ $isActive('subject', $o->code) ? 'btn-navy' : 'btn-outline-secondary' }}">
                            {{ $o->name }}
                        </a>
                    @endforeach
                </div>
            </div>

            @if($showGradeRow)
                {{-- 3단계: 학년 (분류에 종속) --}}
                <div class="d-flex flex-wrap align-items-start mb-2 gap-2">
                    <div class="text-muted small fw-bold" style="width:50px;padding-top:.35rem">학년</div>
                    <div class="d-flex flex-wrap gap-2 flex-grow-1">
                        <a href="{{ $buildUrl(['grade' => null]) }}"
                           class="btn btn-sm rounded-pill {{ ! $activeFilters['grade'] ? 'btn-navy' : 'btn-outline-secondary' }}">
                            전체
                        </a>
                        @foreach($filterOptions['grade'] as $o)
                            <a href="{{ $buildUrl(['grade' => $o->code]) }}"
                               class="btn btn-sm rounded-pill {{ $isActive('grade', $o->code) ? 'btn-navy' : 'btn-outline-secondary' }}">
                                {{ $o->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($showSemesterRow)
                {{-- 4단계: 학기 --}}
                <div class="d-flex flex-wrap align-items-start mb-2 gap-2">
                    <div class="text-muted small fw-bold" style="width:50px;padding-top:.35rem">학기</div>
                    <div class="d-flex flex-wrap gap-2 flex-grow-1">
                        <a href="{{ $buildUrl(['semester' => null]) }}"
                           class="btn btn-sm rounded-pill {{ ! $activeFilters['semester'] ? 'btn-navy' : 'btn-outline-secondary' }}">
                            전체
                        </a>
                        @foreach($filterOptions['semester'] as $o)
                            <a href="{{ $buildUrl(['semester' => $o->code]) }}"
                               class="btn btn-sm rounded-pill {{ $isActive('semester', $o->code) ? 'btn-navy' : 'btn-outline-secondary' }}">
                                {{ $o->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif

        {{-- 초기화 (필터 적용된 경우만) --}}
        @if($activeFilters['school'] || $activeFilters['subject'] || $activeFilters['grade'] || $activeFilters['semester'])
            <div class="text-end mt-2">
                <a href="{{ route('my.order_new', $selectedAgent ? ['agent_id' => $selectedAgent->id] : []) }}"
                   class="btn btn-sm btn-outline-secondary rounded-pill">
                    <i class="bi bi-arrow-counterclockwise"></i> 초기화
                </a>
            </div>
        @endif
    </div>
</div>

<div class="row g-3">
    {{-- 좌측: 도서 목록 --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-journals"></i> 도서 목록</strong>
                <span class="small text-muted">{{ $books->count() }}건 표시 (최대 60건)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>도서</th>
                            <th class="text-end">정가</th>
                            <th class="text-end">할인가</th>
                            <th style="width:90px">수량</th>
                            <th style="width:70px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($books as $b)
                            @php
                                $rate = $bookDiscounts->get($b->id, $selectedAgent->general_rate ?? 0);
                                $unit = (int) round($b->price * (100 - $rate) / 100);
                                $hasBookDiscount = $bookDiscounts->has($b->id);
                            @endphp
                            <tr>
                                <td class="small">
                                    <strong>{{ $b->title }}</strong>
                                    @if($b->subtitle)<span class="text-muted">— {{ $b->subtitle }}</span>@endif
                                    <div class="text-muted small">
                                        <code>{{ $b->isbn }}</code>
                                        @if($b->publisher_name) · {{ $b->publisher_name }} @endif
                                    </div>
                                </td>
                                <td class="text-end small text-muted">{{ number_format($b->price) }}원</td>
                                <td class="text-end small">
                                    <span class="fw-bold navy">{{ number_format($unit) }}원</span>
                                    <div class="text-muted small">
                                        {{ rtrim(rtrim($rate, '0'), '.') }}%
                                        @if($hasBookDiscount)<i class="bi bi-star-fill text-warning" title="개별 할인율"></i>@endif
                                    </div>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('my.cart.add') }}" class="d-flex gap-1">
                                        @csrf
                                        <input type="hidden" name="book_id" value="{{ $b->id }}">
                                        <input type="hidden" name="cart_key" value="{{ $cartKey }}">
                                        <input type="number" name="qty" value="1" min="1" max="9999" class="form-control form-control-sm text-end" style="width:70px">
                                </td>
                                <td>
                                        <button class="btn btn-sm btn-outline-navy w-100"><i class="bi bi-plus"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">
                                @if($q) "{{ $q }}" 검색 결과가 없습니다.
                                @else 등록된 도서가 없습니다. @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 우측: 장바구니 --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="position:sticky;top:1rem">
            <div class="card-header bg-white">
                <strong><i class="bi bi-cart"></i> 장바구니 ({{ $cartLines->count() }})</strong>
            </div>
            <div class="card-body p-0">
                @if($cartLines->isEmpty())
                    <div class="text-muted text-center py-4 small">담은 도서가 없습니다.</div>
                @else
                    <form method="POST" action="{{ route('my.cart.update') }}" id="cartForm">
                        @csrf
                        <input type="hidden" name="cart_key" value="{{ $cartKey }}">
                        <table class="table table-sm mb-0">
                            <tbody>
                                @foreach($cartLines as $line)
                                    @php $b = $line['book']; @endphp
                                    <tr>
                                        <td class="small p-2">
                                            <div class="fw-bold">{{ \Illuminate\Support\Str::limit($b->title, 30) }}</div>
                                            <div class="text-muted small">
                                                {{ number_format($line['unit_price']) }}원 × {{ $line['qty'] }} =
                                                <span class="fw-bold navy">{{ number_format($line['line_total']) }}원</span>
                                            </div>
                                            <div class="d-flex gap-1 mt-1 align-items-center">
                                                <input type="number" name="qty[{{ $b->id }}]" value="{{ $line['qty'] }}" min="0" max="9999"
                                                       class="form-control form-control-sm text-end" style="width:70px">
                                                <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-auto"
                                                        onclick="removeCartItem({{ $b->id }})" title="제거">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="p-3 border-top">
                            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                                <i class="bi bi-arrow-clockwise"></i> 수량 업데이트
                            </button>
                        </div>
                    </form>

                    <div class="p-3 border-top bg-light">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">소계 ({{ $cartLines->sum('qty') }}권)</span>
                            <strong class="navy">{{ number_format($subtotal) }}원</strong>
                        </div>
                        <form method="POST" action="{{ route('my.order.store') }}"
                              onsubmit="return confirm('이 내용으로 주문하시겠습니까?')">
                            @csrf
                            <input type="hidden" name="cart_key" value="{{ $cartKey }}">
                            <input type="hidden" name="agent_id" value="{{ $selectedAgent->id }}">
                            <button type="submit" class="btn btn-navy w-100 btn-lg">
                                <i class="bi bi-check-lg"></i> 주문하기
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- 카트 제거용 숨김 폼 --}}
<form method="POST" action="{{ route('my.cart.remove') }}" id="removeForm" class="d-none">
    @csrf
    <input type="hidden" name="cart_key" value="{{ $cartKey }}">
    <input type="hidden" name="book_id" id="removeBookId">
</form>

@push('scripts')
<script src="https://unpkg.com/@zxing/library@0.21.3/umd/index.min.js"></script>
<style>
.scan-camera-modal {
    position: fixed; inset: 0; background: rgba(0,0,0,0.85);
    z-index: 10500; display: none; align-items: center; justify-content: center; padding: 16px;
}
.scan-camera-modal.show { display: flex; }
.scan-camera-box { background: #fff; border-radius: 12px; max-width: 480px; width: 100%; overflow: hidden; }
.scan-camera-header {
    padding: .75rem 1rem; background: #1a1d2e; color: #fff;
    display: flex; justify-content: space-between; align-items: center;
}
.scan-camera-footer { padding: .75rem 1rem; background: #f8f9fa; }
#scanCameraReader video { width: 100% !important; }
</style>
<script>
function removeCartItem(bookId) {
    if (!confirm('장바구니에서 제거할까요?')) return;
    document.getElementById('removeBookId').value = bookId;
    document.getElementById('removeForm').submit();
}

// 바코드 스캔 → AJAX 카트 추가
(function () {
    const input   = document.getElementById('scanIsbnInput');
    const qtyEl   = document.getElementById('scanQtyInput');
    const btn     = document.getElementById('scanAddBtn');
    const focusEl = document.getElementById('scanAutoFocus');
    const feedback = document.getElementById('scanFeedback');
    if (!input) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
              || document.querySelector('input[name="_token"]')?.value;
    const cartKey = @json($cartKey);
    const scanUrl = @json(route('my.cart.scan'));

    let timer = null;
    function showFeedback(ok, msg, book) {
        feedback.style.display = 'block';
        feedback.className = 'small mt-2 alert py-2 ' + (ok ? 'alert-success' : 'alert-danger');
        feedback.innerHTML = ok && book
            ? `<i class="bi bi-check-circle"></i> <strong>${escapeHtml(book.title)}</strong> 추가됨 · ${msg}`
            : `<i class="bi bi-exclamation-circle"></i> ${escapeHtml(msg)}`;
        clearTimeout(timer);
        timer = setTimeout(() => { feedback.style.display = 'none'; }, 4000);
    }
    function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    async function scanAdd() {
        const isbn = input.value.trim();
        const qty  = Math.max(1, Math.min(99, parseInt(qtyEl.value, 10) || 1));
        if (!isbn) return;

        btn.disabled = true;
        try {
            const res = await fetch(scanUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: new URLSearchParams({ isbn, qty: String(qty), cart_key: cartKey }),
            });
            const data = await res.json();
            if (data.ok) {
                showFeedback(true, data.msg, data.book);
                input.value = '';
                qtyEl.value = 1;
                // 카트 갱신 위해 페이지 reload (간단한 v1) — 사용성 부드럽게 하려면 partial update 가능
                setTimeout(() => location.reload(), 800);
            } else {
                showFeedback(false, data.msg || '도서를 추가할 수 없습니다.');
                input.select();
            }
        } catch (e) {
            showFeedback(false, '서버 통신 오류: ' + e.message);
        } finally {
            btn.disabled = false;
        }
    }

    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); scanAdd(); }
    });
    btn.addEventListener('click', scanAdd);

    // 자동 포커스 (다른 input 클릭하면 잠시 해제)
    function maybeFocus() {
        if (!focusEl.checked) return;
        const ae = document.activeElement;
        if (!ae || ae.tagName === 'BODY') input.focus();
    }
    setInterval(maybeFocus, 1500);
    input.focus();

    // ===== 카메라 스캔 (ZXing-js — ISBN/EAN-13 인식 정확도 최고) =====
    const camBtn    = document.getElementById('scanCameraBtn');
    const camModal  = document.getElementById('scanCameraModal');
    const camClose  = document.getElementById('scanCameraClose');
    const camStatus = document.getElementById('scanCameraStatus');
    const camVideo  = document.getElementById('scanCameraVideo');
    let codeReader  = null;
    let lastScanned = '';
    let lastScanTime = 0;

    async function startCamera() {
        camModal.classList.add('show');
        if (!window.ZXing) {
            camStatus.innerHTML = '<span class="text-danger">⚠️ 스캐너 라이브러리 로드 실패. '
                + '네트워크 확인 후 새로고침해주세요.</span>';
            return;
        }
        camStatus.textContent = '카메라 시작 중...';

        try {
            const hints = new Map();
            hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [
                ZXing.BarcodeFormat.EAN_13,
                ZXing.BarcodeFormat.EAN_8,
                ZXing.BarcodeFormat.UPC_A,
                ZXing.BarcodeFormat.UPC_E,
                ZXing.BarcodeFormat.CODE_128,
                ZXing.BarcodeFormat.CODE_39,
            ]);
            hints.set(ZXing.DecodeHintType.TRY_HARDER, true);

            codeReader = new ZXing.BrowserMultiFormatReader(hints, 200);

            let deviceId = null;
            try {
                const devices = await codeReader.listVideoInputDevices();
                if (devices && devices.length > 0) {
                    const rear = devices.find(d =>
                        /back|rear|environment|후면/i.test(d.label || '')
                    );
                    deviceId = (rear || devices[devices.length - 1]).deviceId;
                }
            } catch (e) {
                // 권한 없으면 listVideoInputDevices 실패 — null로 호출하면 기본 카메라 사용
            }

            await codeReader.decodeFromVideoDevice(deviceId, camVideo, (result, err) => {
                if (result) {
                    onCameraScan(result.getText());
                }
            });

            camStatus.innerHTML = '<strong>바코드를 노란 박스 안에 맞춰주세요</strong> · 인식이 안 되면 아래 버튼 사용';
        } catch (e) {
            camStatus.innerHTML = '<span class="text-danger">카메라 접근 실패: '
                + (e.message || e)
                + '<br>주소창 자물쇠 → 카메라 허용 후 다시 시도. 또는 아래 [폰 카메라 앱으로 찍기] 사용.</span>';
        }
    }

    function onCameraScan(decodedText) {
        if (!decodedText) return;
        if (decodedText === lastScanned && Date.now() - lastScanTime < 3000) return;
        lastScanned = decodedText;
        lastScanTime = Date.now();
        camStatus.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> 인식: ' + decodedText + ' — 담는 중...</span>';
        input.value = decodedText;
        scanAdd().then(() => {
            setTimeout(() => {
                camStatus.textContent = '다음 바코드를 화면에 맞춰주세요...';
            }, 1200);
        });
    }

    function stopCamera() {
        camModal.classList.remove('show');
        if (codeReader) {
            try { codeReader.reset(); } catch (e) {}
            codeReader = null;
        }
    }

    if (camBtn)   camBtn.addEventListener('click', startCamera);
    if (camClose) camClose.addEventListener('click', stopCamera);
    if (camModal) camModal.addEventListener('click', (e) => {
        if (e.target === camModal) stopCamera();
    });

    // 📷 폰 네이티브 카메라 앱 호출 → 사진 받으면 ZXing으로 분석
    // (라이브 인식이 안 되는 폰에서 가장 안정적 — 자동초점·HDR 모두 OS 카메라가 처리)
    const fileInput = document.getElementById('scanFileInput');
    if (fileInput) {
        fileInput.addEventListener('change', async (e) => {
            const file = e.target.files && e.target.files[0];
            if (!file) return;
            camStatus.innerHTML = '<span class="text-info">사진 분석 중...</span>';

            try {
                const dataUrl = await new Promise((res, rej) => {
                    const r = new FileReader();
                    r.onload = () => res(r.result);
                    r.onerror = rej;
                    r.readAsDataURL(file);
                });
                const hints = new Map();
                hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [
                    ZXing.BarcodeFormat.EAN_13,
                    ZXing.BarcodeFormat.EAN_8,
                    ZXing.BarcodeFormat.UPC_A,
                    ZXing.BarcodeFormat.UPC_E,
                    ZXing.BarcodeFormat.CODE_128,
                ]);
                hints.set(ZXing.DecodeHintType.TRY_HARDER, true);
                const imgReader = new ZXing.BrowserMultiFormatReader(hints);
                const result = await imgReader.decodeFromImageUrl(dataUrl);
                onCameraScan(result.getText());
            } catch (err) {
                camStatus.innerHTML = '<span class="text-danger">'
                    + '<i class="bi bi-exclamation-triangle"></i> 바코드 인식 실패. '
                    + '다시 찍거나 ISBN 13자리를 직접 입력하세요.</span>';
            } finally {
                fileInput.value = ''; // 같은 파일 재선택 가능하도록 reset
            }
        });
    }

    // 📸 사진 캡처 → 정지 이미지에서 디코드 (라이브 인식 안 될 때 폴백)
    const captureBtn = document.getElementById('scanCaptureBtn');
    if (captureBtn) {
        captureBtn.addEventListener('click', async () => {
            if (!camVideo.videoWidth) {
                camStatus.innerHTML = '<span class="text-warning">카메라가 아직 준비 중입니다...</span>';
                return;
            }
            captureBtn.disabled = true;
            camStatus.innerHTML = '<span class="text-info">사진 분석 중...</span>';
            try {
                // 현재 프레임을 캔버스에 캡처
                const canvas = document.createElement('canvas');
                canvas.width = camVideo.videoWidth;
                canvas.height = camVideo.videoHeight;
                canvas.getContext('2d').drawImage(camVideo, 0, 0);
                const dataUrl = canvas.toDataURL('image/png');

                // 별도 reader로 이미지 디코드 (live reader와 충돌 방지)
                const hints = new Map();
                hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [
                    ZXing.BarcodeFormat.EAN_13,
                    ZXing.BarcodeFormat.EAN_8,
                    ZXing.BarcodeFormat.UPC_A,
                    ZXing.BarcodeFormat.CODE_128,
                ]);
                hints.set(ZXing.DecodeHintType.TRY_HARDER, true);
                const imgReader = new ZXing.BrowserMultiFormatReader(hints);

                const result = await imgReader.decodeFromImageUrl(dataUrl);
                onCameraScan(result.getText());
            } catch (e) {
                camStatus.innerHTML = '<span class="text-danger">'
                    + '<i class="bi bi-exclamation-triangle"></i> 바코드 인식 실패 — '
                    + '책에서 약 15-20cm 거리에서, 바코드가 또렷이 보이게 다시 찍어주세요.'
                    + '<br>계속 안 되면 ISBN 13자리를 입력칸에 직접 입력하세요.</span>';
            } finally {
                captureBtn.disabled = false;
            }
        });
    }

    // 검색 옆 바코드 토글 버튼 — 카드 열기/닫기
    const toggleBtn = document.getElementById('toggleBarcodeBtn');
    const closeBarcodeBtn = document.getElementById('closeBarcodeBtn');
    const barcodeCard = document.getElementById('barcodeCard');
    function showBarcode() {
        if (!barcodeCard) return;
        barcodeCard.style.display = '';
        // 부드러운 스크롤 + 자동 포커스
        barcodeCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setTimeout(() => input?.focus(), 300);
    }
    function hideBarcode() {
        if (!barcodeCard) return;
        barcodeCard.style.display = 'none';
    }
    toggleBtn?.addEventListener('click', () => {
        if (barcodeCard.style.display === 'none') showBarcode(); else hideBarcode();
    });
    closeBarcodeBtn?.addEventListener('click', hideBarcode);
})();
</script>
@endpush
@endsection
