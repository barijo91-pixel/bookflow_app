@extends('admin.layouts.admin')
@section('title', '문자 발송')

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.notifications.logs') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 발송 이력
        </a>
        <h1 class="h4 mb-0 mt-1">문자(SMS) 발송</h1>
        <p class="text-muted small mb-0 mt-1">선택한 사용자 또는 직접 입력한 번호로 문자를 보냅니다.</p>
    </div>
</div>

@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@unless($aligoReady)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>알리고 키가 설정되지 않았습니다.</strong> 사이트 설정 &gt; 외부 연동에서 키를 입력하고,
        알리고에서 <strong>발신번호 사전등록 + SMS 충전</strong>을 완료해야 실제 발송됩니다. (그 전까지는 발송이 건너뛰어집니다)
    </div>
@endunless

<form method="POST" action="{{ route('admin.notifications.send') }}"
      onsubmit="return confirm('선택한 대상에게 문자를 발송할까요? (건당 과금)')">
    @csrf
    <div class="row g-3">
        {{-- 왼쪽: 대상 선택 --}}
        <div class="col-lg-7">
            <div class="card section-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>받는 사람</strong>
                    <span class="small text-muted">선택 <span id="selCount">0</span>명</span>
                </div>
                <div class="card-body">
                    @php $grouped = $users->groupBy('role_code'); $roleLabel = ['distributor'=>'총판','agent'=>'영업자','academy'=>'학원']; @endphp

                    @forelse($grouped as $role => $list)
                        <div class="mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <strong class="small">{{ $roleLabel[$role] ?? $role }} <span class="text-muted">({{ $list->count() }})</span></strong>
                                <button type="button" class="btn btn-sm btn-link p-0 ms-2 role-all" data-role="{{ $role }}">전체선택</button>
                            </div>
                            <div class="row g-1">
                                @foreach($list as $u)
                                    <div class="col-md-6">
                                        <label class="d-flex align-items-center gap-2 small border rounded px-2 py-1" style="cursor:pointer">
                                            <input type="checkbox" name="user_ids[]" value="{{ $u->id }}" class="form-check-input m-0 recip" data-role="{{ $role }}">
                                            <span>{{ $u->name }} <span class="text-muted">{{ format_phone($u->phone) }}</span></span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">문자 받을 수 있는 사용자가 없습니다.</p>
                    @endforelse

                    <hr>
                    <label class="form-label small text-muted">직접 번호 입력 (선택)</label>
                    <textarea name="extra_phones" class="form-control" rows="2"
                              placeholder="01012345678, 01087654321 (쉼표·줄바꿈·공백 구분)">{{ old('extra_phones') }}</textarea>
                </div>
            </div>
        </div>

        {{-- 오른쪽: 메시지 --}}
        <div class="col-lg-5">
            <div class="card section-card">
                <div class="card-header"><strong>보낼 내용</strong></div>
                <div class="card-body">
                    <textarea name="message" id="message" class="form-control" rows="8" maxlength="2000"
                              placeholder="문자 내용을 입력하세요." required>{{ old('message') }}</textarea>
                    <div class="d-flex justify-content-between small text-muted mt-2">
                        <span><span id="charCount">0</span>자</span>
                        <span id="msgType" class="badge bg-light text-dark">SMS</span>
                    </div>
                    <div class="form-text small">90자 이하: SMS / 초과 시 자동으로 LMS(장문)로 발송됩니다.</div>
                    <button type="submit" class="btn btn-primary w-100 mt-3">
                        <i class="bi bi-send"></i> 문자 발송
                    </button>
                    <p class="small text-muted mt-2 mb-0">
                        <i class="bi bi-info-circle"></i> 발송 결과는 <a href="{{ route('admin.notifications.logs') }}">발송 이력</a>에 기록됩니다.
                    </p>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
(function () {
    const recips = () => Array.from(document.querySelectorAll('.recip'));
    const selCount = document.getElementById('selCount');
    function updateCount() { selCount.textContent = recips().filter(c => c.checked).length; }
    document.addEventListener('change', e => { if (e.target.classList.contains('recip')) updateCount(); });

    // 역할별 전체선택 토글
    document.querySelectorAll('.role-all').forEach(btn => {
        btn.addEventListener('click', () => {
            const role = btn.dataset.role;
            const items = recips().filter(c => c.dataset.role === role);
            const allOn = items.every(c => c.checked);
            items.forEach(c => c.checked = !allOn);
            updateCount();
        });
    });

    // 글자수 + SMS/LMS 표시
    const msg = document.getElementById('message');
    const charCount = document.getElementById('charCount');
    const msgType = document.getElementById('msgType');
    function updateMsg() {
        const len = msg.value.length;
        charCount.textContent = len;
        msgType.textContent = len > 90 ? 'LMS(장문)' : 'SMS';
    }
    msg.addEventListener('input', updateMsg);
    updateMsg(); updateCount();
})();
</script>
@endpush
@endsection
