@extends('public.layouts.app')
@section('title', '영업자 등록')
@section('max_width', '720px')

@section('content')
<div class="mb-3">
    <a href="{{ route('my.agents.index') }}" class="text-muted small text-decoration-none">
        <i class="bi bi-arrow-left"></i> 영업자 관리
    </a>
    <h1 class="h4 navy mt-1 mb-1"><i class="bi bi-person-plus"></i> 영업자 등록</h1>
    <p class="text-muted small mb-0">영업자 계정을 만들면 본 총판 산하로 자동 매핑됩니다. 첫 로그인 시 비밀번호 변경이 강제됩니다.</p>
</div>

@if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ route('my.agents.store') }}">
    @csrf

    <div class="card section-card mb-3">
        <div class="card-header"><strong>영업자 계정 정보</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">이름 <span class="text-danger">*</span></label>
                    <input type="text" name="user_name" class="form-control" value="{{ old('user_name') }}" required maxlength="80" placeholder="영업자 이름">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">휴대폰 <span class="text-danger">*</span></label>
                    <input type="text" name="user_phone" class="form-control" value="{{ old('user_phone') }}" required maxlength="20" placeholder="010-0000-0000" inputmode="numeric">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">로그인 아이디 <span class="text-danger">*</span></label>
                    <input type="text" name="user_login_id" class="form-control" value="{{ old('user_login_id') }}"
                           required pattern="[a-zA-Z0-9]{6,50}" placeholder="영문+숫자 6~50자" autocapitalize="off" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">이메일 <small class="text-muted">(선택)</small></label>
                    <input type="email" name="user_email" class="form-control" value="{{ old('user_email') }}" maxlength="150">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">활동 지역 — 시도 <small class="text-muted">(선택)</small></label>
                    <select name="region_sido" id="agentSido" class="form-select" onchange="filterAgentSigungu()">
                        <option value="">선택 안함</option>
                        @foreach($sidos as $s)<option value="{{ $s->id }}" @selected(old('region_sido') == $s->id)>{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">시군구</label>
                    <select name="region_id" id="agentSigungu" class="form-select">
                        <option value="">선택 안함</option>
                        @foreach($sigungus as $sg)<option value="{{ $sg->id }}" data-parent="{{ $sg->parent_id }}" @selected(old('region_id') == $sg->id)>{{ $sg->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label small text-muted mb-1">초기 비밀번호 <small class="text-muted">(선택 — 비우면 자동 생성)</small></label>
                    <input type="text" name="user_password" class="form-control" minlength="8" maxlength="50" placeholder="비워두면 8자 자동 생성" autocomplete="off">
                    <div class="form-text small">등록 후 1회만 화면에 표시됩니다. 영업자에게 안전하게 전달하세요.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- 정산·세무 정보 (선택) — 비워두면 영업자 본인이 정보수정에서 보완 --}}
    <div class="card section-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>정산·세무 정보 <small class="text-muted ms-1">(선택)</small></strong>
            <span class="small text-muted">정산금 입금·세금계산서용</span>
        </div>
        <div class="card-body">
            <div class="alert alert-light border small text-muted mb-3">
                <i class="bi bi-info-circle navy"></i>
                총판이 영업자에게 <strong>정산금을 입금</strong>할 계좌·사업자 정보입니다.
                지금 비워두면 <strong>영업자 본인이 로그인 후 정보수정에서</strong> 입력할 수 있습니다.
            </div>
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label small text-muted mb-1">사업자 유형</label>
                    <select name="business_type" class="form-select">
                        <option value="none"               @selected(old('business_type','none') === 'none')>비사업자 (N잡·알바) — 3.3% 원천징수</option>
                        <option value="individual_simple"  @selected(old('business_type') === 'individual_simple')>개인사업자 (간이과세) — 연매출 8천만 미만</option>
                        <option value="individual_general" @selected(old('business_type') === 'individual_general')>개인사업자 (일반과세) — 부가세 10% 별도</option>
                        <option value="corporate"          @selected(old('business_type') === 'corporate')>법인</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">사업자등록번호</label>
                    <input type="text" name="business_no" class="form-control" value="{{ old('business_no') }}" maxlength="20" placeholder="000-00-00000">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">상호 (사업자등록증)</label>
                    <input type="text" name="business_name" class="form-control" value="{{ old('business_name') }}" maxlength="100">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">정산 은행</label>
                    <select name="bank_code" class="form-select">
                        <option value="">선택</option>
                        @foreach($bankOptions as $b)
                            <option value="{{ $b->code }}" @selected(old('bank_code') === $b->code)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small text-muted mb-1">계좌번호</label>
                    <input type="text" name="bank_account" class="form-control" value="{{ old('bank_account') }}" maxlength="50" placeholder="-없이 숫자만" inputmode="numeric">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">예금주</label>
                    <input type="text" name="bank_holder" class="form-control" value="{{ old('bank_holder') }}" maxlength="50">
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-light border small text-muted mb-3">
        <i class="bi bi-info-circle navy"></i>
        영업자 등록 후 <strong>영업자 관리</strong> 목록에서 활동 현황(담당 학원·주문 누계)을 확인할 수 있습니다.
        영업자의 할인율·담당 학원은 영업자 본인이 <strong>학원등록</strong>에서 설정합니다.
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('my.agents.index') }}" class="btn btn-link text-muted">취소</a>
        <button class="btn btn-primary btn-lg">
            <i class="bi bi-check-lg"></i> 영업자 등록
        </button>
    </div>
</form>

<script>
function filterAgentSigungu(){
    var sido = document.getElementById('agentSido').value;
    var sg = document.getElementById('agentSigungu');
    if (!sg) return;
    for (var i = 0; i < sg.options.length; i++) {
        var o = sg.options[i];
        if (!o.value) continue;
        o.hidden = (String(o.dataset.parent) !== String(sido));
    }
    if (sg.selectedOptions[0] && sg.selectedOptions[0].hidden) sg.value = '';
}
document.addEventListener('DOMContentLoaded', filterAgentSigungu);
</script>
@endsection
