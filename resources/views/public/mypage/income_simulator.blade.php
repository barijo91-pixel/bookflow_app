@extends('public.layouts.app')
@section('title', '예상 수익보기')

@section('content')
<div class="container py-3">
    <h1 class="h5 mb-3">
        <i class="bi bi-graph-up-arrow"></i> 예상 수익보기
        <small class="text-muted fs-6">내 거래처 기준 예상 수익 계산</small>
    </h1>

    <div class="alert alert-light border small mb-3">
        <i class="bi bi-info-circle"></i>
        학원의 <strong>거래구분(도매/소매)</strong>에 따라 정산 방식이 달라집니다 —
        <strong>도매</strong>는 할인율 기반, <strong>소매</strong>는 학원 소개료 모델.
        본인 사업자 유형({{ \App\Services\TaxService::TYPES[$businessType] }}) · 분배 <strong>{{ $splitRatio }}</strong> 적용.
    </div>

    {{-- 입력 폼 --}}
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="small mb-1">담당 학원 선택 (거래구분 자동 적용)</label>
                    <select name="vendor_id" class="form-select form-select-sm">
                        <option value="">학원 선택 안함 (도매·소매 모두 표시)</option>
                        @foreach($vendors as $v)
                            <option value="{{ $v->id }}" @selected($vendorId == $v->id)>
                                {{ $v->name }} @if(($v->trade_type ?? 'retail') === 'wholesale')(도매 · 할인 {{ (int) $v->discount_rate }}%)@else(소매)@endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="small mb-1">도서 정가</label>
                    <input type="number" name="unit_price" class="form-control form-control-sm" value="{{ $unitPrice }}" min="1000" step="100">
                </div>
                <div class="col-md-2">
                    <label class="small mb-1">수량 (권)</label>
                    <input type="number" name="qty" class="form-control form-control-sm" value="{{ $qty }}" min="1">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary btn-sm w-100"><i class="bi bi-calculator"></i> 계산</button>
                </div>
            </form>
            @if($vendorId)
                <div class="small mt-2 text-success">
                    <i class="bi bi-check-circle"></i>
                    @if($selectedTradeType === 'wholesale')
                        도매 학원 · 적용 할인율 <strong>{{ (int) $discountRate }}%</strong>
                    @else
                        소매 학원 · 학부모 할인율 <strong>{{ (int) $discountRate }}%</strong> (정가의 {{ 100 - (int) $discountRate }}% 판매)
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- 결과 카드 --}}
    <div class="row g-3 mb-3">
        {{-- B2B 학원 도매 — 도매 학원이거나 미선택 시 --}}
        @if(!$vendorId || $selectedTradeType === 'wholesale')
        <div class="{{ $vendorId ? 'col-12' : 'col-md-6' }}">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong><i class="bi bi-building"></i> 학원 도매 (B2B)</strong>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-2">
                        <tbody>
                            <tr>
                                <th class="text-muted" style="width:55%;">정가 합계</th>
                                <td class="text-end">{{ number_format($b2b['gross']) }}원</td>
                            </tr>
                            <tr class="table-info">
                                <th>학원이 결제할 금액</th>
                                <td class="text-end fw-bold">{{ number_format($b2b['academy_paid']) }}원</td>
                            </tr>
                            @if($user->role_code === 'agent')
                                <tr class="border-top">
                                    <th class="text-success">내 마진 (세전)</th>
                                    <td class="text-end fw-bold text-success">{{ number_format($b2b['agent_margin']) }}원</td>
                                </tr>
                                @if($b2bTax['withholding_tax'] > 0)
                                    <tr>
                                        <th class="text-muted">원천징수 3.3%</th>
                                        <td class="text-end text-danger">-{{ number_format($b2bTax['withholding_tax']) }}원</td>
                                    </tr>
                                @endif
                                <tr class="table-success">
                                    <th>최종 실수령</th>
                                    <td class="text-end fw-bold">{{ number_format($b2b['agent_margin'] - $b2bTax['withholding_tax']) }}원</td>
                                </tr>
                            @else
                                <tr class="border-top">
                                    <th class="navy">총판 마진</th>
                                    <td class="text-end fw-bold navy">{{ number_format($b2b['dist_margin']) }}원</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                    @if($user->role_code === 'agent' && $b2b['agent_margin'] > 0)
                        <div class="alert alert-success small mb-0">
                            <strong>권당 수익: {{ number_format(round($b2b['agent_margin'] / max(1,$qty))) }}원</strong>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- B2C 학부모 결제 — 소매 학원이거나 미선택 시 --}}
        @if(!$vendorId || $selectedTradeType === 'retail')
        <div class="{{ $vendorId ? 'col-12' : 'col-md-6' }}">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <strong><i class="bi bi-people"></i> 학부모 결제 (B2C)</strong>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-2">
                        <tbody>
                            <tr>
                                <th class="text-muted" style="width:55%;">학부모 판매가 ({{ round((1 - $b2c['sell_rate']) * 100) }}% 할인)</th>
                                <td class="text-end">{{ number_format($b2c['retail_sale']) }}원</td>
                            </tr>
                            <tr class="table-info">
                                <th>학부모 결제 총액</th>
                                <td class="text-end fw-bold">{{ number_format($b2c['parent_paid']) }}원</td>
                            </tr>
                            <tr>
                                <th class="text-muted">마진풀 (− 총판 매입)</th>
                                <td class="text-end">{{ number_format($b2c['margin_pool']) }}원</td>
                            </tr>
                            <tr>
                                <th class="text-muted">학원 소개료</th>
                                <td class="text-end text-warning">-{{ number_format($b2c['referral_gross']) }}원</td>
                            </tr>
                            @if($user->role_code === 'agent')
                                <tr class="border-top">
                                    <th class="text-success">내 마진 (세전)</th>
                                    <td class="text-end fw-bold text-success">{{ number_format($b2c['agent_net']) }}원</td>
                                </tr>
                                @if($b2cTax['withholding_tax'] > 0)
                                    <tr>
                                        <th class="text-muted">원천징수 3.3%</th>
                                        <td class="text-end text-danger">-{{ number_format($b2cTax['withholding_tax']) }}원</td>
                                    </tr>
                                @endif
                                <tr class="table-success">
                                    <th>최종 실수령</th>
                                    <td class="text-end fw-bold">{{ number_format($b2c['agent_net'] - $b2cTax['withholding_tax']) }}원</td>
                                </tr>
                            @else
                                <tr class="border-top">
                                    <th class="navy">총판 순이익</th>
                                    <td class="text-end fw-bold navy">{{ number_format($b2c['dist_net']) }}원</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- 월 수익 예측 — 선택 학원 거래구분 기준 (미선택 시 도매) --}}
    @php
        $simNet   = ($selectedTradeType === 'retail') ? $b2cTax['net'] : $b2bTax['net'];
        $simLabel = ($selectedTradeType === 'retail') ? '소매(B2C)' : '도매(B2B)';
    @endphp
    @if($user->role_code === 'agent' && $simNet > 0)
        <div class="card">
            <div class="card-header bg-light"><strong><i class="bi bi-calendar-check"></i> 월 수익 예측 ({{ $simLabel }} 기준)</strong></div>
            <div class="card-body">
                <div class="row text-center g-2">
                    @foreach([1 => '한 학원', 3 => '3개 학원', 5 => '5개 학원', 10 => '10개 학원'] as $multiplier => $label)
                        <div class="col-md-3 col-6">
                            <div class="border rounded p-2">
                                <div class="small text-muted">{{ $label }}</div>
                                <div class="h6 mb-0 text-success">{{ number_format($simNet * $multiplier) }}원</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="small text-muted mt-2 mb-0">
                    <i class="bi bi-info-circle"></i> 동일 조건(정가 {{ number_format($unitPrice) }}원 × {{ $qty }}권)을
                    매월 1회씩 거래한다는 가정입니다.
                </p>
            </div>
        </div>
    @endif
</div>
@endsection
