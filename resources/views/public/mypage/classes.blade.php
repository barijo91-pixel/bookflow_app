@extends('public.layouts.app')
@section('title', '학급/학생')

@section('content')
<div class="mb-3 d-flex justify-content-between align-items-center">
    <div>
        <h1 class="h4 navy mb-1"><i class="bi bi-mortarboard"></i> 학급/학생</h1>
        <p class="text-muted small mb-0">{{ $vendor->name ?? '' }} · 총 {{ $classes->count() }}개 학급</p>
    </div>
    <button type="button" class="btn btn-navy btn-sm" data-bs-toggle="modal" data-bs-target="#classCreateModal">
        <i class="bi bi-plus-lg"></i> 학급 추가
    </button>
</div>

@if(session('success'))
    <div class="alert alert-success py-2 small">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger py-2 small">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="card section-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    <th>학급명</th>
                    <th>학년</th>
                    <th>학생 수</th>
                    <th>상태</th>
                    <th style="width:90px;" class="text-end">삭제</th>
                </tr>
            </thead>
            <tbody>
                @forelse($classes as $c)
                    <tr style="cursor:pointer" onclick="location.href='{{ route('my.classes.show', $c->id) }}'">
                        <td>
                            <a href="{{ route('my.classes.show', $c->id) }}" class="text-decoration-none navy fw-bold" onclick="event.stopPropagation()">
                                {{ $c->name }} <i class="bi bi-chevron-right small"></i>
                            </a>
                        </td>
                        <td class="small text-muted">
                            @php $g = $grades->firstWhere('code', $c->grade_code); @endphp
                            {{ $g->name ?? '-' }}
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">{{ $c->student_count }}명</span>
                        </td>
                        <td>
                            @if($c->status === 'active')
                                <span class="badge bg-success">진행중</span>
                            @else
                                <span class="badge bg-secondary">종료</span>
                            @endif
                        </td>
                        <td class="text-end" onclick="event.stopPropagation()">
                            <form method="POST" action="{{ route('my.classes.destroy', $c->id) }}" class="d-inline"
                                  onsubmit="return confirm('「{{ addslashes($c->name) }}」 학급을 삭제할까요?\n학생이 있으면 차단됩니다.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="학급 삭제">
                                    <i class="bi bi-trash"></i> 삭제
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="bi bi-mortarboard" style="font-size:2rem"></i>
                            <p class="mb-0 mt-2">아직 학급이 없습니다.</p>
                            <p class="small">우측 상단의 "학급 추가" 버튼으로 시작하세요.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- 학급 추가 모달 — 학급 정보와 학생을 한 번에 등록 --}}
<div class="modal fade" id="classCreateModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
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
                        <div class="col-lg-4">
                            <div class="card section-card h-100">
                                <div class="card-header"><strong><i class="bi bi-mortarboard"></i> 학급 정보</strong></div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label small text-muted">학급명 *</label>
                                        <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                                               placeholder="예: 초3 영어반 A" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small text-muted">학년</label>
                                        <select name="grade_code" class="form-select">
                                            <option value="">선택 안 함</option>
                                            @foreach($grades as $g)
                                                <option value="{{ $g->code }}" @selected(old('grade_code') === $g->code)>{{ $g->name }}</option>
                                            @endforeach
                                        </select>
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
                        <div class="col-lg-8">
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
                                                    <th style="width:36px;"></th>
                                                    <th>학생 이름</th>
                                                    <th>학부모 이름</th>
                                                    <th>학부모 연락처</th>
                                                    <th>주소</th>
                                                    <th style="width:40px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="studentRows"></tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="card-footer bg-white small text-muted">
                                    학생을 넣으려면 <strong>학부모 이름·연락처</strong>가 필요합니다 —
                                    교재 결제 요청이 이 연락처로 나갑니다. 빈 줄은 저장되지 않습니다.
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
