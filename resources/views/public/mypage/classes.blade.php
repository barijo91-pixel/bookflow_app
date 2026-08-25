@extends('public.layouts.app')
@section('title', '학급/학생')

@section('content')
<div class="mb-3 d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div>
        <h1 class="h4 navy mb-1"><i class="bi bi-mortarboard"></i> 학급/학생</h1>
        <p class="text-muted small mb-0">
            {{ $vendor->name ?? '학원' }} · 총 {{ $classes->count() }}개 학급
        </p>
    </div>

</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if($errors->any())
    <div class="alert alert-danger py-2 small">
        <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@if($classes->isEmpty())
    <div class="card section-card">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-mortarboard" style="font-size:2rem"></i>
            <p class="mb-1 mt-2">등록된 학급이 없습니다.</p>
            <p class="small mb-3">학급과 학생을 한 번에 만들 수 있습니다.</p>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#classCreateModal">
                <i class="bi bi-plus-lg"></i> 학급 등록
            </button>
        </div>
    </div>
@else
<div class="row g-3">
    {{-- LEFT: 학급 정보 --}}
    <div class="col-lg-4">
        {{-- 학급 목록 — 고르면 오른쪽에 그 학급 학생이 나온다 --}}
        <div class="card section-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-list-ul"></i> 학급</strong>
                <button type="button" class="btn btn-sm btn-primary"
                        data-bs-toggle="modal" data-bs-target="#classCreateModal">
                    <i class="bi bi-plus-lg"></i> 학급 등록
                </button>
            </div>
            <div class="list-group list-group-flush" style="max-height:360px; overflow-y:auto;">
                @foreach($classes as $c)
                    <a href="{{ route('my.classes.index', ['class' => $c->id]) }}"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center
                              {{ $selectedClass && $selectedClass->id === $c->id ? 'active' : '' }}">
                        <span>
                            <strong>{{ $c->name }}</strong>
                            @if($c->status !== 'active')
                                <span class="badge bg-secondary ms-1">종료</span>
                            @endif
                        </span>
                        <span class="small {{ $selectedClass && $selectedClass->id === $c->id ? '' : 'text-muted' }}">
                            {{ $c->student_count }}명
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
        {{-- 학급 정보 (기본 접힘, 헤더 클릭 시 펼침) --}}
        <div class="card section-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center" style="cursor:pointer"
                 onclick="toggleClassInfo()">
                <strong><i class="bi bi-info-circle"></i> 학급 정보</strong>
                <span class="small text-muted">
                    <span class="d-none d-sm-inline">{{ $selectedClass->name }}</span>
                    <i class="bi bi-chevron-down" id="classInfoChevron"></i>
                </span>
            </div>
            <form method="POST" action="{{ route('my.classes.update', $selectedClass->id) }}">
                @csrf @method('PUT')
                <div class="card-body" id="classInfoBody" style="display:none;">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label small text-muted mb-1">학급명</label>
                            <input type="text" name="name" class="form-control form-control-sm" value="{{ $selectedClass->name }}" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small text-muted mb-1">상태</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="active" @selected($selectedClass->status === 'active')>진행중</option>
                                <option value="closed" @selected($selectedClass->status === 'closed')>종료</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-muted mb-1">메모</label>
                            <textarea name="memo" class="form-control form-control-sm" rows="2">{{ $selectedClass->memo }}</textarea>
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
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-primary"
                            data-bs-toggle="modal" data-bs-target="#studentBulkModal">
                        <i class="bi bi-person-plus"></i> 학생 등록
                    </button>
                    <a href="{{ route('my.classes.students.import.show', $selectedClass->id) }}"
                       class="btn btn-sm btn-outline-navy d-none d-md-inline-block"
                       title="인원이 많으면 엑셀로 한 번에 등록">
                        <i class="bi bi-file-earmark-spreadsheet"></i> 엑셀
                    </a>
                </div>
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
                        <th>학생</th><th>학부모</th><th>연락처</th><th>주소</th><th></th>
                    </tr></thead>
                    <tbody>
                        @forelse($students as $s)
                            <tr>
                                <td class="small">
                                    <strong>{{ $s->name }}</strong>

                                </td>
                                <td class="small">{{ $s->parent_name ?? '-' }}</td>
                                <td class="small text-muted">{{ $s->parent_phone ? format_phone($s->parent_phone) : '-' }}</td>
                                <td class="small text-muted">
                                    @php $addr = trim(($s->parent_address ?? '').' '.($s->parent_address_detail ?? '')); @endphp
                                    {{ $addr !== '' ? $addr : '-' }}
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        {{-- 학생 수정 (학부모 연락처·주소 포함) --}}
                                        <button type="button" class="btn btn-sm btn-outline-secondary student-edit-btn"
                                                title="학생 수정"
                                                data-action="{{ route('my.classes.students.update', [$selectedClass->id, $s->id]) }}"
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
                                        <form method="POST" action="{{ route('my.classes.students.detach', [$selectedClass->id, $s->id]) }}"
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
                            <tr class="empty-row"><td colspan="5" class="text-center small">
                                <i class="bi bi-people d-block mb-1"></i>
                                등록된 학생이 없습니다. 위의 「학생 등록」으로 추가하세요.
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>

        {{-- 학생 여러 명 등록 모달 — 학급 목록의 등록 모달과 같은 방식 --}}
        <div class="modal fade" id="studentBulkModal" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('my.classes.students.attach_bulk', $selectedClass->id) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title navy">
                                <i class="bi bi-person-plus"></i> 학생 등록
                                <small class="text-muted">— {{ $selectedClass->name }}</small>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-0">
                            <div class="table-responsive" style="max-height:380px; overflow-y:auto;">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:32px;"></th>
                                            <th style="width:14%;">학생 이름 *</th>
                                            <th style="width:14%;">학부모 이름 *</th>
                                            <th style="width:17%;">학부모 연락처 *</th>
                                            <th>주소 *</th>
                                            <th style="width:36px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="bulkStudentRows"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <div class="small text-muted">
                                <strong>4개 항목 모두 필수</strong> · 빈 줄은 저장 안 됨
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-navy" id="bulkAddRow">
                                    <i class="bi bi-plus-lg"></i> 줄 추가
                                </button>
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">취소</button>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> 등록</button>
                            </div>
                        </div>
                    </form>
                </div>
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
                            <div class="col-12">
                                <label class="form-label small mb-1">학생 이름</label>
                                <input type="text" name="student_name" class="form-control form-control-sm" required>
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

@endif

{{-- 학급 추가 모달 — 학급 정보와 학생을 한 번에 등록 --}}
<div class="modal fade" id="classCreateModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('my.classes.store_with_students') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title navy"><i class="bi bi-plus-lg"></i> 새 학급 · 학생 등록</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        {{-- 왼쪽: 학급 정보 --}}
                        <div class="col-lg-3">
                            <div class="card section-card h-100">
                                <div class="card-header"><strong><i class="bi bi-mortarboard"></i> 학급 정보</strong></div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label small text-muted">학급명 *</label>
                                        <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                                               placeholder="예: 초3 영어반 A" required>
                                    </div>

                                    <div class="mb-2">
                                        <label class="form-label small text-muted">설명 / 메모</label>
                                        <textarea name="memo" class="form-control" rows="3">{{ old('memo') }}</textarea>
                                    </div>
                                    <div class="alert alert-light border small text-muted mb-0">
                                        <i class="bi bi-info-circle"></i>
                                        학생 <strong id="studentCountLabel">0</strong>명 입력됨.
                                        지금 안 넣어도 나중에 학급 상세에서 추가할 수 있습니다.
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 오른쪽: 학생 여러 명 --}}
                        <div class="col-lg-9">
                            <div class="card section-card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <strong><i class="bi bi-people"></i> 학생 등록</strong>
                                    <button type="button" class="btn btn-sm btn-outline-navy" id="addStudentRow">
                                        <i class="bi bi-plus-lg"></i> 줄 추가
                                    </button>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive" style="max-height:340px; overflow-y:auto;">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width:32px;"></th>
                                                    <th style="width:14%;">학생 이름 *</th>
                                                    <th style="width:14%;">학부모 이름 *</th>
                                                    <th style="width:17%;">학부모 연락처 *</th>
                                                    <th>주소 *</th>
                                                    <th style="width:36px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="studentRows"></tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="card-footer bg-white small text-muted text-nowrap">
                                    <strong>4개 항목 모두 필수</strong> · 빈 줄은 저장 안 됨
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> 등록
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
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

// 학생 여러 명 등록 모달 — 줄 추가/삭제, 엑셀처럼 이어서 입력
(function () {
    const tbody = document.getElementById('bulkStudentRows');
    const addBtn = document.getElementById('bulkAddRow');
    if (! tbody || ! addBtn) return;
    let seq = 0;

    function bulkRender() {
        const i = seq++;
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="text-muted small text-center row-no"></td>' +
            '<td><input type="text" name="students[' + i + '][student_name]" class="form-control form-control-sm" maxlength="80"></td>' +
            '<td><input type="text" name="students[' + i + '][parent_name]" class="form-control form-control-sm" maxlength="80"></td>' +
            '<td><input type="tel" name="students[' + i + '][parent_phone]" class="form-control form-control-sm" placeholder="01012345678" maxlength="20"></td>' +
            '<td><input type="text" name="students[' + i + '][parent_address]" class="form-control form-control-sm" maxlength="255"></td>' +
            '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-secondary row-del" title="줄 삭제">&times;</button></td>';
        tbody.appendChild(tr);
        renumber();
    }
    function renumber() {
        [...tbody.rows].forEach((r, idx) => {
            const c = r.querySelector('.row-no');
            if (c) c.textContent = idx + 1;
        });
    }
    tbody.addEventListener('click', function (e) {
        const btn = e.target.closest('.row-del');
        if (! btn) return;
        btn.closest('tr').remove();
        if (tbody.rows.length === 0) bulkRender();
        renumber();
    });
    tbody.addEventListener('input', function (e) {
        const tr = e.target.closest('tr');
        if (! tr || tr !== tbody.rows[tbody.rows.length - 1]) return;
        if (e.target.value.trim() !== '') bulkRender();
    });
    addBtn.addEventListener('click', bulkRender);

    for (let i = 0; i < 5; i++) bulkRender();
})();

// ── 학급 등록 모달 (학급 + 학생 동시 등록)

// 학급 추가 모달: 시작일 변경 시 종료일 자동 +6개월
(function () {
    const startEl = document.getElementById('newClassStartedAt');
    const endEl   = document.getElementById('newClassEndedAt');
    if (! startEl || ! endEl) return;
    startEl.addEventListener('change', function () {
        if (! this.value) return;
        const d = new Date(this.value);
        d.setMonth(d.getMonth() + 6);
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        endEl.value = `${y}-${m}-${day}`;
    });
})();

// 학생 줄 추가/삭제 — 학급과 학생을 한 화면에서 등록
(function () {
    const tbody = document.getElementById('studentRows');
    const addBtn = document.getElementById('addStudentRow');
    const label = document.getElementById('studentCountLabel');
    if (! tbody || ! addBtn) return;

    let seq = 0;

    function renderRow() {
        const i = seq++;
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="text-muted small text-center row-no"></td>' +
            '<td><input type="text" name="students[' + i + '][student_name]" class="form-control form-control-sm" maxlength="80"></td>' +
            '<td><input type="text" name="students[' + i + '][parent_name]" class="form-control form-control-sm" maxlength="80"></td>' +
            '<td><input type="tel" name="students[' + i + '][parent_phone]" class="form-control form-control-sm" placeholder="01012345678" maxlength="20"></td>' +
            '<td><input type="text" name="students[' + i + '][parent_address]" class="form-control form-control-sm" maxlength="255"></td>' +
            '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-secondary row-del" title="줄 삭제">&times;</button></td>';
        tbody.appendChild(tr);
        renumber();
    }

    function renumber() {
        [...tbody.rows].forEach((r, idx) => {
            const c = r.querySelector('.row-no');
            if (c) c.textContent = idx + 1;
        });
        countFilled();
    }

    function countFilled() {
        const n = [...tbody.querySelectorAll('input[name$="[student_name]"]')]
            .filter(i => i.value.trim() !== '').length;
        if (label) label.textContent = n;
    }

    tbody.addEventListener('click', function (e) {
        const btn = e.target.closest('.row-del');
        if (! btn) return;
        btn.closest('tr').remove();
        if (tbody.rows.length === 0) renderRow();
        renumber();
    });
    tbody.addEventListener('input', countFilled);
    addBtn.addEventListener('click', renderRow);

    // 마지막 줄에 입력이 들어오면 새 줄을 자동으로 하나 더 (엑셀처럼 이어서 입력)
    tbody.addEventListener('input', function (e) {
        const tr = e.target.closest('tr');
        if (! tr || tr !== tbody.rows[tbody.rows.length - 1]) return;
        if (e.target.value.trim() !== '') renderRow();
    });

    for (let i = 0; i < 5; i++) renderRow();   // 기본 5줄
})();

</script>
@endpush
@endsection
