@extends('public.layouts.app')
@section('title', '영업자 수정')
@section('max_width', '720px')

@section('content')
<div class="mb-3">
    <a href="{{ route('my.agents.index') }}" class="text-muted small text-decoration-none">
        <i class="bi bi-arrow-left"></i> 영업자 관리
    </a>
    <h1 class="h4 navy mt-1 mb-1"><i class="bi bi-person-gear"></i> 영업자 정보 수정</h1>
    <p class="text-muted small mb-0">로그인 아이디 <code>{{ $agent->login_id }}</code> · 가입 {{ $agent->approved_at ? \Carbon\Carbon::parse($agent->approved_at)->format('Y-m-d') : '-' }}</p>
</div>

@if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ route('my.agents.update', $agent->id) }}">
    @csrf @method('PUT')

    <div class="card section-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>영업자 계정 정보</strong>
            <span class="small text-muted">로그인 아이디는 변경 불가</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">이름 <span class="text-danger">*</span></label>
                    <input type="text" name="user_name" class="form-control" value="{{ old('user_name', $agent->name) }}" required maxlength="80">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">휴대폰 <span class="text-danger">*</span></label>
                    <input type="text" name="user_phone" class="form-control" value="{{ old('user_phone', $agent->phone) }}" required maxlength="20" inputmode="numeric">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">이메일 <small class="text-muted">(선택)</small></label>
                    <input type="email" name="user_email" class="form-control" value="{{ old('user_email', $agent->email) }}" maxlength="150">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">상태 <span class="text-danger">*</span></label>
                    <select name="status_code" class="form-select">
                        <option value="active" @selected(old('status_code', $agent->status_code) === 'active')>정상</option>
                        <option value="suspended" @selected(old('status_code', $agent->status_code) === 'suspended')>정지</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    {{-- 정산·세무 정보 --}}
    <div class="card section-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>정산·세무 정보</strong>
            <span class="small text-muted">정산금 입금·세금계산서용</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label small text-muted mb-1">사업자 유형</label>
                    <select name="business_type" class="form-select">
                        <option value="none"               @selected(old('business_type', $agent->business_type ?? 'none') === 'none')>비사업자 (N잡·알바) — 3.3% 원천징수</option>
                        <option value="individual_simple"  @selected(old('business_type', $agent->business_type) === 'individual_simple')>개인사업자 (간이과세)</option>
                        <option value="individual_general" @selected(old('business_type', $agent->business_type) === 'individual_general')>개인사업자 (일반과세)</option>
                        <option value="corporate"          @selected(old('business_type', $agent->business_type) === 'corporate')>법인</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">사업자등록번호</label>
                    <input type="text" name="business_no" class="form-control" value="{{ old('business_no', $agent->business_no) }}" maxlength="20" placeholder="000-00-00000">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">상호 (사업자등록증)</label>
                    <input type="text" name="business_name" class="form-control" value="{{ old('business_name', $agent->business_name) }}" maxlength="100">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">정산 은행</label>
                    <select name="bank_code" class="form-select">
                        <option value="">선택</option>
                        @foreach($bankOptions as $b)
                            <option value="{{ $b->code }}" @selected(old('bank_code', $agent->bank_code) === $b->code)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small text-muted mb-1">계좌번호</label>
                    <input type="text" name="bank_account" class="form-control" value="{{ old('bank_account', $agent->bank_account) }}" maxlength="50" placeholder="-없이 숫자만" inputmode="numeric">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">예금주</label>
                    <input type="text" name="bank_holder" class="form-control" value="{{ old('bank_holder', $agent->bank_holder) }}" maxlength="50">
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('my.agents.index') }}" class="btn btn-link text-muted">취소</a>
        <button class="btn btn-primary btn-lg"><i class="bi bi-check-lg"></i> 저장</button>
    </div>
</form>

{{-- 비밀번호 초기화 (별도 폼) --}}
<div class="card section-card mt-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="small">
            <strong><i class="bi bi-key"></i> 비밀번호 초기화</strong>
            <div class="text-muted">임시 비밀번호를 발급합니다. 영업자는 첫 로그인 시 변경해야 합니다.</div>
        </div>
        <form method="POST" action="{{ route('my.agents.reset_password', $agent->id) }}"
              onsubmit="return confirm('{{ $agent->name }} 영업자의 비밀번호를 초기화할까요?')">
            @csrf
            <button class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-repeat"></i> 비밀번호 초기화</button>
        </form>
    </div>
</div>
@endsection
