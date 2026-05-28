@extends('public.layouts.app')
@section('title', '거래처(학원)')
@section('max_width', '1100px')

@section('content')
<div class="mb-3 d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div>
        <h1 class="h4 navy mb-1">
            <i class="bi bi-building"></i> 거래처(학원)
            <small class="text-muted fs-6">{{ $vendors->count() }}개</small>
        </h1>
        <p class="text-muted small mb-0">본인이 담당하는 학원과 적용 중인 할인율. 인라인 수정 가능.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('my.vendors.create') }}" class="btn btn-sm btn-primary">
            <i class="bi bi-building-add"></i> 새 학원 등록
        </a>
        <a href="{{ route('my.discounts.index') }}" class="btn btn-sm btn-outline-navy">
            <i class="bi bi-percent"></i> 도서별 개별 할인율 관리
        </a>
    </div>
</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

{{-- 폼들은 테이블 외부에 두고, 안쪽 input/button은 form="..." 속성으로 연결 (HTML5 표준) --}}
@foreach($vendors as $v)
    <form id="vendor-edit-{{ $v->avd_id }}" method="POST"
          action="{{ route('my.discounts.vendor.update', $v->avd_id) }}">
        @csrf @method('PUT')
    </form>
    <form id="vendor-del-{{ $v->avd_id }}" method="POST"
          action="{{ route('my.discounts.vendor.destroy', $v->avd_id) }}"
          onsubmit="return confirm('「{{ addslashes($v->name) }}」 거래를 일시 중단할까요?\n\n· 학원 정보는 유지되고 거래만 멈춥니다.\n· 다시 시작하려면 관리자에게 문의해주세요.')">
        @csrf @method('DELETE')
    </form>
@endforeach

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>학원명</th>
                    <th>지역</th>
                    <th>연락처</th>
                    <th style="width:240px" class="text-end">할인율</th>
                    <th style="width:80px" class="text-center">활성</th>
                    <th style="width:120px">상태</th>
                    <th style="width:170px" class="text-end">작업</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vendors as $v)
                    <tr>
                        <td>
                            <strong>{{ $v->name }}</strong>
                            @if($v->owner_name)
                                <div class="text-muted small">대표: {{ $v->owner_name }}</div>
                            @endif
                            @if($v->business_no)
                                <div class="text-muted small"><code>{{ $v->business_no }}</code></div>
                            @endif
                            @if($v->started_at)
                                <div class="text-muted small">
                                    <i class="bi bi-calendar3"></i> {{ \Carbon\Carbon::parse($v->started_at)->format('Y-m-d') }} 시작
                                </div>
                            @endif
                        </td>
                        <td class="small text-muted">
                            {{ trim(($v->sido_name ?? '').' '.($v->sigungu_name ?? '')) ?: '-' }}
                        </td>
                        <td class="small">
                            @if($v->mobile)
                                <i class="bi bi-phone"></i> {{ format_phone($v->mobile) }}
                            @endif
                            @if($v->tel)
                                <div class="text-muted"><i class="bi bi-telephone"></i> {{ format_phone($v->tel) }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="input-group input-group-sm rate-stepper ms-auto">
                                <button type="button" class="btn btn-outline-secondary rate-down" tabindex="-1" aria-label="감소">−</button>
                                <input type="number" step="0.5" min="0" max="100" name="discount_rate"
                                       form="vendor-edit-{{ $v->avd_id }}"
                                       value="{{ rtrim(rtrim($v->discount_rate, '0'), '.') }}"
                                       class="form-control text-end" inputmode="decimal">
                                <button type="button" class="btn btn-outline-secondary rate-up" tabindex="-1" aria-label="증가">+</button>
                                <span class="input-group-text">%</span>
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="form-check form-switch d-inline-block">
                                <input type="checkbox" name="is_active" value="1"
                                       form="vendor-edit-{{ $v->avd_id }}"
                                       class="form-check-input"
                                       @checked($v->discount_active)>
                            </div>
                        </td>
                        <td>
                            @switch($v->status_code)
                                @case('active')     <span class="badge bg-success">정상</span> @break
                                @case('suspended')  <span class="badge bg-secondary">일시정지</span> @break
                                @case('terminated') <span class="badge bg-dark">거래종료</span> @break
                                @default <span class="badge bg-light text-dark">{{ $v->status_code }}</span>
                            @endswitch
                            @if(!$v->discount_active)
                                <div class="mt-1"><span class="badge bg-warning text-dark">매핑 비활성</span></div>
                            @endif
                        </td>
                        <td class="text-end">
                            <button type="submit" form="vendor-edit-{{ $v->avd_id }}"
                                    class="btn btn-sm btn-outline-navy">
                                <i class="bi bi-save"></i> 저장
                            </button>
                            @if($v->discount_active)
                                <button type="submit" form="vendor-del-{{ $v->avd_id }}"
                                        class="btn btn-sm btn-link text-danger p-0 ms-1"
                                        title="거래 일시 중단">
                                    <i class="bi bi-x-circle"></i> 삭제
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-building-x" style="font-size:2rem"></i>
                            <p class="mb-0 mt-2">거래처(학원)이 없습니다.</p>
                            <p class="small mb-0">관리자에게 매핑 요청을 해주세요.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-light border mt-3 small text-muted mb-0">
    <i class="bi bi-info-circle"></i>
    <strong>안내</strong>:
    할인율은 0.5% 단위로 조정할 수 있어요.
    <strong>삭제</strong>를 누르면 거래가 일시 중단되며, 다시 시작하려면 관리자에게 문의해주세요.
    도서마다 다른 할인율을 주려면 <a href="{{ route('my.discounts.index') }}" class="text-decoration-none">할인율 관리</a> 페이지에서 설정하세요.
</div>

@push('scripts')
<script>
// 할인율 입력 +/- 버튼 (모바일 친화 0.5% 단위 stepper)
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.rate-stepper .rate-up, .rate-stepper .rate-down');
    if (!btn) return;
    const wrap = btn.closest('.rate-stepper');
    const input = wrap.querySelector('input[type=number]');
    if (!input) return;
    let v = parseFloat(input.value) || 0;
    const step = parseFloat(input.step) || 0.5;
    const min = input.min !== '' ? parseFloat(input.min) : -Infinity;
    const max = input.max !== '' ? parseFloat(input.max) : Infinity;
    v = btn.classList.contains('rate-up') ? v + step : v - step;
    v = Math.max(min, Math.min(max, v));
    input.value = (Math.round(v * 10) / 10).toString().replace(/\.0$/, '');
});
</script>
@endpush
@endsection
