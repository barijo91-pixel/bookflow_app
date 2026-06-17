@extends('admin.layouts.admin')
@section('title', '거래처(학원) 추가')

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.vendors.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 거래처 목록
        </a>
        <h1 class="h4 mb-0 mt-1">거래처(학원) 추가</h1>
        <p class="text-muted small mb-0 mt-1">거래처 정보 + (선택) 로그인 계정 + 담당 영업자를 한 번에 등록합니다.</p>
    </div>
</div>

@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
@endif

<div class="card section-card">
    <form method="POST" action="{{ route('admin.vendors.store') }}">
        @csrf
        <div class="card-body">
            {{-- 1. 기본 정보 --}}
            <h6 class="text-muted mb-3"><i class="bi bi-building"></i> 기본 정보</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small text-muted">거래처명 *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">거래처 구분 *</label>
                    <select name="type_code" class="form-select" required>
                        @foreach($typeOptions as $t)
                            <option value="{{ $t->code }}" @selected(old('type_code', 'academy') === $t->code)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">대표자</label>
                    <input type="text" name="owner_name" class="form-control" value="{{ old('owner_name') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">사업자번호</label>
                    <input type="text" name="business_no" class="form-control" value="{{ old('business_no') }}" placeholder="000-00-00000">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">업태</label>
                    <input type="text" name="biz_type" class="form-control" value="{{ old('biz_type') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">종목</label>
                    <input type="text" name="biz_item" class="form-control" value="{{ old('biz_item') }}">
                </div>
                <div class="col-md-3"></div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">휴대폰</label>
                    <input type="text" name="mobile" class="form-control" value="{{ old('mobile') }}" placeholder="010-0000-0000">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">일반전화</label>
                    <input type="text" name="tel" class="form-control" value="{{ old('tel') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">주소</label>
                    <div class="input-group">
                        <button type="button" class="btn btn-outline-primary" onclick="openAddrSearch()">
                            <i class="bi bi-search"></i> 주소 검색
                        </button>
                        <input type="text" name="address" id="addrInput" class="form-control" value="{{ old('address') }}" placeholder="검색하거나 직접 입력">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">상세주소</label>
                    <input type="text" name="address_detail" id="addrDetailInput" class="form-control" value="{{ old('address_detail') }}" placeholder="동·호수 등">
                </div>
            </div>

            {{-- 2. 학원 로그인 계정 (선택) --}}
            <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                <h6 class="text-muted mb-0"><i class="bi bi-person-badge"></i> 학원 로그인 계정 <span class="badge bg-light text-dark">선택</span></h6>
                <div class="form-check form-switch">
                    <input type="checkbox" name="create_account" value="1" id="createAccount" class="form-check-input"
                           @checked(old('create_account', '1') === '1')>
                    <label for="createAccount" class="form-check-label small">계정 함께 만들기</label>
                </div>
            </div>
            <div id="accountFields">
                <div class="alert alert-info small">
                    <i class="bi bi-info-circle"></i>
                    계정을 만들면 학원이 <strong>즉시 로그인</strong>하여 주문할 수 있습니다 (첫 로그인 시 비밀번호 변경 강제).
                    만들지 않으면 거래처만 등록되고, 계정은 추후 상세 페이지에서 연결할 수 있습니다.
                </div>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small text-muted">로그인 아이디</label>
                        <input type="text" name="user_login_id" class="form-control" value="{{ old('user_login_id') }}"
                               pattern="[a-zA-Z0-9]{6,50}" placeholder="영문+숫자 6~50자" autocapitalize="off" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">이름 (원장님)</label>
                        <input type="text" name="user_name" class="form-control" value="{{ old('user_name') }}" maxlength="80">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">휴대폰</label>
                        <input type="text" name="user_phone" class="form-control" value="{{ old('user_phone') }}" maxlength="20" placeholder="010-0000-0000">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">이메일 (선택)</label>
                        <input type="email" name="user_email" class="form-control" value="{{ old('user_email') }}" maxlength="150">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">초기 비밀번호 (선택 — 비우면 자동 생성)</label>
                        <input type="text" name="user_password" class="form-control" minlength="8" maxlength="50" placeholder="비워두면 8자 자동 생성" autocomplete="off">
                        <small class="text-muted">등록 후 1회만 화면에 표시됩니다. 학원에 안전하게 전달하세요.</small>
                    </div>
                </div>
            </div>

            {{-- 3. 담당 영업자 (선택) --}}
            <h6 class="text-muted mt-4 mb-3"><i class="bi bi-person-workspace"></i> 담당 영업자 <span class="badge bg-light text-dark">선택</span></h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small text-muted">담당 영업자</label>
                    <select name="agent_user_id" class="form-select">
                        <option value="">나중에 지정 (등록 후 상세에서)</option>
                        @foreach($agents as $a)
                            <option value="{{ $a->id }}" @selected(old('agent_user_id') == $a->id)>
                                {{ $a->name }} ({{ $a->login_id }})
                            </option>
                        @endforeach
                    </select>
                    @if($agents->isEmpty())
                        <div class="small text-danger mt-1">활성 영업자가 없습니다. 먼저 영업자 계정을 등록하세요.</div>
                    @endif
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">할인율 (%)</label>
                    <input type="number" name="discount_rate" class="form-control" value="{{ old('discount_rate', 10) }}" min="0" max="100" step="0.5">
                    <small class="text-muted">학원 도매 할인율</small>
                </div>
            </div>

            {{-- 4. 메모 --}}
            <h6 class="text-muted mt-4 mb-3"><i class="bi bi-sticky"></i> 메모</h6>
            <textarea name="memo" class="form-control" rows="3">{{ old('memo') }}</textarea>

            <div class="alert alert-light border small text-muted mt-3 mb-0">
                <i class="bi bi-info-circle"></i>
                수금 계좌는 <strong>총판</strong>이 관리합니다. 학원 정산계좌는 입력하지 않습니다 (학부모 결제는 총판 계좌로 일원화).
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('admin.vendors.index') }}" class="btn btn-secondary">취소</a>
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> 등록</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
<script>
// 주소 검색 — 도로명 주소를 input에 채움
function openAddrSearch() {
    if (typeof daum === 'undefined') { alert('주소 검색 스크립트 로드 실패 — 직접 입력해주세요.'); return; }
    new daum.Postcode({
        oncomplete: function (data) {
            document.getElementById('addrInput').value = data.roadAddress || data.jibunAddress;
            document.getElementById('addrDetailInput')?.focus();
        }
    }).open();
}

// 계정 함께 만들기 토글
(function () {
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
</script>
@endpush
