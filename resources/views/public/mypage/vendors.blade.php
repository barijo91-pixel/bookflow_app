@extends('public.layouts.app')
@section('title', '담당 학원')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1">
        <i class="bi bi-building"></i> 담당 학원
        <small class="text-muted fs-6">{{ $vendors->count() }}개</small>
    </h1>
    <p class="text-muted small mb-0">본인이 담당하는 학원 목록과 적용 중인 할인율.</p>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>학원명</th>
                    <th>지역</th>
                    <th>연락처</th>
                    <th class="text-end">할인율</th>
                    <th>상태</th>
                    <th>거래 시작</th>
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
                        </td>
                        <td class="small text-muted">
                            {{ trim(($v->sido_name ?? '').' '.($v->sigungu_name ?? '')) ?: '-' }}
                        </td>
                        <td class="small">
                            @if($v->mobile)
                                <i class="bi bi-phone"></i> {{ $v->mobile }}
                            @endif
                            @if($v->tel)
                                <div class="text-muted"><i class="bi bi-telephone"></i> {{ $v->tel }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            <span class="badge {{ $v->discount_active ? 'bg-navy' : 'bg-secondary' }}">
                                {{ rtrim(rtrim($v->discount_rate, '0'), '.') }}%
                            </span>
                        </td>
                        <td>
                            @switch($v->status_code)
                                @case('active')     <span class="badge bg-success">정상</span> @break
                                @case('suspended')  <span class="badge bg-secondary">일시정지</span> @break
                                @case('terminated') <span class="badge bg-dark">거래종료</span> @break
                                @default <span class="badge bg-light text-dark">{{ $v->status_code }}</span>
                            @endswitch
                            @if(!$v->discount_active)
                                <span class="badge bg-warning text-dark">매핑 비활성</span>
                            @endif
                        </td>
                        <td class="small text-muted">
                            {{ $v->started_at ? \Carbon\Carbon::parse($v->started_at)->format('Y-m-d') : '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-building-x" style="font-size:2rem"></i>
                            <p class="mb-0 mt-2">담당 학원이 없습니다.</p>
                            <p class="small mb-0">관리자에게 매핑 요청을 해주세요.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
