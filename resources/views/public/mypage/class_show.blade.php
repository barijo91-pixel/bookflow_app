@extends('public.layouts.app')
@section('title', '학급 · '.$class->name)
@section('max_width', '1200px')

@section('content')
<div class="mb-3">
    <a href="{{ route('my.classes.index') }}" class="text-muted small text-decoration-none">
        <i class="bi bi-arrow-left"></i> 학급 목록으로
    </a>
    <h1 class="h4 navy mb-0 mt-1">
        <i class="bi bi-mortarboard"></i> {{ $class->name }}
        @if($class->status === 'closed')
            <span class="badge bg-secondary fs-6 ms-1">종료</span>
        @endif
    </h1>
</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if(session('share_url'))
    <div class="alert alert-info py-2 small">
        <strong><i class="bi bi-link"></i> 발행된 공유링크:</strong>
        <code class="ms-2">{{ session('share_url') }}</code>
        <button type="button" class="btn btn-sm btn-outline-secondary ms-2"
                onclick="navigator.clipboard.writeText('{{ session('share_url') }}'); this.textContent='✓ 복사됨';">
            <i class="bi bi-clipboard"></i> 복사
        </button>
    </div>
@endif
@if($errors->any())<div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="row g-3">
    {{-- LEFT: 학급 정보 --}}
    <div class="col-lg-4">
        {{-- 학급 정보 (기본 접힘, 헤더 클릭 시 펼침) --}}
        <div class="card section-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center" style="cursor:pointer"
                 onclick="toggleClassInfo()">
                <strong><i class="bi bi-info-circle"></i> 학급 정보</strong>
                <span class="small text-muted">
                    <span class="d-none d-sm-inline">{{ $class->name }}</span>
                    <i class="bi bi-chevron-down" id="classInfoChevron"></i>
                </span>
            </div>
            <form method="POST" action="{{ route('my.classes.update', $class->id) }}">
                @csrf @method('PUT')
                <div class="card-body" id="classInfoBody" style="display:none;">
                    <div class="row g-2">
                        <div class="col-md-7">
                            <label class="form-label small text-muted mb-1">학급명</label>
                            <input type="text" name="name" class="form-control form-control-sm" value="{{ $class->name }}" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small text-muted mb-1">학년</label>
                            <select name="grade_code" class="form-select form-select-sm">
                                <option value="">선택 안 함</option>
                                @foreach($grades as $g)
                                    <option value="{{ $g->code }}" @selected($class->grade_code === $g->code)>{{ $g->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">상태</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="active" @selected($class->status === 'active')>진행중</option>
                                <option value="closed" @selected($class->status === 'closed')>종료</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-muted mb-1">메모</label>
                            <textarea name="memo" class="form-control form-control-sm" rows="2">{{ $class->memo }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end" id="classInfoFooter" style="display:none;">
                    <button class="btn btn-sm btn-navy"><i class="bi bi-save"></i> 저장</button>
                </div>
            </form>
        </div>

        {{-- 학급 교재(공유링크용)는 화면에서 제거 — class_books 데이터·학부모 공유링크 기능은 유지 --}}
    </div>

    {{-- RIGHT: 학생/학부모 (메인) --}}
    <div class="col-lg-8">
        {{-- 학생 목록 + 추가 --}}
        <div class="card section-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-people"></i> 학생/학부모 (<span id="studentCount">{{ $students->count() }}</span>)</strong>
                <a href="{{ route('my.classes.students.import.show', $class->id) }}" class="btn btn-sm btn-outline-navy d-none d-md-inline-block">
                    <i class="bi bi-file-earmark-spreadsheet"></i> 엑셀 일괄 등록
                </a>
            </div>
            @if($students->isNotEmpty())
                <div class="px-3 py-2 border-bottom" style="background:#fafbfc;">
                    <input type="text" id="studentSearch" class="form-control form-control-sm"
                           placeholder="🔍 학생/학부모 이름으로 검색..." autocomplete="off">
                </div>
            @endif
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" id="studentTable">
                    <thead class="table-light"><tr>
                        <th>학생</th><th>학부모</th><th>연락처</th><th></th>
                    </tr></thead>
                    <tbody>
                        @forelse($students as $s)
                            <tr>
                                <td class="small">
                                    <strong>{{ $s->name }}</strong>
                                    @if($s->grade_code)
                                        @php $g = $grades->firstWhere('code', $s->grade_code); @endphp
                                        <span class="badge bg-light text-dark">{{ $g->name ?? $s->grade_code }}</span>
                                    @endif
                                </td>
                                <td class="small">{{ $s->parent_name ?? '-' }}</td>
                                <td class="small text-muted">{{ $s->parent_phone ? format_phone($s->parent_phone) : '-' }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        {{-- 학생 수정 (학부모 연락처·주소 포함) --}}
                                        <button type="button" class="btn btn-sm btn-outline-secondary student-edit-btn"
                                                title="학생 수정"
                                                data-action="{{ route('my.classes.students.update', [$class->id, $s->id]) }}"
                                                data-student-name="{{ $s->name }}"
                                                data-grade="{{ $s->grade_code }}"
                                                data-parent-name="{{ $s->parent_name }}"
                                                data-parent-phone="{{ $s->parent_phone }}"
                                                data-address="{{ $s->parent_address }}"
                                                data-address-detail="{{ $s->parent_address_detail }}"
                                                data-memo="{{ $s->memo }}">
                                            <i class="bi bi-pencil"></i> 수정
                                        </button>
                                        {{-- 학생 삭제 --}}
                                        <form method="POST" action="{{ route('my.classes.students.detach', [$class->id, $s->id]) }}"
                                              onsubmit="return confirm('「{{ $s->name }}」 학생을 이 학급에서 제거할까요?')" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="학생 삭제">
                                                <i class="bi bi-trash"></i> 삭제
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="empty-row"><td colspan="4" class="text-center small">
                                <i class="bi bi-people d-block mb-1"></i>
                                등록된 학생이 없습니다. 아래에서 추가하거나 엑셀로 일괄 등록하세요.
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <button type="button" class="small mb-2 navy fw-bold py-1 px-2 rounded d-flex align-items-center justify-content-between border-0 w-100"
                        style="background:#d4e0ee; border-left:3px solid #1f3a5f !important; cursor:pointer;"
                        onclick="toggleStudentForm()">
                    <span><i class="bi bi-person-plus"></i> 학생 등록</span>
                    <i class="bi bi-chevron-down" id="studentFormChevron"></i>
                </button>
                <form method="POST" action="{{ route('my.classes.students.attach', $class->id) }}" class="row g-2" id="studentAddForm" style="display:none;">
                    @csrf
                    <div class="col-md-3">
                        <input type="text" name="student_name" class="form-control form-control-sm" placeholder="학생 이름" required>
                    </div>
                    <div class="col-md-2">
                        <select name="grade_code" class="form-select form-select-sm">
                            <option value="">학년</option>
                            @foreach($grades as $g)
                                <option value="{{ $g->code }}">{{ $g->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="parent_name" class="form-control form-control-sm" placeholder="학부모 이름" required>
                    </div>
                    <div class="col-md-3">
                        <input type="tel" name="parent_phone" class="form-control form-control-sm" placeholder="학부모 휴대폰" required>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="parent_address" class="form-control form-control-sm" placeholder="학부모 주소 (소매 배송지 — 선택)">
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="parent_address_detail" class="form-control form-control-sm" placeholder="상세주소">
                    </div>
                    <div class="col-md-3 d-grid">
                        <button class="btn btn-sm btn-navy"><i class="bi bi-plus-lg"></i> 학생 추가</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 학생 수정 모달 — 위 목록의 [수정] 버튼이 data-* 값으로 채운다 --}}
        <div class="modal fade" id="studentEditModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" id="studentEditForm" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title navy"><i class="bi bi-pencil"></i> 학생 정보 수정</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2">
                            <div class="col-md-7">
                                <label class="form-label small mb-1">학생 이름</label>
                                <input type="text" name="student_name" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small mb-1">학년</label>
                                <select name="grade_code" class="form-select form-select-sm">
                                    <option value="">학년</option>
                                    @foreach($grades as $g)
                                        <option value="{{ $g->code }}">{{ $g->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">학부모 이름</label>
                                <input type="text" name="parent_name" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-1">학부모 휴대폰</label>
                                <input type="tel" name="parent_phone" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-1">학부모 주소 <span class="text-muted">(소매 배송지)</span></label>
                                <input type="text" name="parent_address" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-1">상세주소</label>
                                <input type="text" name="parent_address_detail" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-1">메모</label>
                                <input type="text" name="memo" class="form-control form-control-sm" maxlength="500">
                            </div>
                        </div>
                        <div class="small text-muted mt-2">
                            <i class="bi bi-info-circle"></i> 같은 휴대폰 번호의 학부모가 이미 있으면 그 학부모로 연결됩니다.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-sm btn-navy"><i class="bi bi-check-lg"></i> 저장</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 공유링크 이력 --}}
        @if($shareLinks->isNotEmpty())
            <div class="card section-card mb-3">
                <div class="card-header"><strong><i class="bi bi-link"></i> 공유링크 발송 이력</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr>
                            <th>학생/학부모</th><th>발송</th><th>만료</th><th>조회수</th>
                        </tr></thead>
                        <tbody>
                            @foreach($shareLinks as $l)
                                <tr>
                                    <td class="small">{{ $l->student_name }} · {{ $l->parent_name }}</td>
                                    <td class="small text-muted">{{ $l->sent_at ? \Carbon\Carbon::parse($l->sent_at)->format('m-d H:i') : '-' }}</td>
                                    <td class="small text-muted">
                                        @if($l->expires_at)
                                            @php $exp = \Carbon\Carbon::parse($l->expires_at); @endphp
                                            <span class="{{ $exp->isPast() ? 'text-danger' : '' }}">{{ $exp->format('Y-m-d') }}</span>
                                        @endif
                                    </td>
                                    <td class="small text-muted">{{ $l->access_count ?? 0 }}회</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

@push('head')
<style>
/* 모바일: 학생 목록 링크/삭제 버튼 — 아이콘 빼고 텍스트만 한 줄 */
@media (max-width: 767.98px) {
    #studentTable td:last-child .btn { white-space: nowrap; padding: .28rem .6rem; font-size: .82rem; }
    #studentTable td:last-child .btn i { display: none; }
    #studentTable td:last-child .d-inline-flex { flex-wrap: nowrap; }
}
</style>
@endpush

@push('scripts')
<script>
// 학급 정보 카드 접기/펼치기 (기본 접힘)
function toggleClassInfo() {
    var b = document.getElementById('classInfoBody');
    var f = document.getElementById('classInfoFooter');
    var c = document.getElementById('classInfoChevron');
    if (!b) return;
    var show = b.style.display === 'none';
    b.style.display = show ? '' : 'none';
    if (f) f.style.display = show ? '' : 'none';
    if (c) { c.classList.toggle('bi-chevron-down', !show); c.classList.toggle('bi-chevron-up', show); }
}

function toggleClassBooks() {
    var b = document.getElementById('classBooksBody');
    var c = document.getElementById('classBooksChevron');
    if (!b) return;
    var show = b.style.display === 'none';
    b.style.display = show ? '' : 'none';
    if (c) { c.classList.toggle('bi-chevron-down', !show); c.classList.toggle('bi-chevron-up', show); }
}

// 학생 수정 모달 — 목록의 [수정] 버튼 data-* 값으로 폼을 채워서 연다
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.student-edit-btn');
    if (!btn) return;
    const form = document.getElementById('studentEditForm');
    form.action = btn.dataset.action;
    const set = (name, val) => { const el = form.querySelector(`[name="${name}"]`); if (el) el.value = val || ''; };
    set('student_name', btn.dataset.studentName);
    set('grade_code', btn.dataset.grade);
    set('parent_name', btn.dataset.parentName);
    set('parent_phone', btn.dataset.parentPhone);
    set('parent_address', btn.dataset.address);
    set('parent_address_detail', btn.dataset.addressDetail);
    set('memo', btn.dataset.memo);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('studentEditModal')).show();
});

// 학생 등록 폼 접기/펼치기 (기본 접힘)
function toggleStudentForm() {
    var b = document.getElementById('studentAddForm');
    var c = document.getElementById('studentFormChevron');
    if (!b) return;
    var show = b.style.display === 'none';
    b.style.display = show ? '' : 'none';
    if (c) { c.classList.toggle('bi-chevron-down', !show); c.classList.toggle('bi-chevron-up', show); }
}
// 저장 검증 오류가 있으면 자동으로 펼침
@if($errors->any())
document.addEventListener('DOMContentLoaded', function(){
    var b = document.getElementById('classInfoBody');
    if (b) b.style.display = '';
    var c = document.getElementById('classInfoChevron');
    if (c) { c.classList.remove('bi-chevron-down'); c.classList.add('bi-chevron-up'); }
});
@endif

// 학생 즉시 검색 (클라이언트 사이드 필터링)
(function () {
    const input = document.getElementById('studentSearch');
    if (!input) return;
    const tbody = document.querySelector('#studentTable tbody');
    const countEl = document.getElementById('studentCount');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const total = rows.length;

    input.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        let visible = 0;
        rows.forEach(tr => {
            // 학생명·학부모명·전화번호 모두 검색 대상
            const text = (tr.cells[0]?.textContent + ' ' + tr.cells[1]?.textContent + ' ' + tr.cells[2]?.textContent).toLowerCase();
            const match = !q || text.includes(q);
            tr.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        if (countEl) countEl.textContent = q ? `${visible} / ${total}` : total;
    });
})();

// 교재 추가 — 출판사 + 제목 검색 필터 (option 재구성)
(function () {
    const input = document.getElementById('bookSearchInput');
    const sel = document.getElementById('bookSelect');
    const pub = document.getElementById('bookPublisher');
    if (!input || !sel) return;

    // 원본 옵션 백업 (placeholder 제외) — 출판사 id 포함
    const allOpts = Array.from(sel.options).slice(1).map(o => ({
        value: o.value, text: o.text, pub: o.dataset.publisher || ''
    }));
    const placeholder = sel.options[0]?.text || '교재 선택';

    function apply() {
        const q = input.value.trim().toLowerCase();
        const pv = pub ? pub.value : '';
        let filtered = allOpts;
        if (pv) filtered = filtered.filter(o => o.pub === pv);
        if (q)  filtered = filtered.filter(o => o.text.toLowerCase().includes(q));
        const shown = filtered.slice(0, 200); // 성능: 최대 200개
        sel.innerHTML = '<option value="">'
            + ((q || pv) ? `검색결과 ${filtered.length}권` : placeholder) + '</option>'
            + shown.map(o => `<option value="${o.value}" data-publisher="${o.pub}">${o.text.replace(/</g,'&lt;')}</option>`).join('');
        if (filtered.length === 1) sel.value = filtered[0].value; // 1개면 자동 선택
    }

    let timer = null;
    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(apply, 150); });
    if (pub) pub.addEventListener('change', apply);
})();
</script>
@endpush
@endsection
