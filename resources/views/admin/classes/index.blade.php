@extends('admin.layouts.admin')
@section('title', '학급 / B2C')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">학급 관리 <small class="text-muted fs-6">전체 {{ number_format($classes->total()) }}개</small></h1>
    <a href="{{ route('admin.classes.create') }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg"></i> 학급 생성
    </a>
</div>

<form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">학원</label>
                <select name="vendor" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($vendors as $v)
                        <option value="{{ $v->id }}" @selected($vendor == $v->id)>{{ $v->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">상태</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">전체</option>
                    <option value="active" @selected($status === 'active')>운영중</option>
                    <option value="closed" @selected($status === 'closed')>종료</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">검색 (학급명/학원명)</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>학원</th>
                    <th>학급명</th>
                    <th>학년</th>
                    <th class="text-end">학생수</th>
                    <th>기간</th>
                    <th>상태</th>
                </tr>
            </thead>
            <tbody>
                @forelse($classes as $c)
                    <tr>
                        <td>{{ $c->id }}</td>
                        <td>{{ $c->vendor_name }}</td>
                        <td><a href="{{ route('admin.classes.show', $c->id) }}" class="text-decoration-none">{{ $c->name }}</a></td>
                        <td><span class="badge bg-light text-dark">{{ $c->grade_code }}</span></td>
                        <td class="text-end">{{ number_format($c->student_count) }}</td>
                        <td class="text-muted small">
                            {{ $c->started_at }} @if($c->ended_at)~ {{ $c->ended_at }}@endif
                        </td>
                        <td>
                            @if($c->status === 'active')
                                <span class="badge bg-success">운영중</span>
                            @else
                                <span class="badge bg-secondary">종료</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">등록된 학급이 없습니다.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $classes->links() }}</div>
</div>
@endsection
