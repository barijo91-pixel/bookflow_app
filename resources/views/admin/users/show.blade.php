@extends('admin.layouts.admin')
@section('title', '사용자 · ' . $user->name)

@section('content')
@php $me = auth()->user(); $isSelf = $user->id === $me->id; $isOtherSuper = $user->isSuperAdmin() && ! $me->isSuperAdmin(); @endphp
<div class="page-header">
    <div>
        <a href="{{ route('admin.users.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 사용자 목록
        </a>
        <h1 class="h4 mb-0 mt-1">
            {{ $user->name }}
            @if($user->isSuperAdmin())<span class="badge bg-danger ms-1">SUPER</span>@endif
            @if($isSelf)<span class="badge bg-primary ms-1">나</span>@endif
            <small class="text-muted ms-2">#{{ $user->id }}</small>
        </h1>
    </div>
    <div class="d-flex gap-2">
        @if(!$isSelf && !$isOtherSuper)
            <form method="POST" action="{{ route('admin.users.reset_password', $user) }}"
                  onsubmit="return confirm('비밀번호를 임의 8자리로 초기화합니다. 진행할까요?')">
                @csrf
                <button class="btn btn-sm btn-outline-warning"><i class="bi bi-key"></i> 비밀번호 초기화</button>
            </form>
        @endif
    </div>
</div>

@if(session('new_password'))
    <div class="alert alert-warning border-0 shadow-sm">
        <strong>새 비밀번호:</strong>
        <code style="font-size:1.1rem">{{ session('new_password') }}</code>
        <span class="text-muted small ms-2">(이 화면 새로고침 시 사라집니다)</span>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card section-card">
            <div class="card-header"><strong>기본 정보</strong></div>
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf @method('PUT')
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">아이디 (변경 불가)</label>
                            <input type="text" class="form-control" value="{{ $user->login_id }}" readonly disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">이메일 (선택)</label>
                            <input type="email" class="form-control" value="{{ $user->email }}" readonly disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">이름</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">휴대폰</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">역할</label>
                            <select name="role_code" id="role_code" class="form-select" required>
                                @foreach($roleOptions as $r)
                                    <option value="{{ $r->code }}" @selected(old('role_code', $user->role_code) === $r->code)>{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">상태</label>
                            <select name="status_code" class="form-select" required>
                                @foreach($statusOptions as $s)
                                    <option value="{{ $s->code }}" @selected(old('status_code', $user->status_code) === $s->code)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3" id="admin_level_wrap" style="{{ $user->role_code === 'admin' ? '' : 'display:none' }}">
                            <label class="form-label small text-muted">관리자 권한</label>
                            <select name="admin_level" class="form-select">
                                <option value="staff" @selected(old('admin_level', $user->admin_level) === 'staff')>일반관리자</option>
                                <option value="super" @selected(old('admin_level', $user->admin_level) === 'super')>슈퍼관리자</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">시·도</label>
                            <select id="sido_select" class="form-select">
                                <option value="">선택</option>
                                @foreach($sidos as $sido)
                                    <option value="{{ $sido->id }}" @selected($currentSidoId === $sido->id)>{{ $sido->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">시·군·구</label>
                            <select name="region_id" id="sigungu_select" class="form-select">
                                <option value="">선택</option>
                                @foreach($sigungus as $sg)
                                    <option value="{{ $sg->id }}" @selected($user->region_id == $sg->id)>{{ $sg->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">주소</label>
                            <input type="text" name="address" class="form-control" value="{{ old('address', $user->address) }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small text-muted">상세주소</label>
                            <input type="text" name="address_detail" class="form-control" value="{{ old('address_detail', $user->address_detail) }}">
                        </div>
                    </div>

                    {{-- 사업자 정보 + 정산 계좌 (총판/사입자) --}}
                    @php
                        $bizCodes = \Illuminate\Support\Facades\DB::table('codes')
                            ->where('group_code', 'bank')->orderBy('sort_order')
                            ->get(['code', 'name']);
                    @endphp
                    <div id="business_section" style="{{ in_array($user->role_code, ['distributor','agent']) ? '' : 'display:none' }}">
                        <hr class="my-4">
                        <h6 class="mb-3 text-muted"><i class="bi bi-receipt-cutoff"></i> 사업자 정보 / 정산 계좌</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small text-muted">사업자 유형</label>
                                <select name="business_type" class="form-select">
                                    @foreach(\App\Services\TaxService::TYPES as $key => $label)
                                        <option value="{{ $key }}" @selected(old('business_type', $user->business_type ?? 'none') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">사업자등록번호</label>
                                <input type="text" name="business_no" class="form-control" placeholder="000-00-00000"
                                       value="{{ old('business_no', $user->business_no ?? '') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">상호명</label>
                                <input type="text" name="business_name" class="form-control"
                                       value="{{ old('business_name', $user->business_name ?? '') }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small text-muted">은행</label>
                                <select name="bank_code" class="form-select">
                                    <option value="">선택</option>
                                    @foreach($bizCodes as $c)
                                        <option value="{{ $c->code }}" @selected(old('bank_code', $user->bank_code) === $c->code)>{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">계좌번호</label>
                                <input type="text" name="bank_account" class="form-control"
                                       value="{{ old('bank_account', $user->bank_account) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">예금주</label>
                                <input type="text" name="bank_holder" class="form-control"
                                       value="{{ old('bank_holder', $user->bank_holder) }}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <small class="text-muted">
                        가입: {{ optional($user->created_at)->format('Y-m-d H:i') }}
                        @if($user->approved_at) · 승인: {{ optional($user->approved_at)->format('Y-m-d') }} @endif
                        @if($user->last_login_at) · 최근 로그인: {{ optional($user->last_login_at)->format('Y-m-d H:i') }} @endif
                    </small>
                    <button class="btn btn-primary"><i class="bi bi-save"></i> 저장</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        {{-- 영업자: 소속 총판 (지정/변경) --}}
        @if($user->role_code === 'agent')
            <div class="card section-card mb-3 {{ $currentDistributor ? '' : 'border-warning' }}">
                <div class="card-header">
                    <strong><i class="bi bi-truck"></i> 소속 총판</strong>
                </div>
                <div class="card-body">
                    @if($currentDistributor)
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <div class="fw-bold">{{ $currentDistributor->name }}</div>
                                <div class="small text-muted">
                                    <code>{{ $currentDistributor->login_id }}</code>
                                    @if($currentDistributor->phone) · {{ format_phone($currentDistributor->phone) }} @endif
                                </div>
                                <div class="small text-muted">
                                    소속일: {{ \Carbon\Carbon::parse($currentDistributor->started_at)->format('Y-m-d') }}
                                </div>
                            </div>
                            <span class="badge bg-success">소속중</span>
                        </div>
                    @else
                        <div class="alert alert-warning small mb-3">
                            <i class="bi bi-exclamation-triangle"></i>
                            소속 총판이 없습니다. 총판을 지정해야 주문 라우팅·정산이 정상 동작합니다.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.users.assign_distributor', $user) }}"
                          onsubmit="return confirm('소속 총판을 지정/변경하시겠습니까?');">
                        @csrf
                        <label class="form-label small text-muted">{{ $currentDistributor ? '총판 변경' : '총판 지정' }}</label>
                        <div class="input-group">
                            <select name="distributor_user_id" class="form-select" required>
                                <option value="">총판 선택...</option>
                                @foreach($availableDistributors as $d)
                                    <option value="{{ $d->id }}"
                                        @selected($currentDistributor && $currentDistributor->id === $d->id)>
                                        {{ $d->name }} ({{ $d->login_id }})
                                    </option>
                                @endforeach
                            </select>
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-check-lg"></i> 적용
                            </button>
                        </div>
                        @if($availableDistributors->isEmpty())
                            <div class="small text-danger mt-1">활성 총판이 없습니다. 먼저 총판 계정을 등록하세요.</div>
                        @endif
                    </form>
                </div>
            </div>
        @endif

        {{-- 학원 계정: 소속 학원(거래처) 지정/변경 --}}
        @if($user->role_code === 'academy')
            <div class="card section-card mb-3 {{ $currentVendor ? '' : 'border-warning' }}">
                <div class="card-header">
                    <strong><i class="bi bi-mortarboard"></i> 소속 학원(거래처)</strong>
                </div>
                <div class="card-body">
                    @if($currentVendor)
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <div class="fw-bold">{{ $currentVendor->name }}</div>
                                @if($currentVendor->mobile)
                                    <div class="small text-muted">{{ format_phone($currentVendor->mobile) }}</div>
                                @endif
                                <a href="{{ route('admin.vendors.show', $currentVendor->id) }}" class="small">거래처 상세 <i class="bi bi-box-arrow-up-right"></i></a>
                            </div>
                            <span class="badge bg-success">연결됨</span>
                        </div>
                    @else
                        <div class="alert alert-warning small mb-3">
                            <i class="bi bi-exclamation-triangle"></i>
                            소속 거래처가 없습니다. 학원을 연결해야 주문·결제가 정상 동작합니다.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.users.assign_vendor', $user) }}"
                          onsubmit="return confirm('소속 학원(거래처)을 지정/변경하시겠습니까?');">
                        @csrf
                        <label class="form-label small text-muted">{{ $currentVendor ? '거래처 변경' : '거래처 지정' }}</label>
                        <div class="input-group">
                            <select name="vendor_id" class="form-select" required>
                                <option value="">거래처 선택...</option>
                                @foreach($availableVendors as $v)
                                    <option value="{{ $v->id }}"
                                        @selected($currentVendor && $currentVendor->id === $v->id)>
                                        {{ $v->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-check-lg"></i> 적용
                            </button>
                        </div>
                        @if($availableVendors->isEmpty())
                            <div class="small text-danger mt-1">등록된 거래처가 없습니다. 먼저 거래처(학원)를 등록하세요.</div>
                        @endif
                    </form>
                </div>
            </div>
        @endif

        <div class="card section-card mb-3">
            <div class="card-header"><strong>관계</strong> <small class="text-muted">(총판-영업자-학원)</small></div>
            <div class="card-body p-0">
                @if($relationsAsParent->isEmpty() && $relationsAsChild->isEmpty())
                    <div class="text-muted text-center py-3 small">관계 없음</div>
                @else
                    @if($relationsAsParent->isNotEmpty())
                        <div class="px-3 pt-3"><small class="text-muted">하위로 매핑된 사용자</small></div>
                        <table class="table table-sm mb-0">
                            <tbody>
                                @foreach($relationsAsParent as $rel)
                                    <tr>
                                        <td><a href="{{ route('admin.users.show', $rel->user_id) }}">{{ $rel->user_name }}</a></td>
                                        <td><span class="badge bg-light text-dark">{{ $rel->role_code }}</span></td>
                                        <td><small class="text-muted">{{ $rel->relation_type }}</small></td>
                                        <td><span class="badge {{ $rel->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ $rel->status }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                    @if($relationsAsChild->isNotEmpty())
                        <div class="px-3 pt-3"><small class="text-muted">상위로 매핑된 사용자</small></div>
                        <table class="table table-sm mb-0">
                            <tbody>
                                @foreach($relationsAsChild as $rel)
                                    <tr>
                                        <td><a href="{{ route('admin.users.show', $rel->user_id) }}">{{ $rel->user_name }}</a></td>
                                        <td><span class="badge bg-light text-dark">{{ $rel->role_code }}</span></td>
                                        <td><small class="text-muted">{{ $rel->relation_type }}</small></td>
                                        <td><span class="badge {{ $rel->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ $rel->status }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                @endif
            </div>
        </div>

        <div class="card section-card">
            <div class="card-header"><strong>최근 주문</strong></div>
            <div class="card-body p-0">
                @if($recentOrders->isEmpty())
                    <div class="text-muted text-center py-3 small">주문 이력 없음</div>
                @else
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>주문번호</th><th>상태</th><th class="text-end">금액</th><th>일시</th></tr>
                        </thead>
                        <tbody>
                            @foreach($recentOrders as $o)
                                <tr>
                                    <td><code>{{ $o->order_no }}</code></td>
                                    <td><span class="badge bg-light text-dark">{{ $o->status_code }}</span></td>
                                    <td class="text-end">{{ number_format($o->total_amount) }}원</td>
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
@endsection

@push('scripts')
<script>
(function () {
    const role = document.getElementById('role_code');
    const adminWrap = document.getElementById('admin_level_wrap');
    const bizSection = document.getElementById('business_section');
    role.addEventListener('change', () => {
        adminWrap.style.display = (role.value === 'admin') ? '' : 'none';
        if (bizSection) {
            bizSection.style.display = (['distributor','agent'].includes(role.value)) ? '' : 'none';
        }
    });

    const sido = document.getElementById('sido_select');
    const sigungu = document.getElementById('sigungu_select');
    const currentRegionId = "{{ $user->region_id }}";

    sido.addEventListener('change', async () => {
        const sidoId = sido.value;
        sigungu.innerHTML = '<option value="">선택</option>';
        if (! sidoId) return;
        try {
            const res = await fetch("{{ route('admin.regions.sigungu') }}?sido_id=" + sidoId, {
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            const list = await res.json();
            for (const r of list) {
                const o = document.createElement('option');
                o.value = r.id;
                o.textContent = r.name;
                sigungu.appendChild(o);
            }
        } catch (e) { console.error(e); }
    });
})();
</script>
@endpush
