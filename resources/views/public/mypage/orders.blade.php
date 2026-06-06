@extends('public.layouts.app')
@section('title', $title)

@section('content')
@php
    $statusOptions = [
        'requested'  => ['접수', 'bg-warning text-dark'],
        'confirmed'  => ['영업자 확정', 'bg-info'],
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
        <a href="{{ route('my.orders.index') }}"
           class="btn btn-sm {{ !$status ? 'btn-navy' : 'btn-outline-secondary' }}">
            전체 ({{ $statusCounts->sum() }})
        </a>
        @foreach($statusOptions as $code => [$label, $cls])
            @if($statusCounts->get($code, 0) > 0)
                <a href="{{ route('my.orders.index', array_merge(request()->only(['date_from','date_to','q']), ['status' => $code])) }}"
                   class="btn btn-sm {{ $status === $code ? 'btn-navy' : 'btn-outline-secondary' }}">
                    {{ $label }} ({{ $statusCounts->get($code, 0) }})
                </a>
            @endif
        @endforeach
    </div>
</div>

{{-- 주문일자 + 키워드 검색 --}}
<form method="GET" action="{{ route('my.orders.index') }}" class="card section-card mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">주문일자 From</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">주문일자 To</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">검색 (주문번호 / 학원명)</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="예: BF20260520 또는 학원명">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button class="btn btn-sm btn-navy flex-grow-1"><i class="bi bi-search"></i> 조회</button>
                <a href="{{ route('my.orders.index') }}" class="btn btn-sm btn-outline-secondary" title="초기화">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
            @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        </div>
    </div>
</form>

<div class="card section-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    <th>주문번호</th>
                    <th>학원</th>
                    @if($user->role_code !== 'agent')
                        <th>영업자</th>
                    @endif
                    @if($user->role_code !== 'distributor')
                        <th>총판</th>
                    @endif
                    <th class="text-end">금액</th>
                    <th>상태</th>
                    <th>주문일</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                    <tr class="order-row" style="cursor:pointer" onclick="location.href='{{ route('my.orders.show', $o->id) }}'">
                        <td>
                            <a href="{{ route('my.orders.show', $o->id) }}" class="text-decoration-none navy fw-bold" onclick="event.stopPropagation()">
                                <code>{{ $o->order_no }}</code> <i class="bi bi-chevron-right small"></i>
                            </a>
                        </td>
                        <td class="small">{{ $o->vendor_name ?? '-' }}</td>
                        @if($user->role_code !== 'agent')
                            <td class="small text-muted">{{ $o->agent_name ?? '-' }}</td>
                        @endif
                        @if($user->role_code !== 'distributor')
                            <td class="small text-muted">{{ $o->distributor_name ?? '-' }}</td>
                        @endif
                        <td class="text-end">{{ number_format($o->total_amount) }}원</td>
                        <td>
                            @php $opt = $statusOptions[$o->status_code] ?? [$o->status_code, 'bg-light text-dark']; @endphp
                            <span class="badge {{ $opt[1] }}">{{ $opt[0] }}</span>
                        </td>
                        <td class="small text-muted">
                            {{ $o->requested_at ? \Carbon\Carbon::parse($o->requested_at)->format('Y-m-d H:i') : \Carbon\Carbon::parse($o->created_at)->format('Y-m-d H:i') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 5 + ($user->role_code !== 'agent' ? 1 : 0) + ($user->role_code !== 'distributor' ? 1 : 0) }}"
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
    @if($orders->hasPages())
        <div class="card-footer">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
