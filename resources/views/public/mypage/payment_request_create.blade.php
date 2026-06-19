@extends('public.layouts.app')
@section('title', '학부모 결제 요청 — #'.$order->order_no)
@section('max_width', '1100px')

@section('content')
<div class="mb-3">
    <a href="{{ route('my.orders.show', $order->id) }}" class="text-muted small text-decoration-none">
        <i class="bi bi-arrow-left"></i> 주문 상세
    </a>
    <h1 class="h4 navy mt-1 mb-1">
        <i class="bi bi-chat-dots"></i> 학부모 결제 요청
    </h1>
    <p class="text-muted small mb-0">
        주문 <code>{{ $order->order_no }}</code> · {{ $vendor->name ?? '-' }} ·
        총액 <strong class="navy">{{ number_format($order->total_amount) }}원</strong>
    </p>
</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="row g-3">
    {{-- LEFT: 학급 선택 + 학생/학부모 + 금액 --}}
    <div class="col-lg-7">
        <form method="POST" action="{{ route('my.orders.payment.store', $order->id) }}" id="payReqForm">
            @csrf

            <div class="card section-card mb-3">
                <div class="card-header"><strong>1. 학급 선택</strong></div>
                <div class="card-body">
                    @if($classes->isEmpty())
                        <div class="alert alert-warning small mb-0">
                            등록된 학급이 없습니다.
                            <a href="{{ route('my.classes.index') }}">학급/학생 페이지</a>에서 먼저 등록해주세요.
                        </div>
                    @else
                        <select name="class_id" id="classSelect" class="form-select form-select-lg">
                            <option value="">학급 선택...</option>
                            @foreach($classes as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}@if($c->grade_code) ({{ $c->grade_code }})@endif</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            </div>

            <div class="card section-card mb-3" id="studentsCard" style="display:none;">
                <div class="card-header">
                    <strong>2. 학생/학부모 선택</strong>
                    <span class="text-muted small ms-2">금액은 자동 산정 (수정 불가)</span>
                </div>
                @if($recommendedAmount > 0)
                    <div class="card-body py-2 small text-muted border-bottom">
                        <i class="bi bi-info-circle"></i>
                        학생 1명당 권장 금액 <strong class="navy">{{ number_format($recommendedAmount) }}원</strong>
                        (도서 1세트 정가 {{ number_format($setListPrice) }}원 × 90% 도서정가제 소매가)
                    </div>
                @endif
                <div id="studentsLoading" class="card-body small text-muted">학생 목록을 불러오는 중...</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" id="studentsTable" style="display:none;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:50px"><input type="checkbox" id="checkAll"></th>
                                <th>학생</th>
                                <th>학부모</th>
                                <th>연락처</th>
                                <th class="text-end" style="width:160px">금액(원)</th>
                            </tr>
                        </thead>
                        <tbody id="studentsTbody"></tbody>
                    </table>
                </div>
                <div id="studentsEmpty" class="card-body small text-muted text-center" style="display:none;">
                    이 학급에 등록된 학생이 없습니다.
                </div>
            </div>

            <div class="card section-card mb-3">
                <div class="card-header"><strong>3. 메모 (선택)</strong></div>
                <div class="card-body">
                    <textarea name="memo" rows="2" class="form-control" maxlength="500"
                              placeholder="예: 2학기 영어 교재"></textarea>
                </div>
            </div>

            <div class="d-grid">
                <button class="btn btn-primary btn-lg" id="sendBtn" disabled>
                    <i class="bi bi-send"></i> 선택한 학부모에게 결제 요청 보내기
                </button>
            </div>
        </form>
    </div>

    {{-- RIGHT: 주문 도서 + 이미 보낸 이력 --}}
    <div class="col-lg-5">
        <div class="card section-card mb-3">
            <div class="card-header"><strong>주문 도서</strong></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>도서</th><th class="text-end">수량</th><th class="text-end">소계</th></tr>
                    </thead>
                    <tbody>
                        @foreach($items as $it)
                            <tr>
                                <td class="small">{{ $it->book_title ?? $it->title_snapshot }}</td>
                                <td class="text-end small">{{ $it->qty }}</td>
                                <td class="text-end small">{{ number_format($it->line_total) }}원</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($existing->isNotEmpty())
            <div class="card section-card">
                <div class="card-header"><strong>이 주문의 결제 요청 이력 ({{ $existing->count() }})</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>학생</th><th>학부모</th><th class="text-end">금액</th><th>상태</th><th>발송</th></tr>
                        </thead>
                        <tbody>
                            @foreach($existing as $e)
                                <tr>
                                    <td class="small">{{ $e->student_name ?? '-' }}</td>
                                    <td class="small">{{ $e->parent_name ?? '-' }}</td>
                                    <td class="text-end small">{{ number_format($e->amount) }}</td>
                                    <td>
                                        @switch($e->status)
                                            @case('sent')    <span class="badge bg-info">발송</span> @break
                                            @case('viewed')  <span class="badge bg-primary">열람</span> @break
                                            @case('paid')    <span class="badge bg-success">결제완료</span> @break
                                            @case('expired') <span class="badge bg-secondary">만료</span> @break
                                            @case('canceled')<span class="badge bg-dark">취소</span> @break
                                            @default <span class="badge bg-light text-dark">{{ $e->status }}</span>
                                        @endswitch
                                    </td>
                                    <td class="small text-muted">
                                        {{ $e->sent_at ? \Carbon\Carbon::parse($e->sent_at)->format('m-d H:i') : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
const studentsUrl = "{{ url('/mypage/classes') }}";
const classSel    = document.getElementById('classSelect');
const card        = document.getElementById('studentsCard');
const loading     = document.getElementById('studentsLoading');
const table       = document.getElementById('studentsTable');
const tbody       = document.getElementById('studentsTbody');
const empty       = document.getElementById('studentsEmpty');
const sendBtn     = document.getElementById('sendBtn');
const checkAll    = document.getElementById('checkAll');

function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function fmtPhone(p) {
    if (!p) return '-';
    const d = String(p).replace(/\D/g, '');
    if (d.length === 11) return d.replace(/(\d{3})(\d{4})(\d{4})/, '$1-$2-$3');
    if (d.length === 10) return d.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3');
    return p;
}

classSel?.addEventListener('change', async () => {
    const id = classSel.value;
    if (!id) {
        card.style.display = 'none';
        updateSendBtn();
        return;
    }
    card.style.display = '';
    loading.style.display = '';
    table.style.display = 'none';
    empty.style.display = 'none';
    tbody.innerHTML = '';

    try {
        const res = await fetch(`${studentsUrl}/${id}/students-with-parents`);
        const list = await res.json();
        loading.style.display = 'none';
        if (!list.length) { empty.style.display = ''; updateSendBtn(); return; }

        table.style.display = '';
        list.forEach((s, idx) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="checkbox" class="row-check" data-idx="${idx}"></td>
                <td class="small">${escapeHtml(s.student_name)} ${s.grade_code ? `<small class="text-muted">${escapeHtml(s.grade_code)}</small>` : ''}</td>
                <td class="small">${escapeHtml(s.parent_name) || '<span class="text-muted">미등록</span>'}</td>
                <td class="small">${s.parent_phone ? fmtPhone(s.parent_phone) : '<span class="text-danger">없음</span>'}</td>
                <td>
                    <input type="hidden" name="recipients[${idx}][student_id]" value="${s.student_id}" disabled>
                    <input type="number" name="recipients[${idx}][amount]" min="0" step="100"
                           class="form-control form-control-sm text-end row-amount bg-light"
                           value="{{ (int) ($recommendedAmount ?? 0) }}" readonly disabled>
                </td>
            `;
            tbody.appendChild(tr);
        });
        updateSendBtn();
    } catch (e) {
        loading.innerHTML = '<span class="text-danger">학생 목록 로드 실패</span>';
    }
});

checkAll?.addEventListener('change', () => {
    document.querySelectorAll('.row-check').forEach(cb => {
        cb.checked = checkAll.checked;
        cb.dispatchEvent(new Event('change'));
    });
});

document.addEventListener('change', (e) => {
    if (e.target.classList.contains('row-check')) {
        const tr = e.target.closest('tr');
        const checked = e.target.checked;
        tr.querySelectorAll('input[name^="recipients"]').forEach(i => i.disabled = !checked);
        tr.style.opacity = checked ? '1' : '0.55';
        updateSendBtn();
    }
});

document.addEventListener('input', (e) => {
    if (e.target.classList.contains('row-amount')) updateSendBtn();
});

function applyBulk() {
    const val = parseInt(document.getElementById('bulkAmount').value, 10) || 0;
    applyAmountToAll(val);
}

function applyRecommended() {
    applyAmountToAll({{ (int) ($recommendedAmount ?? 0) }});
}

function applyAmountToAll(val) {
    document.querySelectorAll('.row-check').forEach((cb) => {
        cb.checked = true;
        cb.dispatchEvent(new Event('change'));
        const tr = cb.closest('tr');
        const amt = tr.querySelector('.row-amount');
        amt.value = val;
    });
    updateSendBtn();
}

function updateSendBtn() {
    const valid = Array.from(document.querySelectorAll('.row-check'))
        .filter(cb => cb.checked)
        .filter(cb => {
            const amt = cb.closest('tr').querySelector('.row-amount');
            return amt && parseInt(amt.value, 10) > 0;
        });
    sendBtn.disabled = valid.length === 0;
    if (valid.length > 0) {
        sendBtn.innerHTML = `<i class="bi bi-send"></i> ${valid.length}명에게 결제 요청 보내기`;
    } else {
        sendBtn.innerHTML = `<i class="bi bi-send"></i> 선택한 학부모에게 결제 요청 보내기`;
    }
}
</script>
@endpush
@endsection
