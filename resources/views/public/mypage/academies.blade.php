@extends('public.layouts.app')
@section('title', '거래처(학원)')
@section('max_width', '1100px')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-building"></i> 거래처(학원)
        <small class="text-muted fs-6">{{ $academies->count() }}곳</small>
    </h1>
    <p class="text-muted small mb-0">본 총판 산하 영업자들이 관리하는 학원(거래처) 전체</p>
</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif

<form method="GET" action="{{ route('my.academies.index') }}" class="card section-card mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-9">
                <label class="form-label small text-muted mb-1">학원명 / 영업자 / 원장</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="학원명·담당 영업자·원장 이름 일부">
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search"></i> 조회</button>
                <a href="{{ route('my.academies.index') }}" class="btn btn-sm btn-outline-secondary" title="초기화"><i class="bi bi-x-lg"></i></a>
            </div>
        </div>
    </div>
</form>

<div class="card section-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    <th>학원</th>
                    <th>거래구분</th>
                    <th>담당 영업자</th>
                    <th>지역</th>
                    <th class="text-end">할인율</th>
                    <th>상태</th>
                </tr>
            </thead>
            <tbody>
                @forelse($academies as $v)
                    <tr>
                        <td class="small">
                            <strong>{{ $v->name }}</strong>
                            @if($v->owner_name)<div class="text-muted small">원장 {{ $v->owner_name }}</div>@endif
                            @if($v->mobile)<div class="text-muted small"><i class="bi bi-phone"></i> {{ format_phone($v->mobile) }}</div>@endif
                        </td>
                        <td>
                            @if($v->trade_type === 'wholesale')
                                <span class="badge bg-primary">도매</span>
                            @else
                                <span class="badge bg-info text-dark">소매</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $v->agent_name }}</td>
                        <td class="small text-muted">{{ trim(($v->sido_name ?? '').' '.($v->sigungu_name ?? '')) ?: '-' }}</td>
                        <td class="text-end small">
                            {{ rtrim(rtrim(number_format($v->discount_rate, 1), '0'), '.') }}%
                            <div class="text-muted" style="font-size:0.7rem;">{{ $v->trade_type === 'wholesale' ? '매입' : '학부모' }}</div>
                        </td>
                        <td>
                            @switch($v->status_code)
                                @case('active') <span class="badge bg-success">정상</span> @break
                                @case('suspended') <span class="badge bg-secondary">정지</span> @break
                                @default <span class="badge bg-light text-dark">{{ $v->status_code }}</span>
                            @endswitch
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-building" style="font-size:2rem"></i>
                            <p class="mb-0 mt-2">산하 학원이 없습니다.</p>
                            <p class="small text-muted mb-0">소속 영업자가 학원을 등록하면 여기에 표시됩니다.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
