@extends('public.layouts.app')
@section('title', '가입 완료')
@section('max_width', '560px')
@section('hide_guest_nav', true)

@section('content')
<div class="card mt-3">
    <div class="card-body p-4 text-center">
        <i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>
        <h1 class="h4 navy mt-3 mb-2">가입이 완료되었습니다</h1>
        <p class="text-muted small mb-4">
            아이디 <strong class="navy">{{ $login_id }}</strong> 로 바로 로그인하실 수 있습니다.
            @if($agent_name)
                <br>담당 영업자는 <strong>{{ $agent_name }}</strong> 님입니다.
            @endif
        </p>
        <a href="{{ route('public.login') }}" class="btn btn-primary btn-lg px-4">
            <i class="bi bi-box-arrow-in-right"></i> 로그인
        </a>
    </div>
</div>
@endsection
