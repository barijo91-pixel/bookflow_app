@extends('public.layouts.app')
@section('title', '교재 상품 안내')
@section('max_width', '1100px')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-bag"></i> 교재 상품 안내</h1>
    <p class="text-muted small mb-0">BookSys에서 판매하는 교재입니다. 카드로 바로 결제하실 수 있습니다.</p>
</div>

@if(! $portOneActive)
    <div class="alert alert-warning small">
        <i class="bi bi-info-circle"></i> 현재 <strong>PG 미설정(테스트 모드)</strong>입니다. 관리자가 PortOne 키를 입력하면 실제 카드 결제가 활성화됩니다.
    </div>
@endif

<div class="row g-3">
    @forelse($books as $b)
        <div class="col-md-4">
            <div class="card section-card h-100">
                @if($b->cover_path)
                    <img src="{{ str_starts_with($b->cover_path, 'http') ? $b->cover_path : asset('storage/'.$b->cover_path) }}" class="card-img-top" alt="{{ $b->title }}" style="height:220px; object-fit:cover;">
                @else
                    <div class="d-flex align-items-center justify-content-center bg-light" style="height:220px;"><i class="bi bi-book" style="font-size:3rem; color:#cbd3dd;"></i></div>
                @endif
                <div class="card-body d-flex flex-column">
                    <h5 class="navy" style="font-size:1.05rem;">{{ $b->title }}</h5>
                    <div class="text-muted small mb-2">
                        <code>{{ $b->isbn }}</code>@if($b->author) · {{ $b->author }} @endif
                    </div>
                    <div class="h5 navy mb-3">{{ number_format($b->price) }}원</div>

                    <dl class="row small text-muted mb-3">
                        <dt class="col-5">배송 방법</dt><dd class="col-7">택배</dd>
                        <dt class="col-5">배송 기간</dt><dd class="col-7">결제 후 영업일 2~3일</dd>
                        <dt class="col-5">교환/반품</dt><dd class="col-7">수령 후 7일 이내</dd>
                        <dt class="col-5">환불</dt><dd class="col-7 mb-0">반품 확인 후 3영업일 이내</dd>
                    </dl>

                    <button type="button" class="btn btn-navy w-100 mt-auto store-pay-btn"
                            data-book-id="{{ $b->id }}"
                            data-title="{{ $b->title }}"
                            data-amount="{{ (int) $b->price }}">
                        <i class="bi bi-credit-card"></i> 카드 결제 {{ number_format($b->price) }}원
                    </button>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="alert alert-light border">표시할 교재가 없습니다.</div></div>
    @endforelse
</div>

<div class="alert alert-light border mt-3 small text-muted mb-0">
    <i class="bi bi-info-circle"></i> 결제 진행 시
    <a href="{{ route('legal.terms') }}" target="_blank">이용약관</a> ·
    <a href="{{ route('legal.privacy') }}" target="_blank">개인정보처리방침</a> ·
    <a href="{{ route('legal.refund') }}" target="_blank">취소·환불정책</a>에 동의하는 것으로 간주됩니다.
</div>
@endsection

@push('scripts')
@if($portOneActive)
<script src="https://cdn.portone.io/v2/browser-sdk.js"></script>
@endif
<script>
(function () {
    var active     = {{ $portOneActive ? 'true' : 'false' }};
    var storeId    = {!! json_encode($portOneStoreId ?? '') !!};
    var channelKey = {!! json_encode($portOneChannelKey ?? '') !!};
    var verifyUrl  = '{{ route('my.store.verify') }}';
    var csrf       = '{{ csrf_token() }}';

    document.querySelectorAll('.store-pay-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!active) { alert('현재 테스트 모드입니다. 관리자가 PG 키를 설정하면 결제가 가능합니다.'); return; }
            var bookId = btn.dataset.bookId;
            var title  = btn.dataset.title;
            var amount = parseInt(btn.dataset.amount, 10);
            var orig   = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> 결제 요청 중...';
            var paymentId = 'store-' + bookId + '-' + Date.now();
            try {
                var response = await PortOne.requestPayment({
                    storeId: storeId,
                    channelKey: channelKey,
                    paymentId: paymentId,
                    orderName: title,
                    totalAmount: amount,
                    currency: 'CURRENCY_KRW',
                    payMethod: 'CARD',
                });
                if (response && response.code != null) {
                    if (!String(response.code).includes('CANCEL')) alert('결제 실패: ' + (response.message || response.code));
                    btn.disabled = false; btn.innerHTML = orig; return;
                }
                var r = await fetch(verifyUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ payment_id: response.paymentId, book_id: bookId }),
                });
                var j = await r.json();
                alert(j.message || (j.success ? '결제 완료' : '검증 실패'));
                btn.disabled = false; btn.innerHTML = orig;
            } catch (e) {
                alert('결제 중 오류가 발생했습니다. 다시 시도해주세요.');
                btn.disabled = false; btn.innerHTML = orig;
            }
        });
    });
})();
</script>
@endpush
