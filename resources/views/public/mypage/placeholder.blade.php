@extends('public.layouts.app')
@section('title', $title)

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1">
        <i class="bi {{ $icon ?? 'bi-tools' }}"></i> {{ $title }}
    </h1>
    <p class="text-muted small mb-0">
        역할: <span class="badge bg-light text-dark">{{ match($user->role_code) {
            'distributor' => '총판',
            'agent'       => '영업자',
            'academy'     => '학원',
            'admin'       => '관리자',
            default       => $user->role_code,
        } }}</span>
    </p>
</div>

<div class="card section-card">
    <div class="card-body p-5 text-center">
        <i class="bi bi-tools text-muted" style="font-size:3.5rem"></i>
        <h2 class="h5 navy mt-3">기능 준비 중</h2>
        <p class="text-muted mb-4">
            {{ $description ?? '이 기능은 곧 제공됩니다.' }}
        </p>
        <a href="{{ route('mypage') }}" class="btn btn-outline-navy">
            <i class="bi bi-arrow-left"></i> 대시보드로
        </a>
    </div>
</div>
@endsection
