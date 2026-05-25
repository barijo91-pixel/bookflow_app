@extends('public.layouts.app')
@section('title', '소속 영업자')
@section('max_width', '1100px')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-person-badge"></i> 소속 영업자
        <small class="text-muted fs-6">{{ $agents->count() }}명</small>
    </h1>
    <p class="text-muted small mb-0">본 총판 산하 영업자 목록과 활동 현황</p>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>영업자</th>
                    <th>연락처</th>
                    <th>지역</th>
                    <th class="text-end">담당 학원</th>
                    <th class="text-end">주문 누계</th>
                    <th>최근 로그인</th>
                    <th>상태</th>
                </tr>
            </thead>
            <tbody>
                @forelse($agents as $a)
                    <tr>
                        <td class="small">
                            <strong>{{ $a->name }}</strong>
                            <div class="text-muted small"><code>{{ $a->login_id }}</code></div>
                        </td>
                        <td class="small text-muted">
                            <i class="bi bi-phone"></i> {{ $a->phone }}
                            @if($a->email)
                                <div class="text-muted small"><i class="bi bi-envelope"></i> {{ $a->email }}</div>
                            @endif
                        </td>
                        <td class="small text-muted">
                            {{ trim(($a->sido_name ?? '').' '.($a->sigungu_name ?? '')) ?: '-' }}
                        </td>
                        <td class="text-end">
                            <span class="badge bg-light text-dark">{{ $a->vendor_count }}개</span>
                        </td>
                        <td class="text-end small">
                            @if($a->order_count > 0)
                                <strong>{{ $a->order_count }}</strong>건
                                <div class="text-muted small">{{ number_format($a->order_amount) }}원</div>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="small text-muted">
                            {{ $a->last_login_at ? \Carbon\Carbon::parse($a->last_login_at)->format('Y-m-d') : '미접속' }}
                        </td>
                        <td>
                            @switch($a->status_code)
                                @case('active') <span class="badge bg-success">정상</span> @break
                                @case('pending') <span class="badge bg-warning text-dark">대기</span> @break
                                @case('suspended') <span class="badge bg-secondary">정지</span> @break
                                @default <span class="badge bg-light text-dark">{{ $a->status_code }}</span>
                            @endswitch
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-person-badge" style="font-size:2rem"></i>
                            <p class="mb-0 mt-2">소속 영업자가 없습니다.</p>
                            <p class="small">관리자에게 영업자 매핑을 요청해주세요.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
