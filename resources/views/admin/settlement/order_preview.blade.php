@extends('admin.layouts.admin')
@section('title', '주문 정산 미리보기')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">주문 #{{ $order->id }} 정산 미리보기
        <small class="text-muted fs-6">{{ $order->vendor?->name ?? '-' }} · 분배 {{ $splitRatio }}</small>
    </h1>
    <div>
        <a href="{{ route('admin.orders.show', $order->id) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> 주문 상세
        </a>
    </div>
</div>

<div class="alert alert-warning small mb-3">
    <i class="bi bi-info-circle"></i>
    이 화면은 <strong>PG 실연동 전 계산 검증용</strong>입니다. 실제 정산은 PG 결제 완료 후 자동 분배됩니다.
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted small">정가 합계</div>
                <div class="h5 mb-0">{{ number_format($totalB2b['gross']) }}원</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <div class="text-muted small">학원 결제 금액</div>
                <div class="h5 mb-0 text-info">{{ number_format($totalB2b['academy_paid']) }}원</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted small">총판 마진</div>
                <div class="h5 mb-0 navy">{{ number_format($totalB2b['dist_margin']) }}원</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <div class="text-muted small">사입자 마진 (세전)</div>
                <div class="h5 mb-0 text-success">{{ number_format($totalB2b['agent_margin']) }}원</div>
            </div>
        </div>
    </div>
</div>

{{-- 주문 항목별 분배 --}}
<div class="card mb-3">
    <div class="card-header bg-light"><strong>주문 항목별 정산</strong></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>도서</th>
                    <th class="text-end">정가</th>
                    <th class="text-end">수량</th>
                    <th class="text-end">학원 결제</th>
                    <th class="text-end">출판사 매입</th>
                    <th class="text-end">총판 마진</th>
                    <th class="text-end">사입자 마진</th>
                </tr>
            </thead>
            <tbody>
                @foreach($itemBreakdown as $row)
                    @php $b = $row['b2b']; $it = $row['item']; @endphp
                    <tr>
                        <td>{{ $it->book?->title ?? '-' }}</td>
                        <td class="text-end">{{ number_format($it->unit_price) }}원</td>
                        <td class="text-end">{{ $it->qty }}권</td>
                        <td class="text-end">{{ number_format($b['academy_paid']) }}원</td>
                        <td class="text-end text-danger">-{{ number_format($b['publisher_cost']) }}원</td>
                        <td class="text-end navy">{{ number_format($b['dist_margin']) }}원</td>
                        <td class="text-end text-success">{{ number_format($b['agent_margin']) }}원</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="3" class="text-end">합계</th>
                    <th class="text-end">{{ number_format($totalB2b['academy_paid']) }}원</th>
                    <th class="text-end text-danger">-{{ number_format($totalB2b['publisher_cost']) }}원</th>
                    <th class="text-end navy">{{ number_format($totalB2b['dist_margin']) }}원</th>
                    <th class="text-end text-success">{{ number_format($totalB2b['agent_margin']) }}원</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- 사입자 실수령 --}}
<div class="card">
    <div class="card-header bg-light"><strong><i class="bi bi-cash-coin"></i> 사입자 정산 (세무 적용)</strong></div>
    <div class="card-body">
        <table class="table table-sm mb-0">
            <tbody>
                <tr>
                    <th class="text-muted" style="width: 50%;">사업자 유형</th>
                    <td><span class="badge bg-secondary">{{ \App\Services\TaxService::TYPES[$businessType] }}</span></td>
                </tr>
                <tr>
                    <th class="text-muted">명목 수수료 (세전)</th>
                    <td class="text-end">{{ number_format($agentTax['gross']) }}원</td>
                </tr>
                @if($agentTax['withholding_tax'] > 0)
                    <tr>
                        <th class="text-muted">원천징수 3.3%</th>
                        <td class="text-end text-danger">-{{ number_format($agentTax['withholding_tax']) }}원</td>
                    </tr>
                @endif
                @if($agentTax['vat'] > 0)
                    <tr>
                        <th class="text-muted">부가세 10% (별도 청구)</th>
                        <td class="text-end text-success">+{{ number_format($agentTax['vat']) }}원</td>
                    </tr>
                @endif
                <tr class="table-info">
                    <th>최종 실수령</th>
                    <td class="text-end fw-bold navy">{{ number_format($agentTax['net']) }}원</td>
                </tr>
            </tbody>
        </table>
        @if(!empty($agentTax['note']))
            <div class="alert alert-light border small mt-2 mb-0">
                <i class="bi bi-info-circle"></i> {{ $agentTax['note'] }}
            </div>
        @endif
    </div>
</div>
@endsection
