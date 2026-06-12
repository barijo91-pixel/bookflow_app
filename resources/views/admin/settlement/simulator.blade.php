@extends('admin.layouts.admin')
@section('title', '정산 시뮬레이터')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">정산 시뮬레이터 <small class="text-muted fs-6">PG 실연동 전 계산 검증용 — 사업계획서 5·7장</small></h1>
</div>

<div class="alert alert-info small mb-3">
    <i class="bi bi-info-circle"></i>
    <strong>유통 단계별 공급율</strong>:
    출판사 → 총판 <code>55%</code> ·
    총판 → 사입자 <code>63%</code> (도도매 마진 <strong>8%p</strong>) ·
    사입자 → 학원 <code>70%</code> (도매 마진 <strong>7%p</strong>) ·
    B2C 소매 <code>90%</code> (도서정가제 -10%)
</div>

{{-- 입력 폼 --}}
<div class="card mb-4">
    <div class="card-header"><strong><i class="bi bi-sliders"></i> 시뮬레이션 입력</strong></div>
    <div class="card-body">
        <form method="GET" action="{{ route('admin.settlement.simulator') }}" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small mb-1">정가 (원)</label>
                <input type="number" name="unit_price" class="form-control form-control-sm" value="{{ $inputs['unit_price'] }}" min="1000" step="100" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">수량 (권)</label>
                <input type="number" name="qty" class="form-control form-control-sm" value="{{ $inputs['qty'] }}" min="1" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">학원 할인율 (%)</label>
                <input type="number" name="discount_rate" class="form-control form-control-sm" value="{{ $inputs['discount_rate'] }}" min="0" max="50" step="0.5" required>
                <small class="text-muted">계획서 기준 30%</small>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">분배 비율</label>
                <select name="split_ratio" class="form-select form-select-sm">
                    @foreach($splitOptions as $key => $opt)
                        <option value="{{ $key }}" @selected($inputs['split_ratio'] === $key)>{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">사입자 사업자 유형</label>
                <select name="business_type" class="form-select form-select-sm">
                    @foreach($businessTypes as $key => $label)
                        <option value="{{ $key }}" @selected($inputs['business_type'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-calculator"></i> 계산
                </button>
                <a href="{{ route('admin.settlement.simulator') }}" class="btn btn-outline-secondary btn-sm">초기화</a>
            </div>
        </form>
    </div>
</div>

{{-- 핵심 결과 카드 (B2B + B2C) --}}
<div class="row g-3 mb-4">
    {{-- B2B 정산 --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-light">
                <strong><i class="bi bi-building"></i> B2B 학원 도매 정산</strong>
                <span class="badge bg-primary ms-1">{{ $b2b['split_label'] }}</span>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <th class="text-muted" style="width: 50%;">정가 합계</th>
                            <td class="text-end">{{ number_format($b2b['gross']) }}원</td>
                        </tr>
                        <tr class="table-info">
                            <th>학원 결제 (할인율 {{ $b2b['discount_rate'] }}%)</th>
                            <td class="text-end fw-bold">{{ number_format($b2b['academy_paid']) }}원</td>
                        </tr>
                        <tr>
                            <th class="text-muted">출판사 매입가 (55%)</th>
                            <td class="text-end text-danger">-{{ number_format($b2b['publisher_cost']) }}원</td>
                        </tr>
                        <tr>
                            <th class="text-muted">총판 → 사입자 공급 (63%)</th>
                            <td class="text-end">{{ number_format($b2b['dist_to_agent']) }}원</td>
                        </tr>
                        <tr>
                            <th class="text-muted">사입자 → 학원 (70% 기준)</th>
                            <td class="text-end">{{ number_format($b2b['agent_to_academy']) }}원</td>
                        </tr>
                    </tbody>
                </table>
                <hr class="my-2">
                <div class="small">
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">도도매 마진 풀 (8%p)</span>
                        <strong>{{ number_format($b2b['pool_dist_agent']) }}원</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span>총판 마진 ({{ $b2b['split_ratio'] }})</span>
                        <strong class="navy">{{ number_format($b2b['dist_margin']) }}원</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span>사입자 도도매 마진 분배</span>
                        <span>{{ number_format($b2b['agent_split_margin']) }}원</span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span>사입자 영업 마진 (학원 협상)</span>
                        <span class="{{ $b2b['agent_negotiation'] < 0 ? 'text-danger' : '' }}">{{ number_format($b2b['agent_negotiation']) }}원</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-top mt-2">
                        <strong>사입자 총 수수료 (세전)</strong>
                        <strong class="text-success">{{ number_format($b2b['agent_total_margin']) }}원</strong>
                    </div>
                </div>
                {{-- 세무 적용 결과 --}}
                <div class="border-top pt-2 mt-2 small">
                    <div class="text-muted mb-1"><i class="bi bi-cash-coin"></i> 사입자 실수령액 ({{ $businessTypes[$inputs['business_type']] }})</div>
                    @if($b2bAgentTax['withholding_tax'] > 0)
                        <div class="d-flex justify-content-between">
                            <span>원천징수 3.3%</span>
                            <span class="text-danger">-{{ number_format($b2bAgentTax['withholding_tax']) }}원</span>
                        </div>
                    @endif
                    @if($b2bAgentTax['vat'] > 0)
                        <div class="d-flex justify-content-between">
                            <span>부가세 10% (별도 청구)</span>
                            <span class="text-success">+{{ number_format($b2bAgentTax['vat']) }}원</span>
                        </div>
                    @endif
                    <div class="d-flex justify-content-between pt-1 border-top mt-1">
                        <strong>실수령</strong>
                        <strong class="navy">{{ number_format($b2bAgentTax['net']) }}원</strong>
                    </div>
                    @if($b2bAgentTax['note'])
                        <div class="text-muted mt-1" style="font-size:0.75rem;">{{ $b2bAgentTax['note'] }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- B2C 정산 --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-light">
                <strong><i class="bi bi-people"></i> B2C 학부모 결제 정산</strong>
                <span class="badge bg-warning text-dark ms-1">PG 수금 (총판 계좌)</span>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <th class="text-muted" style="width: 50%;">정가 합계</th>
                            <td class="text-end">{{ number_format($b2c['gross']) }}원</td>
                        </tr>
                        <tr>
                            <th class="text-muted">학부모 매출 (90%, 도서정가제)</th>
                            <td class="text-end">{{ number_format($b2c['retail_sale']) }}원</td>
                        </tr>
                        @if($b2c['shipping_fee'] > 0)
                            <tr>
                                <th class="text-muted">배송비 (구매자 부담)</th>
                                <td class="text-end">+{{ number_format($b2c['shipping_fee']) }}원</td>
                            </tr>
                        @endif
                        <tr class="table-info">
                            <th>학부모 결제 총액</th>
                            <td class="text-end fw-bold">{{ number_format($b2c['parent_paid']) }}원</td>
                        </tr>
                        <tr>
                            <th class="text-muted">출판사 매입 (55%)</th>
                            <td class="text-end text-danger">-{{ number_format($b2c['publisher_cost']) }}원</td>
                        </tr>
                        <tr>
                            <th class="text-muted">PG 수수료 (2%)</th>
                            <td class="text-end text-danger">-{{ number_format($b2c['pg_fee']) }}원</td>
                        </tr>
                        <tr>
                            <th class="text-muted">BookSys 중계 (매출 0.5%)</th>
                            <td class="text-end text-danger">-{{ number_format($b2c['booksys_fee']) }}원</td>
                        </tr>
                    </tbody>
                </table>
                <hr class="my-2">
                <div class="small">
                    <div class="d-flex justify-content-between py-1">
                        <span>순 마진 풀</span>
                        <strong>{{ number_format($b2c['net_margin_pool']) }}원</strong>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span>사입자 마진 (분배 {{ $b2c['split_ratio'] }})</span>
                        <span>{{ number_format($b2c['agent_margin']) }}원</span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <span class="text-muted">└ 학원 도매 단가 우대 (30%)</span>
                        <span class="text-muted">-{{ number_format($b2c['academy_bonus']) }}원</span>
                    </div>
                    <div class="d-flex justify-content-between py-1">
                        <strong>사입자 실 마진</strong>
                        <strong class="text-success">{{ number_format($b2c['agent_net']) }}원</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-top mt-2">
                        <strong class="navy">총판 순이익</strong>
                        <strong class="navy">{{ number_format($b2c['dist_net']) }}원</strong>
                    </div>
                </div>
                {{-- 세무 적용 결과 --}}
                <div class="border-top pt-2 mt-2 small">
                    <div class="text-muted mb-1"><i class="bi bi-cash-coin"></i> 사입자 실수령액 ({{ $businessTypes[$inputs['business_type']] }})</div>
                    @if($b2cAgentTax['withholding_tax'] > 0)
                        <div class="d-flex justify-content-between">
                            <span>원천징수 3.3%</span>
                            <span class="text-danger">-{{ number_format($b2cAgentTax['withholding_tax']) }}원</span>
                        </div>
                    @endif
                    @if($b2cAgentTax['vat'] > 0)
                        <div class="d-flex justify-content-between">
                            <span>부가세 10% (별도 청구)</span>
                            <span class="text-success">+{{ number_format($b2cAgentTax['vat']) }}원</span>
                        </div>
                    @endif
                    <div class="d-flex justify-content-between pt-1 border-top mt-1">
                        <strong>실수령</strong>
                        <strong class="navy">{{ number_format($b2cAgentTax['net']) }}원</strong>
                    </div>
                    @if($b2cAgentTax['note'])
                        <div class="text-muted mt-1" style="font-size:0.75rem;">{{ $b2cAgentTax['note'] }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 분배 시나리오 비교 --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <strong><i class="bi bi-bar-chart"></i> 분배 비율 시나리오 비교</strong>
        <small class="text-muted ms-2">동일 조건(정가 {{ number_format($inputs['unit_price']) }}원 × {{ $inputs['qty'] }}권)에서 비율만 달리한 결과</small>
    </div>
    <div class="card-body p-0">
        <table class="table table-bordered table-sm mb-0 align-middle text-end small">
            <thead class="table-light">
                <tr>
                    <th class="text-start">시나리오</th>
                    <th>B2B 총판 마진</th>
                    <th>B2B 사입자 마진</th>
                    <th>B2C 총판 순이익</th>
                    <th>B2C 사입자 실마진</th>
                </tr>
            </thead>
            <tbody>
                @foreach($scenarios as $key => $data)
                    <tr class="{{ $inputs['split_ratio'] === $key ? 'table-info' : '' }}">
                        <th class="text-start">{{ $splitOptions[$key]['label'] }}</th>
                        <td>{{ number_format($data['b2b']['dist_margin']) }}원</td>
                        <td>{{ number_format($data['b2b']['agent_total_margin']) }}원</td>
                        <td>{{ number_format($data['b2c']['dist_net']) }}원</td>
                        <td>{{ number_format($data['b2c']['agent_net']) }}원</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- 정책 안내 --}}
<div class="card">
    <div class="card-header bg-light"><strong><i class="bi bi-lightbulb"></i> 정산 정책 (사업계획서 5·7장)</strong></div>
    <div class="card-body small">
        <div class="row g-3">
            <div class="col-md-6">
                <h6 class="mb-2">B2B 정산 흐름</h6>
                <ol class="mb-0 ps-3">
                    <li>학원이 사입자에게 결제 (할인율 적용)</li>
                    <li>사입자가 총판에 도도매가(63%) 송금</li>
                    <li>총판이 출판사에 매입가(55%) 송금</li>
                    <li>사입자 마진: 도도매 분배 + 학원 영업 마진</li>
                    <li>비사업자는 3.3% 원천징수, 사업자는 부가세 처리</li>
                </ol>
            </div>
            <div class="col-md-6">
                <h6 class="mb-2">B2C 정산 흐름 (학부모 결제)</h6>
                <ol class="mb-0 ps-3">
                    <li>학부모가 총판 PG 계좌로 결제 (소매 90%)</li>
                    <li>PG 수수료(2%) + BookSys 중계(0.5%) 차감</li>
                    <li>총판이 출판사 매입(55%) 처리</li>
                    <li>사입자 마진 정산 (학원 우대분 제외)</li>
                    <li>1권 배송비 4,000원, 2권+ 무료, 클래스 묶음 무료</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection
