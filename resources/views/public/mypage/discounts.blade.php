@extends('public.layouts.app')
@section('title', '할인율 관리')
@section('max_width', '1200px')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-percent"></i> 할인율 관리</h1>
    <p class="text-muted small mb-0">학원별 일반 할인율 + 도서별 개별 할인율 (개별 설정이 있으면 우선 적용)</p>
</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="row g-3">
    {{-- LEFT: 학원별 일반 할인율 --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong><i class="bi bi-building"></i> 학원별 일반 할인율</strong></div>
            @if($vendors->isEmpty())
                <div class="card-body text-center text-muted py-4">담당 학원이 없습니다.</div>
            @else
                <div class="list-group list-group-flush">
                    @foreach($vendors as $v)
                        <div class="list-group-item {{ $v->vendor_id == $selectedVendorId ? 'bg-light' : '' }}">
                            <form method="POST" action="{{ route('my.discounts.vendor.update', $v->avd_id) }}" class="row g-2 align-items-center">
                                @csrf @method('PUT')
                                <div class="col-5">
                                    <a href="{{ route('my.discounts.index', ['vendor_id' => $v->vendor_id]) }}"
                                       class="text-decoration-none {{ $v->vendor_id == $selectedVendorId ? 'fw-bold navy' : '' }}">
                                        {{ $v->vendor_name }}
                                    </a>
                                </div>
                                <div class="col-3">
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" min="0" max="100" name="discount_rate"
                                               value="{{ rtrim(rtrim($v->general_rate, '0'), '.') }}"
                                               class="form-control text-end">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-2 text-center">
                                    <div class="form-check form-switch d-inline-block">
                                        <input type="checkbox" name="is_active" value="1" class="form-check-input"
                                               @checked($v->is_active)>
                                    </div>
                                </div>
                                <div class="col-2 text-end">
                                    <button class="btn btn-sm btn-outline-navy">저장</button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- RIGHT: 선택된 학원의 도서별 개별 할인율 --}}
    <div class="col-lg-7">
        @if($selectedVendor)
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-book"></i> {{ $selectedVendor->vendor_name }} — 도서별 개별 할인율</strong>
                    <small class="text-muted">일반: {{ rtrim(rtrim($selectedVendor->general_rate, '0'), '.') }}%</small>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>도서</th>
                                <th class="text-end">정가</th>
                                <th style="width:120px" class="text-end">할인율</th>
                                <th style="width:120px" class="text-end">할인가</th>
                                <th style="width:60px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bookDiscounts as $bd)
                                @php $disc = (int) round($bd->price * (100 - $bd->discount_rate) / 100); @endphp
                                <tr>
                                    <td class="small">
                                        <strong>{{ $bd->title }}</strong>
                                        <div class="text-muted small"><code>{{ $bd->isbn }}</code></div>
                                    </td>
                                    <td class="text-end small text-muted">{{ number_format($bd->price) }}원</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('my.discounts.book.upsert') }}" class="d-flex gap-1 justify-content-end">
                                            @csrf
                                            <input type="hidden" name="vendor_id" value="{{ $selectedVendorId }}">
                                            <input type="hidden" name="book_id" value="{{ $bd->book_id }}">
                                            <div class="input-group input-group-sm" style="max-width:100px">
                                                <input type="number" step="0.01" min="0" max="100" name="discount_rate"
                                                       value="{{ rtrim(rtrim($bd->discount_rate, '0'), '.') }}"
                                                       class="form-control text-end">
                                                <button class="btn btn-outline-navy btn-sm"><i class="bi bi-save"></i></button>
                                            </div>
                                        </form>
                                    </td>
                                    <td class="text-end small fw-bold navy">{{ number_format($disc) }}원</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('my.discounts.book.destroy', $bd->avbd_id) }}"
                                              onsubmit="return confirm('개별 할인율을 제거하시겠어요? (일반 할인율로 돌아감)')" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3 small">개별 할인율 설정된 도서가 없습니다. (모두 일반 할인율 적용)</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- 새 도서 개별 할인율 추가 --}}
                @if($availableBooks->isNotEmpty())
                    <div class="card-footer bg-white">
                        <form method="POST" action="{{ route('my.discounts.book.upsert') }}" class="row g-2 align-items-end">
                            @csrf
                            <input type="hidden" name="vendor_id" value="{{ $selectedVendorId }}">
                            <div class="col-md-7">
                                <label class="form-label small text-muted mb-1">개별 할인율 추가할 도서</label>
                                <select name="book_id" class="form-select form-select-sm" required>
                                    <option value="">선택</option>
                                    @foreach($availableBooks as $ab)
                                        <option value="{{ $ab->id }}">
                                            {{ \Illuminate\Support\Str::limit($ab->title, 40) }} ({{ number_format($ab->price) }}원)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">할인율 %</label>
                                <input type="number" step="0.01" min="0" max="100" name="discount_rate"
                                       value="{{ rtrim(rtrim($selectedVendor->general_rate, '0'), '.') }}"
                                       class="form-control form-control-sm text-end">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button class="btn btn-sm btn-outline-navy"><i class="bi bi-plus"></i> 추가</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        @else
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    좌측에서 학원을 선택해주세요.
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
