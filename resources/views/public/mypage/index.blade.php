@extends('public.layouts.app')
@section('title', '마이페이지')
@section('max_width', '1100px')

@section('content')
<div class="mb-4">
    <h1 class="h4 navy mb-1">안녕하세요, {{ $user->name }}님</h1>
    <p class="text-muted small mb-0">
        <span class="badge bg-navy">{{ match($user->role_code) {
            'distributor' => '총판',
            'agent'       => '영업자',
            'academy'     => '학원',
            'admin'       => '관리자',
            default       => $user->role_code,
        } }}</span>
        {{ $user->login_id }} ·
        @switch($user->status_code)
            @case('active')    <span class="text-success">정상</span> @break
            @case('pending')   <span class="text-warning">승인 대기</span> @break
            @case('suspended') <span class="text-secondary">일시정지</span> @break
            @default {{ $user->status_code }}
        @endswitch
    </p>
</div>

<div class="row g-3">
    {{-- LEFT: 내 정보 --}}
    <div class="col-lg-4">
        <div class="card section-card mb-3">
            <div class="card-header">
                <strong><i class="bi bi-person-vcard"></i> 내 정보</strong>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-4 text-muted">아이디</dt>
                    <dd class="col-8">{{ $user->login_id }}</dd>

                    <dt class="col-4 text-muted">이름</dt>
                    <dd class="col-8">{{ $user->name }}</dd>

                    <dt class="col-4 text-muted">휴대폰</dt>
                    <dd class="col-8">{{ format_phone($user->phone) }}</dd>

                    @if($user->email)
                        <dt class="col-4 text-muted">이메일</dt>
                        <dd class="col-8">{{ $user->email }}</dd>
                    @endif

                    @if($region_name ?? false)
                        <dt class="col-4 text-muted">지역</dt>
                        <dd class="col-8">{{ $region_name }}</dd>
                    @endif

                    @php
                        $prevLogin = session('previous_login_at');
                        $prevLoginText = $prevLogin ? \Carbon\Carbon::parse($prevLogin)->format('Y-m-d H:i') : null;
                    @endphp
                    @if($prevLoginText)
                        <dt class="col-4 text-muted">이전 로그인</dt>
                        <dd class="col-8">{{ $prevLoginText }}</dd>
                    @elseif($user->last_login_at)
                        <dt class="col-4 text-muted">로그인 시각</dt>
                        <dd class="col-8">{{ $user->last_login_at->format('Y-m-d H:i') }}</dd>
                    @endif
                </dl>
            </div>
            <div class="card-footer">
                <a href="{{ route('mypage.profile') }}" class="btn btn-sm btn-outline-navy w-100">
                    <i class="bi bi-pencil-square"></i> 정보/비밀번호 수정
                </a>
            </div>
        </div>

        {{-- 역할별 추가 카드 --}}
        @if($user->role_code === 'distributor' && isset($stock_summary))
            <div class="card section-card mb-3">
                <div class="card-header"><strong><i class="bi bi-box-seam"></i> 내 재고 요약</strong></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-7 text-muted">취급 도서</dt>
                        <dd class="col-5 text-end">{{ number_format($stock_summary['total_books']) }}권</dd>
                        <dt class="col-7 text-muted">총 재고 수량</dt>
                        <dd class="col-5 text-end">{{ number_format($stock_summary['total_qty']) }}</dd>
                        <dt class="col-7 text-muted">안전재고 이하</dt>
                        <dd class="col-5 text-end {{ $stock_summary['low_stock'] > 0 ? 'text-warning fw-bold' : '' }}">
                            {{ number_format($stock_summary['low_stock']) }}
                        </dd>
                    </dl>
                </div>
            </div>
        @endif

        {{-- 영업자: 내 총판 --}}
        @if($user->role_code === 'agent' && isset($my_distributors) && $my_distributors->count())
            <div class="card section-card mb-3">
                <div class="card-header"><strong><i class="bi bi-truck"></i> 내 총판</strong></div>
                <ul class="list-group list-group-flush">
                    @foreach($my_distributors as $d)
                        <li class="list-group-item small">{{ $d->name }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- 학원: 내 거래처(학원) --}}
        @if($user->role_code === 'academy' && isset($my_academies) && $my_academies->count())
            <div class="card section-card mb-3">
                <div class="card-header"><strong><i class="bi bi-building"></i> 내 학원</strong></div>
                <ul class="list-group list-group-flush">
                    @foreach($my_academies as $a)
                        <li class="list-group-item small d-flex justify-content-between">
                            <span>{{ $a->name }}</span>
                            <span class="text-muted">{{ format_phone($a->mobile) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    {{-- RIGHT: 역할별 메인 영역 --}}
    <div class="col-lg-8">
        @if($user->status_code === 'pending')
            <div class="card section-card mb-3">
                <div class="card-body text-center py-5">
                    <i class="bi bi-hourglass-split text-warning" style="font-size:3rem"></i>
                    <h2 class="h5 navy mt-3">승인 대기 중입니다</h2>
                    <p class="text-muted mb-0">관리자 또는 소속 총판이 가입 신청을 확인하면 모든 기능이 활성화됩니다.</p>
                </div>
            </div>
        @endif

        {{-- 영업자: 내 학원 --}}
        @if($user->role_code === 'agent' && isset($my_vendors))
            <div class="card section-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-building"></i> 내 학원 ({{ $my_vendors->count() }})</strong>
                </div>
                <div class="card-body p-0">
                    @if($my_vendors->isEmpty())
                        <div class="empty-state small">
                            <i class="bi bi-building-x"></i>
                            매핑된 학원이 없습니다. 관리자에게 문의해주세요.
                        </div>
                    @else
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr>
                                <th>학원명</th><th>연락처</th><th>상태</th>
                            </tr></thead>
                            <tbody>
                                @foreach($my_vendors as $v)
                                    <tr>
                                        <td class="small">{{ $v->name }}</td>
                                        <td class="small text-muted">{{ format_phone($v->mobile) }}</td>
                                        <td>
                                            @switch($v->status_code)
                                                @case('active')    <span class="badge bg-success">정상</span> @break
                                                @case('suspended') <span class="badge bg-warning text-dark">일시정지</span> @break
                                                @default <span class="badge bg-light text-dark">{{ $v->status_code }}</span>
                                            @endswitch
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endif

        {{-- 학원: 내 담당 영업자 --}}
        @if($user->role_code === 'academy' && isset($my_agents))
            <div class="card section-card mb-3">
                <div class="card-header"><strong><i class="bi bi-person-badge"></i> 담당 영업자 ({{ $my_agents->count() }})</strong></div>
                <div class="card-body p-0">
                    @if($my_agents->isEmpty())
                        <div class="empty-state small">
                            <i class="bi bi-person-x"></i>
                            담당 영업자가 없습니다.
                        </div>
                    @else
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>영업자</th><th>연락처</th><th class="text-end">기본 할인율</th></tr></thead>
                            <tbody>
                                @foreach($my_agents as $a)
                                    <tr>
                                        <td class="small">{{ $a->name }}</td>
                                        <td class="small text-muted">{{ format_phone($a->phone) }}</td>
                                        <td class="text-end small">{{ rtrim(rtrim($a->discount_rate, '0'), '.') }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endif

        {{-- 총판: 내 영업자 --}}
        @if($user->role_code === 'distributor' && isset($my_agents))
            <div class="card section-card mb-3">
                <div class="card-header"><strong><i class="bi bi-person-badge"></i> 내 영업자 ({{ $my_agents->count() }})</strong></div>
                <div class="card-body p-0">
                    @if($my_agents->isEmpty())
                        <div class="empty-state small">
                            <i class="bi bi-person-x"></i>
                            매핑된 영업자가 없습니다.
                        </div>
                    @else
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>이름</th><th>이메일</th></tr></thead>
                            <tbody>
                                @foreach($my_agents as $a)
                                    <tr><td class="small">{{ $a->name }}</td><td class="small text-muted">{{ $a->email }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endif

        {{-- 최근 주문 (모든 역할 공통) --}}
        @if(isset($recent_orders))
            <div class="card section-card mb-3">
                <div class="card-header"><strong><i class="bi bi-receipt"></i> 최근 주문 ({{ $recent_orders->count() }})</strong></div>
                <div class="card-body p-0">
                    @if($recent_orders->isEmpty())
                        <div class="empty-state small">
                            <i class="bi bi-receipt"></i>
                            주문 이력이 없습니다.
                        </div>
                    @else
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>주문번호</th>
                                    <th>거래처</th>
                                    @if($user->role_code !== 'agent')<th>영업자</th>@endif
                                    <th>상태</th>
                                    <th class="text-end">금액</th>
                                    <th>일시</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recent_orders as $o)
                                    <tr>
                                        <td class="small"><code>{{ $o->order_no }}</code></td>
                                        <td class="small">{{ $o->vendor_name }}</td>
                                        @if($user->role_code !== 'agent')<td class="small text-muted">{{ $o->agent_name }}</td>@endif
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
                                        <td class="text-muted small">{{ $o->requested_at ? \Carbon\Carbon::parse($o->requested_at)->format('m-d H:i') : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endif

        {{-- 모바일 앱 안내 --}}
        <div class="card section-card info-banner">
            <div class="card-body">
                <div class="d-flex gap-3 align-items-center">
                    <i class="bi bi-phone navy" style="font-size:2rem"></i>
                    <div>
                        <h5 class="navy mb-1">모바일 앱이 곧 출시됩니다</h5>
                        <p class="text-muted small mb-0">바코드 스캔 주문, 푸시 알림 등 풍부한 기능을 Flutter 앱에서 사용하실 수 있습니다.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
