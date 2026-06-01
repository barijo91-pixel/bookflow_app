@extends('public.layouts.app')
@section('title', '새 학원 등록')
@section('max_width', '1100px')

@section('content')
<div class="mb-3">
    <a href="{{ route('my.vendors.index') }}" class="text-muted small text-decoration-none">
        <i class="bi bi-arrow-left"></i> 담당 학원
    </a>
    <h1 class="h4 navy mt-1 mb-1"><i class="bi bi-building-add"></i> 새 학원 등록</h1>
    <p class="text-muted small mb-0">학원 정보 + (선택) 학원 계정을 한 번에 등록합니다. 본인이 자동으로 담당 영업자로 매핑됩니다.</p>
</div>

@if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ route('my.vendors.store') }}">
    @csrf

    {{-- 1. 학원 정보 --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>1. 학원 정보</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">학원명 <span class="text-danger">*</span></label>
                    <input type="text" name="vendor_name" class="form-control" value="{{ old('vendor_name') }}" required maxlength="150">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">대표자</label>
                    <input type="text" name="owner_name" class="form-control" value="{{ old('owner_name') }}" maxlength="100">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">사업자번호</label>
                    <input type="text" name="business_no" class="form-control" value="{{ old('business_no') }}" maxlength="20" placeholder="000-00-00000">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">휴대폰</label>
                    <input type="text" name="vendor_mobile" class="form-control" value="{{ old('vendor_mobile') }}" maxlength="20" placeholder="010-0000-0000">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">유선전화</label>
                    <input type="text" name="vendor_tel" class="form-control" value="{{ old('vendor_tel') }}" maxlength="20">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">시도</label>
                    <select name="sido_id" id="sidoSelect" class="form-select">
                        <option value="">선택</option>
                        @foreach($sidos as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">시군구</label>
                    <select name="region_id" id="sigunguSelect" class="form-select" disabled>
                        <option value="">시도 먼저 선택</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label small text-muted mb-1">주소</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address') }}" maxlength="255">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">상세주소</label>
                    <input type="text" name="address_detail" class="form-control" value="{{ old('address_detail') }}" maxlength="255">
                </div>
                <div class="col-md-12">
                    <label class="form-label small text-muted mb-1">메모</label>
                    <textarea name="memo" class="form-control" rows="2" maxlength="2000">{{ old('memo') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. 영업자 할인율 --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>2. 영업자 할인율 (본인 기준)</strong></div>
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">할인율 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" name="discount_rate" step="0.5" min="0" max="100"
                               value="{{ old('discount_rate', $defaultRate) }}" class="form-control text-end" required>
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-md-9">
                    <p class="text-muted small mb-0">
                        신규 학원 기본 할인율은 <strong>10%</strong>입니다. 도서별 개별 할인은 등록 후
                        <strong>할인율 관리</strong>에서 조정 가능합니다.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- 3. 학원 계정 --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>3. 학원 계정 생성 <small class="text-muted ms-2">(선택)</small></strong>
            <div class="form-check form-switch">
                <input type="checkbox" name="create_account" value="1" id="createAccount" class="form-check-input"
                       @checked(old('create_account', '1') === '1')>
                <label for="createAccount" class="form-check-label small">계정 함께 만들기</label>
            </div>
        </div>
        <div class="card-body" id="accountFields">
            <div class="alert alert-info small mb-3">
                <i class="bi bi-info-circle"></i>
                계정을 만들지 않으면 학원이 직접 회원가입 후 관리자가 매핑해야 합니다.
                대신 만들면 즉시 로그인 가능 + 첫 로그인 시 비밀번호 변경 강제.
            </div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">로그인 아이디</label>
                    <input type="text" name="user_login_id" class="form-control" value="{{ old('user_login_id') }}"
                           pattern="[a-zA-Z0-9]{6,50}" placeholder="영문+숫자 6~50자">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">이름 (사장님)</label>
                    <input type="text" name="user_name" class="form-control" value="{{ old('user_name') }}" maxlength="80">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">휴대폰</label>
                    <input type="text" name="user_phone" class="form-control" value="{{ old('user_phone') }}" maxlength="20" placeholder="010-0000-0000">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">이메일 (선택)</label>
                    <input type="email" name="user_email" class="form-control" value="{{ old('user_email') }}" maxlength="150">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted mb-1">초기 비밀번호 (선택 — 비우면 자동 생성)</label>
                    <input type="text" name="user_password" class="form-control" minlength="8" maxlength="50" placeholder="비워두면 8자 자동 생성">
                    <div class="form-text small">등록 후 1회만 화면에 표시됩니다. 학원에 안전하게 전달하세요.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- 4. 결제 구분 --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><strong>4. 결제 구분</strong></div>
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="payment_type" id="payCash" value="cash"
                               @checked(old('payment_type', 'cash') === 'cash')>
                        <label class="form-check-label" for="payCash"><strong>현매</strong> (현금 매출)</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="payment_type" id="payCredit" value="credit"
                               @checked(old('payment_type') === 'credit')>
                        <label class="form-check-label" for="payCredit"><strong>여신</strong> (외상 매출)</label>
                    </div>
                </div>
                <div class="col-md-4" id="creditLimitWrap">
                    <label class="form-label small text-muted mb-1">여신 한도 (원)</label>
                    <input type="number" name="credit_limit" min="0" step="10000"
                           value="{{ old('credit_limit', 0) }}"
                           class="form-control text-end" placeholder="예: 1000000">
                </div>
                <div class="col-md-4 small text-muted">
                    <i class="bi bi-info-circle"></i>
                    현재는 단순 구분 용도. 추후 한도 체크·여신 잔액 관리 기능이 추가될 예정.
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-light border small text-muted mb-3">
        <i class="bi bi-info-circle"></i>
        <strong>안내</strong>: 수금 계좌는 <strong>총판</strong>이 관리합니다. 학원 등록 시 별도 입력 X — 학부모 결제는 총판 PG/입금 계좌로 일원화됩니다.
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('my.vendors.index') }}" class="btn btn-link text-muted">취소</a>
        <button class="btn btn-primary btn-lg">
            <i class="bi bi-check-lg"></i> 학원 등록
        </button>
    </div>
</form>

@push('scripts')
<script>
// 시도 → 시군구 동적 로딩
(function() {
    const sido = document.getElementById('sidoSelect');
    const sg   = document.getElementById('sigunguSelect');
    if (!sido) return;
    sido.addEventListener('change', async () => {
        const v = sido.value;
        if (!v) {
            sg.innerHTML = '<option value="">시도 먼저 선택</option>';
            sg.disabled = true;
            return;
        }
        sg.innerHTML = '<option value="">로딩 중...</option>';
        sg.disabled = true;
        try {
            const res = await fetch(`{{ route('my.regions.sigungu') }}?sido_id=${v}`);
            if (res.ok) {
                const list = await res.json();
                sg.innerHTML = '<option value="">선택</option>' + list.map(r =>
                    `<option value="${r.id}">${r.name}</option>`).join('');
                sg.disabled = false;
            } else {
                sg.innerHTML = '<option value="">불러올 수 없음</option>';
            }
        } catch (e) {
            sg.innerHTML = '<option value="">오류</option>';
        }
    });
})();

// 계정 만들기 토글
(function() {
    const tog = document.getElementById('createAccount');
    const wrap = document.getElementById('accountFields');
    if (!tog) return;
    function sync() {
        wrap.style.opacity = tog.checked ? '1' : '0.4';
        wrap.querySelectorAll('input').forEach(i => i.disabled = !tog.checked);
    }
    tog.addEventListener('change', sync);
    sync();
})();

// 결제 구분 — 여신 선택 시에만 한도 입력 활성화
(function() {
    const cash = document.getElementById('payCash');
    const credit = document.getElementById('payCredit');
    const wrap = document.getElementById('creditLimitWrap');
    if (!cash || !credit || !wrap) return;
    const input = wrap.querySelector('input[name="credit_limit"]');
    function sync() {
        const isCredit = credit.checked;
        wrap.style.opacity = isCredit ? '1' : '0.4';
        if (input) {
            input.disabled = !isCredit;
            if (!isCredit) input.value = 0;
        }
    }
    cash.addEventListener('change', sync);
    credit.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
@endsection
