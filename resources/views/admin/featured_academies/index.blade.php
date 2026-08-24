@extends('admin.layouts.admin')
@section('title', '대표 이용학원')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">대표 이용학원 <small class="text-muted fs-6">랜딩 페이지 노출</small></h1>
    <a href="{{ url('/') }}#academies" target="_blank" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-box-arrow-up-right"></i> 랜딩에서 보기
    </a>
</div>

<div class="row g-2 mb-3">
    <div class="col-md-3">
        <div class="stat-card py-2">
            <div class="stat-label small">등록</div>
            <div class="stat-value" style="font-size:1.3rem">{{ number_format($counts['total']) }}곳</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card py-2">
            <div class="stat-label small">노출 중</div>
            <div class="stat-value" style="font-size:1.3rem">{{ number_format($counts['active']) }}곳</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card py-2">
            <div class="stat-label small">이미지 있음</div>
            <div class="stat-value" style="font-size:1.3rem">{{ number_format($counts['logo']) }}곳</div>
            <div class="small text-muted">없으면 이름 카드로 표시</div>
        </div>
    </div>
</div>

{{-- 등록 --}}
<div class="card mb-3">
    <div class="card-header"><strong><i class="bi bi-plus-lg"></i> 학원 추가</strong></div>
    <form method="POST" action="{{ route('admin.featured-academies.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">학원명 <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" required maxlength="120" placeholder="예: 이런영어학원">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">시·도</label>
                    <select name="region_id" class="form-select form-select-sm">
                        <option value="">선택 안 함</option>
                        @foreach($regions as $r)
                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">시·군·구</label>
                    <input type="text" name="city" class="form-control form-control-sm" maxlength="60" placeholder="예: 해운대구">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">이미지 <span class="text-muted">(나중에 가능)</span></label>
                    <input type="file" name="logo_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp,.svg">
                </div>
                <div class="col-md-1">
                    <label class="form-label small text-muted mb-1">순서</label>
                    <input type="number" name="sort_order" class="form-control form-control-sm" value="0" min="0">
                </div>
                <div class="col-md-2 d-flex gap-2 align-items-center">
                    <div class="form-check mb-0">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="newActive" checked>
                        <label class="form-check-label small" for="newActive">노출</label>
                    </div>
                    <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-check-lg"></i> 등록</button>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- 목록 --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>등록된 학원 ({{ $rows->count() }})</strong>
        <form method="GET" class="d-flex gap-1">
            <select name="region_id" class="form-select form-select-sm" onchange="this.form.submit()" style="width:auto;">
                <option value="">전체 지역</option>
                @foreach($regions as $r)
                    <option value="{{ $r->id }}" @selected($regionId === (int) $r->id)>{{ $r->name }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:70px;">이미지</th>
                    <th>학원명</th>
                    <th style="width:140px;">시·도</th>
                    <th style="width:120px;">시·군·구</th>
                    <th style="width:80px;">순서</th>
                    <th style="width:80px;">노출</th>
                    <th style="width:180px;" class="text-end">처리</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <form method="POST" action="{{ route('admin.featured-academies.update', $row->id) }}" enctype="multipart/form-data" id="f{{ $row->id }}">
                            @csrf @method('PUT')
                        </form>
                        <td>
                            @if($row->logo_path)
                                <img src="{{ asset('storage/'.$row->logo_path) }}" alt="{{ $row->name }}"
                                     style="width:56px; height:40px; object-fit:contain; background:#f6f7fb; border-radius:4px;">
                            @else
                                <div class="d-flex align-items-center justify-content-center text-muted"
                                     style="width:56px; height:40px; background:#f6f7fb; border-radius:4px;">
                                    <i class="bi bi-image"></i>
                                </div>
                            @endif
                        </td>
                        <td><input type="text" name="name" form="f{{ $row->id }}" value="{{ $row->name }}" class="form-control form-control-sm" required maxlength="120"></td>
                        <td>
                            <select name="region_id" form="f{{ $row->id }}" class="form-select form-select-sm">
                                <option value="">-</option>
                                @foreach($regions as $r)
                                    <option value="{{ $r->id }}" @selected((int) $row->region_id === (int) $r->id)>{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="text" name="city" form="f{{ $row->id }}" value="{{ $row->city }}" class="form-control form-control-sm" maxlength="60"></td>
                        <td><input type="number" name="sort_order" form="f{{ $row->id }}" value="{{ $row->sort_order }}" class="form-control form-control-sm" min="0"></td>
                        <td>
                            <div class="form-check">
                                <input type="checkbox" name="is_active" value="1" form="f{{ $row->id }}" class="form-check-input" @checked($row->is_active)>
                            </div>
                        </td>
                        <td class="text-end text-nowrap">
                            <input type="file" name="logo_file" form="f{{ $row->id }}" class="form-control form-control-sm d-inline-block"
                                   accept=".jpg,.jpeg,.png,.webp,.svg" style="width:120px;" title="이미지 교체">
                            <button class="btn btn-sm btn-primary" form="f{{ $row->id }}" title="저장"><i class="bi bi-check-lg"></i></button>
                            <form method="POST" action="{{ route('admin.featured-academies.destroy', $row->id) }}" class="d-inline"
                                  onsubmit="return confirm('{{ $row->name }} 을(를) 삭제할까요?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="삭제"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-building" style="font-size:2rem"></i>
                            <p class="mb-1 mt-2">등록된 대표 이용학원이 없습니다.</p>
                            <p class="small mb-0">이름만 먼저 등록하고 이미지는 나중에 채워도 됩니다.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-light border small text-muted mt-3 mb-0">
    <i class="bi bi-info-circle"></i>
    랜딩에는 <strong>노출</strong>이 켜진 학원만 나옵니다. 지역탭은 등록된 시·도만 자동으로 생기고,
    이미지가 없으면 학원 이름 카드로 표시됩니다.
</div>
@endsection
