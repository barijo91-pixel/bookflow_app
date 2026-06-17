@extends('public.layouts.app')
@section('title', '영업자 등록 완료')
@section('max_width', '720px')

@section('content')
<div class="text-center my-4">
    <div class="display-4 text-success"><i class="bi bi-check-circle-fill"></i></div>
    <h1 class="h4 navy mt-2">영업자 「{{ $createdUser['name'] }}」 등록 완료</h1>
    <p class="text-muted small mb-0">본 총판 산하로 자동 매핑되었습니다.</p>
</div>

<div class="card border-warning mb-3">
    <div class="card-header bg-warning text-dark">
        <strong><i class="bi bi-key"></i> 영업자 계정 초기 비밀번호 (1회만 표시)</strong>
    </div>
    <div class="card-body bg-warning-subtle">
        <p class="small mb-3">
            <i class="bi bi-exclamation-triangle-fill text-danger"></i>
            <strong>이 비밀번호는 지금 한 번만 표시됩니다.</strong>
            영업자에게 안전하게 전달하세요. 첫 로그인 시 변경이 강제됩니다.
        </p>
        <table class="table table-sm mb-0" id="loginInfoTable">
            <tr><th style="width:140px">로그인 아이디</th><td><code>{{ $createdUser['login_id'] }}</code></td></tr>
            <tr><th>이름</th><td>{{ $createdUser['name'] }}</td></tr>
            <tr><th>휴대폰</th><td>{{ format_phone($createdUser['phone']) }}</td></tr>
            <tr><th>초기 비밀번호</th><td><code class="text-danger fs-5">{{ $createdUser['password'] }}</code></td></tr>
            <tr><th>로그인 주소</th><td><strong>{{ url('/login') }}</strong></td></tr>
        </table>
        <button class="btn btn-sm btn-outline-dark mt-3" onclick="copyLogin()">
            <i class="bi bi-clipboard"></i> 로그인 정보 복사
        </button>
    </div>
</div>

<div class="d-flex gap-2">
    <a href="{{ route('my.agents.create') }}" class="btn btn-outline-primary">
        <i class="bi bi-plus"></i> 영업자 추가 등록
    </a>
    <a href="{{ route('my.agents.index') }}" class="btn btn-primary ms-auto">
        소속 영업자 목록으로 <i class="bi bi-arrow-right"></i>
    </a>
</div>

@push('scripts')
<script>
function copyLogin() {
    const text = `[BookSys 영업자 계정]
이름: {{ $createdUser['name'] }}
로그인 아이디: {{ $createdUser['login_id'] }}
초기 비밀번호: {{ $createdUser['password'] }}

* 첫 로그인 시 비밀번호 변경이 필요합니다.
* https://booksys.co.kr/login`;
    navigator.clipboard.writeText(text).then(() => alert('복사되었습니다. 영업자에게 전달하세요.'));
}
</script>
@endpush
@endsection
