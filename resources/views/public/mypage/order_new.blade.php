@extends('public.layouts.app')
@section('title', '도서 주문하기')
@section('max_width', '1200px')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-bag-plus"></i> 도서 주문하기</h1>
    <p class="text-muted small mb-0">
        @if($vendor)
            <strong>{{ $vendor->name }}</strong> · 영업자 선택 후 도서를 담아 주문하세요.
        @else
            <span class="text-danger">학원 매핑이 없습니다. 관리자에게 문의해주세요.</span>
        @endif
    </p>
</div>

@if(!$vendor)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> 학원이 연결되지 않은 계정입니다. 관리자에게 vendor_users 매핑을 요청해주세요.
    </div>
    @php return; @endphp
@endif

@if($agents->isEmpty())
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> 이 학원에 매핑된 영업자가 없습니다. 관리자에게 문의해주세요.
    </div>
    @php return; @endphp
@endif

{{-- 영업자 선택 --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('my.order_new') }}" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">영업자 (담당)</label>
                <select name="agent_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($agents as $a)
                        <option value="{{ $a->id }}" @selected($selectedAgent && $a->id == $selectedAgent->id)>
                            {{ $a->name }} · 기본 할인율 {{ rtrim(rtrim($a->general_rate, '0'), '.') }}%
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">도서 검색 (제목 / ISBN / 시리즈)</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="예: Bricks Phonics, 9788...">
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-sm btn-navy"><i class="bi bi-search"></i> 검색</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    {{-- 좌측: 도서 목록 --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-journals"></i> 도서 목록</strong>
                <span class="small text-muted">{{ $books->count() }}건 표시 (최대 30건)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>도서</th>
                            <th class="text-end">정가</th>
                            <th class="text-end">할인가</th>
                            <th style="width:90px">수량</th>
                            <th style="width:70px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($books as $b)
                            @php
                                $rate = $bookDiscounts->get($b->id, $selectedAgent->general_rate ?? 0);
                                $unit = (int) round($b->price * (100 - $rate) / 100);
                                $hasBookDiscount = $bookDiscounts->has($b->id);
                            @endphp
                            <tr>
                                <td class="small">
                                    <strong>{{ $b->title }}</strong>
                                    @if($b->subtitle)<span class="text-muted">— {{ $b->subtitle }}</span>@endif
                                    <div class="text-muted small">
                                        <code>{{ $b->isbn }}</code>
                                        @if($b->publisher_name) · {{ $b->publisher_name }} @endif
                                    </div>
                                </td>
                                <td class="text-end small text-muted">{{ number_format($b->price) }}원</td>
                                <td class="text-end small">
                                    <span class="fw-bold navy">{{ number_format($unit) }}원</span>
                                    <div class="text-muted small">
                                        {{ rtrim(rtrim($rate, '0'), '.') }}%
                                        @if($hasBookDiscount)<i class="bi bi-star-fill text-warning" title="개별 할인율"></i>@endif
                                    </div>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('my.cart.add') }}" class="d-flex gap-1">
                                        @csrf
                                        <input type="hidden" name="book_id" value="{{ $b->id }}">
                                        <input type="hidden" name="cart_key" value="{{ $cartKey }}">
                                        <input type="number" name="qty" value="1" min="1" max="9999" class="form-control form-control-sm text-end" style="width:70px">
                                </td>
                                <td>
                                        <button class="btn btn-sm btn-outline-navy w-100"><i class="bi bi-plus"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">
                                @if($q) "{{ $q }}" 검색 결과가 없습니다.
                                @else 등록된 도서가 없습니다. @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 우측: 장바구니 --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="position:sticky;top:1rem">
            <div class="card-header bg-white">
                <strong><i class="bi bi-cart"></i> 장바구니 ({{ $cartLines->count() }})</strong>
            </div>
            <div class="card-body p-0">
                @if($cartLines->isEmpty())
                    <div class="text-muted text-center py-4 small">담은 도서가 없습니다.</div>
                @else
                    <form method="POST" action="{{ route('my.cart.update') }}" id="cartForm">
                        @csrf
                        <input type="hidden" name="cart_key" value="{{ $cartKey }}">
                        <table class="table table-sm mb-0">
                            <tbody>
                                @foreach($cartLines as $line)
                                    @php $b = $line['book']; @endphp
                                    <tr>
                                        <td class="small p-2">
                                            <div class="fw-bold">{{ \Illuminate\Support\Str::limit($b->title, 30) }}</div>
                                            <div class="text-muted small">
                                                {{ number_format($line['unit_price']) }}원 × {{ $line['qty'] }} =
                                                <span class="fw-bold navy">{{ number_format($line['line_total']) }}원</span>
                                            </div>
                                            <div class="d-flex gap-1 mt-1 align-items-center">
                                                <input type="number" name="qty[{{ $b->id }}]" value="{{ $line['qty'] }}" min="0" max="9999"
                                                       class="form-control form-control-sm text-end" style="width:70px">
                                                <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-auto"
                                                        onclick="removeCartItem({{ $b->id }})" title="제거">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="p-3 border-top">
                            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">
                                <i class="bi bi-arrow-clockwise"></i> 수량 업데이트
                            </button>
                        </div>
                    </form>

                    <div class="p-3 border-top bg-light">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">소계 ({{ $cartLines->sum('qty') }}권)</span>
                            <strong class="navy">{{ number_format($subtotal) }}원</strong>
                        </div>
                        <form method="POST" action="{{ route('my.order.store') }}"
                              onsubmit="return confirm('이 내용으로 주문하시겠습니까?')">
                            @csrf
                            <input type="hidden" name="cart_key" value="{{ $cartKey }}">
                            <input type="hidden" name="agent_id" value="{{ $selectedAgent->id }}">
                            <button type="submit" class="btn btn-navy w-100 btn-lg">
                                <i class="bi bi-check-lg"></i> 주문하기
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- 카트 제거용 숨김 폼 --}}
<form method="POST" action="{{ route('my.cart.remove') }}" id="removeForm" class="d-none">
    @csrf
    <input type="hidden" name="cart_key" value="{{ $cartKey }}">
    <input type="hidden" name="book_id" id="removeBookId">
</form>

@push('scripts')
<script>
function removeCartItem(bookId) {
    if (!confirm('장바구니에서 제거할까요?')) return;
    document.getElementById('removeBookId').value = bookId;
    document.getElementById('removeForm').submit();
}
</script>
@endpush
@endsection
