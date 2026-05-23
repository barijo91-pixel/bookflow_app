@extends('public.layouts.app')
@section('title', '회원가입')
@section('max_width', '560px')

@section('content')
<div class="card mt-3">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <i class="bi bi-person-plus navy" style="font-size:2.5rem"></i>
            <h1 class="h4 navy mt-2 mb-1">회원가입</h1>
            <p class="text-muted small mb-0">총판 / 영업자 / 학원 가입 신청</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger py-2 small">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('public.register.attempt') }}" autocomplete="on">
            @csrf

            <div class="mb-3">
                <label class="form-label small text-muted">역할 *</label>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="role_code" id="role_distributor" value="distributor" @checked(old('role_code') === 'distributor')>
                    <label class="btn btn-outline-secondary" for="role_distributor">총판</label>

                    <input type="radio" class="btn-check" name="role_code" id="role_agent" value="agent" @checked(old('role_code') === 'agent' || ! old('role_code'))>
                    <label class="btn btn-outline-secondary" for="role_agent">영업자</label>

                    <input type="radio" class="btn-check" name="role_code" id="role_academy" value="academy" @checked(old('role_code') === 'academy')>
                    <label class="btn btn-outline-secondary" for="role_academy">학원</label>
                </div>
            </div>

            {{-- 영업자만 총판 선택 --}}
            <div class="mb-3" id="distributor_select" style="display:none;">
                <label class="form-label small text-muted">소속 총판 (선택)</label>
                <select name="parent_user_id" class="form-select">
                    <option value="">선택 안함 — 관리자가 배정 예정</option>
                    @foreach($distributors as $d)
                        <option value="{{ $d->id }}" @selected(old('parent_user_id') == $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
                <small class="text-muted">총판이 승인 후 활성화됩니다.</small>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small text-muted">이름 *</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">휴대폰 *</label>
                    <input type="tel" name="phone" value="{{ old('phone') }}" class="form-control" placeholder="01012345678" required>
                </div>
                <div class="col-12">
                    <label class="form-label small text-muted">이메일 (로그인 ID) *</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">비밀번호 *</label>
                    <input type="password" name="password" class="form-control" minlength="8" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">비밀번호 확인 *</label>
                    <input type="password" name="password_confirmation" class="form-control" minlength="8" required>
                </div>
                <div class="col-12">
                    <div class="form-text small">
                        <i class="bi bi-info-circle"></i>
                        비밀번호는 <strong>8자 이상, 영문 + 숫자 조합</strong>이어야 합니다. (특수문자 권장)
                    </div>
                </div>
            </div>

            <div class="form-check mt-4 mb-3">
                <input type="checkbox" name="agree_terms" id="agree_terms" value="1" class="form-check-input" required>
                <label for="agree_terms" class="form-check-label small">
                    이용약관 및 개인정보처리방침에 동의합니다.
                </label>
            </div>

            <button type="submit" class="btn btn-navy w-100 btn-lg">
                <i class="bi bi-check-lg"></i> 가입 신청
            </button>
        </form>

        <div class="mt-3 text-center small">
            이미 회원이신가요?
            <a href="{{ route('public.login') }}" class="navy fw-bold">로그인</a>
        </div>
    </div>
</div>

<div class="alert alert-info mt-3 small">
    <strong><i class="bi bi-info-circle"></i> 안내</strong> — 가입 후 관리자 또는 소속 총판의 승인이 있어야 로그인이 가능합니다.
</div>
@endsection

@push('scripts')
<script>
(function () {
    const sel = document.getElementById('distributor_select');
    document.querySelectorAll('input[name="role_code"]').forEach(r => {
        r.addEventListener('change', () => sel.style.display = (r.value === 'agent' && r.checked) ? 'block' : sel.style.display);
        r.addEventListener('change', () => {
            sel.style.display = document.querySelector('input[name="role_code"]:checked')?.value === 'agent' ? 'block' : 'none';
        });
    });
    // initial
    sel.style.display = document.querySelector('input[name="role_code"]:checked')?.value === 'agent' ? 'block' : 'none';
})();
</script>
@endpush
