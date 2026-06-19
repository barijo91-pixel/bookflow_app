@extends('public.layouts.app')
@section('title', '학원 · '.$vendor->name)
@section('max_width', '1200px')

@section('content')
<div class="mb-3 d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div>
        <a href="{{ route('my.vendors.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 거래처(학원) 목록
        </a>
        <h1 class="h4 navy mb-0 mt-1">
            <i class="bi bi-building"></i> {{ $vendor->name }}
            <small class="text-muted">#{{ $vendor->id }}</small>
            @switch($vendor->status_code)
                @case('active')     <span class="badge bg-success fs-6 ms-1">정상</span> @break
                @case('suspended')  <span class="badge bg-secondary fs-6 ms-1">일시정지</span> @break
                @case('terminated') <span class="badge bg-dark fs-6 ms-1">거래종료</span> @break
            @endswitch
        </h1>
    </div>
</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="row g-3">
    {{-- LEFT: 기본 정보 편집 폼 --}}
    <div class="col-lg-7">
        <div class="card section-card">
            <div class="card-header"><strong><i class="bi bi-info-circle"></i> 기본 정보</strong></div>
            <form method="POST" action="{{ route('my.vendors.update', $vendor->id) }}">
                @csrf @method('PUT')
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">학원명 *</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $vendor->name) }}" required maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">거래구분 *</label>
                            <div class="d-flex gap-3 pt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="trade_type" id="tradeRetail" value="retail" @checked(old('trade_type', $vendor->trade_type ?? 'retail') === 'retail')>
                                    <label class="form-check-label" for="tradeRetail"><strong>소매</strong> <span class="text-muted small">학생별·학부모 결제</span></label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="trade_type" id="tradeWholesale" value="wholesale" @checked(old('trade_type', $vendor->trade_type ?? 'retail') === 'wholesale')>
                                    <label class="form-check-label" for="tradeWholesale"><strong>도매</strong> <span class="text-muted small">묶음·학원 일괄</span></label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">대표자</label>
                            <input type="text" name="owner_name" class="form-control" value="{{ old('owner_name', $vendor->owner_name) }}" maxlength="100">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">사업자번호</label>
                            <input type="text" name="business_no" class="form-control" value="{{ old('business_no', $vendor->business_no) }}" maxlength="20" placeholder="000-00-00000">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small text-muted">휴대폰</label>
                            <input type="text" name="mobile" class="form-control" value="{{ old('mobile', $vendor->mobile) }}" maxlength="20" placeholder="010-0000-0000">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">유선전화</label>
                            <input type="text" name="tel" class="form-control" value="{{ old('tel', $vendor->tel) }}" maxlength="20">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">시도</label>
                            <select id="sidoSelect" class="form-select">
                                <option value="">선택</option>
                                @foreach($sidos as $s)
                                    <option value="{{ $s->id }}" @selected($currentSidoId === $s->id)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">시군구</label>
                            <select name="region_id" id="sigunguSelect" class="form-select">
                                <option value="">선택</option>
                                @foreach($sigungus as $sg)
                                    <option value="{{ $sg->id }}" @selected($vendor->region_id == $sg->id)>{{ $sg->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label small text-muted">주소</label>
                            <div class="input-group">
                                <button type="button" class="btn btn-outline-navy" onclick="openAddrSearch()">
                                    <i class="bi bi-search"></i> 검색
                                </button>
                                <input type="text" name="address" id="addrInput" class="form-control" value="{{ old('address', $vendor->address) }}" maxlength="255">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">상세주소</label>
                            <input type="text" name="address_detail" id="addrDetailInput" class="form-control" value="{{ old('address_detail', $vendor->address_detail) }}" maxlength="255">
                        </div>
                    </div>

                    {{-- 결제 구분 --}}
                    <div class="section-divider mt-4 mb-2">
                        <small class="text-muted fw-bold text-uppercase">결제 구분</small>
                    </div>
                    <div class="row g-3 align-items-center">
                        <div class="col-md-5">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="payment_type" id="payCash" value="cash"
                                       @checked(old('payment_type', $vendor->payment_type ?? 'cash') === 'cash')>
                                <label class="form-check-label" for="payCash"><strong>현매</strong> (현금 매출)</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="payment_type" id="payCredit" value="credit"
                                       @checked(old('payment_type', $vendor->payment_type ?? 'cash') === 'credit')>
                                <label class="form-check-label" for="payCredit"><strong>여신</strong> (외상 매출)</label>
                            </div>
                        </div>
                        <div class="col-md-4" id="creditLimitWrap">
                            <label class="form-label small text-muted mb-1">여신 한도 (원)</label>
                            <input type="number" name="credit_limit" min="0" step="10000"
                                   value="{{ old('credit_limit', $vendor->credit_limit ?? 0) }}"
                                   class="form-control text-end">
                        </div>
                    </div>

                    {{-- 메모 --}}
                    <div class="section-divider mt-4 mb-2">
                        <small class="text-muted fw-bold text-uppercase">메모</small>
                    </div>
                    <textarea name="memo" class="form-control" rows="3" maxlength="2000">{{ old('memo', $vendor->memo) }}</textarea>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <small class="text-muted">등록: {{ optional($vendor->created_at)->format('Y-m-d') ?? '-' }}</small>
                    <button class="btn btn-primary"><i class="bi bi-save"></i> 저장</button>
                </div>
            </form>
        </div>
    </div>

    {{-- RIGHT: 본인 매핑 + 최근 주문 --}}
    <div class="col-lg-5">
        {{-- 본인 매핑 할인율 (참고) --}}
        <div class="card section-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-percent"></i> 본인 매핑 할인율</strong>
                <a href="{{ route('my.discounts.index', ['vendor_id' => $vendor->id]) }}" class="btn btn-sm btn-outline-navy">
                    <i class="bi bi-sliders"></i> 할인율 조정
                </a>
            </div>
            <div class="card-body py-3">
                <div class="d-flex align-items-baseline gap-2">
                    <span class="h3 navy mb-0">{{ rtrim(rtrim($myMapping->discount_rate, '0'), '.') }}%</span>
                    @if(! $myMapping->is_active)
                        <span class="badge bg-warning text-dark">중단</span>
                    @else
                        <span class="text-muted small">거래중</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- 최근 주문 --}}
        <div class="card section-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-receipt"></i> 최근 주문</strong>
                <small class="text-muted">본인 주문 기준 {{ $recentOrders->count() }}건</small>
            </div>
            <div class="card-body p-0">
                @if($recentOrders->isEmpty())
                    <div class="empty-state small">
                        <i class="bi bi-receipt"></i>
                        이 학원에 대한 주문 이력이 없습니다.
                    </div>
                @else
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>주문번호</th><th>상태</th><th class="text-end">금액</th><th>일시</th></tr>
                        </thead>
                        <tbody>
                            @foreach($recentOrders as $o)
                                <tr>
                                    <td class="small"><code>{{ $o->order_no }}</code></td>
                                    <td>
                                        @switch($o->status_code)
                                            @case('requested') <span class="badge bg-warning text-dark">접수</span> @break
                                            @case('confirmed') <span class="badge bg-info">확정</span> @break
                                            @case('accepted')  <span class="badge bg-primary">총판접수</span> @break
                                            @case('shipped')   <span class="badge bg-success">출고</span> @break
                                            @case('in_transit')<span class="badge bg-success">배송중</span> @break
                                            @case('completed') <span class="badge bg-dark">완료</span> @break
                                            @case('canceled')  <span class="badge bg-secondary">취소</span> @break
                                            @default <span class="badge bg-light text-dark">{{ $o->status_code }}</span>
                                        @endswitch
                                    </td>
                                    <td class="text-end small">{{ number_format($o->total_amount) }}원</td>
                                    <td class="text-muted small">{{ \Carbon\Carbon::parse($o->created_at)->format('m-d H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
{{-- 다음 우편번호 검색 --}}
<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
<script>
function openAddrSearch() {
    if (typeof daum === 'undefined') { alert('주소 검색 스크립트 로드 실패 — 직접 입력해주세요.'); return; }
    new daum.Postcode({
        oncomplete: async function (data) {
            const addr = data.roadAddress || data.jibunAddress;
            document.getElementById('addrInput').value = addr;
            document.getElementById('addrDetailInput')?.focus();
            const sidoSel = document.getElementById('sidoSelect');
            const sgSel   = document.getElementById('sigunguSelect');
            if (sidoSel && data.sido) {
                for (const opt of sidoSel.options) {
                    if (opt.textContent.trim() === data.sido.trim()
                        || opt.textContent.trim().startsWith(data.sido.replace(/특별시|광역시|특별자치도|특별자치시|도/g, '').trim())) {
                        sidoSel.value = opt.value;
                        sidoSel.dispatchEvent(new Event('change'));
                        setTimeout(() => {
                            if (sgSel && data.sigungu) {
                                for (const sgOpt of sgSel.options) {
                                    if (sgOpt.textContent.trim() === data.sigungu.trim()) { sgSel.value = sgOpt.value; break; }
                                }
                            }
                        }, 600);
                        break;
                    }
                }
            }
        }
    }).open();
}

// 시도 → 시군구
(function() {
    const sido = document.getElementById('sidoSelect');
    const sg   = document.getElementById('sigunguSelect');
    if (!sido) return;
    sido.addEventListener('change', async () => {
        const v = sido.value;
        if (!v) { sg.innerHTML = '<option value="">선택</option>'; return; }
        sg.innerHTML = '<option value="">로딩 중...</option>';
        try {
            const res = await fetch(`{{ route('my.regions.sigungu') }}?sido_id=${v}`);
            if (res.ok) {
                const list = await res.json();
                sg.innerHTML = '<option value="">선택</option>' + list.map(r =>
                    `<option value="${r.id}">${r.name}</option>`).join('');
            }
        } catch (e) { sg.innerHTML = '<option value="">오류</option>'; }
    });
})();

// 여신 선택 시에만 한도 입력 활성화
(function() {
    const cash = document.getElementById('payCash');
    const credit = document.getElementById('payCredit');
    const wrap = document.getElementById('creditLimitWrap');
    if (!cash || !credit || !wrap) return;
    const input = wrap.querySelector('input[name="credit_limit"]');
    function sync() {
        const isCredit = credit.checked;
        wrap.style.opacity = isCredit ? '1' : '0.4';
        if (input) input.disabled = !isCredit;
    }
    cash.addEventListener('change', sync);
    credit.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
@endsection
