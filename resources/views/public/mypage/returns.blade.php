@extends('public.layouts.app')
@section('title', '반품 관리')
@section('max_width', '1400px')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-arrow-return-left"></i> 반품 관리</h1>
    <p class="text-muted small mb-0">
        반품은 <strong>주문 상세에서 품목별 수량으로 접수</strong>하고, 총판이 확정하면 결제된 건은 <strong>PG 부분취소로 자동 환불</strong>됩니다.
    </p>
</div>

{{-- 기간 + 보기 --}}
<form method="GET" action="{{ route('my.returns.index') }}" class="card section-card mb-3">
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
            <div class="col-md-2 d-flex gap-1">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search"></i> 조회</button>
                <a href="{{ route('my.returns.index', ['view' => $view]) }}" class="btn btn-sm btn-outline-secondary" title="이번 달">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
            <div class="col-md-6 d-flex flex-wrap gap-1 justify-content-md-end">
                @foreach($views as $key => $label)
                    <a href="{{ route('my.returns.index', ['view' => $key, 'from' => $from, 'to' => $to]) }}"
                       class="btn btn-sm {{ $view === $key ? 'btn-navy' : 'btn-outline-secondary' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </div>
</form>

{{-- 요약 — 라벨과 값을 한 줄에 두고 카드 높이를 줄인다 --}}
<div class="row g-2 mb-3 sales-summary">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-2">
                <span class="stat-label mb-0">반품 접수</span>
                <span class="stat-value ms-auto">{{ number_format($summary->total ?? 0) }}<span class="fs-6">건</span></span>
            </div>
            @if(($summary->requested ?? 0) > 0)
                <div class="small text-danger fw-bold text-end">확정 대기 {{ number_format($summary->requested) }}건</div>
            @endif
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card d-flex align-items-center gap-2">
            <span class="stat-label mb-0">확정 반품 수량</span>
            <span class="stat-value ms-auto">{{ number_format($summary->confirmed_qty ?? 0) }}<span class="fs-6">권</span></span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-2">
                <span class="stat-label mb-0">확정 반품 금액</span>
                <span class="stat-value ms-auto">{{ number_format($summary->confirmed_amount ?? 0) }}<span class="fs-6">원</span></span>
            </div>
            @if(($summary->refunded_amount ?? 0) > 0)
                <div class="small text-muted text-end">환불 완료 {{ number_format($summary->refunded_amount) }}원</div>
            @endif
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex align-items-center gap-2">
                <span class="stat-label mb-0">반품률</span>
                <span class="stat-value ms-auto">
                    @if($returnRate !== null){{ $returnRate }}<span class="fs-6">%</span>
                    @else<span class="fs-6 text-muted fw-normal">매출 없음</span>@endif
                </span>
            </div>
            <div class="small text-muted text-end">기간 매출 {{ number_format($sales) }}원</div>
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

@if($view === 'list')
    {{-- 상태 탭 --}}
    <div class="d-flex flex-wrap gap-1 mb-2">
        <a href="{{ route('my.returns.index', ['view' => 'list', 'from' => $from, 'to' => $to]) }}"
           class="btn btn-sm {{ $status === '' ? 'btn-navy' : 'btn-outline-secondary' }}">전체</a>
        @foreach($statuses as $k => $label)
            <a href="{{ route('my.returns.index', ['view' => 'list', 'from' => $from, 'to' => $to, 'status' => $k]) }}"
               class="btn btn-sm {{ $status === $k ? 'btn-navy' : 'btn-outline-secondary' }}">{{ $label }}</a>
        @endforeach
    </div>

    @php
        // 물류센터 반품 회수 엑셀 — 출고 엑셀과 같은 기준으로 총판 역할
        $canExportPickup = $user->role_code === 'distributor';
        $pickupStatus    = \App\Http\Controllers\Public\ReturnExportController::EXPORTABLE_STATUS;
    @endphp

    @if($canExportPickup)
        <div class="d-none d-md-flex align-items-center gap-2 mb-2 flex-wrap">
            <form method="POST" action="{{ route('my.returns.export_logistics') }}" id="pickupForm" class="m-0">
                @csrf
                <input type="hidden" name="return_ids" id="pickupIds" value="">
                <button type="submit" class="btn btn-sm btn-navy" id="pickupBtn" disabled>
                    <i class="bi bi-file-earmark-excel"></i> 물류센터 회수 엑셀<span id="pickupCount"></span>
                </button>
            </form>
            <span class="small text-muted">
                접수일 앞 체크박스로 <strong>확정된</strong> 반품을 골라 회수 요청을 만드세요.
            </span>
        </div>
    @endif

    <div class="card section-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-table"></i> 반품 목록</strong>
            <small class="text-muted">{{ $from }} ~ {{ $to }} · {{ $rows->count() }}건</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-row-highlight">
                <thead class="table-light">
                    <tr>
                        @if($canExportPickup)
                            <th style="width:34px" class="text-center">
                                <input type="checkbox" id="returnPickAll" class="form-check-input" title="전체 선택">
                            </th>
                        @endif
                        <th>접수일</th>
                        <th>반품번호</th>
                        <th>주문번호</th>
                        <th>거래처</th>
                        @if($user->role_code !== 'agent')<th>영업자</th>@endif
                        <th>사유</th>
                        <th class="text-end">수량</th>
                        <th class="text-end">금액</th>
                        <th>환불</th>
                        <th>상태</th>
                        <th class="text-end">처리</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        @php
                            $stBadge = ['requested' => 'bg-warning text-dark', 'confirmed' => 'bg-success',
                                        'rejected' => 'bg-secondary', 'canceled' => 'bg-secondary'][$r->status] ?? 'bg-light text-dark';
                            $rfLabel = ['none' => '해당 없음', 'pending' => '대기', 'partial' => '일부', 'done' => '완료', 'failed' => '실패'][$r->refund_status] ?? '-';
                            $rfClass = ['done' => 'text-success', 'partial' => 'text-danger', 'failed' => 'text-danger'][$r->refund_status] ?? 'text-muted';
                        @endphp
                        <tr>
                            @if($canExportPickup)
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input return-pick" value="{{ $r->id }}"
                                           @disabled(! in_array($r->status, $pickupStatus, true))
                                           title="{{ in_array($r->status, $pickupStatus, true) ? '회수 엑셀 대상' : '확정된 반품만 회수 요청할 수 있습니다' }}">
                                </td>
                            @endif
                            <td class="text-muted small">{{ \Carbon\Carbon::parse($r->requested_at)->format('m-d H:i') }}</td>
                            <td><a href="#" class="link-name fw-bold" onclick="return showReturn({{ $r->id }})">{{ $r->return_no }}</a></td>
                            <td><a href="{{ route('my.orders.show', $r->order_id) }}" class="link-name">{{ $r->order_no }}</a></td>
                            <td class="fw-bold">{{ $r->vendor_name }}</td>
                            @if($user->role_code !== 'agent')<td class="text-muted small">{{ $r->agent_name ?? '-' }}</td>@endif
                            <td class="small">{{ $reasons[$r->reason_code] ?? $r->reason_code }}</td>
                            <td class="text-end">{{ number_format($r->total_qty) }}</td>
                            <td class="text-end fw-bold navy">{{ number_format($r->total_amount) }}원</td>
                            <td class="small {{ $rfClass }}">
                                @if($r->status === 'confirmed')
                                    {{ $rfLabel }}@if($r->refund_amount > 0 && $r->refund_status !== 'done') {{ number_format($r->refund_amount) }}원 @endif
                                @else - @endif
                            </td>
                            <td><span class="badge {{ $stBadge }}">{{ $statuses[$r->status] ?? $r->status }}</span></td>
                            <td class="text-end">
                                @if($r->status === 'requested')
                                    @if($user->role_code === 'distributor')
                                        <form method="POST" action="{{ route('my.returns.confirm', $r->id) }}" class="d-inline"
                                              onsubmit="return confirm('반품을 확정할까요?\n결제된 주문은 {{ number_format($r->total_amount) }}원이 PG 부분취소로 환불됩니다.')">
                                            @csrf
                                            <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> 확정</button>
                                        </form>
                                        <form method="POST" action="{{ route('my.returns.reject', $r->id) }}" class="d-inline"
                                              onsubmit="var v = prompt('반려 사유를 입력하세요'); if (v === null) return false; this.reason.value = v; return true;">
                                            @csrf
                                            <input type="hidden" name="reason" value="">
                                            <button class="btn btn-sm btn-outline-secondary">반려</button>
                                        </form>
                                    @endif
                                    @if($user->role_code === 'agent')
                                        <form method="POST" action="{{ route('my.returns.cancel', $r->id) }}" class="d-inline"
                                              onsubmit="return confirm('반품 접수를 취소할까요?')">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary">접수 취소</button>
                                        </form>
                                    @endif
                                @elseif($r->status === 'confirmed' && in_array($r->refund_status, ['failed', 'partial']))
                                    @if($user->role_code === 'distributor')
                                        <form method="POST" action="{{ route('my.returns.retry_refund', $r->id) }}" class="d-inline"
                                              onsubmit="return confirm('남은 환불을 다시 시도할까요?')">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-repeat"></i> 환불 재시도</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canExportPickup ? 12 : 11 }}" class="text-center text-muted py-5">
                                <i class="bi bi-arrow-return-left" style="font-size:2rem"></i>
                                <p class="mb-1 mt-2">이 기간에 접수된 반품이 없습니다.</p>
                                <p class="small mb-0">반품 접수는 <a href="{{ route('my.orders.index') }}" class="link-name">주문 상세</a>에서 합니다.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- 상세 모달 --}}
    <div class="modal fade" id="returnDetailModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <strong class="navy" id="rdTitle">반품 상세</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="rdBody">
                    <div class="text-center text-muted py-4">불러오는 중...</div>
                </div>
            </div>
        </div>
    </div>
    <script>
    function showReturn(id) {
        var modal = new bootstrap.Modal(document.getElementById('returnDetailModal'));
        document.getElementById('rdBody').innerHTML = '<div class="text-center text-muted py-4">불러오는 중...</div>';
        modal.show();
        fetch('{{ url('mypage/returns') }}/' + id)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var fmt = function (n) { return Number(n).toLocaleString(); };
                // DB 값(사유·이름·교재명 등)은 사용자 입력 — innerHTML 에 넣기 전 반드시 이스케이프 (XSS)
                var esc = function (s) {
                    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                    });
                };
                var h = '<div class="small text-muted mb-2">' + esc(d.return.return_no) + ' · ' + esc(d.return.vendor_name || '') + '</div>';
                h += '<table class="table table-sm small mb-3"><thead class="table-light"><tr><th>교재</th><th class="text-end">수량</th><th class="text-end">단가</th><th class="text-end">금액</th></tr></thead><tbody>';
                d.items.forEach(function (it) {
                    h += '<tr><td>' + esc(it.title_snapshot) + '</td><td class="text-end">' + fmt(it.qty) + '</td><td class="text-end">' + fmt(it.unit_price) + '</td><td class="text-end fw-bold">' + fmt(it.line_total) + '원</td></tr>';
                });
                h += '</tbody></table>';
                if (d.return.reason_text) h += '<div class="small mb-2"><span class="text-muted">사유:</span> ' + esc(d.return.reason_text) + '</div>';
                if (d.refunds.length) {
                    h += '<div class="fw-bold small navy mb-1">환불 이력</div>';
                    d.refunds.forEach(function (rf) {
                        var who = rf.parent_name ? (esc(rf.parent_name) + (rf.student_name ? ' (' + esc(rf.student_name) + ')' : '')) : '학원 결제';
                        h += '<div class="small d-flex justify-content-between border-bottom py-1"><span>' + who + '</span><span class="' + (rf.status === 'success' ? 'text-success' : 'text-danger') + '">' + fmt(rf.amount) + '원 · ' + (rf.status === 'success' ? '환불됨' : '실패') + '</span></div>';
                        if (rf.error_message) h += '<div class="small text-danger">' + esc(rf.error_message) + '</div>';
                    });
                }
                document.getElementById('rdBody').innerHTML = h;
            })
            .catch(function () {
                document.getElementById('rdBody').innerHTML = '<div class="text-danger small">상세를 불러오지 못했습니다.</div>';
            });
        return false;
    }
    </script>
@else
    @php $maxAmt = $rows->max('amount') ?: 1; $sumAmt = $rows->sum('amount'); @endphp
    <div class="card section-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-table"></i> {{ $views[$view] }} 반품</strong>
            <small class="text-muted">{{ $from }} ~ {{ $to }} · {{ $rows->count() }}행 · 확정 기준</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-row-highlight">
                <thead class="table-light">
                    <tr>
                        <th style="width:26%;">{{ $views[$view] }}</th>
                        <th class="text-end" style="width:10%;">접수 건수</th>
                        <th class="text-end" style="width:10%;">확정 수량</th>
                        <th class="text-end" style="width:14%;">확정 금액</th>
                        <th style="width:12%;" class="text-end">비중</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        @php $pct = $sumAmt > 0 ? round($r->amount / $sumAmt * 100, 1) : 0; @endphp
                        <tr>
                            <td class="fw-bold">
                                @if($view === 'reason'){{ $reasons[$r->label] ?? $r->label }}
                                @else{{ $r->label ?: '-' }}@endif
                            </td>
                            <td class="text-end text-muted">{{ number_format($r->cnt) }}</td>
                            <td class="text-end text-muted">{{ number_format($r->qty) }}</td>
                            <td class="text-end fw-bold navy">{{ number_format($r->amount) }}원</td>
                            <td class="text-end text-muted">{{ $pct }}%</td>
                            <td>
                                <div class="progress" style="height:6px; background:#eef2f8;">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: {{ $maxAmt > 0 ? round($r->amount / $maxAmt * 100) : 0 }}%; background:#2c5282;"></div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-arrow-return-left" style="font-size:2rem"></i>
                                <p class="mb-0 mt-2">이 기간에 반품이 없습니다.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                    <tfoot class="table-light">
                        <tr>
                            <th>합계</th>
                            <th class="text-end">{{ number_format($rows->sum('cnt')) }}</th>
                            <th class="text-end">{{ number_format($rows->sum('qty')) }}</th>
                            <th class="text-end navy">{{ number_format($sumAmt) }}원</th>
                            <th class="text-end">100%</th>
                            <th></th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endif
@if($canExportPickup ?? false)
{{-- 체크박스 → 회수 엑셀에 확정된 반품만 실어 보낸다 --}}
<script>
(function () {
    var picks = function () { return Array.prototype.slice.call(document.querySelectorAll('.return-pick:not(:disabled)')); };
    var all = document.getElementById('returnPickAll');
    if (! picks().length && ! all) return;
    var btn = document.getElementById('pickupBtn'),
        ids = document.getElementById('pickupIds'),
        cnt = document.getElementById('pickupCount');

    function sync() {
        var on = picks().filter(function (c) { return c.checked; });
        if (btn) {
            ids.value = on.map(function (c) { return c.value; }).join(',');
            btn.disabled = on.length === 0;
            cnt.textContent = on.length ? ' (' + on.length + ')' : '';
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
