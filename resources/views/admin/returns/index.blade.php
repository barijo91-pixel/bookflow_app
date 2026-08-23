@extends('admin.layouts.admin')
@section('title', '반품 관리')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">반품 관리 <small class="text-muted fs-6">전사 · 확정 시 PG 부분취소로 환불</small></h1>
    <a href="{{ route('admin.sales.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-bar-chart-line"></i> 매출 조회로
    </a>
</div>

{{-- 필터 --}}
<form method="GET" action="{{ route('admin.returns.index') }}" class="card mb-3">
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
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">총판</label>
                <select name="distributor_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    @foreach($distributors as $d)
                        <option value="{{ $d->id }}" @selected($distId === (int) $d->id)>{{ $d->business_name ?: $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
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
                <a href="{{ route('admin.returns.index', ['view' => $view]) }}" class="btn btn-sm btn-outline-secondary" title="초기화">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-1 mt-2">
            @foreach($views as $key => $label)
                <a href="{{ route('admin.returns.index', ['view' => $key, 'from' => $from, 'to' => $to, 'distributor_id' => $distId, 'agent_id' => $agentId]) }}"
                   class="btn btn-sm {{ $view === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
</form>

{{-- 요약 --}}
<div class="row g-2 mb-3">
    <div class="col-md-2">
        <div class="stat-card py-2">
            <div class="stat-label small">반품 접수</div>
            <div class="stat-value" style="font-size:1.3rem">{{ number_format($summary->total ?? 0) }}건</div>
            @if(($summary->requested ?? 0) > 0)
                <div class="small text-danger fw-bold">확정 대기 {{ number_format($summary->requested) }}건</div>
            @endif
        </div>
    </div>
    <div class="col-md-2">
        <div class="stat-card py-2">
            <div class="stat-label small">확정 수량</div>
            <div class="stat-value" style="font-size:1.3rem">{{ number_format($summary->confirmed_qty ?? 0) }}권</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card py-2">
            <div class="stat-label small">확정 반품 금액</div>
            <div class="stat-value" style="font-size:1.3rem">{{ number_format($summary->confirmed_amount ?? 0) }}원</div>
            <div class="small text-muted">환불 완료 {{ number_format($summary->refunded_amount ?? 0) }}원</div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stat-card py-2">
            <div class="stat-label small">환불 미완</div>
            <div class="stat-value {{ ($summary->refund_stuck ?? 0) > 0 ? 'text-danger' : '' }}" style="font-size:1.3rem">
                {{ number_format($summary->refund_stuck ?? 0) }}건
            </div>
            <div class="small text-muted">재시도 필요</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card py-2">
            <div class="stat-label small">반품률 <span class="text-muted">(확정액 ÷ 기간 매출)</span></div>
            <div class="stat-value" style="font-size:1.3rem">
                @if($returnRate !== null){{ $returnRate }}%@else<span class="fs-6 text-muted">매출 없음</span>@endif
            </div>
            <div class="small text-muted">기간 매출 {{ number_format($sales) }}원</div>
        </div>
    </div>
</div>

@if($view === 'list')
    <div class="d-flex flex-wrap gap-1 mb-2">
        <a href="{{ route('admin.returns.index', ['view' => 'list', 'from' => $from, 'to' => $to, 'distributor_id' => $distId, 'agent_id' => $agentId]) }}"
           class="btn btn-sm {{ $status === '' ? 'btn-primary' : 'btn-outline-secondary' }}">전체</a>
        @foreach($statuses as $k => $label)
            <a href="{{ route('admin.returns.index', ['view' => 'list', 'from' => $from, 'to' => $to, 'status' => $k, 'distributor_id' => $distId, 'agent_id' => $agentId]) }}"
               class="btn btn-sm {{ $status === $k ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>반품 목록</strong>
            <small class="text-muted">{{ $from }} ~ {{ $to }} · {{ $rows->count() }}건</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>접수일</th>
                        <th>반품번호</th>
                        <th>주문번호</th>
                        <th>거래처</th>
                        <th>총판</th>
                        <th>영업자</th>
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
                            <td class="text-muted small">{{ \Carbon\Carbon::parse($r->requested_at)->format('m-d H:i') }}</td>
                            <td><a href="#" class="fw-bold" onclick="return showReturn({{ $r->id }})">{{ $r->return_no }}</a></td>
                            <td><a href="{{ route('admin.orders.show', $r->order_id) }}">{{ $r->order_no }}</a></td>
                            <td class="fw-bold">{{ $r->vendor_name }}</td>
                            <td class="small text-muted">{{ $r->dist_name ?? '-' }}</td>
                            <td class="small text-muted">{{ $r->agent_name ?? '-' }}</td>
                            <td class="small">{{ $reasons[$r->reason_code] ?? $r->reason_code }}</td>
                            <td class="text-end">{{ number_format($r->total_qty) }}</td>
                            <td class="text-end fw-bold">{{ number_format($r->total_amount) }}원</td>
                            <td class="small {{ $rfClass }}">
                                @if($r->status === 'confirmed')
                                    {{ $rfLabel }}@if($r->refund_amount > 0 && $r->refund_status !== 'done') {{ number_format($r->refund_amount) }}원@endif
                                @else - @endif
                            </td>
                            <td><span class="badge {{ $stBadge }}">{{ $statuses[$r->status] ?? $r->status }}</span></td>
                            <td class="text-end text-nowrap">
                                @if($r->status === 'requested')
                                    <form method="POST" action="{{ route('admin.returns.confirm', $r->id) }}" class="d-inline"
                                          onsubmit="return confirm('반품을 확정할까요?\n결제된 주문은 {{ number_format($r->total_amount) }}원이 PG 부분취소로 환불됩니다.')">
                                        @csrf
                                        <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> 확정</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.returns.reject', $r->id) }}" class="d-inline"
                                          onsubmit="var v = prompt('반려 사유를 입력하세요'); if (v === null) return false; this.reason.value = v; return true;">
                                        @csrf
                                        <input type="hidden" name="reason" value="">
                                        <button class="btn btn-sm btn-outline-secondary">반려</button>
                                    </form>
                                @elseif($r->status === 'confirmed' && in_array($r->refund_status, ['failed', 'partial']))
                                    <form method="POST" action="{{ route('admin.returns.retry_refund', $r->id) }}" class="d-inline"
                                          onsubmit="return confirm('남은 환불을 다시 시도할까요?')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-repeat"></i> 환불 재시도</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="text-center text-muted py-5">
                            <i class="bi bi-arrow-return-left" style="font-size:2rem"></i>
                            <p class="mb-0 mt-2">이 기간에 접수된 반품이 없습니다.</p>
                        </td></tr>
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
                    <strong>반품 상세</strong>
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
        fetch('{{ url('admin/returns') }}/' + id)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var fmt = function (n) { return Number(n).toLocaleString(); };
                var h = '<div class="small text-muted mb-2">' + d.return.return_no + ' · ' + (d.return.vendor_name || '') + '</div>';
                h += '<table class="table table-sm small mb-3"><thead class="table-light"><tr><th>교재</th><th class="text-end">수량</th><th class="text-end">단가</th><th class="text-end">금액</th></tr></thead><tbody>';
                d.items.forEach(function (it) {
                    h += '<tr><td>' + it.title_snapshot + '</td><td class="text-end">' + it.qty + '</td><td class="text-end">' + fmt(it.unit_price) + '</td><td class="text-end fw-bold">' + fmt(it.line_total) + '원</td></tr>';
                });
                h += '</tbody></table>';
                if (d.return.reason_text) h += '<div class="small mb-2"><span class="text-muted">사유:</span> ' + d.return.reason_text + '</div>';
                if (d.refunds.length) {
                    h += '<div class="fw-bold small mb-1">환불 이력</div>';
                    d.refunds.forEach(function (rf) {
                        var who = rf.parent_name ? (rf.parent_name + (rf.student_name ? ' (' + rf.student_name + ')' : '')) : '학원 결제';
                        h += '<div class="small d-flex justify-content-between border-bottom py-1"><span>' + who + '</span><span class="' + (rf.status === 'success' ? 'text-success' : 'text-danger') + '">' + fmt(rf.amount) + '원 · ' + (rf.status === 'success' ? '환불됨' : '실패') + '</span></div>';
                        if (rf.error_message) h += '<div class="small text-danger">' + rf.error_message + '</div>';
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
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>{{ $views[$view] }} 반품</strong>
            <small class="text-muted">{{ $from }} ~ {{ $to }} · {{ $rows->count() }}행 · 확정 기준</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:28%;">{{ $views[$view] }}</th>
                        <th class="text-end" style="width:10%;">접수 건수</th>
                        <th class="text-end" style="width:10%;">확정 수량</th>
                        <th class="text-end" style="width:14%;">확정 금액</th>
                        <th class="text-end" style="width:10%;">비중</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        @php $pct = $sumAmt > 0 ? round($r->amount / $sumAmt * 100, 1) : 0; @endphp
                        <tr>
                            <td class="fw-bold">
                                @if($view === 'reason'){{ $reasons[$r->label] ?? $r->label }}@else{{ $r->label ?: '-' }}@endif
                            </td>
                            <td class="text-end text-muted">{{ number_format($r->cnt) }}</td>
                            <td class="text-end text-muted">{{ number_format($r->qty) }}</td>
                            <td class="text-end fw-bold">{{ number_format($r->amount) }}원</td>
                            <td class="text-end text-muted">{{ $pct }}%</td>
                            <td>
                                <div class="progress" style="height:6px; background:#eef2f8;">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: {{ $maxAmt > 0 ? round($r->amount / $maxAmt * 100) : 0 }}%; background:#2c5282;"></div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-arrow-return-left" style="font-size:2rem"></i>
                            <p class="mb-0 mt-2">이 기간에 반품이 없습니다.</p>
                        </td></tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                    <tfoot class="table-light">
                        <tr>
                            <th>합계</th>
                            <th class="text-end">{{ number_format($rows->sum('cnt')) }}</th>
                            <th class="text-end">{{ number_format($rows->sum('qty')) }}</th>
                            <th class="text-end">{{ number_format($sumAmt) }}원</th>
                            <th class="text-end">100%</th>
                            <th></th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endif
@endsection
