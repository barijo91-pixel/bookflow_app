@extends('admin.layouts.admin')
@section('title', '대시보드')

@section('content')
@php
    $prevLogin = session('previous_login_at');
    $prevLoginText = $prevLogin ? \Carbon\Carbon::parse($prevLogin)->format('Y-m-d H:i') : null;
    $currentIp = session('current_login_ip');
@endphp
<div class="page-header">
    <h1 class="h4 mb-0">대시보드</h1>
    <div class="text-muted small text-end">
        <div>{{ now()->format('Y-m-d (D) H:i') }}</div>
        @if($prevLoginText)
            <div class="mt-1">
                <i class="bi bi-shield-check"></i>
                이전 로그인: {{ $prevLoginText }}
                @if($currentIp) <span class="text-muted">· IP {{ $currentIp }}</span> @endif
            </div>
        @else
            <div class="mt-1 text-success">
                <i class="bi bi-shield-check"></i> 첫 로그인입니다
            </div>
        @endif
    </div>
</div>

{{-- 상단 통계 카드 4개 --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-label"><i class="bi bi-person-plus"></i> 승인 대기</div>
            <div class="stat-value">{{ number_format($stats['users_pending']) }}</div>
            <a href="{{ route('admin.users.pending') }}" class="stat-link">처리하기 <i class="bi bi-chevron-right"></i></a>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-label"><i class="bi bi-receipt"></i> 진행중 주문</div>
            <div class="stat-value">{{ number_format($stats['orders_pending']) }}</div>
            <a href="{{ route('admin.orders.index') }}?status=requested" class="stat-link">목록 <i class="bi bi-chevron-right"></i></a>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-label"><i class="bi bi-cash-stack"></i> 누적 매출</div>
            <div class="stat-value" style="font-size:1.4rem">{{ number_format($stats['amount_total']) }}<small style="font-size:1rem">원</small></div>
            <span class="stat-link text-muted">오늘 {{ number_format($stats['amount_today']) }}원</span>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-label"><i class="bi bi-exclamation-triangle"></i> 주의</div>
            <div class="stat-value">
                <span class="text-warning">{{ number_format($stats['low_stocks']) }}</span>
                <span class="text-muted" style="font-size:1rem">/</span>
                <span class="text-danger">{{ number_format($stats['notifications_failed']) }}</span>
            </div>
            <span class="stat-link text-muted">재고부족 / 알림실패</span>
        </div>
    </div>
</div>

{{-- 정산 통계 (계획서 7장) --}}
@if(($settlement->cnt ?? 0) > 0)
<div class="card section-card mb-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-cash-coin"></i> 정산 통계</strong>
        <a href="{{ route('admin.settlement.records') }}" class="small text-decoration-none">전체 보기 <i class="bi bi-chevron-right"></i></a>
    </div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-2 col-6">
                <div class="text-muted small">정산 건수</div>
                <div class="h5 mb-0">{{ number_format($settlement->cnt) }}건</div>
            </div>
            <div class="col-md-2 col-6">
                <div class="text-muted small">학부모 결제 합계</div>
                <div class="h5 mb-0">{{ number_format($settlement->parent_paid_total) }}원</div>
            </div>
            <div class="col-md-2 col-6">
                <div class="text-muted small">총판 순이익</div>
                <div class="h5 mb-0 navy">{{ number_format($settlement->dist_net_total) }}원</div>
            </div>
            <div class="col-md-2 col-6">
                <div class="text-muted small">사입자 마진</div>
                <div class="h5 mb-0 text-success">{{ number_format($settlement->agent_net_total) }}원</div>
            </div>
            <div class="col-md-2 col-6">
                <div class="text-muted small">PG 수수료</div>
                <div class="h5 mb-0 text-danger">{{ number_format($settlement->pg_fee_total) }}원</div>
            </div>
            <div class="col-md-2 col-6">
                <div class="text-muted small">BookSys 중계</div>
                <div class="h5 mb-0">{{ number_format($settlement->booksys_fee_total) }}원</div>
            </div>
        </div>
        @if($settlement->pending_cnt > 0)
            <div class="alert alert-warning small mt-3 mb-0 d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-exclamation-circle"></i>
                    사입자 지급 대기: <strong>{{ $settlement->pending_cnt }}건 · {{ number_format($settlement->pending_total) }}원</strong>
                </span>
                <a href="{{ route('admin.settlement.records') }}?status=computed" class="btn btn-sm btn-warning">지급 처리</a>
            </div>
        @endif
    </div>
</div>
@endif

{{-- 매출 추세 차트 (최근 30일) --}}
<div class="card section-card mb-4">
    <div class="card-header bg-light"><strong><i class="bi bi-graph-up"></i> 최근 30일 매출 추세</strong></div>
    <div class="card-body" style="position:relative; height:240px;">
        <canvas id="salesChart"></canvas>
    </div>
</div>

{{-- TOP 5 사입자 + 학원 --}}
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card section-card h-100">
            <div class="card-header bg-light"><strong><i class="bi bi-trophy"></i> 활성 사입자 TOP 5 (최근 30일)</strong></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>사입자</th><th class="text-end">주문 수</th><th class="text-end">매출</th></tr>
                    </thead>
                    <tbody>
                        @forelse($topAgents as $a)
                            <tr>
                                <td>{{ $a->name }}</td>
                                <td class="text-end">{{ number_format($a->orders_cnt) }}건</td>
                                <td class="text-end">{{ number_format($a->amount) }}원</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">최근 30일 거래 없음</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card section-card h-100">
            <div class="card-header bg-light"><strong><i class="bi bi-building-fill"></i> 활성 학원 TOP 5 (최근 30일)</strong></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>학원</th><th class="text-end">주문 수</th><th class="text-end">매출</th></tr>
                    </thead>
                    <tbody>
                        @forelse($topVendors as $v)
                            <tr>
                                <td>{{ $v->name }}</td>
                                <td class="text-end">{{ number_format($v->orders_cnt) }}건</td>
                                <td class="text-end">{{ number_format($v->amount) }}원</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">최근 30일 거래 없음</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- 역할별 사용자 --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="role-card">
            <div class="role-card-icon"><i class="bi bi-truck"></i></div>
            <div>
                <div class="role-card-label">총판</div>
                <div class="role-card-value">{{ number_format($stats['distributors']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="role-card">
            <div class="role-card-icon"><i class="bi bi-person-badge"></i></div>
            <div>
                <div class="role-card-label">영업자</div>
                <div class="role-card-value">{{ number_format($stats['agents']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="role-card">
            <div class="role-card-icon"><i class="bi bi-building"></i></div>
            <div>
                <div class="role-card-label">학원</div>
                <div class="role-card-value">{{ number_format($stats['vendors']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="role-card">
            <div class="role-card-icon"><i class="bi bi-journals"></i></div>
            <div>
                <div class="role-card-label">도서 마스터</div>
                <div class="role-card-value">{{ number_format($stats['books']) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- 활동 위젯 4개 --}}
<div class="row g-3">
    {{-- 최근 주문 --}}
    <div class="col-lg-6">
        <div class="card section-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-receipt"></i> 최근 주문</strong>
                <a href="{{ route('admin.orders.index') }}" class="small text-decoration-none">전체보기 <i class="bi bi-chevron-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        @forelse($recentOrders as $o)
                            <tr>
                                <td class="small">
                                    <a href="{{ route('admin.orders.show', $o->id) }}" class="text-decoration-none"><code>{{ $o->order_no }}</code></a>
                                </td>
                                <td class="small">{{ $o->vendor_name }}</td>
                                <td class="small text-muted">{{ $o->agent_name }}</td>
                                <td>
                                    @switch($o->status_code)
                                        @case('requested') <span class="badge bg-warning text-dark">접수</span> @break
                                        @case('confirmed') <span class="badge bg-info">확정</span> @break
                                        @case('accepted')  <span class="badge bg-primary">총판접수</span> @break
                                        @case('shipped')   <span class="badge bg-success">출고</span> @break
                                        @case('in_transit')<span class="badge bg-success">배송중</span> @break
                                        @case('completed') <span class="badge bg-dark">완료</span> @break
                                        @case('canceled')  <span class="badge bg-secondary">취소</span> @break
                                        @default <span class="badge bg-light text-dark">{{ $o->status_code }}</span>
                                    @endswitch
                                </td>
                                <td class="text-end small">{{ number_format($o->total_amount) }}원</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-3 small">최근 주문이 없습니다.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 최근 가입 --}}
    <div class="col-lg-6">
        <div class="card section-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-people"></i> 최근 가입</strong>
                <a href="{{ route('admin.users.index') }}" class="small text-decoration-none">전체보기 <i class="bi bi-chevron-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        @forelse($recentUsers as $u)
                            <tr>
                                <td class="small">
                                    <a href="{{ route('admin.users.show', $u->id) }}" class="text-decoration-none">{{ $u->name }}</a>
                                    <div class="text-muted"><code>{{ $u->login_id }}</code></div>
                                </td>
                                <td><span class="badge bg-light text-dark">{{ $u->role_code }}</span></td>
                                <td>
                                    @switch($u->status_code)
                                        @case('active')     <span class="badge bg-success">승인</span> @break
                                        @case('pending')    <span class="badge bg-warning text-dark">대기</span> @break
                                        @case('suspended')  <span class="badge bg-secondary">일시정지</span> @break
                                        @default <span class="badge bg-light text-dark">{{ $u->status_code }}</span>
                                    @endswitch
                                </td>
                                <td class="text-muted small">{{ \Carbon\Carbon::parse($u->created_at)->format('m-d') }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-3 small">데이터 없음</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 최근 알림 --}}
    <div class="col-lg-6">
        <div class="card section-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-bell"></i> 최근 알림</strong>
                <a href="{{ route('admin.notifications.logs') }}" class="small text-decoration-none">전체보기 <i class="bi bi-chevron-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        @forelse($recentNotifications as $n)
                            <tr>
                                <td class="small"><code>{{ $n->event_code }}</code></td>
                                <td><span class="badge bg-light text-dark">{{ $n->channel }}</span></td>
                                <td class="small text-muted">{{ $n->recipient_phone }}</td>
                                <td>
                                    @switch($n->status)
                                        @case('sent')    <span class="badge bg-success">발송</span> @break
                                        @case('failed')  <span class="badge bg-danger">실패</span> @break
                                        @case('skipped') <span class="badge bg-warning text-dark">건너뜀</span> @break
                                        @default <span class="badge bg-light text-dark">{{ $n->status }}</span>
                                    @endswitch
                                </td>
                                <td class="text-muted small">{{ \Carbon\Carbon::parse($n->created_at)->format('m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-3 small">발송 이력 없음</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 최근 감사 활동 --}}
    <div class="col-lg-6">
        <div class="card section-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-shield-check"></i> 최근 감사 활동</strong>
                <a href="{{ route('admin.audit-logs.index') }}" class="small text-decoration-none">전체보기 <i class="bi bi-chevron-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        @forelse($recentAudits as $a)
                            <tr>
                                <td class="small">{{ $a->user_name ?? '시스템' }}</td>
                                <td class="small"><code>{{ $a->entity }}</code> #{{ $a->entity_id }}</td>
                                <td>
                                    @php $cls = match($a->action) {
                                        'create','add'    => 'bg-success',
                                        'update','modify' => 'bg-primary',
                                        'delete','remove' => 'bg-danger',
                                        'approve'         => 'bg-info',
                                        default           => 'bg-light text-dark',
                                    }; @endphp
                                    <span class="badge {{ $cls }}">{{ $a->action }}</span>
                                </td>
                                <td class="text-muted small">{{ \Carbon\Carbon::parse($a->created_at)->format('m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-3 small">활동 이력 없음</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Chart.js: 매출 추세 --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    const ctx = document.getElementById('salesChart');
    if (! ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: {!! $chartLabels->toJson() !!},
            datasets: [
                {
                    label: '매출(원)',
                    data: {!! $chartAmounts->toJson() !!},
                    borderColor: '#1f3a5f',
                    backgroundColor: 'rgba(31,58,95,0.1)',
                    fill: true, tension: 0.3, yAxisID: 'y1',
                },
                {
                    label: '주문수',
                    data: {!! $chartCnt->toJson() !!},
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220,53,69,0.1)',
                    borderDash: [4,4], tension: 0.3, yAxisID: 'y2',
                },
            ],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y1: { position: 'left', title: { display: true, text: '매출(원)' },
                      ticks: { callback: v => v.toLocaleString() } },
                y2: { position: 'right', title: { display: true, text: '주문수' },
                      grid: { drawOnChartArea: false } },
            },
        },
    });
})();
</script>
@endsection
