@extends('public.layouts.app')
@section('title', '주문 수정 #'.$order->order_no)

@section('content')
<div class="mb-3">
    <a href="{{ route('my.orders.show', $order->id) }}" class="text-muted small text-decoration-none">
        <i class="bi bi-arrow-left"></i> 주문 상세로
    </a>
    <h1 class="h4 navy mt-1 mb-1">
        <i class="bi bi-pencil-square"></i> 주문 수정 <code>{{ $order->order_no }}</code>
    </h1>
    <p class="text-muted small mb-0">
        {{ $vendor->name ?? '-' }} · 접수 대기 상태에서만 수정 가능. 도서 단가/할인율은 변경 불가 (수량 조정·삭제만).
    </p>
</div>

@if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ route('my.orders.update', $order->id) }}"
      onsubmit="return confirm('수정 사항을 저장하시겠습니까?\n· 수량 0 또는 삭제 체크된 도서는 주문에서 제거됩니다.')">
    @csrf @method('PUT')

    <div class="card section-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><i class="bi bi-book"></i> 주문 도서 ({{ count($items) }}건)</strong>
            <small class="text-muted">현재 총액 <strong class="navy">{{ number_format($order->total_amount) }}원</strong></small>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="editTable">
                <thead class="table-light">
                    <tr>
                        <th>도서</th>
                        <th class="text-end" style="width:100px">단가</th>
                        <th class="text-center" style="width:140px">수량</th>
                        <th class="text-end" style="width:120px">소계</th>
                        <th class="text-center" style="width:80px">삭제</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $i => $it)
                        <tr data-row data-unit="{{ (int) $it->unit_price }}">
                            <td class="small">
                                <input type="hidden" name="items[{{ $i }}][id]" value="{{ $it->id }}">
                                <strong>{{ $it->book_title ?? $it->title_snapshot }}</strong>
                                <div class="text-muted small"><code>{{ $it->book_isbn ?? $it->isbn_snapshot }}</code></div>
                                <div class="text-muted small">
                                    정가 {{ number_format($it->list_price) }}원 ·
                                    할인 {{ rtrim(rtrim($it->discount_rate, '0'), '.') }}%
                                </div>
                            </td>
                            <td class="text-end small">{{ number_format($it->unit_price) }}원</td>
                            <td class="text-center">
                                <input type="number" name="items[{{ $i }}][qty]" value="{{ $it->qty }}"
                                       min="0" max="99999"
                                       class="form-control form-control-sm text-end qty-input"
                                       style="max-width:90px; margin:auto;">
                            </td>
                            <td class="text-end small navy fw-bold line-total">{{ number_format($it->line_total) }}원</td>
                            <td class="text-center">
                                <div class="form-check d-inline-block">
                                    <input type="checkbox" name="items[{{ $i }}][delete]" value="1"
                                           class="form-check-input delete-check"
                                           id="del-{{ $it->id }}">
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="3" class="text-end fw-bold">새 총액</td>
                        <td class="text-end fw-bold navy fs-6" id="newTotal">{{ number_format($order->total_amount) }}원</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-body">
            <label class="form-label small text-muted mb-1">수정 사유 (선택)</label>
            <input type="text" name="reason" class="form-control form-control-sm" maxlength="500"
                   placeholder="예: 학년이 바뀌어 수량 조정">
        </div>
        <div class="card-footer text-end">
            <a href="{{ route('my.orders.show', $order->id) }}" class="btn btn-sm btn-link text-muted me-2">취소</a>
            <button class="btn btn-primary"><i class="bi bi-check-lg"></i> 수정 저장</button>
        </div>
    </div>

    <div class="alert alert-light border mt-3 small text-muted mb-0">
        <i class="bi bi-info-circle"></i>
        <strong>안내</strong>:
        수량을 <strong>0</strong>으로 두거나 <strong>삭제</strong>를 체크하면 해당 도서는 주문에서 제거됩니다.
        도서 추가가 필요하면 이 주문을 취소하고 다시 주문하세요.
        단가/할인율은 주문 시점 스냅샷이라 변경되지 않습니다.
    </div>
</form>

@push('scripts')
<script>
(function () {
    const fmt = n => new Intl.NumberFormat('ko-KR').format(n);
    const table = document.getElementById('editTable');
    if (!table) return;

    function recalc() {
        let total = 0;
        table.querySelectorAll('[data-row]').forEach(tr => {
            const unit = parseInt(tr.dataset.unit, 10) || 0;
            const qtyInput = tr.querySelector('.qty-input');
            const delCheck = tr.querySelector('.delete-check');
            const lineCell = tr.querySelector('.line-total');
            const deleted = delCheck.checked || parseInt(qtyInput.value, 10) === 0;
            const qty = parseInt(qtyInput.value, 10) || 0;
            const line = deleted ? 0 : unit * qty;
            lineCell.textContent = fmt(line) + '원';
            tr.style.opacity = deleted ? '0.4' : '1';
            qtyInput.disabled = delCheck.checked;
            total += line;
        });
        document.getElementById('newTotal').textContent = fmt(total) + '원';
    }
    table.addEventListener('input', e => { if (e.target.matches('.qty-input')) recalc(); });
    table.addEventListener('change', e => { if (e.target.matches('.delete-check')) recalc(); });
})();
</script>
@endpush
@endsection
