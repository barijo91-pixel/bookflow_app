<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $vendor->name ?? 'BookSys' }} — 교재 결제 안내</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --navy:#1f3a5f; --navy-dark:#15294a; }
        body { font-family: 'Noto Sans KR', sans-serif; background:#f6f7fb; margin:0; }
        .pay-wrap { max-width:520px; margin:0 auto; padding:1rem; }
        .pay-header { background: var(--navy); color:#fff; padding:1.4rem 1rem; border-radius:14px 14px 0 0; text-align:center; }
        .pay-card { background:#fff; border-radius:0 0 14px 14px; padding:1.4rem 1.2rem; box-shadow:0 2px 12px rgba(0,0,0,0.06); }
        .pay-amount { font-size: 2rem; font-weight: 800; color: var(--navy); text-align:center; }
        .pay-section { padding: 1rem 0; border-top:1px solid #eef0f4; }
        .pay-section:first-of-type { border-top:none; }
        .pay-section h6 { color: var(--navy); font-weight:700; margin-bottom:.6rem; font-size:.95rem; }
        .bank-info { background:#f6f7fb; border-radius:10px; padding:.9rem 1rem; }
        .bank-row { display:flex; justify-content:space-between; padding:.2rem 0; font-size:.95rem; }
        .bank-row .label { color:#6c757d; font-size:.85rem; }
        .copy-btn { background:none; border:1px solid var(--navy); color: var(--navy); padding:.2rem .6rem; border-radius:6px; font-size:.8rem; cursor:pointer; }
        .copy-btn:hover { background: var(--navy); color:#fff; }
        .status-badge { display:inline-block; padding:.3rem .8rem; border-radius:999px; font-size:.8rem; font-weight:600; }
        .status-sent { background:#dbeafe; color:#1e40af; }
        .status-viewed { background:#e0e7ff; color:#3730a3; }
        .status-paid { background:#d1fae5; color:#065f46; }
        .status-expired, .status-canceled { background:#fee2e2; color:#991b1b; }
        .items-list { margin: .8rem 0; }
        .items-list li { padding:.3rem 0; border-bottom:1px dashed #eef0f4; font-size:.9rem; }
        .items-list li:last-child { border-bottom:none; }
        footer { text-align:center; padding:1.5rem 1rem; color:#94a3b8; font-size:.8rem; }
    </style>
</head>
<body>
<div class="pay-wrap">
    <div class="pay-header">
        <div style="opacity:.85; font-size:.85rem;">{{ $vendor->name ?? '' }}</div>
        <h1 style="font-size:1.4rem; margin:.4rem 0 0;">교재 결제 안내</h1>
    </div>
    <div class="pay-card">

        @if(session('success'))
            <div class="alert alert-success small text-center mb-3">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger small text-center mb-3">{{ session('error') }}</div>
        @endif

        @if($pr->status === 'paid')
            <div class="text-center mb-3">
                <span class="status-badge status-paid">
                    <i class="bi bi-check-circle-fill"></i> 결제 완료
                </span>
                <p class="small text-muted mt-2 mb-0">
                    {{ \Carbon\Carbon::parse($pr->paid_at)->format('Y-m-d H:i') }} 결제 확인됨
                </p>
            </div>
        @elseif($pr->status === 'expired')
            <div class="text-center mb-3">
                <span class="status-badge status-expired">
                    <i class="bi bi-clock-history"></i> 만료된 결제 요청
                </span>
                <p class="small text-muted mt-2 mb-0">학원에 새 결제 요청을 요청해주세요.</p>
            </div>
        @elseif($pr->status === 'canceled')
            <div class="text-center mb-3">
                <span class="status-badge status-canceled">
                    <i class="bi bi-x-circle"></i> 취소된 결제 요청
                </span>
            </div>
        @endif

        <div class="pay-section">
            <h6>결제 금액</h6>
            <div class="pay-amount">{{ number_format($pr->amount) }}원</div>
            <p class="text-center small text-muted mt-2 mb-0">
                {{ $pr->student_name }} 학생 · {{ $pr->parent_name ?? '학부모님' }}
            </p>
        </div>

        @if(! empty($items))
            <div class="pay-section">
                <h6>교재 내역</h6>
                <ul class="items-list list-unstyled">
                    @foreach($items as $it)
                        <li class="d-flex justify-content-between">
                            <span>{{ $it['title'] ?? '-' }}</span>
                            <span class="text-muted">{{ $it['qty'] ?? 1 }}권</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(in_array($pr->status, ['sent', 'viewed']))
            <div class="pay-section">
                <h6><i class="bi bi-credit-card-2-front"></i> 카드로 간편하게 결제</h6>
                @if($portOneActive ?? false)
                    {{-- PortOne 실 PG 결제 --}}
                    <button type="button" id="portOnePayBtn" class="btn w-100 py-3"
                            style="background:var(--navy); color:#fff; font-weight:700; font-size:1.05rem; border-radius:10px;">
                        <i class="bi bi-credit-card"></i> 카드 결제 {{ number_format($pr->amount) }}원
                    </button>
                    <p class="small text-center text-muted mt-2 mb-0" style="font-size:.75rem;">
                        <i class="bi bi-shield-check"></i> 안전한 PG사 결제 시스템
                    </p>
                @else
                    {{-- mock 결제 (PortOne 미설정 시 fallback) --}}
                    <form method="POST" action="{{ route('public.pay.mock', $pr->token) }}">
                        @csrf
                        <button type="submit" class="btn w-100 py-3" style="background:var(--navy); color:#fff; font-weight:700; font-size:1.05rem; border-radius:10px;">
                            <i class="bi bi-credit-card"></i> 카드 결제 {{ number_format($pr->amount) }}원
                        </button>
                    </form>
                    <p class="small text-center text-warning mt-2 mb-0" style="font-size:.75rem;">
                        <i class="bi bi-info-circle"></i> 현재 <strong>테스트 모드</strong>입니다. 관리자가 PG 키 설정 시 실 결제 가능합니다.
                    </p>
                @endif
            </div>

            <div class="pay-section">
                <h6>또는 계좌 입금</h6>
                @if($distributor && $distributor->bank_account)
                    <div class="bank-info">
                        <div class="bank-row"><span class="label">은행</span><strong>{{ $bankName ?? $distributor->bank_code }}</strong></div>
                        <div class="bank-row align-items-center">
                            <span class="label">계좌번호</span>
                            <span><strong id="accNum">{{ $distributor->bank_account }}</strong>
                                <button class="copy-btn" onclick="copyAcc()">복사</button>
                            </span>
                        </div>
                        <div class="bank-row"><span class="label">예금주</span><strong>{{ $distributor->bank_holder ?? '-' }}</strong></div>
                        <div class="bank-row mt-2 pt-2 border-top">
                            <span class="label">입금자명</span>
                            <strong>{{ $pr->parent_name ?? $pr->student_name }}</strong>
                        </div>
                    </div>
                    <p class="small text-muted mt-2 mb-0">
                        <i class="bi bi-info-circle"></i>
                        입금 후 학원으로 연락 주시면 확인 후 교재가 전달됩니다.
                    </p>
                @else
                    <div class="alert alert-warning small mb-0">
                        입금 계좌 정보가 아직 설정되지 않았습니다. 학원에 문의해주세요.
                    </div>
                @endif
            </div>

            @if($pr->memo)
                <div class="pay-section">
                    <h6>학원 메모</h6>
                    <p class="small text-muted mb-0">{{ $pr->memo }}</p>
                </div>
            @endif

            @if($pr->expires_at)
                <div class="text-center small text-muted">
                    <i class="bi bi-clock"></i>
                    이 결제 요청은 {{ \Carbon\Carbon::parse($pr->expires_at)->format('Y년 m월 d일') }}까지 유효합니다.
                </div>
            @endif
        @endif

    </div>
</div>

<footer>
    Powered by BookSys · 안전한 교재 거래 플랫폼
</footer>

<script>
function copyAcc() {
    const acc = document.getElementById('accNum').textContent.trim();
    navigator.clipboard.writeText(acc).then(() => {
        alert('계좌번호가 복사되었습니다.\n\n' + acc);
    });
}
</script>

@if(($portOneActive ?? false) && in_array($pr->status, ['sent', 'viewed']))
{{-- PortOne (구 아임포트) SDK --}}
<script src="https://cdn.iamport.kr/v1/iamport.js"></script>
<script>
(function() {
    const IMP = window.IMP;
    IMP.init('{{ $portOneImpUid }}');

    const merchantUid = 'pay_{{ $pr->id }}_' + Date.now();
    const csrfToken = '{{ csrf_token() }}';

    document.getElementById('portOnePayBtn').addEventListener('click', function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> 결제 요청 중...';

        IMP.request_pay({
            pg: 'html5_inicis',  // 또는 'kakaopay', 'tosspayments' — 관리자가 선택 가능하게 추후 확장
            pay_method: 'card',
            merchant_uid: merchantUid,
            name: '교재 대금 — {{ $vendor->name ?? 'BookSys' }}',
            amount: {{ (int) $pr->amount }},
            buyer_name: {!! json_encode($pr->parent_name ?? $pr->student_name ?? '') !!},
            buyer_tel: {!! json_encode($pr->parent_phone ?? '') !!},
        }, function(rsp) {
            if (rsp.success) {
                // 결제 성공 → 서버 검증 호출
                fetch('{{ route('public.pay.portone', $pr->token) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        imp_uid: rsp.imp_uid,
                        merchant_uid: rsp.merchant_uid,
                    }),
                })
                .then(r => r.json())
                .then(j => {
                    if (j.success) {
                        window.location.href = j.redirect_url || window.location.href;
                    } else {
                        alert('결제 검증 실패: ' + (j.message || '알 수 없는 오류'));
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-credit-card"></i> 카드 결제 {{ number_format($pr->amount) }}원';
                    }
                })
                .catch(err => {
                    alert('서버 통신 오류. 다시 시도해주세요.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-credit-card"></i> 카드 결제 {{ number_format($pr->amount) }}원';
                });
            } else {
                // 결제 실패/취소
                if (rsp.error_msg && !rsp.error_msg.includes('USER_CANCEL')) {
                    alert('결제 실패: ' + rsp.error_msg);
                }
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-credit-card"></i> 카드 결제 {{ number_format($pr->amount) }}원';
            }
        });
    });
})();
</script>
@endif
</body>
</html>
