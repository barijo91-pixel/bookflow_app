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
            'publisher' => $activeFilters['publisher'] ?: null,
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
<div class="card section-card mb-3 filter-card">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('my.order_new') }}" class="row g-2 align-items-end">
            {{-- 영업자 --}}
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">영업자 (담당)</label>
                <select name="agent_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($agents as $a)
                        <option value="{{ $a->id }}" @selected($selectedAgent && $a->id == $selectedAgent->id)>
                            {{ $a->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            {{-- 출판사 --}}
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">출판사</label>
                <select name="publisher" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체 출판사</option>
                    @foreach($filterOptions['publisher'] as $o)
                        <option value="{{ $o->code }}" @selected($activeFilters['publisher'] == $o->code)>{{ $o->name }}</option>
                    @endforeach
                </select>
            </div>
            {{-- 검색 --}}
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">도서 검색</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" value="{{ $q }}" class="form-control"
                           placeholder="제목 · ISBN · 시리즈 · 저자로 검색">
                </div>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-navy flex-grow-1"><i class="bi bi-search"></i> 검색</button>
            </div>
            {{-- 현재 필터 hidden (필터 유지) --}}
            @if($activeFilters['school'])   <input type="hidden" name="school"   value="{{ $activeFilters['school'] }}"> @endif
            @if($activeFilters['subject'])  <input type="hidden" name="subject"  value="{{ $activeFilters['subject'] }}"> @endif
            @if($activeFilters['grade'])    <input type="hidden" name="grade"    value="{{ $activeFilters['grade'] }}"> @endif
            @if($activeFilters['semester']) <input type="hidden" name="semester" value="{{ $activeFilters['semester'] }}"> @endif
        </form>
    </div>
</div>

{{-- ★ AI 책 사진 인식 (메인) — 표지를 찍으면 자동으로 찾아 담기 --}}
<div class="card section-card mb-3" style="border-left:4px solid #0d6efd !important;">
    <div class="card-body py-3">
        <div class="d-flex align-items-center gap-2 mb-2">
            <strong><i class="bi bi-camera-fill text-primary fs-5"></i> 책 사진으로 찾기</strong>
            <span class="badge bg-primary">AI</span>
            <span class="small text-muted">표지를 찍으면 책을 자동으로 찾아 담아요 · <strong class="text-primary">실제 적용은 7월부터예요</strong></span>
        </div>
        <label for="visionFileInput" class="btn btn-primary w-100">
            <i class="bi bi-camera-fill"></i> 책 표지 사진 찍기 / 선택
        </label>
        <input type="file" id="visionFileInput" accept="image/*" capture="environment" style="display:none">
        <div id="visionResult" class="small mt-2"></div>
        <div class="mt-2 text-end">
            <button type="button" class="btn btn-sm btn-link text-muted p-0" id="toggleBarcodeBtn">
                <i class="bi bi-upc-scan"></i> 바코드로 스캔 (보조)
            </button>
        </div>
    </div>
</div>

{{-- 바코드 스캔 (보조 — 기본 숨김, 위 "바코드로 스캔" 링크로 토글) --}}
<div class="card section-card mb-3" id="barcodeCard" style="display:none; border-left:4px solid #ffc107 !important;">
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

{{-- 적용된 필터 요약 (모바일 접이식 헤더용) --}}
@php
    $sumParts = [];
    if ($activeFilters['school'])   $sumParts[] = optional(collect($filterOptions['school'])->firstWhere('code', $activeFilters['school']))->name;
    if ($activeFilters['subject'])  $sumParts[] = optional(collect($filterOptions['subject'])->firstWhere('code', $activeFilters['subject']))->name;
    if ($activeFilters['grade'])    $sumParts[] = optional(collect($filterOptions['grade'])->firstWhere('code', $activeFilters['grade']))->name;
    if ($activeFilters['semester']) $sumParts[] = optional(collect($filterOptions['semester'])->firstWhere('code', $activeFilters['semester']))->name;
    $filterSummary = implode(' · ', array_filter($sumParts));
    $hasActiveFilter = !empty($filterSummary);
@endphp

{{-- 필터 카드 - Progressive Disclosure --}}
<div class="card section-card mb-3">
    {{-- 모바일 전용 접이식 헤더 --}}
    <div class="card-header filter-toggle d-md-none d-flex justify-content-between align-items-center" onclick="toggleFilterBody()">
        <strong>
            <i class="bi bi-funnel"></i> 검색옵션
            @if($hasActiveFilter)
                <span class="badge bg-navy ms-1">{{ $filterSummary }}</span>
            @else
                <span class="text-muted fw-normal">전체</span>
            @endif
        </strong>
        <i class="bi bi-chevron-down" id="filterChevron"></i>
    </div>
    <div class="card-body py-3" id="filterBody">
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
        <div class="card section-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-journals"></i> 도서 목록</strong>
                <span class="small text-muted">{{ $books->count() }}건 표시 (최대 60건)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 table-row-highlight">
                    <thead class="table-light">
                        <tr>
                            <th>도서</th>
                            <th class="text-end">정가</th>
                            <th class="text-end">할인가</th>
                            <th class="text-center" style="width:58px">수량</th>
                            <th class="text-center" style="width:62px">담기</th>
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
                                        <input type="number" name="qty" value="1" min="1" max="9999" class="form-control form-control-sm text-end px-1" style="width:48px">
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
    <div class="col-lg-4" id="cartSection">
        <div class="card section-card" style="position:sticky;top:1rem">
            <div class="card-header d-flex align-items-center">
                <strong><i class="bi bi-cart"></i> 장바구니 ({{ $cartLines->count() }})</strong>
                {{-- 모바일: 도서 목록으로 올라가기 --}}
                <button type="button" class="btn btn-sm btn-link p-0 ms-auto d-lg-none text-decoration-none"
                        onclick="window.scrollTo({top:0,behavior:'smooth'})">
                    <i class="bi bi-arrow-up-circle"></i> 도서 담으러
                </button>
            </div>
            <div class="card-body p-0">
                @if($cartLines->isEmpty())
                    <div class="empty-state small">
                        <i class="bi bi-cart-x"></i>
                        담은 도서가 없습니다.
                    </div>
                @else
                    {{-- 컬럼 헤더 — 도서 목록 테이블과 같은 톤 (thead 일치) --}}
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 bg-light border-bottom fw-semibold">
                        <span>도서</span>
                        <div class="d-flex align-items-center flex-shrink-0">
                            <span style="width:60px; text-align:center;">수량</span>
                            <span style="width:32px;"></span>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('my.cart.update') }}" id="cartForm">
                        @csrf
                        <input type="hidden" name="cart_key" value="{{ $cartKey }}">
                        <table class="table table-sm mb-0">
                            <tbody>
                                @foreach($cartLines as $line)
                                    @php $b = $line['book']; @endphp
                                    <tr>
                                        <td class="small p-2">
                                            <div class="d-flex align-items-center gap-2">
                                                {{-- 좌측: 책 제목 + 가격 --}}
                                                <div class="flex-grow-1 min-w-0">
                                                    <div class="fw-bold text-truncate">{{ \Illuminate\Support\Str::limit($b->title, 28) }}</div>
                                                    <div class="text-muted small">
                                                        {{ number_format($line['unit_price']) }}원 × {{ $line['qty'] }} =
                                                        <span class="fw-bold navy">{{ number_format($line['line_total']) }}원</span>
                                                    </div>
                                                </div>
                                                {{-- 우측: 수량 입력 + 삭제 --}}
                                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                                    <input type="number" name="qty[{{ $b->id }}]" value="{{ $line['qty'] }}" min="0" max="9999"
                                                           class="form-control form-control-sm text-end" style="width:60px">
                                                    <button type="button" class="btn btn-sm btn-link text-danger p-1"
                                                            onclick="removeCartItem({{ $b->id }})" title="제거">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
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
                            @if($classes->isNotEmpty())
                                <select name="class_id" class="form-select form-select-sm mb-2" aria-label="학급 선택">
                                    <option value="">학급 선택 안 함</option>
                                    @foreach($classes as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            @endif
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

{{-- 모바일 플로팅 장바구니 버튼 (담은 게 있을 때만) --}}
@if($cartLines->isNotEmpty())
<button type="button" class="cart-fab" onclick="document.getElementById('cartSection').scrollIntoView({behavior:'smooth'})">
    <i class="bi bi-cart-fill"></i>
    <span class="cart-fab-count">{{ $cartLines->sum('qty') }}</span>
    <span class="cart-fab-amount">{{ number_format($subtotal) }}원</span>
    <i class="bi bi-chevron-down small"></i>
</button>
@endif

@push('scripts')
<script src="https://unpkg.com/@zxing/library@0.21.3/umd/index.min.js"></script>
<style>
/* 모바일 필터 접이식 — 기본 접힘, 헤더 탭하면 펼침 (데스크탑은 항상 표시) */
@media (max-width: 767.98px) {
    #filterBody { display: none; }
    #filterBody.show { display: block; }
    .filter-toggle { cursor: pointer; }
    .filter-toggle strong { font-size: 1rem; }

    /* 행 라벨(분류/과목/학년/학기) 폰트 키움 */
    #filterBody .text-muted.small.fw-bold { font-size: .9rem !important; }

    /* 선택지 칩 — 테두리 제거, 연한 배경 / 선택 시 네이비 */
    #filterBody .btn { font-size: .92rem; padding: .4rem 1rem; border: 0; }
    #filterBody .btn-outline-secondary {
        background: #eef1f5; color: #44515f;
    }
    #filterBody .btn-outline-secondary:hover,
    #filterBody .btn-outline-secondary:focus {
        background: #e2e6ec; color: #1f2d3d;
    }
    #filterBody .btn-navy { background: var(--navy); color: #fff; }
}

/* 모바일 플로팅 장바구니 버튼 — 데스크탑(lg+)은 우측 sticky 장바구니라 숨김 */
.cart-fab { display: none; }
@media (max-width: 991px) {
    .cart-fab {
        display: inline-flex; align-items: center; gap: 8px;
        position: fixed; right: 14px; bottom: 20px; z-index: 140;
        background: var(--navy); color: #fff; border: 0;
        padding: 11px 18px; border-radius: 999px; font-weight: 700;
        box-shadow: 0 4px 16px rgba(31,58,95,.45);
    }
    .cart-fab i.bi-cart-fill { font-size: 1.15rem; }
    .cart-fab-count {
        background: #fff; color: var(--navy); border-radius: 999px;
        padding: 0 8px; font-size: .8rem; min-width: 22px; text-align: center;
    }
    .cart-fab-amount { font-size: .9rem; }
}
/* 모바일 하단 탭바 있을 때(≤768px) 탭바 위로 띄움 */
@media (max-width: 768px) {
    .cart-fab { bottom: 72px; }
}
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

// 모바일 필터 접기/펼치기
function toggleFilterBody() {
    var b = document.getElementById('filterBody');
    var c = document.getElementById('filterChevron');
    if (!b) return;
    b.classList.toggle('show');
    if (c) { c.classList.toggle('bi-chevron-down'); c.classList.toggle('bi-chevron-up'); }
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

    // 📷 폰 네이티브 카메라 앱 → 사진 → 리사이즈 + 4방향 회전 + ZXing 디코드
    // 고해상도(12MP+) 사진은 ZXing이 처리 어려움 → 1600px max로 리사이즈 + EXIF 무시
    const fileInput = document.getElementById('scanFileInput');

    function makeHints() {
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
        return hints;
    }

    function drawRotated(srcCanvas, degrees) {
        const c = document.createElement('canvas');
        const ctx = c.getContext('2d');
        if (degrees === 90 || degrees === 270) {
            c.width = srcCanvas.height; c.height = srcCanvas.width;
        } else {
            c.width = srcCanvas.width;  c.height = srcCanvas.height;
        }
        ctx.translate(c.width / 2, c.height / 2);
        ctx.rotate(degrees * Math.PI / 180);
        ctx.drawImage(srcCanvas, -srcCanvas.width / 2, -srcCanvas.height / 2);
        return c;
    }

    async function tryDecodeCanvas(canvas) {
        const reader = new ZXing.BrowserMultiFormatReader(makeHints());
        // canvas → temp img → decodeFromImageElement
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = async () => {
                try {
                    const result = await reader.decodeFromImageElement(img);
                    resolve(result.getText());
                } catch (e) { reject(e); }
            };
            img.onerror = reject;
            img.src = canvas.toDataURL('image/png');
        });
    }

    // 흑백 + 대비 강화 (바코드 인식률 ↑)
    function enhanceContrast(srcCanvas) {
        const c = document.createElement('canvas');
        c.width = srcCanvas.width; c.height = srcCanvas.height;
        const ctx = c.getContext('2d');
        ctx.drawImage(srcCanvas, 0, 0);
        const data = ctx.getImageData(0, 0, c.width, c.height);
        const px = data.data;
        for (let i = 0; i < px.length; i += 4) {
            // 그레이스케일 (BT.601)
            const g = px[i]*0.299 + px[i+1]*0.587 + px[i+2]*0.114;
            // 임계값 130으로 이진화 (흑/백 강제)
            const v = g < 130 ? 0 : 255;
            px[i] = v; px[i+1] = v; px[i+2] = v;
        }
        ctx.putImageData(data, 0, 0);
        return c;
    }

    // 네이티브 BarcodeDetector API (Chrome Android 84+ — OS급 정확도)
    async function nativeDetect(canvas) {
        if (! ('BarcodeDetector' in window)) return null;
        try {
            const detector = new BarcodeDetector({
                formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39']
            });
            const bitmap = await createImageBitmap(canvas);
            const results = await detector.detect(bitmap);
            if (results && results.length > 0) {
                // ISBN(978/979 시작) 우선
                const isbn = results.find(r => /^97[89]/.test(r.rawValue));
                return (isbn || results[0]).rawValue;
            }
        } catch (e) { /* fallback */ }
        return null;
    }

    async function decodeImageFile(file) {
        const url = URL.createObjectURL(file);
        const img = await new Promise((res, rej) => {
            const i = new Image();
            i.onload = () => res(i);
            i.onerror = rej;
            i.src = url;
        });

        const MAX = 1600;
        const ratio = Math.min(1, MAX / Math.max(img.naturalWidth, img.naturalHeight));
        const w = Math.round(img.naturalWidth * ratio);
        const h = Math.round(img.naturalHeight * ratio);
        const baseCanvas = document.createElement('canvas');
        baseCanvas.width = w; baseCanvas.height = h;
        baseCanvas.getContext('2d').drawImage(img, 0, 0, w, h);
        URL.revokeObjectURL(url);

        // === Stage 1: 네이티브 BarcodeDetector (가장 정확) ===
        const rotations = [0, 90, 180, 270];
        if ('BarcodeDetector' in window) {
            for (const rot of rotations) {
                if (typeof showGlobalToast === 'function') {
                    showGlobalToast(`🧭 OS 디텍터 시도 (회전 ${rot}°)...`, 'info');
                }
                const canvas = rot === 0 ? baseCanvas : drawRotated(baseCanvas, rot);
                const text = await nativeDetect(canvas);
                if (text) return text;
            }
        }

        // === Stage 2: ZXing 원본 ===
        for (const rot of rotations) {
            try {
                const canvas = rot === 0 ? baseCanvas : drawRotated(baseCanvas, rot);
                if (typeof showGlobalToast === 'function') {
                    showGlobalToast(`🔄 ZXing 시도 (회전 ${rot}°)...`, 'info');
                }
                const text = await tryDecodeCanvas(canvas);
                if (text) return text;
            } catch (e) {}
        }

        // === Stage 3: 흑백 + 대비 강화 후 재시도 ===
        if (typeof showGlobalToast === 'function') {
            showGlobalToast('🎨 이미지 보정 후 재시도...', 'info');
        }
        const enhanced = enhanceContrast(baseCanvas);
        if ('BarcodeDetector' in window) {
            for (const rot of rotations) {
                const c = rot === 0 ? enhanced : drawRotated(enhanced, rot);
                const text = await nativeDetect(c);
                if (text) return text;
            }
        }
        for (const rot of rotations) {
            try {
                const c = rot === 0 ? enhanced : drawRotated(enhanced, rot);
                const text = await tryDecodeCanvas(c);
                if (text) return text;
            } catch (e) {}
        }

        throw new Error('모든 방법으로 인식 실패 (사진 품질 또는 바코드 손상)');
    }

    // 화면 상단 고정 토스트 (모달이든 어디든 항상 보임)
    function showGlobalToast(msg, type = 'info') {
        let el = document.getElementById('scanGlobalToast');
        if (! el) {
            el = document.createElement('div');
            el.id = 'scanGlobalToast';
            el.style.cssText = 'position:fixed; top:0; left:0; right:0; padding:14px 16px; '
                + 'z-index:99999; color:#fff; font-size:14px; font-weight:600; text-align:center; '
                + 'box-shadow:0 4px 16px rgba(0,0,0,0.3); transition:opacity .3s;';
            document.body.appendChild(el);
        }
        const colors = { info: '#1f3a5f', warn: '#ffc107', error: '#dc3545', success: '#198754' };
        el.style.background = colors[type] || colors.info;
        el.style.color = type === 'warn' ? '#000' : '#fff';
        el.innerHTML = msg;
        el.style.display = 'block';
        el.style.opacity = '1';
        if (type === 'success' || type === 'error') {
            setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.style.display = 'none', 300); }, 4000);
        }
    }

    if (fileInput) {
        fileInput.addEventListener('change', async (e) => {
            const file = e.target.files && e.target.files[0];
            if (! file) {
                showGlobalToast('⚠️ 파일을 받지 못했습니다 (취소되었거나 권한 문제)', 'warn');
                return;
            }
            const sizeKB = Math.round(file.size / 1024);
            showGlobalToast(`📂 파일 수신: ${file.name || '사진'} (${sizeKB}KB) — 분석 시작...`, 'info');
            camStatus.innerHTML = `<span class="text-info">분석 중... (${sizeKB}KB)</span>`;
            try {
                const text = await decodeImageFile(file);
                showGlobalToast(`✅ 인식 성공: ${text} — 장바구니 담는 중...`, 'success');
                onCameraScan(text);
            } catch (err) {
                const errMsg = err.message || String(err);
                showGlobalToast(`❌ 바코드 인식 실패: ${errMsg}`, 'error');
                camStatus.innerHTML = '<span class="text-danger">'
                    + '<i class="bi bi-exclamation-triangle"></i> 바코드 인식 실패: ' + errMsg + '<br>'
                    + '• 바코드만 화면 가득 채워서 다시 찍어보세요<br>'
                    + '• 빛 반사 피하기 / 정면으로<br>'
                    + '• 또는 ISBN 13자리를 입력칸에 직접 입력하세요.</span>';
            } finally {
                fileInput.value = '';
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

// 📚 AI 표지 인식 → cart.recognize (바코드 없는 책 보완)
(function () {
    const fileInput = document.getElementById('visionFileInput');
    const resultEl  = document.getElementById('visionResult');
    if (!fileInput) return;

    const recognizeUrl = @json(route('my.cart.recognize'));
    const scanUrl      = @json(route('my.cart.scan'));
    const cartKey      = @json($cartKey);
    const csrf = '{{ csrf_token() }}';

    function resizeImage(file, maxPx) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            const img = new Image();
            reader.onload = e => { img.src = e.target.result; };
            reader.onerror = reject;
            img.onload = () => {
                let w = img.width, h = img.height;
                if (w > maxPx || h > maxPx) { const r = Math.min(maxPx / w, maxPx / h); w = Math.round(w * r); h = Math.round(h * r); }
                const canvas = document.createElement('canvas');
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                resolve(canvas.toDataURL('image/jpeg', 0.85));
            };
            img.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    async function addByIsbn(isbn) {
        const r = await fetch(scanUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ isbn: isbn, cart_key: cartKey, qty: 1 }),
        });
        return r.json();
    }

    fileInput.addEventListener('change', async function () {
        const file = this.files[0];
        if (!file) return;
        resultEl.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split"></i> 표지 인식 중... (몇 초 걸려요)</span>';
        try {
            const dataUrl = await resizeImage(file, 1600);
            const res = await fetch(recognizeUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ image: dataUrl, cart_key: cartKey, qty: 1 }),
            });
            const j = await res.json();
            if (j.ok) {
                resultEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> ' + j.msg + '</span>';
                setTimeout(() => location.reload(), 900);
            } else if (j.candidates && j.candidates.length) {
                let html = '<div class="text-warning mb-1">' + j.msg + '</div>';
                j.candidates.forEach(b => {
                    html += '<button type="button" class="btn btn-sm btn-outline-secondary d-block w-100 text-start mb-1 vision-pick" data-isbn="' + (b.isbn || '') + '">'
                          + b.title + ' <span class="text-muted">' + Number(b.price).toLocaleString() + '원</span></button>';
                });
                resultEl.innerHTML = html;
                resultEl.querySelectorAll('.vision-pick').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        const isbn = btn.dataset.isbn;
                        if (!isbn) { resultEl.innerHTML = '<span class="text-danger">이 후보는 ISBN이 없어 직접 검색해 주세요.</span>'; return; }
                        const jj = await addByIsbn(isbn);
                        if (jj.ok) { resultEl.innerHTML = '<span class="text-success">' + jj.msg + '</span>'; setTimeout(() => location.reload(), 900); }
                        else { resultEl.innerHTML = '<span class="text-danger">' + jj.msg + '</span>'; }
                    });
                });
            } else {
                resultEl.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ' + j.msg + '</span>';
            }
        } catch (e) {
            resultEl.innerHTML = '<span class="text-danger">인식 요청 실패: ' + (e.message || e) + '</span>';
        }
        this.value = '';
    });
})();
</script>
@endpush
@endsection
