@extends('admin.layouts.admin')
@section('title', '대시보드')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">대시보드</h1>
    <span class="text-muted small">{{ now()->format('Y-m-d (D) H:i') }}</span>
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
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
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
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
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
                                    <div class="text-muted">{{ $u->email }}</div>
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
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
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
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
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
@endsection
