@extends('public.layouts.app')
@section('title', '로그인')
@section('max_width', '440px')

@section('content')
<div class="card mt-4">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <i class="bi bi-box-arrow-in-right navy" style="font-size:2.5rem"></i>
            <h1 class="h4 navy mt-2 mb-1">BookFlow 로그인</h1>
            <p class="text-muted small mb-0">총판 · 영업자 · 학원 통합 로그인</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger py-2 small mb-3">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('public.login.attempt') }}" autocomplete="on">
            @csrf
            <div class="mb-3">
                <label class="form-label small text-muted">이메일</label>
                <input type="email" name="email" value="{{ old('email') }}"
                       class="form-control form-control-lg" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label small text-muted">비밀번호</label>
                <input type="password" name="password" class="form-control form-control-lg" required>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="remember" id="remember" class="form-check-input" value="1" checked>
                <label for="remember" class="form-check-label small">로그인 유지 (30일)</label>
            </div>
            <button type="submit" class="btn btn-navy w-100 btn-lg">
                <i class="bi bi-box-arrow-in-right"></i> 로그인
            </button>
        </form>

        <div class="mt-3 text-center small">
            아직 회원이 아니신가요?
            <a href="{{ route('public.register') }}" class="navy fw-bold">회원가입</a>
        </div>
        <hr>
        <div class="text-center small text-muted">
            관리자이신가요? <a href="{{ route('admin.login') }}">관리자 콘솔로 이동</a>
        </div>
    </div>
</div>
@endsection
