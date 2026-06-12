@extends('public.layouts.app')
@section('title', '세무 정보')
@section('max_width', '1100px')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-receipt-cutoff"></i> 세무 정보</h1>
    <p class="text-muted small mb-0">사입자(영업자) 사업자 유형에 따른 정산 안내 — 계획서 8-A장 기반</p>
</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif

<div class="row g-3">
    {{-- LEFT: 현재 상태 + 단계 안내 --}}
    <div class="col-lg-7">
        {{-- 현재 사업자 유형 --}}
        <div class="card section-card mb-3">
            <div class="card-header"><strong><i class="bi bi-person-vcard"></i> 현재 사업자 유형</strong></div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h3 class="h5 navy mb-2">{{ $types[$user->business_type ?? 'none'] }}</h3>
                        @if($user->business_no)
                            <div class="small text-muted">사업자번호: <code>{{ $user->business_no }}</code></div>
                        @endif
                        @if($user->business_name)
                            <div class="small text-muted">상호: {{ $user->business_name }}</div>
                        @endif
                    </div>
                    <a href="{{ route('mypage.profile') }}" class="btn btn-sm btn-outline-navy">
                        <i class="bi bi-pencil"></i> 변경
                    </a>
                </div>
            </div>
        </div>

        {{-- 누적 수수료 + 단계 --}}
        <div class="card section-card mb-3">
            <div class="card-header"><strong><i class="bi bi-graph-up"></i> {{ now()->year }}년 누적 수수료</strong></div>
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-md-5">
                        <div class="small text-muted">예상 수수료 (당해년도)</div>
                        <div class="h3 navy mb-0">{{ number_format($estimatedCommission) }}<span class="small text-muted ms-1">원</span></div>
                        <div class="small text-muted mt-1">※ PG 정산 시스템 도입 전 임시 추정치</div>
                    </div>
                    <div class="col-md-7">
                        <div class="alert alert-{{ $stageInfo['alert_level'] }} py-2 mb-0">
                            <strong><i class="bi bi-info-circle"></i> {{ $stageInfo['label'] }}</strong>
                            <div class="small mt-1">{{ $stageInfo['recommendation'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 정산 시뮬레이션 --}}
        <div class="card section-card mb-3">
            <div class="card-header"><strong><i class="bi bi-calculator"></i> 예상 정산 (현재 사업자 유형 기준)</strong></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">명목 수수료</dt>
                    <dd class="col-7 text-end">{{ number_format($taxCalc['gross']) }}원</dd>

                    @if($taxCalc['withholding_tax'] > 0)
                        <dt class="col-5 text-muted">3.3% 원천징수</dt>
                        <dd class="col-7 text-end text-danger">-{{ number_format($taxCalc['withholding_tax']) }}원</dd>
                    @endif

                    @if($taxCalc['vat'] > 0)
                        <dt class="col-5 text-muted">부가세 10% (별도 수령)</dt>
                        <dd class="col-7 text-end text-success">+{{ number_format($taxCalc['vat']) }}원</dd>
                    @endif

                    <dt class="col-5 fw-bold mt-2 pt-2 border-top">실수령액</dt>
                    <dd class="col-7 text-end fw-bold mt-2 pt-2 border-top navy h5 mb-0">{{ number_format($taxCalc['net']) }}원</dd>
                </dl>
                <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> {{ $taxCalc['note'] }}</div>
            </div>
        </div>
    </div>

    {{-- RIGHT: 사업자 유형 비교 + 단계 가이드 --}}
    <div class="col-lg-5">
        {{-- 유형 비교표 --}}
        <div class="card section-card mb-3">
            <div class="card-header"><strong><i class="bi bi-list-check"></i> 사업자 유형 비교</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>유형</th><th>적용 조건</th><th>세금</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="small"><strong>비사업자</strong></td>
                            <td class="small">N잡·알바<br>(사업자등록 없음)</td>
                            <td class="small text-danger">3.3% 원천징수</td>
                        </tr>
                        <tr>
                            <td class="small"><strong>간이과세</strong></td>
                            <td class="small">개인사업자<br>연매출 8천 미만</td>
                            <td class="small">부가세 간이<br>연 1회 신고</td>
                        </tr>
                        <tr>
                            <td class="small"><strong>일반과세</strong></td>
                            <td class="small">개인사업자<br>연매출 8천 이상</td>
                            <td class="small">부가세 10% 별도<br>연 2회 신고</td>
                        </tr>
                        <tr>
                            <td class="small"><strong>법인</strong></td>
                            <td class="small">법인 등록</td>
                            <td class="small">법인세 + 부가세</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 단계별 가이드 --}}
        <div class="card section-card">
            <div class="card-header"><strong><i class="bi bi-signpost-split"></i> 연수입 단계별 가이드</strong></div>
            <div class="card-body small">
                <ol class="mb-0 ps-3">
                    <li class="mb-2">
                        <strong class="navy">3,000만원 이상</strong><br>
                        개인사업자 등록 검토. 차량·통신비 등 비용 처리 시작.
                    </li>
                    <li class="mb-2">
                        <strong class="navy">5,000만원 이상</strong><br>
                        세무사 고용 또는 기장 위탁. 종합소득세 환급 가능.
                    </li>
                    <li class="mb-2">
                        <strong class="navy">8,000만원 이상</strong><br>
                        일반과세자 전환 의무. 부가세 10% 별도 징수·신고.
                    </li>
                    <li class="mb-0">
                        <strong class="navy">1억 이상</strong><br>
                        법인 전환 검토. 대표이사 급여 구조로 소득세 절감.
                    </li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-light border mt-3 small text-muted mb-0">
    <i class="bi bi-info-circle"></i>
    <strong>안내</strong>: 위 내용은 일반적인 세무 정보입니다. 개별 상황에 따라 다를 수 있으므로 세무사와 반드시 확인하시기 바랍니다.
    실제 정산은 PG 시스템 도입 후 거래별 자동 계산됩니다.
</div>
@endsection
