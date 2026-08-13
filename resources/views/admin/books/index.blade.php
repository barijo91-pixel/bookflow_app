@extends('admin.layouts.admin')
@section('title', '도서')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">도서 마스터 <small class="text-muted fs-6">전체 {{ number_format($books->total()) }}권</small></h1>
    <div class="d-flex gap-2">
        {{-- 현재 필터가 그대로 적용된 상태로 내려받기 --}}
        <a href="{{ route('admin.books.export', request()->only(['q','publisher','status','school','subject'])) }}"
           class="btn btn-sm btn-outline-navy">
            <i class="bi bi-download"></i> 엑셀 다운로드
        </a>
        <a href="{{ route('admin.books.covers.show') }}" class="btn btn-sm btn-outline-navy">
            <i class="bi bi-images"></i> 표지 일괄 업로드
        </a>
        <a href="{{ route('admin.books.import.show') }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-file-earmark-excel"></i> 엑셀 일괄 등록
        </a>
        <a href="{{ route('admin.books.create') }}" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg"></i> 도서 등록
        </a>
    </div>
</div>

<form method="GET" class="card section-card mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">출판사</label>
                <select name="publisher" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    @foreach($publisherOptions as $p)
                        <option value="{{ $p->id }}" @selected($publisher == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">학교</label>
                <select name="school" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    @foreach($schoolOptions as $s)
                        <option value="{{ $s->code }}" @selected($school === $s->code)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">과목</label>
                <select name="subject" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    @foreach($subjectOptions as $s)
                        <option value="{{ $s->code }}" @selected($subject === $s->code)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">상태</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    @foreach($statusOptions as $s)
                        <option value="{{ $s->code }}" @selected($status === $s->code)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">검색 (제목/ISBN/저자/시리즈/출판사코드)</label>
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

@if($publisherOptions->isNotEmpty())
<div class="card section-card mb-3" style="border-color:#f1aeb5;">
    <div class="card-body py-2">
        <form method="POST" action="{{ route('admin.books.bulk_by_publisher') }}"
              class="row g-2 align-items-end" onsubmit="return confirmBulkDeleteByPublisher(this)">
            @csrf
            @method('DELETE')
            <input type="hidden" name="confirm_name" id="bulkDelConfirmName">
            <div class="col-auto">
                <label class="form-label small text-danger mb-1 fw-bold">출판사 도서 일괄삭제 (위험)</label>
                <select name="publisher_id" class="form-select form-select-sm" required style="min-width:200px;">
                    <option value="">출판사 선택…</option>
                    @foreach($publisherOptions as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-danger">이 출판사 도서 전체 삭제</button>
            </div>
            <div class="col-12">
                <span class="small text-muted">선택한 출판사의 모든 도서를 삭제합니다(soft delete — 복구 가능). 주문 이력이 있는 도서는 자동 제외됩니다. 실행 시 출판사명 확인 입력이 필요합니다.</span>
            </div>
        </form>
    </div>
</div>
<script>
function confirmBulkDeleteByPublisher(f){
    var sel = f.publisher_id;
    if(!sel.value){ alert('출판사를 선택하세요.'); return false; }
    var name = sel.options[sel.selectedIndex].text.trim();
    var typed = prompt('['+name+'] 출판사의 모든 도서를 삭제합니다.\n삭제하려면 아래에 출판사명을 정확히 입력하세요:');
    if(typed === null){ return false; }
    if(typed.trim() !== name){ alert('출판사명이 일치하지 않습니다. 삭제 취소.'); return false; }
    f.confirm_name.value = typed.trim();
    return true;
}
</script>
@endif

<div class="card section-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    <th style="width:60px;"><x-sort-link field="id" label="#" :sort="$sort" :dir="$dir" /></th>
                    <th style="width:60px; white-space:nowrap;">표지</th>
                    <th><x-sort-link field="title" label="제목" :sort="$sort" :dir="$dir" /></th>
                    <th><x-sort-link field="isbn" label="ISBN" :sort="$sort" :dir="$dir" /></th>
                    <th><x-sort-link field="publisher_id" label="출판사" :sort="$sort" :dir="$dir" /></th>
                    <th><x-sort-link field="school_code" label="학교/과목" :sort="$sort" :dir="$dir" /></th>
                    <th class="text-end"><x-sort-link field="price" label="정가" :sort="$sort" :dir="$dir" /></th>
                    <th><x-sort-link field="status_code" label="상태" :sort="$sort" :dir="$dir" /></th>
                </tr>
            </thead>
            <tbody>
                @forelse($books as $b)
                    <tr>
                        <td>{{ $b->id }}</td>
                        <td style="width:50px;">
                            {{-- 표지 크기는 표준 행 높이(42px)를 넘지 않게 유지 --}}
                            @if($b->cover_path)
                                <img src="{{ str_starts_with($b->cover_path, 'http') ? $b->cover_path : asset('storage/'.$b->cover_path) }}"
                                     alt="" style="height:32px;border-radius:3px;display:block">
                            @else
                                <div class="text-muted" style="font-size:1.15rem;line-height:1"><i class="bi bi-book"></i></div>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.books.show', $b) }}" class="link-name">{{ $b->title }} <i class="bi bi-chevron-right small"></i></a>
                            @if($b->subtitle)<span class="text-muted small ms-1">— {{ $b->subtitle }}</span>@endif
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
    <div class="card-footer">{{ $books->links() }}</div>
</div>
@endsection
