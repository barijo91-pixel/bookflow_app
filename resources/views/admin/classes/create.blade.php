@extends('admin.layouts.admin')
@section('title', '학급 생성')

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.classes.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 학급 목록
        </a>
        <h1 class="h4 mb-0 mt-1">학급 생성</h1>
    </div>
</div>

@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="card border-0 shadow-sm">
    <form method="POST" action="{{ route('admin.classes.store') }}">
        @csrf
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small text-muted">학원 *</label>
                    <select name="vendor_id" class="form-select" required>
                        <option value="">선택</option>
                        @foreach($vendors as $v)
                            <option value="{{ $v->id }}">{{ $v->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">학급명 *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required placeholder="예: 초3 영어 A반">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">학년</label>
                    <select name="grade_code" class="form-select">
                        <option value="">선택</option>
                        @foreach($grades as $g)
                            <option value="{{ $g->code }}">{{ $g->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">시작일</label>
                    <input type="date" name="started_at" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">종료일</label>
                    <input type="date" name="ended_at" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label small text-muted">메모</label>
                    <textarea name="memo" rows="2" class="form-control"></textarea>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between">
            <small class="text-muted">학생/학부모 등록과 교재 매핑은 생성 후 상세 페이지에서 진행합니다.</small>
            <div>
                <a href="{{ route('admin.classes.index') }}" class="btn btn-secondary">취소</a>
                <button class="btn btn-primary"><i class="bi bi-check-lg"></i> 생성</button>
            </div>
        </div>
    </form>
</div>
@endsection
