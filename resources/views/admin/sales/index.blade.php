@extends('admin.layouts.admin')
@section('title', '매출 조회')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">매출 조회 <small class="text-muted fs-6">전사 · 실제 결제된 금액 기준</small></h1>
    <a href="{{ route('admin.settlement.records') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-cash-stack"></i> 정산 레코드로
    </a>
</div>

{{-- 필터 --}}
<form method="GET" action="{{ route('admin.sales.index') }}" class="card mb-3">
    <input type="hidden" name="view" value="{{ $view }}">
    <div class="card-body py-2">
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
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">총판</label>
                <select name="distributor_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    @foreach($distributors as $d)
                        <option value="{{ $d->id }}" @selected($distId === (int) $d->id)>{{ $d->business_name ?: $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">영업자</label>
                <select name="agent_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    @foreach($agents as $a)
                        <option value="{{ $a->id }}" @selected($agentId === (int) $a->id)>{{ $a->business_name ?: $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search"></i> 조회</button>
                <a href="{{ route('admin.sales.index', ['view' => $view]) }}" class="btn btn-sm btn-outline-secondary" title="초기화">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-1 mt-2">
            @foreach($views as $key => $label)
                <a href="{{ route('admin.sales.index', ['view' => $key, 'from' => $from, 'to' => $to, 'trade' => $trade, 'distributor_id' => $distId, 'agent_id' => $agentId]) }}"
                   class="btn btn-sm {{ $view === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
</form>

{{-- 요약 --}}
<div class="row g-2 mb-3 sales-summary">
    <div class="col-md-3">
        <div class="stat-card py-2">
            <div class="stat-label small">매출</div>
            <div class="stat-value" style="font-size:1.3rem">{{ number_format($summary->revenue ?? 0) }}원</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stat-card py-2">
            <div class="stat-label small">결제 건수</div>
            <div class="stat-value" style="font-size:1.3rem">{{ number_format($summary->cnt ?? 0) }}건</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stat-card py-2">
            <div class="stat-label small">주문 수</div>
            <div class="stat-value" style="font-size:1.3rem">{{ number_format($summary->orders ?? 0) }}건</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stat-card py-2">
            <div class="stat-label small">거래처 수</div>
            <div class="stat-value" style="font-size:1.3rem">{{ number_format($summary->vendors ?? 0) }}곳</div>
        </div>
    </div>
    <div class="col-md-3">
        @php
            $retail = (int) ($byTrade['retail']->revenue ?? 0);
            $whole  = (int) ($byTrade['wholesale']->revenue ?? 0);
            $sumT   = $retail + $whole;
        @endphp
        <div class="stat-card py-2">
            <div class="stat-label small">소매 / 도매</div>
            <div style="font-size:.95rem; line-height:1.6;">
                <div class="fw-bold">소매 {{ number_format($retail) }}원
                    <span class="text-muted fw-normal">{{ $sumT ? round($retail / $sumT * 100) : 0 }}%</span></div>
                <div class="fw-bold">도매 {{ number_format($whole) }}원
                    <span class="text-muted fw-normal">{{ $sumT ? round($whole / $sumT * 100) : 0 }}%</span></div>
            </div>
        </div>
    </div>
</div>

@php
    $isBook = in_array($view, ['book', 'publisher'], true);
    $maxRev = $rows->max('revenue') ?: 1;
    $sumRev = $rows->sum('revenue');
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>{{ $views[$view] }} 매출</strong>
        <small class="text-muted">{{ $from }} ~ {{ $to }} · {{ $rows->count() }}행</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:24%;">{{ $views[$view] }}</th>
                    <th class="text-end" style="width:10%;">{{ $isBook ? '수량' : '결제 건수' }}</th>
                    <th class="text-end" style="width:10%;">주문 수</th>
                    <th class="text-end" style="width:14%;">매출</th>
                    <th class="text-end" style="width:10%;">비중</th>
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
                        <td class="text-end fw-bold">{{ number_format($r->revenue) }}원</td>
                        <td class="text-end text-muted">{{ $pct }}%</td>
                        <td>
                            <div class="progress" style="height:6px; background:#eef2f8;">
                                <div class="progress-bar" role="progressbar"
                                     style="width: {{ $maxRev > 0 ? round($r->revenue / $maxRev * 100) : 0 }}%; background:#2c5282;"></div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-bar-chart" style="font-size:2rem"></i>
                        <p class="mb-0 mt-2">이 기간에 결제된 매출이 없습니다.</p>
                    </td></tr>
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
                <tfoot class="table-light">
                    <tr>
                        <th>합계</th>
                        <th class="text-end">{{ number_format($rows->sum('cnt')) }}</th>
                        <th class="text-end">{{ number_format($summary->orders ?? 0) }}</th>
                        <th class="text-end">{{ number_format($sumRev) }}원</th>
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

@push('head')
<style>
/* 요약 카드 — 위 필터 카드와 같은 계열이라 경계가 안 보인다 → 아이보리로 확실히 구분
   (mypage 매출·반품과 같은 색) */
.sales-summary .stat-card {
    background: #fdf8ec;
    border: 1px solid #ecdfc4;
    border-left: 3px solid #c9a227;
}
/* text-danger 같은 경고색은 살려둔다 */
.sales-summary .stat-value:not(.text-danger) { color: #7a5c00; }
.sales-summary .stat-label { color: #8a7440; }
.sales-summary .stat-card .navy { color: #7a5c00 !important; }
</style>
@endpush