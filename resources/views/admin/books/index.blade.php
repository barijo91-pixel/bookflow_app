@extends('admin.layouts.admin')
@section('title', '도서')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">도서 마스터 <small class="text-muted fs-6">전체 {{ number_format($books->total()) }}권</small></h1>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.books.import.show') }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-file-earmark-excel"></i> 엑셀 일괄 등록
        </a>
        <a href="{{ route('admin.books.create') }}" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg"></i> 도서 등록
        </a>
    </div>
</div>

<form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">상태</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($statusOptions as $s)
                        <option value="{{ $s->code }}" @selected($status === $s->code)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">학교</label>
                <select name="school" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($schoolOptions as $s)
                        <option value="{{ $s->code }}" @selected($school === $s->code)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">과목</label>
                <select name="subject" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($subjectOptions as $s)
                        <option value="{{ $s->code }}" @selected($subject === $s->code)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">출판사</label>
                <select name="publisher" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($publisherOptions as $p)
                        <option value="{{ $p->id }}" @selected($publisher == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">검색 (제목/ISBN/저자/시리즈)</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:60px;">#</th>
                    <th>표지</th>
                    <th>제목</th>
                    <th>ISBN</th>
                    <th>출판사</th>
                    <th>학교/과목</th>
                    <th class="text-end">정가</th>
                    <th>상태</th>
                </tr>
            </thead>
            <tbody>
                @forelse($books as $b)
                    <tr>
                        <td>{{ $b->id }}</td>
                        <td style="width:50px;">
                            @if($b->cover_path)
                                <img src="{{ str_starts_with($b->cover_path, 'http') ? $b->cover_path : asset('storage/'.$b->cover_path) }}"
                                     alt="" style="height:40px;border-radius:3px">
                            @else
                                <div class="text-muted" style="font-size:1.4rem"><i class="bi bi-book"></i></div>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.books.show', $b) }}" class="text-decoration-none">{{ $b->title }}</a>
                            @if($b->subtitle)<div class="text-muted small">{{ $b->subtitle }}</div>@endif
                            @if($b->series_name)<div class="text-muted small">시리즈: {{ $b->series_name }}</div>@endif
                        </td>
                        <td class="text-muted small"><code>{{ $b->isbn }}</code></td>
                        <td>{{ optional($b->publisher)->name }}</td>
                        <td>
                            <span class="badge bg-light text-dark">{{ $b->school_code }}</span>
                            <span class="badge bg-light text-dark">{{ $b->subject_code }}</span>
                        </td>
                        <td class="text-end">{{ number_format($b->price) }}원</td>
                        <td>
                            @switch($b->status_code)
                                @case('selling') <span class="badge bg-success">판매중</span> @break
                                @case('paused') <span class="badge bg-warning text-dark">일시중지</span> @break
                                @case('discontinued') <span class="badge bg-dark">절판</span> @break
                                @case('upcoming') <span class="badge bg-info">출간예정</span> @break
                                @default <span class="badge bg-light text-dark">{{ $b->status_code }}</span>
                            @endswitch
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">데이터가 없습니다.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $books->links() }}</div>
</div>
@endsection
