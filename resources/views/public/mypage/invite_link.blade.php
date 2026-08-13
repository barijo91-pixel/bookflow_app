@extends('public.layouts.app')
@section('title', '학원 가입 링크')
@section('max_width', '900px')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-link-45deg"></i> 학원 가입 링크</h1>
    <p class="text-muted small mb-0">
        학원이 직접 가입하겠다고 하면 이 링크를 보내주세요. 이 링크로 가입한 학원은 자동으로 내 담당이 됩니다.
    </p>
</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif

<div class="card section-card mb-3">
    <div class="card-header"><strong><i class="bi bi-send"></i> 내 링크</strong></div>
    <div class="card-body">
        <div class="input-group mb-2">
            <input type="text" id="inviteUrl" class="form-control" value="{{ $url }}" readonly
                   onclick="this.select()" style="font-size:.95rem">
            <button class="btn btn-primary" type="button" id="copyBtn">
                <i class="bi bi-clipboard"></i> 복사
            </button>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="sms:?body={{ rawurlencode('[BookSys] 학원 가입 링크입니다. '.$url) }}"
               class="btn btn-sm btn-outline-navy d-md-none">
                <i class="bi bi-chat-dots"></i> 문자로 보내기
            </a>
            <form method="POST" action="{{ route('my.invite.regenerate') }}" class="d-inline"
                  onsubmit="return confirm('새 링크를 발급하면 지금 링크는 즉시 사용할 수 없게 됩니다. 계속할까요?')">
                @csrf
                <button class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-repeat"></i> 링크 재발급
                </button>
            </form>
        </div>
        <small class="text-muted d-block mt-2">
            링크가 외부에 퍼졌다고 판단되면 재발급하세요. 이전 링크는 바로 막힙니다.
        </small>
    </div>
</div>

<div class="card section-card mb-3">
    <div class="card-header"><strong><i class="bi bi-info-circle"></i> 이 링크로 가입하면</strong></div>
    <div class="card-body py-3 small">
        <ul class="mb-0 ps-3">
            <li>학원이 <strong>학원명 · 원장명 · 연락처 · 지역 · 사업자번호</strong>와 로그인 계정을 직접 입력합니다.</li>
            <li>거래처(학원)와 계정이 한 번에 만들어지고 <strong>내 담당으로 연결</strong>됩니다. (할인율 10% 시작)</li>
            <li>거래구분은 <strong>소매</strong>로 시작합니다. 도매로 거래하려면 학원 상세에서 변경하세요.</li>
            <li>가입 즉시 로그인·주문이 가능합니다. 별도 승인 절차는 없습니다.</li>
        </ul>
    </div>
</div>

<div class="card section-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong><i class="bi bi-building"></i> 내 담당 학원</strong>
        <a href="{{ route('my.vendors.index') }}" class="btn btn-sm btn-outline-navy">전체 보기</a>
    </div>
    <div class="card-body p-0">
        @if($joined->isEmpty())
            <div class="text-center text-muted py-4 small">아직 담당 학원이 없습니다.</div>
        @else
            <table class="table table-hover align-middle mb-0 table-row-highlight">
                <thead class="table-light">
                    <tr>
                        <th>학원</th>
                        <th>원장</th>
                        <th>연락처</th>
                        <th>등록일</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($joined as $v)
                        <tr>
                            <td class="text-nowrap">
                                <a href="{{ route('my.vendors.show', $v->id) }}" class="link-name">{{ $v->name }}</a>
                            </td>
                            <td class="text-muted">{{ $v->owner_name ?: '-' }}</td>
                            <td class="text-muted text-nowrap">{{ $v->mobile ? format_phone($v->mobile) : '-' }}</td>
                            <td class="text-muted text-nowrap small">
                                {{ $v->created_at ? \Illuminate\Support\Carbon::parse($v->created_at)->format('Y-m-d') : '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<script>
document.getElementById('copyBtn').addEventListener('click', async function () {
    var input = document.getElementById('inviteUrl');
    try {
        await navigator.clipboard.writeText(input.value);
    } catch (e) {
        input.select();
        document.execCommand('copy');   // 구형 브라우저 / http 환경 폴백
    }
    var old = this.innerHTML;
    this.innerHTML = '<i class="bi bi-check-lg"></i> 복사됨';
    var btn = this;
    setTimeout(function () { btn.innerHTML = old; }, 1500);
});
</script>
@endsection
