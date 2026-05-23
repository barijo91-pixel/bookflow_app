@extends('admin.layouts.admin')
@section('title', '사용자 추가')

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.users.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 사용자 목록
        </a>
        <h1 class="h4 mb-0 mt-1">사용자 추가</h1>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <form method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small text-muted">이메일 (로그인 ID)</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">초기 비밀번호</label>
                    <input type="text" name="password" class="form-control" value="{{ old('password') }}" required minlength="8" maxlength="50">
                    <small class="text-muted">8자 이상, 영문+숫자. 등록 후 사용자에게 별도 전달 (첫 로그인 시 변경 강제됨).</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">이름</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">휴대폰</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">역할</label>
                    <select name="role_code" id="role_code" class="form-select" required>
                        @foreach($roleOptions as $r)
                            <option value="{{ $r->code }}" @selected(old('role_code') === $r->code)>{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4" id="admin_level_wrap" style="display:none">
                    <label class="form-label small text-muted">관리자 권한</label>
                    <select name="admin_level" class="form-select">
                        <option value="staff" @selected(old('admin_level') === 'staff')>일반관리자</option>
                        <option value="super" @selected(old('admin_level') === 'super')>슈퍼관리자</option>
                    </select>
                </div>
                <div class="col-md-4"></div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">시·도</label>
                    <select id="sido_select" class="form-select">
                        <option value="">선택</option>
                        @foreach($sidos as $sido)
                            <option value="{{ $sido->id }}">{{ $sido->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">시·군·구</label>
                    <select name="region_id" id="sigungu_select" class="form-select">
                        <option value="">선택</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">주소</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address') }}">
                </div>
                <div class="col-12">
                    <label class="form-label small text-muted">상세주소</label>
                    <input type="text" name="address_detail" class="form-control" value="{{ old('address_detail') }}">
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between">
            <small class="text-muted">등록 시 자동으로 active 상태가 됩니다.</small>
            <div>
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">취소</a>
                <button class="btn btn-primary"><i class="bi bi-check-lg"></i> 등록</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const role = document.getElementById('role_code');
    const adminWrap = document.getElementById('admin_level_wrap');
    const sync = () => { adminWrap.style.display = (role.value === 'admin') ? '' : 'none'; };
    role.addEventListener('change', sync); sync();

    const sido = document.getElementById('sido_select');
    const sigungu = document.getElementById('sigungu_select');
    sido.addEventListener('change', async () => {
        sigungu.innerHTML = '<option value="">선택</option>';
        if (! sido.value) return;
        const res = await fetch("{{ route('admin.regions.sigungu') }}?sido_id=" + sido.value, {
            headers: {'Accept': 'application/json'}
        });
        const list = await res.json();
        for (const r of list) {
            const o = document.createElement('option');
            o.value = r.id; o.textContent = r.name;
            sigungu.appendChild(o);
        }
    });
})();
</script>
@endpush
