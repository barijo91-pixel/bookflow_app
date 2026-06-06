@extends('admin.layouts.admin')
@section('title', '거래처 추가')

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.vendors.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 거래처 목록
        </a>
        <h1 class="h4 mb-0 mt-1">거래처 추가</h1>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
@endif

<div class="card section-card">
    <form method="POST" action="{{ route('admin.vendors.store') }}">
        @csrf
        <div class="card-body">
            <h6 class="text-muted mb-3"><i class="bi bi-building"></i> 기본 정보</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small text-muted">거래처명 *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">거래처 구분 *</label>
                    <select name="type_code" class="form-select" required>
                        @foreach($typeOptions as $t)
                            <option value="{{ $t->code }}" @selected(old('type_code', 'academy') === $t->code)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">대표자</label>
                    <input type="text" name="owner_name" class="form-control" value="{{ old('owner_name') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">사업자번호</label>
                    <input type="text" name="business_no" class="form-control" value="{{ old('business_no') }}" placeholder="000-00-00000">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">업태</label>
                    <input type="text" name="biz_type" class="form-control" value="{{ old('biz_type') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">종목</label>
                    <input type="text" name="biz_item" class="form-control" value="{{ old('biz_item') }}">
                </div>
                <div class="col-md-3"></div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">휴대폰</label>
                    <input type="text" name="mobile" class="form-control" value="{{ old('mobile') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">일반전화</label>
                    <input type="text" name="tel" class="form-control" value="{{ old('tel') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">시·도</label>
                    <select id="sido_select" class="form-select">
                        <option value="">선택</option>
                        @foreach($sidos as $sido)
                            <option value="{{ $sido->id }}">{{ $sido->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">시·군·구</label>
                    <select name="region_id" id="sigungu_select" class="form-select">
                        <option value="">선택</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">주소</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">상세주소</label>
                    <input type="text" name="address_detail" class="form-control" value="{{ old('address_detail') }}">
                </div>
            </div>

            <h6 class="text-muted mt-4 mb-3"><i class="bi bi-bank"></i> 정산 계좌</h6>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small text-muted">은행</label>
                    <select name="bank_code" class="form-select">
                        <option value="">선택</option>
                        @foreach($bankOptions as $b)
                            <option value="{{ $b->code }}" @selected(old('bank_code') === $b->code)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label small text-muted">계좌번호</label>
                    <input type="text" name="bank_account" class="form-control" value="{{ old('bank_account') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">예금주</label>
                    <input type="text" name="bank_holder" class="form-control" value="{{ old('bank_holder') }}">
                </div>
            </div>

            <h6 class="text-muted mt-4 mb-3"><i class="bi bi-sticky"></i> 메모</h6>
            <textarea name="memo" class="form-control" rows="3">{{ old('memo') }}</textarea>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <small class="text-muted">담당자/영업자 매핑은 등록 후 상세 페이지에서 진행합니다.</small>
            <div>
                <a href="{{ route('admin.vendors.index') }}" class="btn btn-secondary">취소</a>
                <button class="btn btn-primary"><i class="bi bi-check-lg"></i> 등록</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
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
