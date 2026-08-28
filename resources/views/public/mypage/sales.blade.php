@extends('public.layouts.app')
@section('title', '매출 조회')
@section('max_width', '1400px')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-bar-chart-line"></i> 매출 조회</h1>
    <p class="text-muted small mb-0">
        학부모(소매)·학원(도매)이 <strong>실제로 결제한 금액</strong> 기준입니다. 주문만 되고 아직 결제되지 않은 건은 잡히지 않습니다.
    </p>
</div>

{{-- 기간 + 보기 --}}
<form method="GET" action="{{ route('my.sales.index') }}" class="card section-card mb-3">
    <input type="hidden" name="view" value="{{ $view }}">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">시작일</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">종료일</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">거래구분</label>
                <select name="trade" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($trades as $k => $label)
                        <option value="{{ $k }}" @selected((string) $trade === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search"></i> 조회</button>
                <a href="{{ route('my.sales.index', ['view' => $view]) }}" class="btn btn-sm btn-outline-secondary" title="이번 달">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
            <div class="col-md-4 d-flex flex-wrap gap-1 justify-content-md-end">
                @foreach($views as $key => $label)
                    @if($key === 'agent' && $user->role_code === 'agent') @continue @endif
                    <a href="{{ route('my.sales.index', ['view' => $key, 'from' => $from, 'to' => $to, 'trade' => $trade]) }}"
                       class="btn btn-sm {{ $view === $key ? 'btn-navy' : 'btn-outline-secondary' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </div>
</form>

{{-- 요약 — 라벨과 값을 한 줄에 두고 카드 높이를 줄인다 --}}
<div class="row g-2 mb-3 sales-summary">
    <div class="col-6 col-lg-3">
        <div class="stat-card d-flex align-items-center gap-2">
            <span class="stat-label mb-0">매출</span>
            <span class="stat-value ms-auto">{{ number_format($summary->revenue ?? 0) }}<span class="fs-6">원</span></span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card d-flex align-items-center gap-2">
            <span class="stat-label mb-0">결제 건수</span>
            <span class="stat-value ms-auto">{{ number_format($summary->cnt ?? 0) }}<span class="fs-6">건</span></span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card d-flex align-items-center gap-2">
            <span class="stat-label mb-0">주문 수</span>
            <span class="stat-value ms-auto">{{ number_format($summary->orders ?? 0) }}<span class="fs-6">건</span></span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        @php
            $retail = (int) ($byTrade['retail']->revenue ?? 0);
            $whole  = (int) ($byTrade['wholesale']->revenue ?? 0);
            $bothRev = (int) ($byTrade['both']->revenue ?? 0);
            $sumT   = $retail + $whole + $bothRev;
        @endphp
        <div class="stat-card">
            <div class="d-flex align-items-center gap-2">
                <span class="stat-label mb-0">소매</span>
                <span class="ms-auto fw-bold navy">{{ number_format($retail) }}원
                    <span class="text-muted fw-normal small">{{ $sumT ? round($retail / $sumT * 100) : 0 }}%</span>
                </span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="stat-label mb-0">도매</span>
                <span class="ms-auto fw-bold navy">{{ number_format($whole) }}원
                    <span class="text-muted fw-normal small">{{ $sumT ? round($whole / $sumT * 100) : 0 }}%</span>
                </span>
            </div>
            {{-- 도·소매 학원 매출 — 있을 때만. 없으면 줄만 늘어난다 --}}
            @if($bothRev > 0)
                <div class="d-flex align-items-center gap-2">
                    <span class="stat-label mb-0">도·소매</span>
                    <span class="ms-auto fw-bold navy">{{ number_format($bothRev) }}원
                        <span class="text-muted fw-normal small">{{ $sumT ? round($bothRev / $sumT * 100) : 0 }}%</span>
                    </span>
                </div>
            @endif
        </div>
    </div>
</div>

@push('head')
<style>
/* 요약 카드 — 한 줄 배치 + 세로 여백 축소.
   위 필터 카드가 연한 파랑이라 같은 계열이면 경계가 안 보인다 → 아이보리로 확실히 구분 */
.sales-summary .stat-card {
    padding: .6rem .9rem; height: auto;
    background: #fdf8ec;
    border: 1px solid #ecdfc4;
    border-left: 3px solid #c9a227;
}
.sales-summary .stat-value { font-size: 1.35rem; line-height: 1.2; margin-top: 0; color: #7a5c00; }
.sales-summary .stat-label { font-size: .85rem; color: #8a7440; }
.sales-summary .stat-card .navy { color: #7a5c00 !important; }
</style>
@endpush

@php
    $isBook  = in_array($view, ['book', 'publisher'], true);
    $maxRev  = $rows->max('revenue') ?: 1;
    $sumRev  = $rows->sum('revenue');
@endphp

<div class="card section-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-table"></i> {{ $views[$view] }} 매출</strong>
        <small class="text-muted">{{ $from }} ~ {{ $to }} · {{ $rows->count() }}행</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    <th style="width:22%;">{{ $views[$view] }}</th>
                    <th class="text-end" style="width:10%;">{{ $isBook ? '수량' : '결제 건수' }}</th>
                    <th class="text-end" style="width:10%;">주문 수</th>
                    <th class="text-end" style="width:14%;">매출</th>
                    <th style="width:12%;" class="text-end">비중</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                    @php $pct = $sumRev > 0 ? round($r->revenue / $sumRev * 100, 1) : 0; @endphp
                    <tr>
                        <td class="fw-bold">{{ $r->label ?: '-' }}</td>
                        <td class="text-end text-muted">{{ number_format($r->cnt) }}</td>
                        <td class="text-end text-muted">{{ number_format($r->orders) }}</td>
                        <td class="text-end fw-bold navy">{{ number_format($r->revenue) }}원</td>
                        <td class="text-end text-muted">{{ $pct }}%</td>
                        <td>
                            {{-- 상대 크기를 막대로 — 숫자만 보면 편차가 안 잡힌다 --}}
                            <div class="progress" style="height:6px; background:#eef2f8;">
                                <div class="progress-bar" role="progressbar"
                                     style="width: {{ $maxRev > 0 ? round($r->revenue / $maxRev * 100) : 0 }}%; background:#2c5282;"></div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-bar-chart" style="font-size:2rem"></i>
                            <p class="mb-1 mt-2">이 기간에 결제된 매출이 없습니다.</p>
                            <p class="small mb-0">학부모 결제가 완료되면 집계됩니다.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
                <tfoot class="table-light">
                    <tr>
                        <th>합계</th>
                        <th class="text-end">{{ number_format($rows->sum('cnt')) }}</th>
                        <th class="text-end">{{ number_format($summary->orders ?? 0) }}</th>
                        <th class="text-end navy">{{ number_format($sumRev) }}원</th>
                        <th class="text-end">100%</th>
                        <th></th>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

@if($isBook)
    <div class="alert alert-light border small text-muted mt-3 mb-0">
        <i class="bi bi-info-circle"></i>
        결제는 주문 단위로 이뤄져 도서별 금액이 따로 남지 않습니다.
        <strong>주문 결제액을 그 주문의 도서 금액 비중으로 나눠</strong> 계산한 값입니다.
    </div>
@endif
@endsection
