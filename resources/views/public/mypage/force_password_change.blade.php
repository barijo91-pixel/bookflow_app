@extends('public.layouts.app')
@section('title', '비밀번호 변경 필요')
@section('max_width', '480px')

@section('content')
<div class="card mt-3">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <i class="bi bi-shield-lock navy" style="font-size:2.5rem"></i>
            <h1 class="h5 navy mt-2 mb-1">비밀번호 변경이 필요합니다</h1>
            <p class="text-muted small mb-0">
                보안을 위해 비밀번호를 변경한 뒤 서비스 이용이 가능합니다.
            </p>
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

        <form method="POST" action="{{ route('mypage.force_password_change.submit') }}" autocomplete="off">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label small text-muted">현재 비밀번호 *</label>
                <input type="password" name="current_password" class="form-control" required autofocus>
            </div>

            <div class="mb-3">
                <label class="form-label small text-muted">새 비밀번호 *</label>
                <input type="password" name="password" class="form-control" minlength="8" required>
                <div class="form-text small">8자 이상, 영문 + 숫자 조합 (특수문자 권장)</div>
            </div>

            <div class="mb-4">
                <label class="form-label small text-muted">새 비밀번호 확인 *</label>
                <input type="password" name="password_confirmation" class="form-control" minlength="8" required>
            </div>

            <button type="submit" class="btn btn-navy w-100 btn-lg">
                <i class="bi bi-check-lg"></i> 변경하고 계속
            </button>
        </form>

        <div class="mt-3 text-center small text-muted">
            <a href="{{ route('public.logout') }}" class="text-muted"
               onclick="event.preventDefault(); document.getElementById('lo').submit();">
                <i class="bi bi-box-arrow-right"></i> 로그아웃
            </a>
            <form id="lo" method="POST" action="{{ route('public.logout') }}" class="d-none">
                @csrf
            </form>
        </div>
    </div>
</div>
@endsection
