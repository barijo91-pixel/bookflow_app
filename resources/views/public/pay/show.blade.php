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
            {{-- 카톡 등 인앱 브라우저에서는 카드 결제창(INIStdPay)이 차단됨 → 외부 브라우저 유도 --}}
            <div id="inappNotice" class="pay-section" style="display:none;">
                <div style="background:#fff8e1; border:1px solid #ffe08a; border-radius:10px; padding:.9rem 1rem;">
                    <div style="font-weight:700; color:#8a6d00; margin-bottom:.35rem;">
                        <i class="bi bi-exclamation-triangle-fill"></i> 카카오톡 안에서는 카드 결제가 제한됩니다
                    </div>
                    <div class="small" style="color:#6b5600; line-height:1.55;">
                        아래 버튼을 눌러 <strong>기본 브라우저(크롬/사파리)</strong>에서 결제해 주세요.
                        <span id="inappIosHint" style="display:none;">
                            <br>iPhone은 화면 <strong>오른쪽 아래 <i class="bi bi-three-dots"></i> 또는 <i class="bi bi-box-arrow-up"></i></strong> →
                            <strong>“Safari로 열기”</strong>를 눌러주세요.
                        </span>
                    </div>
                    <button type="button" id="openExternalBtn" class="btn w-100 mt-2 py-2"
                            style="background:#8a6d00; color:#fff; font-weight:700; border-radius:8px;">
                        <i class="bi bi-box-arrow-up-right"></i> 다른 브라우저로 열기
                    </button>
                    <button type="button" id="copyPayUrlBtn" class="btn w-100 mt-2 py-2"
                            style="background:#fff; color:#8a6d00; border:1px solid #ffe08a; font-weight:600; border-radius:8px;">
                        <i class="bi bi-clipboard"></i> 결제 주소 복사
                    </button>
                </div>
            </div>

            <div class="pay-section">
                <h6><i class="bi bi-credit-card-2-front"></i> 간편하게 결제</h6>
                @if($portOneActive ?? false)
                    {{-- PortOne 실 PG 결제 — 설정된 채널(카드/카카오페이)마다 버튼 --}}
                    <div class="d-grid gap-2">
                        @foreach($portOneMethods as $m)
                            <button type="button" class="portone-pay-btn btn w-100 py-3"
                                    data-channel-key="{{ $m['channelKey'] }}" data-pay-method="{{ $m['payMethod'] }}"
                                    data-label="{{ $m['label'] }}"
                                    style="background:var(--navy); color:#fff; font-weight:700; font-size:1.05rem; border-radius:10px;">
                                <i class="bi bi-{{ $m['icon'] }}"></i> {{ $m['label'] }} 결제 {{ number_format($pr->amount) }}원
                            </button>
                        @endforeach
                    </div>
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

/* 인앱 브라우저(카카오톡/네이버/인스타 등) 감지 → 외부 브라우저 유도.
   이니시스 결제창(INIStdPay)이 인앱에서 "해당기기로는 결제 진행 불가" 로 차단되는 문제 대응. */
(function () {
    var ua = (navigator.userAgent || '').toLowerCase();
    var isKakao = ua.indexOf('kakaotalk') > -1;
    var isInApp = isKakao
        || ua.indexOf('naver') > -1 || ua.indexOf('inapp') > -1
        || ua.indexOf('instagram') > -1 || ua.indexOf('fb_iab') > -1 || ua.indexOf('fbav') > -1
        || ua.indexOf('daumapps') > -1 || ua.indexOf('line/') > -1;
    if (!isInApp) return;

    var isAndroid = ua.indexOf('android') > -1;
    var notice = document.getElementById('inappNotice');
    var iosHint = document.getElementById('inappIosHint');
    var openBtn = document.getElementById('openExternalBtn');
    var copyBtn = document.getElementById('copyPayUrlBtn');
    var url = window.location.href;

    if (notice) notice.style.display = '';
    if (!isAndroid && iosHint) iosHint.style.display = '';   // iOS는 수동 안내 (자동 전환 불가)

    // 카카오톡은 외부 브라우저로 여는 전용 스킴 지원
    function openExternal() {
        if (isKakao) {
            window.location.href = 'kakaotalk://web/openExternal?url=' + encodeURIComponent(url);
            return;
        }
        if (isAndroid) {
            // 크롬으로 강제 전환 (intent 스킴)
            var noScheme = url.replace(/^https?:\/\//, '');
            window.location.href = 'intent://' + noScheme + '#Intent;scheme=https;package=com.android.chrome;end';
            return;
        }
        // iOS 기타 인앱 — 자동 전환 불가, 주소 복사로 안내
        copyUrl(true);
    }

    function copyUrl(fromOpen) {
        var done = function () {
            alert('결제 주소가 복사되었습니다.\n\n크롬/사파리 주소창에 붙여넣어 결제해 주세요.');
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(done).catch(function () { window.prompt('아래 주소를 복사해 주세요', url); });
        } else {
            var ta = document.createElement('textarea');
            ta.value = url; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); done(); }
            catch (e) { window.prompt('아래 주소를 복사해 주세요', url); }
            document.body.removeChild(ta);
        }
    }

    if (openBtn) openBtn.addEventListener('click', openExternal);
    if (copyBtn) copyBtn.addEventListener('click', function () { copyUrl(false); });

    // 카카오톡 안드로이드는 진입 즉시 외부 브라우저로 전환 (결제 실패 경험 자체를 차단)
    if (isKakao && isAndroid) {
        setTimeout(openExternal, 400);
    }
})();
</script>

@if(($portOneActive ?? false) && in_array($pr->status, ['sent', 'viewed']))
{{-- PortOne V2 결제 SDK --}}
<script src="https://cdn.portone.io/v2/browser-sdk.js"></script>
<script>
(function() {
    const csrfToken = '{{ csrf_token() }}';
    const storeId   = {!! json_encode($portOneStoreId ?? '') !!};
    const orderName = {!! json_encode('교재 대금 — '.($vendor->name ?? 'BookSys')) !!};
    const amount    = {{ (int) $pr->amount }};
    const customer  = {
        fullName:    {!! json_encode($pr->parent_name ?? $pr->student_name ?? '') !!},
        phoneNumber: {!! json_encode($pr->parent_phone ?? '') !!},
        email:       {!! json_encode(setting('company_email', 'help@booksys.co.kr')) !!},
    };
    const verifyUrl = '{{ route('public.pay.portone', $pr->token) }}';
    const btns = document.querySelectorAll('.portone-pay-btn');

    /* 모바일 리다이렉트 복귀 처리 —
       모바일은 결제창이 별도 페이지로 열리고, 완료 후 redirectUrl 로 돌아오며
       쿼리스트링에 paymentId(성공) 또는 code/message(실패·취소)가 붙는다. */
    (function handleRedirectReturn() {
        const q = new URLSearchParams(window.location.search);
        if (!q.has('portone_return')) return;

        const paymentId = q.get('paymentId');
        const code      = q.get('code');
        const message   = q.get('message');
        // 주소창 정리 (뒤로가기 시 재검증 방지)
        history.replaceState(null, '', window.location.pathname);

        if (code) {
            if (!String(code).includes('CANCEL')) alert('결제 실패: ' + (message || code));
            return;
        }
        if (!paymentId) return;

        const box = document.createElement('div');
        box.style.cssText = 'padding:.8rem 1rem;background:#e8f1ff;color:#1e40af;border-radius:10px;margin:0 0 .8rem;font-weight:600;text-align:center;';
        box.innerHTML = '<i class="bi bi-hourglass-split"></i> 결제 확인 중입니다...';
        const wrap = document.querySelector('.pay-card') || document.body;
        wrap.prepend(box);

        fetch(verifyUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ payment_id: paymentId }),
        })
        .then(r => r.json())
        .then(j => {
            if (j.success) { window.location.href = j.redirect_url || window.location.pathname; }
            else { box.remove(); alert('결제 검증 실패: ' + (j.message || '알 수 없는 오류')); }
        })
        .catch(() => { box.remove(); alert('결제 확인 중 오류가 발생했습니다. 잠시 후 새로고침해 주세요.'); });
    })();

    btns.forEach(function (btn) {
        const orig = btn.innerHTML;
        btn.addEventListener('click', async function () {
            btns.forEach(b => b.disabled = true);
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> 결제 요청 중...';
            const paymentId = 'pay-{{ $pr->id }}-' + Date.now();
            try {
                const response = await PortOne.requestPayment({
                    storeId: storeId,
                    channelKey: btn.dataset.channelKey,
                    paymentId: paymentId,
                    orderName: orderName,
                    totalAmount: amount,
                    currency: 'CURRENCY_KRW',
                    payMethod: btn.dataset.payMethod,
                    customer: customer,
                    // 모바일은 리다이렉트 방식으로 동작 — 미지정 시 결제창이 뜨지 않음(이니시스 Dev.Error)
                    redirectUrl: window.location.origin + window.location.pathname + '?portone_return=1',
                });

                if (response && response.code != null) {
                    if (!String(response.code).includes('CANCEL')) {
                        alert('결제 실패: ' + (response.message || response.code));
                    }
                    btns.forEach(b => b.disabled = false); btn.innerHTML = orig;
                    return;
                }

                const r = await fetch(verifyUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ payment_id: response.paymentId }),
                });
                const j = await r.json();
                if (j.success) {
                    window.location.href = j.redirect_url || window.location.href;
                } else {
                    alert('결제 검증 실패: ' + (j.message || '알 수 없는 오류'));
                    btns.forEach(b => b.disabled = false); btn.innerHTML = orig;
                }
            } catch (e) {
                alert('결제 중 오류가 발생했습니다. 다시 시도해주세요.');
                btns.forEach(b => b.disabled = false); btn.innerHTML = orig;
            }
        });
    });
})();
</script>
@endif
</body>
</html>
