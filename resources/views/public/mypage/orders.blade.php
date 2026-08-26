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
    {{-- 도서주문 제목과 동일하게 한 크기로 — 건수는 아래 '전체(N)' 탭에 이미 있어 제거 --}}
    <h1 class="h4 navy mb-1">
        <i class="bi bi-receipt"></i> {{ $title }}
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
            $keep = request()->only(['date_from','date_to','q','vendor_id','trade_type','credit']);
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
        {{-- 필터를 flex 로 배치해 폭 고정 — 좁아지면 자연스럽게 줄바꿈, 넓으면 한 줄 --}}
        @php $isAcademy = $user->role_code === 'academy'; @endphp
        <div class="d-flex flex-wrap align-items-end gap-2">
            <div style="width:130px">
                <label class="form-label small text-muted mb-1">시작일</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control form-control-sm">
            </div>
            <div style="width:130px">
                <label class="form-label small text-muted mb-1">종료일</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control form-control-sm">
            </div>
            @if(! $isAcademy)
            <div style="width:170px">
                <label class="form-label small text-muted mb-1">학원</label>
                <select name="vendor_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체 학원</option>
                    @foreach($vendorOptions as $vo)
                        <option value="{{ $vo->id }}" @selected((string) $selectedVendor === (string) $vo->id)>{{ $vo->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="width:100px">
                <label class="form-label small text-muted mb-1">거래</label>
                <select name="trade_type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    <option value="retail" @selected($tradeType === 'retail')>소매</option>
                    <option value="wholesale" @selected($tradeType === 'wholesale')>도매</option>
                </select>
            </div>
            <div style="width:100px">
                <label class="form-label small text-muted mb-1">결제</label>
                <select name="credit" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    <option value="normal" @selected($creditType === 'normal')>일반</option>
                    <option value="credit" @selected($creditType === 'credit')>여신</option>
                </select>
            </div>
            @endif
            {{-- 주문번호는 조회 버튼 바로 앞 --}}
            <div style="width:{{ $isAcademy ? '220px' : '175px' }}">
                <label class="form-label small text-muted mb-1">주문번호</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="주문번호">
            </div>
            <div class="d-flex gap-1 flex-shrink-0">
                <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-search"></i> 조회</button>
                <a href="{{ route('my.orders.index') }}" class="btn btn-sm btn-outline-secondary" title="초기화">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
            @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        </div>
    </div>
</form>

@php
    $exportable = \App\Http\Controllers\Public\OrderExportController::EXPORTABLE_STATUS;
    // 물류센터 출고 엑셀은 총판 역할 (영업자 화면에선 노출 안 함)
    $canExportLogistics = $user->role_code === 'distributor';
    // 영업자 일괄 확정은 '결제유형=여신' 을 골랐을 때만 노출 (그래야 무슨 주문을 확정하는지 명확)
    $canBulkConfirm = $user->role_code === 'agent' && $creditType === 'credit';
    // 체크박스·일괄 작업 바는 둘 중 하나라도 가능할 때만
    $canExport = $canExportLogistics || $canBulkConfirm;
    // 행별 결제/여신 상태
    $payInfo = function ($o) use ($paidMap) {
        $total = (int) $o->total_amount;
        $paid  = (int) ($paidMap[$o->id] ?? 0);
        return [
            'paid'   => $total > 0 && $paid >= $total,
            'credit' => (bool) ($o->credit_allowed ?? false),
        ];
    };
@endphp

@if($canExport)
    {{-- 일괄 작업 바 (체크박스 선택) --}}
    <div class="d-none d-md-flex align-items-center gap-2 mb-2 flex-wrap">
        @if($canBulkConfirm)
            {{-- 여신 미결제 주문 일괄 확정 --}}
            <form method="POST" action="{{ route('my.orders.bulk_confirm') }}" id="confirmForm" class="m-0"
                  onsubmit="return confirm('선택한 여신 주문을 일괄 확정할까요?')">
                @csrf
                <input type="hidden" name="order_ids" id="confirmIds" value="">
                <button type="submit" class="btn btn-sm btn-success" id="confirmBtn" disabled>
                    <i class="bi bi-check2-all"></i> 선택 확정<span id="confirmCount"></span>
                </button>
            </form>
        @endif
        {{-- 물류센터 출고 엑셀 — 총판만 --}}
        @if($canExportLogistics)
            <form method="POST" action="{{ route('my.orders.export_logistics') }}" id="logisticsForm" class="m-0">
                @csrf
                <input type="hidden" name="order_ids" id="logisticsIds" value="">
                <button type="submit" class="btn btn-sm btn-navy" id="logisticsBtn" disabled>
                    <i class="bi bi-file-earmark-excel"></i> 물류센터 출고 엑셀<span id="logisticsCount"></span>
                </button>
            </form>
        @endif
        {{-- 안내문은 여신 확정(영업자) 때만 — 총판 물류엑셀은 버튼만으로 충분 --}}
        @if($canBulkConfirm)
            <span class="small text-muted">
                주문일 앞 체크박스로 <strong>여신 미결제</strong> 주문을 골라 확정하세요.
            </span>
        @endif
    </div>
@endif

<div class="card section-card">
    {{-- 데스크탑: 표 --}}
    <div class="table-responsive d-none d-md-block">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    @if($canExport)
                        <th style="width:34px;">
                            <input type="checkbox" class="form-check-input" id="orderPickAll" title="선택 가능한 주문 전체 선택">
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
                            @php
                                $pi = $payInfo($o);
                                $canConfirmRow = $canBulkConfirm && $o->status_code === 'requested' && $pi['credit'] && ! $pi['paid'];
                                $canExportRow  = $canExportLogistics && in_array($o->status_code, $exportable, true);
                                $selectable = $canConfirmRow || $canExportRow;
                            @endphp
                            <td onclick="event.stopPropagation()">
                                <input type="checkbox" class="form-check-input order-pick" value="{{ $o->id }}"
                                       data-confirm="{{ $canConfirmRow ? 1 : 0 }}" data-export="{{ $canExportRow ? 1 : 0 }}"
                                       {{ $selectable ? '' : 'disabled' }}
                                       title="{{ $canConfirmRow ? '여신 주문 확정 대상' : ($canExportRow ? '물류 출고 엑셀 대상' : '선택할 수 없는 상태') }}">
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
                                <span class="fw-semibold navy">{{ $sum['first'] }}</span>
                                @if($sum['kinds'] > 1)
                                    <span class="text-muted">외 {{ $sum['kinds'] - 1 }}종</span>
                                @endif
                                <span class="text-muted">· {{ number_format($sum['qty']) }}권</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        @if($user->role_code !== 'academy')
                            <td class="small">
                                {{ $o->vendor_name ?? '-' }}
                                @if($o->credit_allowed)<span class="badge bg-warning text-dark ms-1" style="font-size:.65rem;">여신</span>@endif
                            </td>
                            <td><span class="badge {{ ($o->trade_type ?? 'retail') === 'wholesale' ? 'bg-secondary' : 'bg-light text-dark' }}">{{ ($o->trade_type ?? 'retail') === 'wholesale' ? '도매' : '소매' }}</span></td>
                        @endif
                        <td class="text-end">{{ number_format($o->total_amount) }}원</td>
                        <td>
                            @php $opt = $statusOptions[$o->status_code] ?? [$o->status_code, 'bg-light text-dark']; @endphp
                            <span class="badge {{ $opt[1] }}">{{ $opt[0] }}</span>
                            {{-- 결제 상태 — 접수 단계에서만 의미 있음(확정 이후는 이미 결제/여신 처리됨) --}}
                            @php $pi2 = $payInfo($o); @endphp
                            @if($o->status_code === 'requested')
                                <div class="small mt-1">
                                    @if($pi2['paid'])
                                        <span class="text-success"><i class="bi bi-check-circle-fill"></i> 결제완료</span>
                                    @elseif($pi2['credit'])
                                        <span class="text-warning"><i class="bi bi-credit-card-2-back"></i> 여신</span>
                                    @else
                                        <span class="text-muted"><i class="bi bi-hourglass-split"></i> 미결제</span>
                                    @endif
                                </div>
                            @endif
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
                        @if($o->credit_allowed)<span class="badge bg-warning text-dark">여신</span>@endif
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
{{-- 체크박스 → 두 가지 일괄 작업(여신 확정 / 물류 엑셀)에 각각 해당 주문만 실어 보낸다 --}}
<script>
(function () {
    var picks = function () { return Array.prototype.slice.call(document.querySelectorAll('.order-pick:not(:disabled)')); };
    if (! picks().length && ! document.getElementById('orderPickAll')) return;
    var all = document.getElementById('orderPickAll');

    var confirmBtn   = document.getElementById('confirmBtn'),
        confirmIds   = document.getElementById('confirmIds'),
        confirmCount = document.getElementById('confirmCount');
    var logiBtn   = document.getElementById('logisticsBtn'),
        logiIds   = document.getElementById('logisticsIds'),
        logiCount = document.getElementById('logisticsCount');

    function sync() {
        var on = picks().filter(function (c) { return c.checked; });
        var confSel = on.filter(function (c) { return c.dataset.confirm === '1'; });
        var expSel  = on.filter(function (c) { return c.dataset.export === '1'; });

        if (confirmBtn) {
            confirmIds.value = confSel.map(function (c) { return c.value; }).join(',');
            confirmBtn.disabled = confSel.length === 0;
            confirmCount.textContent = confSel.length ? ' (' + confSel.length + ')' : '';
        }
        if (logiBtn) {
            logiIds.value = expSel.map(function (c) { return c.value; }).join(',');
            logiBtn.disabled = expSel.length === 0;
            logiCount.textContent = expSel.length ? ' (' + expSel.length + ')' : '';
        }
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
