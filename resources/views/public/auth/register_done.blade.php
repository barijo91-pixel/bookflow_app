@extends('public.layouts.app')
@section('title', '가입 신청 완료')
@section('max_width', '500px')

@section('content')
<div class="card mt-4">
    <div class="card-body p-5 text-center">
        <i class="bi bi-check-circle text-success" style="font-size:4rem"></i>
        <h1 class="h4 navy mt-3">가입 신청이 접수되었습니다</h1>
        <p class="text-muted mb-4">아이디: <strong>{{ $login_id }}</strong></p>

        <div class="alert alert-info text-start small">
            <strong>다음 단계</strong>
            <ol class="mb-0 mt-2 ps-3">
                <li>관리자/소속 총판이 가입 신청을 확인합니다.</li>
                <li>승인되면 별도 안내가 발송됩니다.</li>
                <li>승인 후 위 아이디로 로그인이 가능합니다.</li>
            </ol>
        </div>

        <a href="{{ route('home') }}" class="btn btn-outline-navy">
            <i class="bi bi-house"></i> 홈으로
        </a>
        <a href="{{ route('public.login') }}" class="btn btn-navy">
            <i class="bi bi-box-arrow-in-right"></i> 로그인 화면
        </a>
    </div>
</div>
@endsection
