@extends('admin.layouts.admin')
@section('title', '거래처 · ' . $vendor->name)

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.vendors.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 거래처 목록
        </a>
        <h1 class="h4 mb-0 mt-1">{{ $vendor->name }} <small class="text-muted">#{{ $vendor->id }}</small></h1>
    </div>
    <form method="POST" action="{{ route('admin.vendors.destroy', $vendor) }}"
          onsubmit="return confirm('정말 삭제할까요? (주문 이력이 있으면 차단됩니다)')">
        @csrf @method('DELETE')
        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> 삭제</button>
    </form>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-3">
    {{-- LEFT: 기본 정보 편집 폼 --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong>기본 정보</strong></div>
            <form method="POST" action="{{ route('admin.vendors.update', $vendor) }}">
                @csrf @method('PUT')
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">거래처명 *</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $vendor->name) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">구분 *</label>
                            <select name="type_code" class="form-select" required>
                                @foreach($typeOptions as $t)
                                    <option value="{{ $t->code }}" @selected(old('type_code', $vendor->type_code) === $t->code)>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">상태 *</label>
                            <select name="status_code" class="form-select" required>
                                @foreach($statusOptions as $s)
                                    <option value="{{ $s->code }}" @selected(old('status_code', $vendor->status_code) === $s->code)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">대표자</label>
                            <input type="text" name="owner_name" class="form-control" value="{{ old('owner_name', $vendor->owner_name) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">사업자번호</label>
                            <input type="text" name="business_no" class="form-control" value="{{ old('business_no', $vendor->business_no) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">업태</label>
                            <input type="text" name="biz_type" class="form-control" value="{{ old('biz_type', $vendor->biz_type) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">종목</label>
                            <input type="text" name="biz_item" class="form-control" value="{{ old('biz_item', $vendor->biz_item) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">휴대폰</label>
                            <input type="text" name="mobile" class="form-control" value="{{ old('mobile', $vendor->mobile) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">일반전화</label>
                            <input type="text" name="tel" class="form-control" value="{{ old('tel', $vendor->tel) }}">
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
                                    <option value="{{ $sg->id }}" @selected($vendor->region_id == $sg->id)>{{ $sg->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">주소</label>
                            <input type="text" name="address" class="form-control" value="{{ old('address', $vendor->address) }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small text-muted">상세주소</label>
                            <input type="text" name="address_detail" class="form-control" value="{{ old('address_detail', $vendor->address_detail) }}">
                        </div>
                    </div>

                    <h6 class="text-muted mt-4 mb-3"><i class="bi bi-bank"></i> 정산 계좌</h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small text-muted">은행</label>
                            <select name="bank_code" class="form-select">
                                <option value="">선택</option>
                                @foreach($bankOptions as $b)
                                    <option value="{{ $b->code }}" @selected(old('bank_code', $vendor->bank_code) === $b->code)>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small text-muted">계좌번호</label>
                            <input type="text" name="bank_account" class="form-control" value="{{ old('bank_account', $vendor->bank_account) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">예금주</label>
                            <input type="text" name="bank_holder" class="form-control" value="{{ old('bank_holder', $vendor->bank_holder) }}">
                        </div>
                    </div>

                    <h6 class="text-muted mt-4 mb-3"><i class="bi bi-sticky"></i> 메모</h6>
                    <textarea name="memo" class="form-control" rows="3">{{ old('memo', $vendor->memo) }}</textarea>
                </div>
                <div class="card-footer bg-white d-flex justify-content-between">
                    <small class="text-muted">등록: {{ optional($vendor->created_at)->format('Y-m-d H:i') }}</small>
                    <button class="btn btn-primary"><i class="bi bi-save"></i> 저장</button>
                </div>
            </form>
        </div>
    </div>

    {{-- RIGHT: 담당자 + 영업자 매핑 + 최근 주문 --}}
    <div class="col-lg-5">
        {{-- 담당자 --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-people"></i> 담당자</strong>
                <small class="text-muted">{{ $staffs->count() }}명</small>
            </div>
            <div class="card-body p-0">
                @if($staffs->isEmpty())
                    <div class="text-muted text-center py-3 small">담당자가 없습니다.</div>
                @else
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr>
                            <th>이름</th><th>이메일</th><th>역할</th><th></th>
                        </tr></thead>
                        <tbody>
                            @foreach($staffs as $s)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.users.show', $s->user_id) }}">{{ $s->name }}</a>
                                        @if($s->is_primary)<span class="badge bg-primary ms-1">주</span>@endif
                                    </td>
                                    <td class="text-muted small">{{ $s->email }}</td>
                                    <td><span class="badge bg-light text-dark">{{ $s->role }}</span></td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('admin.vendors.staffs.detach', [$vendor, $s->link_id]) }}"
                                              onsubmit="return confirm('담당자 매핑을 해제할까요?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-link text-danger p-0">제거</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="card-footer bg-white">
                <form method="POST" action="{{ route('admin.vendors.staffs.attach', $vendor) }}">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-5">
                            <label class="form-label small text-muted mb-1">사용자 (academy 역할)</label>
                            <select name="user_id" class="form-select form-select-sm" required>
                                <option value="">선택</option>
                                @foreach($candidateStaffs as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->login_id }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-3">
                            <label class="form-label small text-muted mb-1">역할</label>
                            <select name="role" class="form-select form-select-sm">
                                <option value="owner">원장</option>
                                <option value="manager">매니저</option>
                                <option value="staff">스태프</option>
                            </select>
                        </div>
                        <div class="col-2">
                            <div class="form-check pt-3">
                                <input type="checkbox" name="is_primary" value="1" id="is_primary" class="form-check-input">
                                <label for="is_primary" class="form-check-label small">주담당</label>
                            </div>
                        </div>
                        <div class="col-2">
                            <button class="btn btn-sm btn-outline-primary w-100">추가</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- 영업자 매핑 + 할인율 --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-person-badge"></i> 영업자 / 할인율</strong>
                <small class="text-muted">{{ $agentLinks->count() }}명</small>
            </div>
            <div class="card-body p-0">
                @if($agentLinks->isEmpty())
                    <div class="text-muted text-center py-3 small">매핑된 영업자가 없습니다.</div>
                @else
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr>
                            <th>영업자</th><th class="text-end" style="width:110px;">할인율(%)</th><th>상태</th><th></th>
                        </tr></thead>
                        <tbody>
                            @foreach($agentLinks as $a)
                                <tr>
                                    <form method="POST" action="{{ route('admin.vendors.agents.update', [$vendor, $a->id]) }}">
                                        @csrf @method('PUT')
                                        <td>
                                            <a href="{{ route('admin.users.show', $a->agent_id) }}">{{ $a->name }}</a>
                                            <div class="text-muted small">{{ $a->email }}</div>
                                        </td>
                                        <td class="text-end">
                                            <input type="number" step="0.01" min="0" max="100"
                                                   name="discount_rate" value="{{ rtrim(rtrim($a->discount_rate, '0'), '.') }}"
                                                   class="form-control form-control-sm text-end">
                                        </td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                                       @checked($a->is_active) onchange="this.form.submit()">
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-link p-0">저장</button>
                                    </form>
                                            <form method="POST" action="{{ route('admin.vendors.agents.detach', [$vendor, $a->id]) }}"
                                                  onsubmit="return confirm('영업자 매핑을 해제할까요? 주문 이력 시 비활성화 처리됩니다.')"
                                                  class="d-inline">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-link text-danger p-0">해제</button>
                                            </form>
                                        </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="card-footer bg-white">
                <form method="POST" action="{{ route('admin.vendors.agents.attach', $vendor) }}">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-7">
                            <label class="form-label small text-muted mb-1">영업자 선택</label>
                            <select name="agent_user_id" class="form-select form-select-sm" required>
                                <option value="">선택</option>
                                @foreach($candidateAgents as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->login_id }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-3">
                            <label class="form-label small text-muted mb-1">기본 할인율(%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="discount_rate" class="form-control form-control-sm text-end" value="30" required>
                        </div>
                        <div class="col-2">
                            <button class="btn btn-sm btn-outline-primary w-100">매핑</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- 최근 주문 --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong><i class="bi bi-receipt"></i> 최근 주문</strong></div>
            <div class="card-body p-0">
                @if($recentOrders->isEmpty())
                    <div class="text-muted text-center py-3 small">주문 이력이 없습니다.</div>
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
    const sido = document.getElementById('sido_select');
    const sigungu = document.getElementById('sigungu_select');
    if (! sido || ! sigungu) return;
    sido.addEventListener('change', async () => {
        sigungu.innerHTML = '<option value="">선택</option>';
        if (! sido.value) return;
        const res = await fetch("{{ route('admin.regions.sigungu') }}?sido_id=" + sido.value, {
            headers: {'Accept': 'application/json'}
        });
        const list = await res.json();
        for (const r of list) {
            const o = document.createElement('option');
            o.value = r.id; o.textContent = r.name;
            sigungu.appendChild(o);
        }
    });
})();
</script>
@endpush
