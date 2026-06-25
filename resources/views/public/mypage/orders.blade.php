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
            <div class="col-md-{{ $user->role_code !== 'academy' ? '2' : '4' }}">
                <label class="form-label small text-muted mb-1">검색 (주문번호 / 학원명)</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="주문번호·학원명">
            </div>
            @if($user->role_code !== 'academy')
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">거래구분</label>
                <select name="trade_type" class="form-select form-select-sm">
                    <option value="">전체</option>
                    <option value="retail" @selected($tradeType === 'retail')>소매</option>
                    <option value="wholesale" @selected($tradeType === 'wholesale')>도매</option>
                </select>
            </div>
            @endif
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
    {{-- 데스크탑: 표 --}}
    <div class="table-responsive d-none d-md-block">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    <th>주문번호</th>
                    <th>학급</th>
                    @if($user->role_code !== 'academy')<th>학원</th><th>구분</th>@endif
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
                        <td class="small">
                            @if($o->class_name)
                                <span class="badge bg-light text-dark"><i class="bi bi-mortarboard"></i> {{ $o->class_name }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        @if($user->role_code !== 'academy')
                            <td class="small">{{ $o->vendor_name ?? '-' }}</td>
                            <td><span class="badge {{ ($o->trade_type ?? 'retail') === 'wholesale' ? 'bg-secondary' : 'bg-light text-dark' }}">{{ ($o->trade_type ?? 'retail') === 'wholesale' ? '도매' : '소매' }}</span></td>
                        @endif
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
                        <td colspan="{{ 6 + ($user->role_code !== 'agent' ? 1 : 0) + ($user->role_code !== 'distributor' ? 1 : 0) - ($user->role_code === 'academy' ? 1 : 0) + ($user->role_code !== 'academy' ? 1 : 0) }}"
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
                        @if($user->role_code !== 'distributor' && $o->distributor_name){{ $o->distributor_name }} · @endif
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
