@extends('admin.layouts.admin')
@section('title', '도서 · ' . $book->title)

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.books.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 도서 목록
        </a>
        <h1 class="h4 mb-0 mt-1">
            {{ $book->title }}
            <small class="text-muted">#{{ $book->id }}</small>
            @if($book->source === 'aladin')<span class="badge bg-info ms-1">알라딘</span>@endif
        </h1>
    </div>
    <form method="POST" action="{{ route('admin.books.destroy', $book) }}"
          onsubmit="return confirm('정말 삭제할까요? (주문 이력이 있으면 차단됩니다)')">
        @csrf @method('DELETE')
        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> 삭제</button>
    </form>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card section-card">
            <div class="card-header"><strong>기본 정보</strong></div>
            <form method="POST" action="{{ route('admin.books.update', $book) }}">
                @csrf @method('PUT')
                <input type="hidden" name="source" value="{{ $book->source }}">
                <div class="card-body">
                    {{-- 표지 + 기본 메타 --}}
                    <div class="row g-3 align-items-start">
                        <div class="col-md-2 d-flex flex-column align-items-center">
                            <label class="form-label small text-muted">표지</label>
                            <div class="cover-thumb">
                                @if($book->cover_path)
                                    <img id="cover_preview"
                                         src="{{ str_starts_with($book->cover_path, 'http') ? $book->cover_path : asset('storage/'.$book->cover_path) }}"
                                         alt="표지">
                                @else
                                    <i class="bi bi-book"></i>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-10">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">ISBN13 *</label>
                                    <input type="text" name="isbn" class="form-control" value="{{ old('isbn', $book->isbn) }}" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small text-muted">제목 *</label>
                                    <input type="text" name="title" class="form-control" value="{{ old('title', $book->title) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">부제목</label>
                                    <input type="text" name="subtitle" class="form-control" value="{{ old('subtitle', $book->subtitle) }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">시리즈명</label>
                                    <input type="text" name="series_name" class="form-control" value="{{ old('series_name', $book->series_name) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">출판사</label>
                                    <select name="publisher_id" class="form-select">
                                        <option value="">선택</option>
                                        @foreach($publisherOptions as $p)
                                            <option value="{{ $p->id }}" @selected(old('publisher_id', $book->publisher_id) == $p->id)>{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">저자</label>
                                    <input type="text" name="author" class="form-control" value="{{ old('author', $book->author) }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">출간일</label>
                                    <input type="date" name="pub_date" class="form-control" value="{{ old('pub_date', optional($book->pub_date)->format('Y-m-d')) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- 분류 / 가격 섹션 --}}
                    <div class="section-divider mt-4 mb-2">
                        <small class="text-muted fw-bold text-uppercase">분류 · 가격</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label small text-muted">학교</label>
                            <select name="school_code" class="form-select">
                                <option value="">선택</option>
                                @foreach($schoolOptions as $s)
                                    <option value="{{ $s->code }}" @selected(old('school_code', $book->school_code) === $s->code)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">과목</label>
                            <select name="subject_code" class="form-select">
                                <option value="">선택</option>
                                @foreach($subjectOptions as $s)
                                    <option value="{{ $s->code }}" @selected(old('subject_code', $book->subject_code) === $s->code)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">정가 (원) *</label>
                            <input type="number" min="0" name="price" class="form-control text-end" value="{{ old('price', $book->price) }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">기본 할인율(%)</label>
                            <input type="number" step="0.5" min="0" max="100" name="default_discount_rate" class="form-control text-end" value="{{ old('default_discount_rate', $book->default_discount_rate) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">상태 *</label>
                            <select name="status_code" class="form-select" required>
                                @foreach($statusOptions as $s)
                                    <option value="{{ $s->code }}" @selected(old('status_code', $book->status_code) === $s->code)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- 학년 · 학기 · 난이도 (체크박스) --}}
                    <div class="section-divider mt-4 mb-2">
                        <small class="text-muted fw-bold text-uppercase">학년 · 학기 · 난이도</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">학년 (복수)</label>
                            <div class="check-pill-group">
                                @foreach($gradeOptions as $g)
                                    <label class="check-pill">
                                        <input type="checkbox" name="grade_codes[]" value="{{ $g->code }}"
                                               @checked(in_array($g->code, $gradeCodes, true))>
                                        <span>{{ $g->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">학기 (복수)</label>
                            <div class="check-pill-group">
                                @foreach($semesterOptions as $sem)
                                    <label class="check-pill">
                                        <input type="checkbox" name="semester_codes[]" value="{{ $sem->code }}"
                                               @checked(in_array($sem->code, $semesterCodes ?? [], true))>
                                        <span>{{ $sem->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small text-muted">난이도 (복수)</label>
                            <div class="check-pill-group">
                                @foreach($levelOptions as $l)
                                    <label class="check-pill">
                                        <input type="checkbox" name="level_codes[]" value="{{ $l->code }}"
                                               @checked(in_array($l->code, $levelCodes, true))>
                                        <span>{{ $l->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- 기타 메타 --}}
                    <div class="section-divider mt-4 mb-2">
                        <small class="text-muted fw-bold text-uppercase">기타</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small text-muted">규격</label>
                            <input type="text" name="spec" class="form-control" value="{{ old('spec', $book->spec) }}" placeholder="예: 188×257">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">판/쇄</label>
                            <input type="text" name="edition" class="form-control" value="{{ old('edition', $book->edition) }}" placeholder="예: 3판 5쇄">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">표지 URL/경로</label>
                            <input type="text" name="cover_path" class="form-control" value="{{ old('cover_path', $book->cover_path) }}" placeholder="https://... 또는 portfolio/cover.jpg">
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <small class="text-muted">
                        등록: {{ optional($book->created_at)->format('Y-m-d') }} ·
                        소스: <code>{{ $book->source }}</code>
                    </small>
                    <button class="btn btn-primary"><i class="bi bi-save"></i> 저장</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card section-card">
            <div class="card-header"><strong><i class="bi bi-box-seam"></i> 총판별 재고</strong></div>
            <div class="card-body p-0">
                @if($stocks->isEmpty())
                    <div class="text-muted text-center py-3 small">재고 정보 없음</div>
                @else
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr>
                            <th>총판</th>
                            <th class="text-end">재고</th>
                            <th class="text-end">예약</th>
                        </tr></thead>
                        <tbody>
                            @foreach($stocks as $s)
                                <tr>
                                    <td><a href="{{ route('admin.users.show', $s->dist_id) }}">{{ $s->dist_name }}</a></td>
                                    <td class="text-end {{ $s->qty <= $s->low_stock_threshold ? 'text-danger fw-bold' : '' }}">
                                        {{ number_format($s->qty) }}
                                    </td>
                                    <td class="text-end text-muted">{{ number_format($s->reserved_qty) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="card-footer">
                <small class="text-muted">재고 조정은 추후 "재고 관리" 메뉴에서 진행 예정</small>
            </div>
        </div>
    </div>
</div>
@endsection
