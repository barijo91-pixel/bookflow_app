@extends('public.layouts.app')
@section('title', '대표 이용학원')
@section('max_width', '1200px')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-buildings"></i> 대표 이용학원</h1>
    <p class="text-muted small mb-0">
        BookSys 홈페이지에 소개할 학원을 등록합니다. 등록하면 <strong>관리자 확인 후 노출</strong>됩니다.
        학원 이름·간판 이미지를 쓰는 것이므로 <strong>학원 동의를 받고</strong> 올려 주세요.
    </p>
</div>

@if($waiting > 0)
    <div class="alert alert-light border small mb-3">
        <i class="bi bi-hourglass-split"></i>
        노출 대기 <strong>{{ $waiting }}곳</strong> — 관리자가 확인하면 홈페이지에 나옵니다.
    </div>
@endif

{{-- 등록 --}}
<div class="card section-card mb-3">
    <div class="card-header"><strong><i class="bi bi-plus-lg"></i> 학원 추가</strong></div>
    <form method="POST" action="{{ route('my.featured-academies.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">학원명 <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" required maxlength="120"
                           value="{{ old('name') }}" placeholder="예: 이런영어학원">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">시·도</label>
                    <select name="region_id" class="form-select form-select-sm">
                        <option value="">선택 안 함</option>
                        @foreach($regions as $r)
                            <option value="{{ $r->id }}" @selected(old('region_id') == $r->id)>{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">시·군·구</label>
                    <input type="text" name="city" class="form-control form-control-sm" maxlength="60"
                           value="{{ old('city') }}" placeholder="예: 해운대구">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">
                        간판·로고 이미지 <span class="text-muted">(나중에 올려도 됩니다)</span>
                    </label>
                    <input type="file" name="logo_file" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp,.svg">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-primary w-100"><i class="bi bi-check-lg"></i> 등록</button>
                </div>
            </div>
            @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            @error('logo_file')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
    </form>
</div>

{{-- 내가 등록한 학원 --}}
<div class="card section-card">
    <div class="card-header">
        <strong><i class="bi bi-list-ul"></i> 등록한 학원 ({{ $rows->count() }})</strong>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    <th style="width:70px;">이미지</th>
                    <th>학원명</th>
                    <th style="width:150px;">시·도</th>
                    <th style="width:120px;">시·군·구</th>
                    @if($user->role_code !== 'agent')<th style="width:110px;">등록자</th>@endif
                    <th style="width:110px;">노출</th>
                    <th style="width:190px;" class="text-end">처리</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <form method="POST" action="{{ route('my.featured-academies.update', $row->id) }}"
                              enctype="multipart/form-data" id="fa{{ $row->id }}">
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
                        <td>
                            <input type="text" name="name" form="fa{{ $row->id }}" value="{{ $row->name }}"
                                   class="form-control form-control-sm" required maxlength="120">
                        </td>
                        <td>
                            <select name="region_id" form="fa{{ $row->id }}" class="form-select form-select-sm">
                                <option value="">-</option>
                                @foreach($regions as $r)
                                    <option value="{{ $r->id }}" @selected((int) $row->region_id === (int) $r->id)>{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="text" name="city" form="fa{{ $row->id }}" value="{{ $row->city }}"
                                   class="form-control form-control-sm" maxlength="60">
                        </td>
                        @if($user->role_code !== 'agent')
                            <td class="small text-muted">{{ $row->owner_name ?? '-' }}</td>
                        @endif
                        <td>
                            @if($row->is_active)
                                <span class="badge bg-success">노출 중</span>
                            @else
                                <span class="badge bg-warning text-dark">대기</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <input type="file" name="logo_file" form="fa{{ $row->id }}" class="form-control form-control-sm d-inline-block"
                                   accept=".jpg,.jpeg,.png,.webp,.svg" style="width:120px;" title="이미지 올리기/교체">
                            <button class="btn btn-sm btn-primary" form="fa{{ $row->id }}" title="저장"><i class="bi bi-check-lg"></i></button>
                            <form method="POST" action="{{ route('my.featured-academies.destroy', $row->id) }}" class="d-inline"
                                  onsubmit="return confirm('{{ $row->name }} 을(를) 삭제할까요?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="삭제"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $user->role_code === 'agent' ? 6 : 7 }}" class="text-center text-muted py-5">
                            <i class="bi bi-buildings" style="font-size:2rem"></i>
                            <p class="mb-1 mt-2">등록한 학원이 없습니다.</p>
                            <p class="small mb-0">이름만 먼저 등록하고 이미지는 나중에 올려도 됩니다.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-light border small text-muted mt-3 mb-0">
    <i class="bi bi-info-circle"></i>
    노출 여부는 관리자가 정합니다. 이미지가 없으면 학원 이름 카드로 표시되고,
    시·도를 넣으면 홈페이지 <strong>지역탭</strong>에 함께 걸립니다.
</div>
@endsection
