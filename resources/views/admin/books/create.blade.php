@extends('admin.layouts.admin')
@section('title', '도서 등록')

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.books.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 도서 목록
        </a>
        <h1 class="h4 mb-0 mt-1">도서 등록</h1>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
@endif

<div class="card section-card mb-3">
    <div class="card-body py-3">
        <label class="form-label small text-muted mb-1"><i class="bi bi-upc-scan"></i> ISBN13 알라딘 자동 조회</label>
        <div class="d-flex gap-2">
            <input type="text" id="isbn_search" class="form-control" placeholder="ISBN13 (예: 9788901234567)" maxlength="13">
            <button type="button" id="btn_isbn_lookup" class="btn btn-outline-primary">
                <i class="bi bi-search"></i> 조회
            </button>
            <button type="button" id="btn_keyword_search" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#keywordModal">
                <i class="bi bi-search"></i> 키워드 검색
            </button>
        </div>
        <small class="text-muted">ISBN 입력 후 조회 또는 Enter — 알라딘 TTB에서 자동으로 정보를 가져옵니다.</small>
        <div id="aladin_result" class="mt-2"></div>
    </div>
</div>

<div class="card section-card">
    <form method="POST" action="{{ route('admin.books.store') }}" id="book_form">
        @csrf
        <input type="hidden" name="source" id="source_input" value="manual">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2" style="text-align:center">
                    <img id="cover_preview" src="" alt="" style="max-width:100%; max-height:180px; display:none; border-radius:6px">
                    <div id="cover_placeholder" class="text-muted py-4"><i class="bi bi-book" style="font-size:3rem"></i></div>
                </div>
                <div class="col-md-10">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small text-muted">ISBN13 *</label>
                            <input type="text" name="isbn" id="isbn_input" class="form-control" value="{{ old('isbn') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted" title="출판사 자체 도서코드 (총판 주문용)">출판사 코드</label>
                            <input type="text" name="publisher_code" class="form-control" value="{{ old('publisher_code') }}" placeholder="예: B00150000003" maxlength="50">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">출판사</label>
                            <select name="publisher_id" id="publisher_select" class="form-select">
                                <option value="">선택</option>
                                @foreach($publisherOptions as $p)
                                    <option value="{{ $p->id }}" @selected(old('publisher_id') == $p->id)>{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">시리즈명</label>
                            <input type="text" name="series_name" class="form-control" value="{{ old('series_name') }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small text-muted">제목 *</label>
                            <input type="text" name="title" id="title_input" class="form-control" value="{{ old('title') }}" required>
                        </div>
                        {{-- 부제목/저자/출간일 — 화면 노출 X, 알라딘 자동 채움 시 hidden으로 저장 --}}
                        <input type="hidden" name="subtitle" id="subtitle_input" value="{{ old('subtitle') }}">
                        <input type="hidden" name="author"   id="author_input"   value="{{ old('author') }}">
                        <input type="hidden" name="pub_date" id="pub_date_input" value="{{ old('pub_date') }}">
                    </div>
                </div>
            </div>

            <hr>
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label small text-muted">학교</label>
                    <select name="school_code" class="form-select">
                        <option value="">선택</option>
                        @foreach($schoolOptions as $s)
                            <option value="{{ $s->code }}" @selected(old('school_code') === $s->code)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">과목</label>
                    <select name="subject_code" class="form-select">
                        <option value="">선택</option>
                        @foreach($subjectOptions as $s)
                            <option value="{{ $s->code }}" @selected(old('subject_code') === $s->code)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">정가 (원) *</label>
                    <input type="number" min="0" name="price" id="price_input" class="form-control text-end" value="{{ old('price', 0) }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">기본 할인율(%)</label>
                    <input type="number" step="0.5" min="0" max="100" name="default_discount_rate" class="form-control text-end" value="{{ old('default_discount_rate', 0) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted">상태 *</label>
                    <select name="status_code" class="form-select" required>
                        @foreach($statusOptions as $s)
                            <option value="{{ $s->code }}" @selected(old('status_code', 'selling') === $s->code)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">학년 (복수 선택 가능)</label>
                    <div>
                        @foreach($gradeOptions as $g)
                            <label class="me-2 small">
                                <input type="checkbox" name="grade_codes[]" value="{{ $g->code }}"
                                       @checked(in_array($g->code, (array) old('grade_codes', []), true))>
                                {{ $g->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">학기 (복수 선택 가능)</label>
                    <div>
                        @foreach($semesterOptions as $sem)
                            <label class="me-2 small">
                                <input type="checkbox" name="semester_codes[]" value="{{ $sem->code }}"
                                       @checked(in_array($sem->code, (array) old('semester_codes', []), true))>
                                {{ $sem->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small text-muted">난이도 (복수 선택 가능)</label>
                    <div>
                        @foreach($levelOptions as $l)
                            <label class="me-2 small">
                                <input type="checkbox" name="level_codes[]" value="{{ $l->code }}"
                                       @checked(in_array($l->code, (array) old('level_codes', []), true))>
                                {{ $l->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">규격</label>
                    <input type="text" name="spec" class="form-control" value="{{ old('spec') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">판/쇄</label>
                    <input type="text" name="edition" class="form-control" value="{{ old('edition') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">표지 URL/경로</label>
                    <input type="text" name="cover_path" id="cover_path_input" class="form-control" value="{{ old('cover_path') }}">
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <small class="text-muted">알라딘에서 자동 채움 시 source=aladin 으로 기록됩니다.</small>
            <div>
                <a href="{{ route('admin.books.index') }}" class="btn btn-secondary">취소</a>
                <button class="btn btn-primary"><i class="bi bi-check-lg"></i> 등록</button>
            </div>
        </div>
    </form>
</div>

{{-- Keyword Search Modal --}}
<div class="modal fade" id="keywordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">알라딘 키워드 검색</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex gap-2 mb-3">
                    <input type="text" id="keyword_input" class="form-control" placeholder="제목/저자/출판사 검색">
                    <button type="button" id="keyword_search_btn" class="btn btn-primary"><i class="bi bi-search"></i> 검색</button>
                </div>
                <div id="keyword_results"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const elIsbn = document.getElementById('isbn_search');
    const elBtn = document.getElementById('btn_isbn_lookup');
    const elResult = document.getElementById('aladin_result');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    async function lookup(isbn) {
        elResult.innerHTML = '<div class="text-muted small"><i class="bi bi-hourglass-split"></i> 조회 중...</div>';
        try {
            const res = await fetch("{{ route('admin.books.aladin.lookup') }}?isbn=" + encodeURIComponent(isbn), {
                headers: {'Accept': 'application/json'}
            });
            const json = await res.json();
            if (! json.ok) {
                elResult.innerHTML = '<div class="alert alert-warning py-2 mb-0 small">' + (json.error || '조회 실패') + '</div>';
                return;
            }
            applyAladinData(json.data);
            elResult.innerHTML = '<div class="alert alert-success py-2 mb-0 small"><i class="bi bi-check-circle"></i> 자동 채움 완료 — 필요한 항목만 수정하세요.</div>';
        } catch (e) {
            elResult.innerHTML = '<div class="alert alert-danger py-2 mb-0 small">오류: ' + e.message + '</div>';
        }
    }

    function applyAladinData(d) {
        document.getElementById('isbn_input').value = d.isbn || '';
        document.getElementById('title_input').value = d.title || '';
        document.getElementById('subtitle_input').value = d.subtitle || '';
        document.getElementById('author_input').value = d.author || '';
        document.getElementById('price_input').value = d.price || 0;
        if (d.pub_date) document.getElementById('pub_date_input').value = d.pub_date.substring(0, 10);
        if (d.cover) {
            document.getElementById('cover_path_input').value = d.cover;
            const img = document.getElementById('cover_preview');
            img.src = d.cover;
            img.style.display = 'block';
            document.getElementById('cover_placeholder').style.display = 'none';
        }
        // 출판사: 알라딘에서 firstOrCreate된 ID를 사용
        if (d.publisher_id) {
            const sel = document.getElementById('publisher_select');
            // 옵션이 없으면 새로 추가
            let opt = sel.querySelector(`option[value="${d.publisher_id}"]`);
            if (! opt) {
                opt = document.createElement('option');
                opt.value = d.publisher_id;
                opt.textContent = d.publisher;
                sel.appendChild(opt);
            }
            sel.value = d.publisher_id;
        }
        document.getElementById('source_input').value = 'aladin';
    }

    elBtn.addEventListener('click', () => {
        const v = elIsbn.value.trim();
        if (v) lookup(v);
    });
    elIsbn.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); elBtn.click(); }
    });

    // Keyword search
    const kwInput = document.getElementById('keyword_input');
    const kwBtn = document.getElementById('keyword_search_btn');
    const kwResults = document.getElementById('keyword_results');

    async function keywordSearch() {
        const q = kwInput.value.trim();
        if (! q) return;
        kwResults.innerHTML = '<div class="text-muted small">검색 중...</div>';
        try {
            const res = await fetch("{{ route('admin.books.aladin.search') }}?q=" + encodeURIComponent(q), {
                headers: {'Accept': 'application/json'}
            });
            const json = await res.json();
            if (! json.ok || ! json.data.length) {
                kwResults.innerHTML = '<div class="text-muted py-3 text-center">결과 없음</div>';
                return;
            }
            const html = json.data.map((it, i) => `
                <div class="d-flex gap-3 border-bottom py-2 align-items-start">
                    ${it.cover ? `<img src="${it.cover}" style="height:60px;border-radius:3px">` : '<div style="width:45px"></div>'}
                    <div class="flex-grow-1">
                        <div><strong>${it.title}</strong>${it.subtitle ? ' <span class="text-muted">— '+it.subtitle+'</span>' : ''}</div>
                        <div class="text-muted small">${it.author} · ${it.publisher} · ${it.pub_date} · ${it.price.toLocaleString()}원 · <code>${it.isbn}</code></div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-isbn="${it.isbn}">선택</button>
                </div>
            `).join('');
            kwResults.innerHTML = html;
            kwResults.querySelectorAll('button[data-isbn]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const isbn = btn.dataset.isbn;
                    bootstrap.Modal.getInstance(document.getElementById('keywordModal')).hide();
                    elIsbn.value = isbn;
                    lookup(isbn);
                });
            });
        } catch (e) {
            kwResults.innerHTML = '<div class="alert alert-danger py-2 small">' + e.message + '</div>';
        }
    }
    kwBtn.addEventListener('click', keywordSearch);
    kwInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); keywordSearch(); } });

    // 표지 입력 실시간 프리뷰
    document.getElementById('cover_path_input').addEventListener('input', (e) => {
        const v = e.target.value.trim();
        const img = document.getElementById('cover_preview');
        const ph = document.getElementById('cover_placeholder');
        if (v) { img.src = v.startsWith('http') ? v : ('/storage/' + v.replace(/^\//, ''));
                 img.style.display = 'block'; ph.style.display = 'none'; }
        else   { img.style.display = 'none'; ph.style.display = 'block'; }
    });
})();
</script>
@endpush
