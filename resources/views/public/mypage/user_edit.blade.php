@extends('public.layouts.app')
@section('title', '계정 수정 · '.$target->name)
@section('max_width', '800px')

@section('content')
<div class="mb-3">
    <a href="{{ route('my.users.index') }}" class="text-muted small text-decoration-none">
        <i class="bi bi-arrow-left"></i> 사용자관리
    </a>
    <h1 class="h4 navy mb-0 mt-1">
        <i class="bi bi-person-gear"></i> {{ $target->name }}
        @if($target->role_code === 'agent')
            <span class="badge bg-navy align-middle">영업자</span>
        @else
            <span class="badge bg-secondary align-middle">학원</span>
        @endif
    </h1>
</div>

@if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if($errors->any())
    <div class="alert alert-danger py-2 small">
        <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ route('my.users.update', $target->id) }}">
    @csrf
    @method('PUT')

    <div class="card section-card mb-3">
        <div class="card-header"><strong><i class="bi bi-person"></i> 계정 정보</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small text-muted">아이디</label>
                    <input type="text" class="form-control" value="{{ $target->login_id }}" disabled>
                    <small class="text-muted">아이디는 변경할 수 없습니다.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">상태 *</label>
                    <select name="status_code" class="form-select" required>
                        <option value="active"    @selected(old('status_code', $target->status_code) === 'active')>정상</option>
                        <option value="suspended" @selected(old('status_code', $target->status_code) === 'suspended')>일시정지</option>
                    </select>
                    @if($target->status_code === 'pending')
                        <small class="text-danger">승인대기 상태입니다 — 목록에서 승인 처리하세요.</small>
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">이름 *</label>
                    <input type="text" name="name" value="{{ old('name', $target->name) }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">휴대폰 *</label>
                    <input type="tel" name="phone" value="{{ old('phone', $target->phone) }}" class="form-control" placeholder="01012345678" required>
                </div>
                <div class="col-12">
                    <label class="form-label small text-muted">이메일</label>
                    <input type="email" name="email" value="{{ old('email', $target->email) }}" class="form-control">
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="{{ route('my.users.index') }}" class="btn btn-outline-secondary">취소</a>
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> 저장</button>
    </div>
</form>

{{-- 역할별 상세 정보는 각 전용 화면에서 --}}
<div class="card section-card">
    <div class="card-header"><strong><i class="bi bi-link-45deg"></i> 관련 정보</strong></div>
    <div class="card-body py-3">
        @if($target->role_code === 'agent')
            <a href="{{ route('my.agents.edit', $target->id) }}" class="btn btn-sm btn-outline-navy">
                <i class="bi bi-person-badge"></i> 영업자 상세 정보 (지역 · 사업자 · 정산 계좌)
            </a>
        @elseif($vendor)
            <a href="{{ route('my.vendors.show', $vendor->id) }}" class="btn btn-sm btn-outline-navy">
                <i class="bi bi-building"></i> 학원 정보 ({{ $vendor->name }})
            </a>
        @else
            <span class="text-muted small">연결된 학원이 없습니다. 영업자가 학원을 등록·연결하면 여기에 표시됩니다.</span>
        @endif
    </div>
</div>
@endsection
