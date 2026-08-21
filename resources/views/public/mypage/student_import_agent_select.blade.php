@extends('public.layouts.app')
@section('title', '학급&학생 (담당 학원)')
@section('max_width', '900px')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-people"></i> 학급&학생</h1>
    <p class="text-muted small mb-0">담당 학원의 학급을 만들고 학생을 등록합니다. 인원이 많으면 엑셀로도 올릴 수 있습니다.</p>
</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
@if($errors->any())
    <div class="alert alert-danger py-2 small">
        <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@if($vendors->isEmpty())
    <div class="card section-card">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-building-x" style="font-size:2rem"></i>
            @if($wholesaleHidden > 0)
                <p class="mb-0 mt-2">담당 학원이 모두 도매입니다.</p>
                <p class="small mb-0">도매 학원은 학원이 일괄 매입하므로 학급·학생 등록이 필요 없습니다.</p>
            @else
                <p class="mb-0 mt-2">담당 학원이 없습니다.</p>
                <p class="small mb-0">관리자에게 학원 매핑 요청을 해주세요.</p>
            @endif
        </div>
    </div>
@else
    @foreach($vendors as $v)
        <div class="card section-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-building"></i> {{ $v->name }}</strong>
                <small class="text-muted">활성 학급 {{ count($vendorClasses[$v->id] ?? []) }}개</small>
            </div>
            @if(empty($vendorClasses[$v->id]) || count($vendorClasses[$v->id]) === 0)
                <div class="card-body small text-muted text-center py-3">
                    활성 학급이 없습니다. 아래에서 학급을 먼저 만들어주세요.
                </div>
            @else
                <div class="list-group list-group-flush">
                    @foreach($vendorClasses[$v->id] as $c)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <strong>{{ $c->name }}</strong>
                                    @if($c->grade_code)
                                        <span class="badge bg-light text-dark ms-2">{{ $c->grade_code }}</span>
                                    @endif
                                    <span class="text-muted small ms-2">학생 {{ $c->student_count }}명</span>
                                </div>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="collapse" data-bs-target="#classEdit{{ $c->id }}" title="학급 수정">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary"
                                            data-bs-toggle="modal" data-bs-target="#bulkModal{{ $c->id }}">
                                        <i class="bi bi-people-fill"></i> 학생 등록
                                    </button>
                                    <a href="{{ route('my.classes.students.import.show', $c->id) }}"
                                       class="btn btn-sm btn-outline-secondary" title="엑셀로 일괄 등록">
                                        <i class="bi bi-file-earmark-spreadsheet"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="collapse mt-2" id="classEdit{{ $c->id }}">
                                <form method="POST" action="{{ route('my.agent.classes.update', $c->id) }}"
                                      class="row g-2 align-items-end">
                                    @csrf
                                    @method('PUT')
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1">학급명 *</label>
                                        <input type="text" name="name" value="{{ $c->name }}" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted mb-1">학년</label>
                                        <select name="grade_code" class="form-select form-select-sm">
                                            <option value="">선택 안 함</option>
                                            @foreach($grades as $g)
                                                <option value="{{ $g->code }}" @selected($c->grade_code === $g->code)>{{ $g->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted mb-1">메모</label>
                                        <input type="text" name="memo" value="{{ $c->memo }}" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-check-lg"></i> 저장</button>
                                    </div>
                                </form>
                            </div>

                            {{-- 학생 여러 명 등록 모달 (학급마다 하나) --}}
                            <div class="modal fade" id="bulkModal{{ $c->id }}" tabindex="-1">
                                <div class="modal-dialog modal-xl">
                                    <div class="modal-content">
                                        <form method="POST" action="{{ route('my.agent.classes.students', $c->id) }}">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title navy">
                                                    <i class="bi bi-person-plus"></i> 학생 등록
                                                    <small class="text-muted">— {{ $v->name }} / {{ $c->name }}</small>
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-0">
                                                <div class="table-responsive" style="max-height:380px; overflow-y:auto;">
                                                    <table class="table table-sm align-middle mb-0">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th style="width:32px;"></th>
                                                                <th style="width:14%;">학생 이름</th>
                                                                <th style="width:14%;">학부모 이름</th>
                                                                <th style="width:17%;">학부모 연락처</th>
                                                                <th>주소</th>
                                                                <th style="width:36px;"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="bulk-rows"></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <div class="modal-footer d-flex justify-content-between">
                                                <div class="small text-muted">
                                                    <strong>학부모 이름·연락처</strong> 필수 (결제 요청 발송) · 빈 줄은 저장 안 됨
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <button type="button" class="btn btn-sm btn-outline-navy bulk-add">
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
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- 학급 추가 --}}
            <div class="card-footer bg-white">
                <a class="link-name" style="font-size:1.05rem" data-bs-toggle="collapse"
                   href="#classNew{{ $v->id }}" role="button">
                    <i class="bi bi-plus-circle-fill"></i> 학급 추가
                </a>
                <div class="collapse mt-2" id="classNew{{ $v->id }}">
                    <form method="POST" action="{{ route('my.agent.classes.store', $v->id) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">학급명 *</label>
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="예: 초등 3학년 A반" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">학년</label>
                            <select name="grade_code" class="form-select form-select-sm">
                                <option value="">선택 안 함</option>
                                @foreach($grades as $g)
                                    <option value="{{ $g->code }}">{{ $g->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">메모</label>
                            <input type="text" name="memo" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i> 등록</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif

<div class="alert alert-light border small text-muted mb-0">
    <i class="bi bi-info-circle"></i>
    <strong>안내</strong>:
    @if($wholesaleHidden > 0)
        도매 학원 {{ $wholesaleHidden }}곳은 학원이 일괄 매입하는 구조라 목록에서 제외됩니다.<br>
    @endif
    여기서 만든 학급은 학원 계정 화면(학급/학생)에도 그대로 보입니다.
    학원과 학급 구성을 맞춘 뒤 진행하세요. 잘못 등록된 학생은 학원 측 학급 상세에서 개별 제거 가능합니다.
</div>

<script>
// 학생 여러 명 등록 모달 — 학급마다 하나씩 있으므로 공통 초기화
(function () {
    function initBulk(modal) {
        const tbody = modal.querySelector('.bulk-rows');
        const addBtn = modal.querySelector('.bulk-add');
        if (! tbody || tbody.dataset.ready) return;
        tbody.dataset.ready = '1';
        let seq = 0;

        function render() {
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
            if (tbody.rows.length === 0) render();
            renumber();
        });
        tbody.addEventListener('input', function (e) {
            const tr = e.target.closest('tr');
            if (! tr || tr !== tbody.rows[tbody.rows.length - 1]) return;
            if (e.target.value.trim() !== '') render();
        });
        if (addBtn) addBtn.addEventListener('click', render);
        for (let i = 0; i < 5; i++) render();
    }

    // 모달이 열릴 때 준비 (학급이 많아도 필요한 것만 만든다)
    document.querySelectorAll('.modal[id^="bulkModal"]').forEach(function (m) {
        m.addEventListener('show.bs.modal', function () { initBulk(m); });
    });
})();
</script>
@endsection
