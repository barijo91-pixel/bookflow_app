@extends('admin.layouts.admin')
@section('title', '주문 관리')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">주문 관리 <small class="text-muted fs-6">전체 {{ number_format($orders->total()) }}건</small></h1>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-card py-2">
            <div class="stat-label small">오늘 접수</div>
            <div class="stat-value" style="font-size:1.3rem">{{ number_format($summary['today']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card py-2">
            <div class="stat-label small">대기/접수중</div>
            <div class="stat-value text-warning" style="font-size:1.3rem">{{ number_format($summary['pending']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card py-2">
            <div class="stat-label small">배송 중</div>
            <div class="stat-value" style="font-size:1.3rem">{{ number_format($summary['shipping']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card py-2">
            <div class="stat-label small">누적 금액</div>
            <div class="stat-value" style="font-size:1.1rem">{{ number_format($summary['amount_total']) }}원</div>
        </div>
    </div>
</div>

<form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">상태</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($statusOptions as $s)
                        <option value="{{ $s->code }}" @selected($status === $s->code)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">거래처</label>
                <select name="vendor" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($vendors as $v)
                        <option value="{{ $v->id }}" @selected($vendor == $v->id)>{{ $v->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">영업자</label>
                <select name="agent" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($agents as $a)
                        <option value="{{ $a->id }}" @selected($agent == $a->id)>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">총판</label>
                <select name="dist" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($distributors as $d)
                        <option value="{{ $d->id }}" @selected($dist == $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">검색 (주문번호/거래처)</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
            </div>
        </div>
        <div class="row g-2 align-items-end mt-1">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">주문일자 From</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">주문일자 To</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-grid">
                <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i> 초기화</a>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>주문번호</th>
                    <th>거래처</th>
                    <th>영업자</th>
                    <th>총판</th>
                    <th class="text-end">금액</th>
                    <th>상태</th>
                    <th>접수일시</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                    <tr>
                        <td>{{ $o->id }}</td>
                        <td>
                            <a href="{{ route('admin.orders.show', $o->id) }}" class="text-decoration-none"><code>{{ $o->order_no }}</code></a>
                        </td>
                        <td>{{ $o->vendor_name }}</td>
                        <td class="small">{{ $o->agent_name }}</td>
                        <td class="small">{{ $o->dist_name }}</td>
                        <td class="text-end">{{ number_format($o->total_amount) }}원</td>
                        <td>
                            @switch($o->status_code)
                                @case('requested') <span class="badge bg-warning text-dark">접수</span> @break
                                @case('confirmed') <span class="badge bg-info">영업자확정</span> @break
                                @case('accepted')  <span class="badge bg-primary">총판접수</span> @break
                                @case('shipped')   <span class="badge bg-success">출고</span> @break
                                @case('in_transit')<span class="badge bg-success">배송중</span> @break
                                @case('completed') <span class="badge bg-dark">완료</span> @break
                                @case('canceled')  <span class="badge bg-secondary">취소</span> @break
                                @case('returned')  <span class="badge bg-danger">반품</span> @break
                                @default <span class="badge bg-light text-dark">{{ $o->status_code }}</span>
                            @endswitch
                        </td>
                        <td class="text-muted small">{{ optional($o->requested_at)->format('Y-m-d H:i') ?: \Carbon\Carbon::parse($o->created_at)->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">주문이 없습니다.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $orders->links() }}</div>
</div>
@endsection
