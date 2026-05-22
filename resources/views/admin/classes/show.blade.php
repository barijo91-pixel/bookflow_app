@extends('admin.layouts.admin')
@section('title', '학급 · ' . $class->name)

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.classes.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 학급 목록
        </a>
        <h1 class="h4 mb-0 mt-1">
            {{ $class->name }}
            <small class="text-muted">· {{ $vendor->name ?? '' }}</small>
        </h1>
    </div>
    <form method="POST" action="{{ route('admin.classes.destroy', $class->id) }}"
          onsubmit="return confirm('정말 삭제할까요? (소속 학생 있으면 차단됩니다)')">
        @csrf @method('DELETE')
        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> 삭제</button>
    </form>
</div>

@if(session('share_url'))
    <div class="alert alert-info">
        <strong>발행된 공유링크:</strong>
        <code style="font-size:1rem">{{ session('share_url') }}</code>
        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="navigator.clipboard.writeText('{{ session('share_url') }}')">
            <i class="bi bi-clipboard"></i> 복사
        </button>
    </div>
@endif

@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="row g-3">
    {{-- LEFT: 학급 기본 정보 + 교재 매핑 --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>학급 정보</strong></div>
            <form method="POST" action="{{ route('admin.classes.update', $class->id) }}">
                @csrf @method('PUT')
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">학급명 *</label>
                            <input type="text" name="name" class="form-control" value="{{ $class->name }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">학년</label>
                            <select name="grade_code" class="form-select">
                                <option value="">선택</option>
                                @foreach($grades as $g)
                                    <option value="{{ $g->code }}" @selected($class->grade_code === $g->code)>{{ $g->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">상태</label>
                            <select name="status" class="form-select">
                                <option value="active" @selected($class->status === 'active')>운영중</option>
                                <option value="closed" @selected($class->status === 'closed')>종료</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">시작일</label>
                            <input type="date" name="started_at" class="form-control" value="{{ $class->started_at }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">종료일</label>
                            <input type="date" name="ended_at" class="form-control" value="{{ $class->ended_at }}">
                        </div>
                        <div class="col-md-6"></div>
                        <div class="col-12">
                            <label class="form-label small text-muted">메모</label>
                            <textarea name="memo" rows="2" class="form-control">{{ $class->memo }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white text-end">
                    <button class="btn btn-primary"><i class="bi bi-save"></i> 저장</button>
                </div>
            </form>
        </div>

        {{-- 교재 매핑 --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-journals"></i> 학급 교재 ({{ $books->count() }}권)</strong>
            </div>
            <div class="card-body p-0">
                @if($books->isEmpty())
                    <div class="text-muted text-center py-3 small">매핑된 교재가 없습니다.</div>
                @else
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px;"></th>
                                <th>도서</th>
                                <th>출판사</th>
                                <th class="text-end">정가</th>
                                <th class="text-end">수량</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($books as $b)
                                <tr>
                                    <td>
                                        @if($b->cover_path)
                                            <img src="{{ str_starts_with($b->cover_path, 'http') ? $b->cover_path : asset('storage/'.$b->cover_path) }}" style="height:32px;border-radius:3px">
                                        @else
                                            <i class="bi bi-book text-muted"></i>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.books.show', $b->book_id) }}" class="text-decoration-none">{{ $b->title }}</a>
                                        <div class="text-muted small"><code>{{ $b->isbn }}</code></div>
                                    </td>
                                    <td class="small">{{ $b->publisher_name }}</td>
                                    <td class="text-end small">{{ number_format($b->price) }}원</td>
                                    <td class="text-end">{{ number_format($b->qty) }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.classes.books.detach', [$class->id, $b->cb_id]) }}"
                                              onsubmit="return confirm('교재를 제거할까요?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-link text-danger p-0">제거</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="card-footer bg-white">
                <form method="POST" action="{{ route('admin.classes.books.attach', $class->id) }}">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-7">
                            <label class="form-label small text-muted mb-1">도서 ID</label>
                            <input type="number" name="book_id" class="form-control form-control-sm" required placeholder="도서 목록에서 ID 확인">
                        </div>
                        <div class="col-3">
                            <label class="form-label small text-muted mb-1">수량</label>
                            <input type="number" min="1" name="qty" value="1" class="form-control form-control-sm text-end" required>
                        </div>
                        <div class="col-2">
                            <button class="btn btn-sm btn-outline-primary w-100">추가</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- RIGHT: 학생/학부모 + 공유링크 --}}
    <div class="col-lg-5">
        {{-- 학생 / 학부모 --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white">
                <strong><i class="bi bi-mortarboard"></i> 학생 / 학부모 ({{ $students->count() }}명)</strong>
            </div>
            <div class="card-body p-0">
                @if($students->isEmpty())
                    <div class="text-muted text-center py-3 small">등록된 학생이 없습니다.</div>
                @else
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr>
                            <th>학생</th><th>학부모</th><th>휴대폰</th><th></th>
                        </tr></thead>
                        <tbody>
                            @foreach($students as $s)
                                <tr>
                                    <td class="small">{{ $s->name }}@if($s->grade_code)<span class="badge bg-light text-dark ms-1">{{ $s->grade_code }}</span>@endif</td>
                                    <td class="small text-muted">{{ $s->parent_name ?: '-' }}</td>
                                    <td class="small text-muted">{{ $s->parent_phone ?: '-' }}</td>
                                    <td class="text-end">
                                        @if($s->parent_phone)
                                            <form method="POST" action="{{ route('admin.classes.share', $class->id) }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="student_id" value="{{ $s->id }}">
                                                <button class="btn btn-sm btn-link p-0" title="공유링크 발송">
                                                    <i class="bi bi-send"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.classes.students.detach', [$class->id, $s->id]) }}" class="d-inline"
                                              onsubmit="return confirm('학생을 제거할까요?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="card-footer bg-white">
                <form method="POST" action="{{ route('admin.classes.students.attach', $class->id) }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-7">
                            <input type="text" name="student_name" class="form-control form-control-sm" placeholder="학생 이름 *" required>
                        </div>
                        <div class="col-5">
                            <select name="grade_code" class="form-select form-select-sm">
                                <option value="">학년 선택</option>
                                @foreach($grades as $g)
                                    <option value="{{ $g->code }}">{{ $g->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-5">
                            <input type="text" name="parent_name" class="form-control form-control-sm" placeholder="학부모 이름">
                        </div>
                        <div class="col-7">
                            <input type="text" name="parent_phone" class="form-control form-control-sm" placeholder="학부모 휴대폰">
                        </div>
                        <div class="col-12 d-grid">
                            <button class="btn btn-sm btn-outline-primary">학생 추가</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- 공유링크 이력 --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <strong><i class="bi bi-link-45deg"></i> 공유링크 ({{ $shareLinks->count() }})</strong>
            </div>
            <div class="card-body p-0">
                @if($shareLinks->isEmpty())
                    <div class="text-muted text-center py-3 small">발송된 공유링크가 없습니다.</div>
                @else
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>학부모</th><th>학생</th><th>발송</th><th>접속</th></tr></thead>
                        <tbody>
                            @foreach($shareLinks as $l)
                                <tr>
                                    <td class="small">{{ $l->parent_name }}<div class="text-muted">{{ $l->parent_phone }}</div></td>
                                    <td class="small">{{ $l->student_name }}</td>
                                    <td class="small text-muted">{{ $l->sent_at ? \Carbon\Carbon::parse($l->sent_at)->format('m-d H:i') : '-' }}</td>
                                    <td class="small text-muted">
                                        {{ $l->access_count }}회
                                        @if($l->accessed_at)<div>{{ \Carbon\Carbon::parse($l->accessed_at)->format('m-d H:i') }}</div>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
