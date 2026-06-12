@extends('public.layouts.app')
@section('title', '내 정보 수정')
@section('max_width', '640px')

@section('content')
<div class="mb-3">
    <a href="{{ route('mypage') }}" class="text-muted small text-decoration-none">
        <i class="bi bi-arrow-left"></i> 마이페이지로
    </a>
    <h1 class="h4 navy mt-1 mb-0">내 정보 수정</h1>
</div>

@if($errors->any())
    <div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="card mb-3">
    <div class="card-header"><strong><i class="bi bi-person"></i> 기본 정보</strong></div>
    <form method="POST" action="{{ route('mypage.profile.update') }}">
        @csrf @method('PUT')
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label small text-muted">아이디 (변경 불가)</label>
                <input type="text" class="form-control" value="{{ $user->login_id }}" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label small text-muted">이름</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label small text-muted">휴대폰</label>
                <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label small text-muted">이메일 (선택, 알림 수신용)</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" maxlength="150">
            </div>

            {{-- 영업자(사입자) 전용: 사업자 유형 + 정산 계좌 --}}
            @if($user->role_code === 'agent')
                <hr>
                <div class="mb-2">
                    <strong class="navy"><i class="bi bi-receipt-cutoff"></i> 사업자 유형 (세무 정산용)</strong>
                    <div class="small text-muted">정산 시 적용되는 세금 처리 방식이 달라집니다. <a href="{{ route('mypage.tax') }}" class="text-decoration-none">세무 정보 →</a></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label small text-muted">사업자 유형 *</label>
                        <select name="business_type" class="form-select" id="businessType">
                            <option value="none"               @selected(old('business_type', $user->business_type ?? 'none') === 'none')>비사업자 (N잡·알바) — 3.3% 원천징수</option>
                            <option value="individual_simple"  @selected(old('business_type', $user->business_type) === 'individual_simple')>개인사업자 (간이과세) — 연매출 8천만 미만</option>
                            <option value="individual_general" @selected(old('business_type', $user->business_type) === 'individual_general')>개인사업자 (일반과세) — 부가세 10% 별도</option>
                            <option value="corporate"          @selected(old('business_type', $user->business_type) === 'corporate')>법인</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="businessNoWrap">
                        <label class="form-label small text-muted">사업자등록번호</label>
                        <input type="text" name="business_no" value="{{ old('business_no', $user->business_no) }}" class="form-control" maxlength="20" placeholder="000-00-00000">
                    </div>
                    <div class="col-md-6" id="businessNameWrap">
                        <label class="form-label small text-muted">상호 (사업자등록증)</label>
                        <input type="text" name="business_name" value="{{ old('business_name', $user->business_name) }}" class="form-control" maxlength="100">
                    </div>
                </div>
                <hr>
                <div class="mb-2">
                    <strong class="navy"><i class="bi bi-bank"></i> 정산 입금 계좌</strong>
                    <div class="small text-muted">정산 시 이 계좌로 입금됩니다.</div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small text-muted">은행</label>
                        <select name="bank_code" class="form-select">
                            <option value="">선택</option>
                            @foreach($bankOptions as $b)
                                <option value="{{ $b->code }}" @selected(old('bank_code', $user->bank_code) === $b->code)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small text-muted">계좌번호</label>
                        <input type="text" name="bank_account" value="{{ old('bank_account', $user->bank_account) }}" class="form-control" maxlength="50" placeholder="-없이 숫자만">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">예금주</label>
                        <input type="text" name="bank_holder" value="{{ old('bank_holder', $user->bank_holder) }}" class="form-control" maxlength="50">
                    </div>
                </div>
            @endif

            {{-- 총판 전용: 수금 계좌 (학부모 결제 → 총판 PG/계좌로 일원화) --}}
            @if($user->role_code === 'distributor')
                <hr>
                <div class="mb-2">
                    <strong class="navy"><i class="bi bi-cash-coin"></i> 수금 계좌</strong>
                    <div class="small text-muted">학부모 결제 → 이 계좌로 입금. 정산은 본 계좌 기준.</div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small text-muted">은행</label>
                        <select name="bank_code" class="form-select">
                            <option value="">선택</option>
                            @foreach($bankOptions as $b)
                                <option value="{{ $b->code }}" @selected(old('bank_code', $user->bank_code) === $b->code)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small text-muted">계좌번호</label>
                        <input type="text" name="bank_account" value="{{ old('bank_account', $user->bank_account) }}" class="form-control" maxlength="50" placeholder="-없이 숫자만">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">예금주</label>
                        <input type="text" name="bank_holder" value="{{ old('bank_holder', $user->bank_holder) }}" class="form-control" maxlength="50">
                    </div>
                </div>
            @endif
        </div>
        <div class="card-footer text-end">
            <button class="btn btn-navy"><i class="bi bi-save"></i> 저장</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header"><strong><i class="bi bi-key"></i> 비밀번호 변경</strong></div>
    <form method="POST" action="{{ route('mypage.password.update') }}">
        @csrf @method('PUT')
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label small text-muted">현재 비밀번호</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label small text-muted">새 비밀번호</label>
                <input type="password" name="password" class="form-control" minlength="8" required>
                <div class="form-text small">8자 이상, 영문 + 숫자 조합 (특수문자 권장)</div>
            </div>
            <div class="mb-3">
                <label class="form-label small text-muted">새 비밀번호 확인</label>
                <input type="password" name="password_confirmation" class="form-control" minlength="8" required>
            </div>
        </div>
        <div class="card-footer text-end">
            <button class="btn btn-navy"><i class="bi bi-key"></i> 비밀번호 변경</button>
        </div>
    </form>
</div>
@endsection
