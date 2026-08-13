@extends('public.layouts.app')
@section('title', '학원 가입')
@section('max_width', '640px')

@section('content')
<div class="card mt-3">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <i class="bi bi-building-add navy" style="font-size:2.5rem"></i>
            <h1 class="h4 navy mt-2 mb-1">학원 가입</h1>
            <p class="text-muted small mb-0">
                담당 영업자 <strong class="navy">{{ $agent->name }}</strong> 님을 통한 가입입니다.
            </p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger py-2 small">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('academy.join.attempt', $token) }}" autocomplete="on">
            @csrf

            <h2 class="h6 navy border-bottom pb-2 mb-3"><i class="bi bi-building"></i> 학원 정보</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-7">
                    <label class="form-label small text-muted">학원명 *</label>
                    <input type="text" name="vendor_name" value="{{ old('vendor_name') }}" class="form-control" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label small text-muted">원장명 *</label>
                    <input type="text" name="owner_name" value="{{ old('owner_name') }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">휴대폰 *</label>
                    <input type="tel" name="phone" value="{{ old('phone') }}" class="form-control" placeholder="01012345678" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">사업자등록번호</label>
                    <input type="text" name="business_no" value="{{ old('business_no') }}" class="form-control" placeholder="숫자만">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">시/도</label>
                    <select id="sidoSelect" class="form-select">
                        <option value="">선택</option>
                        @foreach($sidos as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">시/군/구</label>
                    <select name="region_id" id="sigunguSelect" class="form-select">
                        <option value="">시/도를 먼저 선택하세요</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small text-muted">주소</label>
                    <input type="text" name="address" value="{{ old('address') }}" class="form-control mb-2">
                    <input type="text" name="address_detail" value="{{ old('address_detail') }}" class="form-control" placeholder="상세주소">
                </div>
            </div>

            <h2 class="h6 navy border-bottom pb-2 mb-3"><i class="bi bi-person"></i> 로그인 계정</h2>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label small text-muted">아이디 *</label>
                    <input type="text" name="login_id" value="{{ old('login_id') }}" class="form-control" required>
                    <small class="text-muted">6자 이상 50자 이하, 영문 + 숫자만 가능</small>
                </div>
                <div class="col-12">
                    <label class="form-label small text-muted">이메일 (선택, 알림 수신용)</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">비밀번호 *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">비밀번호 확인 *</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
                <div class="col-12">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        비밀번호는 <strong>8자 이상, 영문 + 숫자 조합</strong>이어야 합니다.
                    </small>
                </div>
            </div>

            <div class="form-check mt-4 mb-3">
                <input class="form-check-input" type="checkbox" name="agree_terms" id="agree" value="1" required>
                <label class="form-check-label small" for="agree">이용약관 및 개인정보처리방침에 동의합니다.</label>
            </div>

            <button class="btn btn-primary w-100 btn-lg">
                <i class="bi bi-check-lg"></i> 가입하기
            </button>
        </form>

        <div class="mt-3 text-center small">
            이미 계정이 있으신가요?
            <a href="{{ route('public.login') }}" class="navy fw-bold">로그인</a>
        </div>
    </div>
</div>

<div class="alert alert-info mt-3 small">
    <strong><i class="bi bi-info-circle"></i> 안내</strong> —
    가입 즉시 로그인해서 교재를 주문하실 수 있습니다.
    학급·학생 등록이나 교재 선정은 담당 영업자가 도와드립니다.
</div>

<script>
// 시/도 → 시/군/구 (가입 화면은 로그인 전이라 공개 조회 API 대신 시도 선택 시 서버 조회)
(function () {
    var sido = document.getElementById('sidoSelect');
    var sigungu = document.getElementById('sigunguSelect');
    if (!sido || !sigungu) return;
    sido.addEventListener('change', async function () {
        sigungu.innerHTML = '<option value="">불러오는 중...</option>';
        if (!this.value) { sigungu.innerHTML = '<option value="">시/도를 먼저 선택하세요</option>'; return; }
        try {
            var res = await fetch('{{ route('public.regions.sigungu') }}?sido_id=' + this.value);
            var rows = await res.json();
            sigungu.innerHTML = '<option value="">선택</option>';
            rows.forEach(function (r) {
                var o = document.createElement('option');
                o.value = r.id; o.textContent = r.name;
                sigungu.appendChild(o);
            });
        } catch (e) {
            sigungu.innerHTML = '<option value="">불러오지 못했습니다</option>';
        }
    });
})();
</script>
@endsection
