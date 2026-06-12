@extends('admin.layouts.admin')
@section('title', '정산 레코드 #' . $record->id)

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.settlement.records') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 정산 레코드 목록
        </a>
        <h1 class="h4 mb-0 mt-1">
            정산 #{{ $record->id }}
            @if($record->status === 'paid_out')
                <span class="badge bg-success ms-2">지급 완료</span>
            @elseif($record->status === 'computed')
                <span class="badge bg-warning text-dark ms-2">지급 대기</span>
            @else
                <span class="badge bg-secondary ms-2">{{ $record->status }}</span>
            @endif
        </h1>
    </div>
    @if($record->status === 'computed' && $record->agent_payout > 0)
        <form method="POST" action="{{ route('admin.settlement.record_mark_paid', $record->id) }}"
              onsubmit="return confirm('사입자({{ $record->agent?->name }})에게 {{ number_format($record->agent_payout) }}원 지급 처리하시겠습니까?');">
            @csrf
            <button class="btn btn-success btn-sm">
                <i class="bi bi-cash-coin"></i> 사입자 지급 완료 처리
            </button>
        </form>
    @endif
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        {{-- 거래 정보 --}}
        <div class="card mb-3">
            <div class="card-header bg-light"><strong>거래 정보</strong></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <th class="text-muted" style="width:35%;">정산 일시</th>
                            <td>{{ $record->computed_at ? \Carbon\Carbon::parse($record->computed_at)->format('Y-m-d H:i:s') : '-' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">주문</th>
                            <td>
                                <a href="{{ route('admin.orders.show', $record->order_id) }}">
                                    <code>#{{ $record->order_id }}</code>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted">학원</th>
                            <td>{{ $record->vendor?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">담당 사입자</th>
                            <td>{{ $record->agent?->name ?? '-' }}
                                @if($record->agent_business_type !== 'none')
                                    <span class="badge bg-info">{{ \App\Services\TaxService::TYPES[$record->agent_business_type] ?? $record->agent_business_type }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted">수금 총판</th>
                            <td>{{ $record->distributor?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted">PG 거래 ID</th>
                            <td><code>{{ $record->pg_transaction_id ?: '-' }}</code></td>
                        </tr>
                        <tr>
                            <th class="text-muted">분배 비율</th>
                            <td>{{ $record->split_ratio }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 분배 디테일 --}}
        @if($record->breakdown)
            <div class="card">
                <div class="card-header bg-light"><strong>주문 항목별 분배</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>도서</th>
                                <th class="text-end">수량</th>
                                <th class="text-end">정가</th>
                                <th class="text-end">학부모 매출</th>
                                <th class="text-end">사입자 실 마진</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($record->breakdown as $bd)
                                <tr>
                                    <td class="small">{{ $bd['title'] ?? '-' }}</td>
                                    <td class="text-end">{{ $bd['qty'] ?? 0 }}권</td>
                                    <td class="text-end">{{ number_format($bd['gross'] ?? 0) }}원</td>
                                    <td class="text-end">{{ number_format($bd['retail'] ?? 0) }}원</td>
                                    <td class="text-end text-success">{{ number_format($bd['agent_net'] ?? 0) }}원</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-5">
        {{-- 금액 분배 표 --}}
        <div class="card mb-3">
            <div class="card-header bg-light"><strong>금액 분배</strong></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <th class="text-muted">정가 합계</th>
                            <td class="text-end">{{ number_format($record->gross_amount) }}원</td>
                        </tr>
                        <tr class="table-info">
                            <th>학부모 결제 (수금)</th>
                            <td class="text-end fw-bold">{{ number_format($record->parent_paid) }}원</td>
                        </tr>
                        <tr>
                            <th class="text-muted">출판사 매입 (55%)</th>
                            <td class="text-end text-danger">-{{ number_format($record->publisher_cost) }}원</td>
                        </tr>
                        <tr>
                            <th class="text-muted">PG 수수료 (2%)</th>
                            <td class="text-end text-danger">-{{ number_format($record->pg_fee) }}원</td>
                        </tr>
                        <tr>
                            <th class="text-muted">BookSys 중계</th>
                            <td class="text-end text-danger">-{{ number_format($record->booksys_fee) }}원</td>
                        </tr>
                        @if($record->shipping_fee > 0)
                            <tr>
                                <th class="text-muted">배송비</th>
                                <td class="text-end text-danger">-{{ number_format($record->shipping_fee) }}원</td>
                            </tr>
                        @endif
                        <tr>
                            <th class="text-muted">사입자 마진</th>
                            <td class="text-end">-{{ number_format($record->agent_margin) }}원</td>
                        </tr>
                        <tr class="border-top">
                            <th class="navy">총판 순이익</th>
                            <td class="text-end fw-bold navy">{{ number_format($record->dist_net) }}원</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 사입자 정산 --}}
        <div class="card">
            <div class="card-header bg-light"><strong><i class="bi bi-person-badge"></i> 사입자 정산</strong></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <th class="text-muted">사입자 마진 (명목)</th>
                            <td class="text-end">{{ number_format($record->agent_margin) }}원</td>
                        </tr>
                        <tr>
                            <th class="text-muted">학원 도매 우대 차감</th>
                            <td class="text-end text-danger">-{{ number_format($record->academy_bonus) }}원</td>
                        </tr>
                        <tr>
                            <th class="text-muted">사입자 실 마진</th>
                            <td class="text-end">{{ number_format($record->agent_net) }}원</td>
                        </tr>
                        @if($record->agent_withholding_tax > 0)
                            <tr>
                                <th class="text-muted">원천징수 3.3%</th>
                                <td class="text-end text-danger">-{{ number_format($record->agent_withholding_tax) }}원</td>
                            </tr>
                        @endif
                        @if($record->agent_vat > 0)
                            <tr>
                                <th class="text-muted">부가세 10% (별도)</th>
                                <td class="text-end text-success">+{{ number_format($record->agent_vat) }}원</td>
                            </tr>
                        @endif
                        <tr class="table-success">
                            <th>사입자 실수령</th>
                            <td class="text-end fw-bold">{{ number_format($record->agent_payout) }}원</td>
                        </tr>
                    </tbody>
                </table>
                @if($record->paid_out_at)
                    <div class="alert alert-success small mt-2 mb-0">
                        <i class="bi bi-check-circle"></i>
                        {{ \Carbon\Carbon::parse($record->paid_out_at)->format('Y-m-d H:i') }} 지급 완료
                    </div>
                @endif
                @if($record->memo)
                    <div class="border-top mt-2 pt-2 small text-muted" style="white-space:pre-wrap;">{{ $record->memo }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
