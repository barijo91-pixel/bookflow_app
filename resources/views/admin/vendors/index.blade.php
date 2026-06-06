@extends('admin.layouts.admin')
@section('title', '거래처(학원)')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">거래처(학원) <small class="text-muted fs-6">전체 {{ number_format($vendors->total()) }}곳</small></h1>
    <a href="{{ route('admin.vendors.create') }}" class="btn btn-sm btn-primary">
        <i class="bi bi-building-add"></i> 거래처 추가
    </a>
</div>

<form method="GET" class="card section-card mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">거래처 구분</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($typeOptions as $t)
                        <option value="{{ $t->code }}" @selected($type === $t->code)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">상태</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($statusOptions as $s)
                        <option value="{{ $s->code }}" @selected($status === $s->code)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">검색 (이름/대표자/사업자번호/연락처)</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-search"></i> 조회
                </button>
            </div>
        </div>
    </div>
</form>

<div class="card section-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    <th style="width:60px;">#</th>
                    <th>거래처명</th>
                    <th>대표자</th>
                    <th>사업자번호</th>
                    <th>연락처</th>
                    <th>구분</th>
                    <th>상태</th>
                    <th>등록일</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vendors as $v)
                    <tr>
                        <td>{{ $v->id }}</td>
                        <td>
                            <a href="{{ route('admin.vendors.show', $v) }}" class="text-decoration-none navy fw-bold">
                                {{ $v->name }} <i class="bi bi-chevron-right small"></i>
                            </a>
                        </td>
                        <td>{{ $v->owner_name }}</td>
                        <td class="text-muted small">{{ $v->business_no }}</td>
                        <td class="text-muted small">{{ format_phone($v->mobile ?: $v->tel) }}</td>
                        <td><span class="badge bg-light text-dark">{{ $v->type_code }}</span></td>
                        <td>
                            @switch($v->status_code)
                                @case('active') <span class="badge bg-success">정상</span> @break
                                @case('suspended') <span class="badge bg-warning text-dark">일시정지</span> @break
                                @case('terminated') <span class="badge bg-dark">거래종료</span> @break
                                @default <span class="badge bg-light text-dark">{{ $v->status_code }}</span>
                            @endswitch
                        </td>
                        <td class="text-muted small">{{ optional($v->created_at)->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">데이터가 없습니다.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        {{ $vendors->links() }}
    </div>
</div>
@endsection
