@extends('public.layouts.app')
@section('title', '정산 내역')

@section('content')
<div class="container py-3">
    <h1 class="h5 mb-3">
        <i class="bi bi-cash-stack"></i>
        @if($user->role_code === 'agent') 내 정산 내역 @else 수금/정산 관리 @endif
    </h1>

    {{-- 누적 통계 카드 — 관리자 화면과 동일한 .stat-card 표준 --}}
    <div class="row g-2 mb-3">
        <div class="col-md-3 col-6">
            <div class="stat-card py-2">
                <div class="stat-label small">정산 건수</div>
                <div class="stat-value" style="font-size:1.3rem">{{ number_format($stats->cnt) }}건</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card py-2">
                <div class="stat-label small">학부모 결제 합계</div>
                <div class="stat-value" style="font-size:1.3rem">{{ number_format($stats->parent_paid_total) }}원</div>
            </div>
        </div>
        @if($user->role_code === 'agent')
            <div class="col-md-3 col-6">
                <div class="stat-card py-2">
                    <div class="stat-label small">미지급 수수료</div>
                    <div class="stat-value text-warning" style="font-size:1.3rem">{{ number_format($stats->pending_total) }}원</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card py-2">
                    <div class="stat-label small">지급 완료</div>
                    <div class="stat-value text-success" style="font-size:1.3rem">{{ number_format($stats->paid_out_total) }}원</div>
                </div>
            </div>
        @else
            <div class="col-md-3 col-6">
                <div class="stat-card py-2">
                    <div class="stat-label small">총판 순이익 합계</div>
                    <div class="stat-value" style="font-size:1.3rem">{{ number_format($stats->dist_net_total) }}원</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card py-2">
                    <div class="stat-label small">사입자 지급 대기</div>
                    <div class="stat-value text-warning" style="font-size:1.3rem">{{ number_format($stats->pending_total) }}원</div>
                </div>
            </div>
        @endif
    </div>

    {{-- 필터 --}}
    <form method="GET" class="d-flex gap-2 mb-3">
        <select name="status" class="form-select form-select-sm" style="max-width:200px;" onchange="this.form.submit()">
            <option value="">전체 상태</option>
            <option value="computed" @selected($status === 'computed')>지급 대기</option>
            <option value="paid_out" @selected($status === 'paid_out')>지급 완료</option>
            <option value="canceled" @selected($status === 'canceled')>취소</option>
        </select>
        @if($status)
            <a href="{{ route('mypage.settlements') }}" class="btn btn-sm btn-outline-secondary">필터 초기화</a>
        @endif
    </form>

    {{-- 정산 목록 --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><x-sort-link field="date" label="정산일시" :sort="$sort" :dir="$dir" /></th>
                        <th>주문</th>
                        <th>학원</th>
                        <th class="text-end"><x-sort-link field="parent" label="학부모 결제" :sort="$sort" :dir="$dir" /></th>
                        @if($user->role_code === 'agent')
                            <th class="text-end"><x-sort-link field="margin" label="실 마진" :sort="$sort" :dir="$dir" /></th>
                            <th class="text-end"><x-sort-link field="net" label="실수령액" :sort="$sort" :dir="$dir" /></th>
                        @else
                            <th class="text-end"><x-sort-link field="dist" label="총판 순이익" :sort="$sort" :dir="$dir" /></th>
                            <th class="text-end"><x-sort-link field="payout" label="사입자 지급" :sort="$sort" :dir="$dir" /></th>
                        @endif
                        <th><x-sort-link field="status" label="상태" :sort="$sort" :dir="$dir" /></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $r)
                        <tr>
                            <td class="small">
                                @if($r->computed_at)
                                    {{ \Carbon\Carbon::parse($r->computed_at)->format('m-d H:i') }}
                                @else - @endif
                            </td>
                            <td class="small"><code>#{{ $r->order_id }}</code></td>
                            <td class="small">{{ $r->vendor?->name ?? '-' }}</td>
                            <td class="text-end">{{ number_format($r->parent_paid) }}원</td>
                            @if($user->role_code === 'agent')
                                <td class="text-end">{{ number_format($r->agent_net) }}원</td>
                                <td class="text-end fw-bold text-success">{{ number_format($r->agent_payout) }}원</td>
                            @else
                                <td class="text-end fw-bold navy">{{ number_format($r->dist_net) }}원</td>
                                <td class="text-end">{{ number_format($r->agent_payout) }}원</td>
                            @endif
                            <td>
                                @if($r->status === 'paid_out')
                                    <span class="badge bg-success">지급 완료</span>
                                @elseif($r->status === 'computed')
                                    <span class="badge bg-warning text-dark">지급 대기</span>
                                @else
                                    <span class="badge bg-secondary">{{ $r->status }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">정산 내역이 없습니다.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($records->hasPages())
            <div class="card-footer">{{ $records->links() }}</div>
        @endif
    </div>

    @if($user->role_code === 'agent')
        <div class="alert alert-info small mt-3 mb-0">
            <i class="bi bi-info-circle"></i>
            <strong>실수령액</strong>은 세무 적용 후 금액입니다.
            현재 사업자 유형: <strong>{{ \App\Services\TaxService::TYPES[$user->business_type ?? 'none'] }}</strong>
            (<a href="{{ route('mypage.profile') }}">변경</a>)
        </div>
    @endif
</div>
@endsection
