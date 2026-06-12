@extends('admin.layouts.admin')
@section('title', '정산 레코드')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">정산 레코드 <small class="text-muted fs-6">PG 결제 완료 시 자동 생성</small></h1>
    <a href="{{ route('admin.settlement.simulator') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-calculator"></i> 시뮬레이터로
    </a>
</div>

{{-- 합계 요약 --}}
<div class="row g-2 mb-3">
    <div class="col-md-3">
        <div class="card"><div class="card-body py-2 px-3">
            <div class="small text-muted">학부모 결제 합계</div>
            <div class="h6 mb-0">{{ number_format($totals->parent_paid) }}원</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-primary"><div class="card-body py-2 px-3">
            <div class="small text-muted">총판 순이익</div>
            <div class="h6 mb-0 navy">{{ number_format($totals->dist_net) }}원</div>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2 px-3">
            <div class="small text-muted">사입자 마진</div>
            <div class="h6 mb-0 text-success">{{ number_format($totals->agent_net) }}원</div>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2 px-3">
            <div class="small text-muted">PG 수수료</div>
            <div class="h6 mb-0 text-danger">{{ number_format($totals->pg_fee) }}원</div>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card"><div class="card-body py-2 px-3">
            <div class="small text-muted">BookSys 중계</div>
            <div class="h6 mb-0">{{ number_format($totals->booksys_fee) }}원</div>
        </div></div>
    </div>
</div>

{{-- 필터 --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="small mb-1">상태</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">전체</option>
                    <option value="computed" @selected($status === 'computed')>지급 대기</option>
                    <option value="paid_out" @selected($status === 'paid_out')>지급 완료</option>
                    <option value="canceled" @selected($status === 'canceled')>취소</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small mb-1">사입자</label>
                <select name="agent_id" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($agents as $a)
                        <option value="{{ $a->id }}" @selected(request('agent_id') == $a->id)>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="small mb-1">정산일 시작</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="small mb-1">정산일 끝</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> 필터 적용</button>
                <a href="{{ route('admin.settlement.records') }}" class="btn btn-outline-secondary btn-sm">초기화</a>
            </div>
        </form>
    </div>
</div>

{{-- 목록 --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>정산일시</th>
                    <th>주문</th>
                    <th>학원</th>
                    <th>사입자</th>
                    <th class="text-end">학부모 결제</th>
                    <th class="text-end">총판 순이익</th>
                    <th class="text-end">사입자 실수령</th>
                    <th>상태</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $r)
                    <tr>
                        <td><code>{{ $r->id }}</code></td>
                        <td class="small">{{ $r->computed_at ? \Carbon\Carbon::parse($r->computed_at)->format('m-d H:i') : '-' }}</td>
                        <td class="small"><code>#{{ $r->order_id }}</code></td>
                        <td class="small">{{ $r->vendor?->name ?? '-' }}</td>
                        <td class="small">{{ $r->agent?->name ?? '-' }}</td>
                        <td class="text-end">{{ number_format($r->parent_paid) }}원</td>
                        <td class="text-end fw-bold navy">{{ number_format($r->dist_net) }}원</td>
                        <td class="text-end text-success">{{ number_format($r->agent_payout) }}원</td>
                        <td>
                            @if($r->status === 'paid_out')
                                <span class="badge bg-success">지급 완료</span>
                            @elseif($r->status === 'computed')
                                <span class="badge bg-warning text-dark">대기</span>
                            @else
                                <span class="badge bg-secondary">{{ $r->status }}</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.settlement.record_show', $r->id) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">정산 레코드가 없습니다. 학부모가 결제하면 자동 생성됩니다.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($records->hasPages())
        <div class="card-footer">{{ $records->links() }}</div>
    @endif
</div>
@endsection
