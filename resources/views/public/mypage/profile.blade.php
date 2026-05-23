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
    <div class="card-header bg-white"><strong><i class="bi bi-person"></i> 기본 정보</strong></div>
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
        </div>
        <div class="card-footer bg-white text-end">
            <button class="btn btn-navy"><i class="bi bi-save"></i> 저장</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header bg-white"><strong><i class="bi bi-key"></i> 비밀번호 변경</strong></div>
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
        <div class="card-footer bg-white text-end">
            <button class="btn btn-navy"><i class="bi bi-key"></i> 비밀번호 변경</button>
        </div>
    </form>
</div>
@endsection
