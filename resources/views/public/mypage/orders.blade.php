@extends('public.layouts.app')
@section('title', $title)

@section('content')
@php
    $statusOptions = [
        'requested'  => ['접수', 'bg-warning text-dark'],
        'confirmed'  => ['확정', 'bg-info'],
        'accepted'   => ['총판 접수', 'bg-primary'],
        'shipped'    => ['출고', 'bg-success'],
        'in_transit' => ['배송중', 'bg-success'],
        'completed'  => ['완료', 'bg-dark'],
        'canceled'   => ['취소', 'bg-secondary'],
        'returned'   => ['반품', 'bg-secondary'],
    ];
@endphp

<div class="mb-3">
    <h1 class="h4 navy mb-1">
        <i class="bi bi-receipt"></i> {{ $title }}
        <small class="text-muted fs-6">{{ $orders->total() }}건</small>
    </h1>
    <p class="text-muted small mb-0">
        @if($user->role_code === 'agent')
            학원이 올린 주문을 확인하고 영업자가 확정 처리합니다.
        @elseif($user->role_code === 'distributor')
            영업자가 확정한 주문을 접수하고 출고 처리합니다.
        @else
            본인 학원이 올린 주문 내역입니다.
        @endif
    </p>
</div>

{{-- 상태 필터 --}}
<div class="card section-card mb-3">
    <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
        @php
            // 전체 = 취소 제외 (기본 목록과 같은 기준)
            $keep = request()->only(['date_from','date_to','q','vendor_id','trade_type']);
            $canceledCount = $statusCounts->get('canceled', 0);
        @endphp
        <a href="{{ route('my.orders.index', $keep) }}"
           class="btn btn-sm {{ !$status ? 'btn-navy' : 'btn-outline-secondary' }}">
            전체 ({{ $statusCounts->sum() - $canceledCount }})
        </a>
        @foreach($statusOptions as $code => [$label, $cls])
            @continue($code === 'canceled')
            @if($statusCounts->get($code, 0) > 0)
                <a href="{{ route('my.orders.index', array_merge($keep, ['status' => $code])) }}"
                   class="btn btn-sm {{ $status === $code ? 'btn-navy' : 'btn-outline-secondary' }}">
                    {{ $label }} ({{ $statusCounts->get($code, 0) }})
                </a>
            @endif
        @endforeach
        {{-- 취소는 기본 목록에서 빠져 있으므로, 0건이어도 눌러볼 수 있게 항상 노출 --}}
        <a href="{{ route('my.orders.index', array_merge($keep, ['status' => 'canceled'])) }}"
           class="btn btn-sm ms-auto {{ $status === 'canceled' ? 'btn-navy' : 'btn-outline-secondary' }}">
            <i class="bi bi-x-circle"></i> 취소 ({{ $canceledCount }})
        </a>
    </div>
</div>

{{-- 주문일자 + 키워드 검색 --}}
<form method="GET" action="{{ route('my.orders.index') }}" class="card section-card mb-3">
    <div class="card-body py-3">
        {{-- 필터는 한 줄 유지 — 컬럼 합이 12를 넘지 않게 배분 (역할별) --}}
        @php $isAcademy = $user->role_code === 'academy'; @endphp
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">시작일</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">종료일</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control form-control-sm">
            </div>
            @if(! $isAcademy)
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">학원</label>
                <select name="vendor_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체 학원</option>
                    @foreach($vendorOptions as $vo)
                        <option value="{{ $vo->id }}" @selected((string) $selectedVendor === (string) $vo->id)>{{ $vo->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-md-{{ $isAcademy ? '6' : '2' }}">
                <label class="form-label small text-muted mb-1">주문번호</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="주문번호">
            </div>
            @if(! $isAcademy)
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">거래구분</label>
                <select name="trade_type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    <option value="retail" @selected($tradeType === 'retail')>소매</option>
                    <option value="wholesale" @selected($tradeType === 'wholesale')>도매</option>
                </select>
            </div>
            @endif
            <div class="col-md-2 d-flex gap-1">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search"></i> 조회</button>
                <a href="{{ route('my.orders.index') }}" class="btn btn-sm btn-outline-secondary" title="초기화">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
            @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        </div>
    </div>
</form>

@php
    // 물류로 넘길 수 있는 주문만 체크 가능 — 확정 이후 (접수 대기·취소는 제외)
    $canExport = in_array($user->role_code, ['agent', 'distributor'], true);
    $exportable = \App\Http\Controllers\Public\OrderExportController::EXPORTABLE_STATUS;
@endphp

@if($canExport)
    {{-- 선택 주문을 물류센터 출고 엑셀로 --}}
    <form method="POST" action="{{ route('my.orders.export_logistics') }}" id="logisticsForm" class="d-none d-md-block mb-2">
        @csrf
        <input type="hidden" name="order_ids" id="logisticsIds" value="">
        <div class="d-flex align-items-center gap-2">
            <button type="submit" class="btn btn-sm btn-navy" id="logisticsBtn" disabled>
                <i class="bi bi-file-earmark-excel"></i> 물류센터 출고 엑셀
                <span id="logisticsCount"></span>
            </button>
            <span class="small text-muted">
                주문일 앞 체크박스로 고르세요. <strong>확정 이후</strong>의 주문만 선택할 수 있습니다.
            </span>
        </div>
    </form>
@endif

<div class="card section-card">
    {{-- 데스크탑: 표 --}}
    <div class="table-responsive d-none d-md-block">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    @if($canExport)
                        <th style="width:34px;">
                            <input type="checkbox" class="form-check-input" id="logisticsAll" title="선택 가능한 주문 전체 선택">
                        </th>
                    @endif
                    <th><x-sort-link field="date" label="주문일" :sort="$sort" :dir="$dir" /></th>
                    {{-- 주문번호에 학급을 함께 표기 (학급 컬럼 별도 운용 안 함) --}}
                    <th><x-sort-link field="order_no" label="주문번호 (학급)" :sort="$sort" :dir="$dir" /></th>
                    <th>주문교재</th>
                    @if($user->role_code !== 'academy')<th><x-sort-link field="vendor" label="학원" :sort="$sort" :dir="$dir" /></th><th>구분</th>@endif
                    <th class="text-end"><x-sort-link field="amount" label="금액" :sort="$sort" :dir="$dir" /></th>
                    <th><x-sort-link field="status" label="상태" :sort="$sort" :dir="$dir" /></th>
                    @if($user->role_code !== 'agent')
                        <th><x-sort-link field="agent" label="영업자" :sort="$sort" :dir="$dir" /></th>
                    @endif
                    {{-- 총판 컬럼은 학원에게 감춘다 (학원은 영업자와 거래) --}}
                    @if(! in_array($user->role_code, ['distributor', 'academy'], true))
                        <th><x-sort-link field="distributor" label="총판" :sort="$sort" :dir="$dir" /></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                    <tr class="order-row" style="cursor:pointer" onclick="location.href='{{ route('my.orders.show', $o->id) }}'">
                        @if($canExport)
                            @php $selectable = in_array($o->status_code, $exportable, true); @endphp
                            <td onclick="event.stopPropagation()">
                                <input type="checkbox" class="form-check-input logistics-pick" value="{{ $o->id }}"
                                       {{ $selectable ? '' : 'disabled' }}
                                       title="{{ $selectable ? '물류 출고 엑셀에 포함' : '확정 이후의 주문만 내보낼 수 있습니다' }}">
                            </td>
                        @endif
                        <td class="small text-muted text-nowrap">
                            {{ \Carbon\Carbon::parse($o->requested_at ?? $o->created_at)->format('Y-m-d H:i') }}
                        </td>
                        <td class="text-nowrap">
                            <a href="{{ route('my.orders.show', $o->id) }}" class="text-decoration-none navy fw-bold" onclick="event.stopPropagation()">
                                <code>{{ $o->order_no }}</code>
                            </a>
                            @if($o->class_name)
                                <span class="text-muted small">({{ $o->class_name }})</span>
                            @elseif(($o->trade_type ?? 'retail') !== 'wholesale')
                                {{-- 소매인데 학급이 없는 건 학급 필수 적용 전에 만들어진 주문 --}}
                                <span class="text-muted small">(학급 미지정)</span>
                            @endif
                        </td>
                        <td class="small">
                            @php $sum = $itemSummaries[$o->id] ?? null; @endphp
                            @if($sum)
                                {{ $sum['first'] }}
                                @if($sum['kinds'] > 1)
                                    <span class="text-muted">외 {{ $sum['kinds'] - 1 }}종</span>
                                @endif
                                <span class="text-muted">· {{ number_format($sum['qty']) }}권</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        @if($user->role_code !== 'academy')
                            <td class="small">{{ $o->vendor_name ?? '-' }}</td>
                            <td><span class="badge {{ ($o->trade_type ?? 'retail') === 'wholesale' ? 'bg-secondary' : 'bg-light text-dark' }}">{{ ($o->trade_type ?? 'retail') === 'wholesale' ? '도매' : '소매' }}</span></td>
                        @endif
                        <td class="text-end">{{ number_format($o->total_amount) }}원</td>
                        <td>
                            @php $opt = $statusOptions[$o->status_code] ?? [$o->status_code, 'bg-light text-dark']; @endphp
                            <span class="badge {{ $opt[1] }}">{{ $opt[0] }}</span>
                        </td>
                        @if($user->role_code !== 'agent')
                            <td class="small text-muted">{{ $o->agent_name ?? '-' }}</td>
                        @endif
                        @if(! in_array($user->role_code, ['distributor', 'academy'], true))
                            <td class="small text-muted">{{ $o->distributor_name ?? '-' }}</td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 5 + ($canExport ? 1 : 0) + ($user->role_code !== 'academy' ? 2 : 0) + ($user->role_code !== 'agent' ? 1 : 0) + (! in_array($user->role_code, ['distributor','academy'], true) ? 1 : 0) }}"
                            class="text-center text-muted py-5">
                            <i class="bi bi-inbox" style="font-size:2rem"></i>
                            <p class="mb-0 mt-2">
                                @if($status) 해당 상태의 주문이 없습니다.
                                @else 주문 내역이 없습니다. @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- 모바일: 카드 리스트 --}}
    <div class="d-md-none">
        @forelse($orders as $o)
            @php $opt = $statusOptions[$o->status_code] ?? [$o->status_code, 'bg-light text-dark']; @endphp
            <a href="{{ route('my.orders.show', $o->id) }}" class="order-card-m">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <code class="navy fw-bold">{{ $o->order_no }}</code>
                    <span class="badge {{ $opt[1] }}">{{ $opt[0] }}</span>
                </div>
                @if($o->class_name)
                    <div class="small mb-1"><span class="badge bg-light text-dark"><i class="bi bi-mortarboard"></i> {{ $o->class_name }}</span></div>
                @endif
                @if($user->role_code !== 'academy')
                    <div class="fw-bold mb-1">{{ $o->vendor_name ?? '-' }}
                        <span class="badge {{ ($o->trade_type ?? 'retail') === 'wholesale' ? 'bg-secondary' : 'bg-light text-dark' }}">{{ ($o->trade_type ?? 'retail') === 'wholesale' ? '도매' : '소매' }}</span>
                    </div>
                @endif
                <div class="d-flex justify-content-between align-items-end">
                    <div class="small text-muted">
                        @if($user->role_code !== 'agent' && $o->agent_name){{ $o->agent_name }} · @endif
                        @if(! in_array($user->role_code, ['distributor', 'academy'], true) && $o->distributor_name){{ $o->distributor_name }} · @endif
                        <span class="navy fw-bold">{{ number_format($o->total_amount) }}원</span>
                    </div>
                    <div class="text-muted" style="font-size:.72rem">
                        {{ \Carbon\Carbon::parse($o->requested_at ?? $o->created_at)->format('m-d H:i') }}
                    </div>
                </div>
            </a>
        @empty
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox" style="font-size:2rem"></i>
                <p class="mb-0 mt-2">
                    @if($status) 해당 상태의 주문이 없습니다. @else 주문 내역이 없습니다. @endif
                </p>
            </div>
        @endforelse
    </div>

    @if($orders->hasPages())
        <div class="card-footer">{{ $orders->links() }}</div>
    @endif
</div>
@if($canExport)
{{-- 체크박스 → 선택 주문 id 를 폼에 실어 보낸다 --}}
<script>
(function () {
    var form  = document.getElementById('logisticsForm');
    if (! form) return;
    var btn   = document.getElementById('logisticsBtn');
    var cnt   = document.getElementById('logisticsCount');
    var hid   = document.getElementById('logisticsIds');
    var all   = document.getElementById('logisticsAll');
    var picks = function () { return Array.prototype.slice.call(document.querySelectorAll('.logistics-pick:not(:disabled)')); };

    function sync() {
        var on = picks().filter(function (c) { return c.checked; });
        hid.value  = on.map(function (c) { return c.value; }).join(',');
        btn.disabled = on.length === 0;
        cnt.textContent = on.length ? '(' + on.length + '건)' : '';
        if (all) {
            var total = picks().length;
            all.checked = total > 0 && on.length === total;
            all.indeterminate = on.length > 0 && on.length < total;
        }
    }
    picks().forEach(function (c) { c.addEventListener('change', sync); });
    if (all) all.addEventListener('change', function () {
        picks().forEach(function (c) { c.checked = all.checked; });
        sync();
    });
    sync();
})();
</script>
@endif
@endsection

@push('head')
<style>
.order-card-m {
    display: block; padding: .85rem 1rem; border-bottom: 1px solid #eef0f4;
    text-decoration: none; color: #212529;
}
.order-card-m:last-child { border-bottom: 0; }
.order-card-m:active { background: #f6f7fb; }
</style>
@endpush
