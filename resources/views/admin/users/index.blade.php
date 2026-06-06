@extends('admin.layouts.admin')
@section('title', '사용자 목록')

@section('content')
@php $me = auth()->user(); @endphp
<div class="page-header">
    <h1 class="h4 mb-0">사용자 목록 <small class="text-muted fs-6">전체 {{ number_format($users->total()) }}명</small></h1>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-primary">
            <i class="bi bi-person-plus"></i> 사용자 추가
        </a>
        <a href="{{ route('admin.users.import.show') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-file-earmark-spreadsheet"></i> 엑셀 대량 등록
        </a>
    </div>
</div>

<form method="GET" class="card section-card mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">역할</label>
                <select name="role" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($roleOptions as $r)
                        <option value="{{ $r->code }}" @selected($role === $r->code)>{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">상태</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($statusOptions as $s)
                        <option value="{{ $s->code }}" @selected($status === $s->code)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">총판별 (산하 영업자·학원)</label>
                <select name="distributor" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($distributorOptions as $d)
                        <option value="{{ $d->id }}" @selected($distributor == $d->id)>{{ $d->name }} ({{ $d->login_id }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">검색 (이름/아이디/이메일/연락처)</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                    <i class="bi bi-search"></i> 조회
                </button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary" title="초기화">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </div>
</form>

<div class="card section-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    <th style="width:60px;">#</th>
                    <th>이름</th>
                    <th>아이디</th>
                    <th>연락처</th>
                    <th>역할</th>
                    <th>소속</th>
                    <th>상태</th>
                    <th>가입일</th>
                    <th class="text-end" style="width:240px;">조치</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    @php
                        $isSelf = $u->id === $me->id;
                        $isOtherSuper = $u->isSuperAdmin() && ! $me->isSuperAdmin();
                        $canSuspend = ! $isSelf && ! $isOtherSuper;
                    @endphp
                    <tr>
                        <td>{{ $u->id }}</td>
                        <td>
                            <a href="{{ route('admin.users.show', $u) }}" class="text-decoration-none">
                                {{ $u->name }}
                            </a>
                            @if($u->isSuperAdmin())<span class="badge bg-danger ms-1">SUPER</span>@endif
                            @if($isSelf)<span class="badge bg-primary ms-1">나</span>@endif
                        </td>
                        <td class="text-muted small"><code>{{ $u->login_id }}</code></td>
                        <td class="text-muted small">{{ format_phone($u->phone) }}</td>
                        <td><span class="badge bg-light text-dark">{{ $u->role_code }}</span></td>
                        <td class="small">
                            @php $aff = $affiliations[$u->id] ?? null; @endphp
                            @if(! $aff)
                                <span class="text-muted">—</span>
                            @elseif(! empty($aff['is_distributor']))
                                <span class="text-muted">산하 영업자 {{ $aff['count'] }}명</span>
                            @elseif(empty($aff['names']))
                                <span class="text-muted">—</span>
                            @else
                                @php
                                    $names = $aff['names'];
                                    $first = $names[0];
                                    $extra = count($names) - 1;
                                @endphp
                                <span>{{ $first }}</span>
                                @if($extra > 0)
                                    <span class="badge bg-secondary ms-1" title="{{ implode(', ', $names) }}">외 {{ $extra }}</span>
                                @endif
                            @endif
                        </td>
                        <td>
                            @switch($u->status_code)
                                @case('active') <span class="badge bg-success">승인</span> @break
                                @case('pending') <span class="badge bg-warning text-dark">대기</span> @break
                                @case('suspended') <span class="badge bg-secondary">일시정지</span> @break
                                @case('terminated') <span class="badge bg-dark">거래종료</span> @break
                                @default <span class="badge bg-light text-dark">{{ $u->status_code }}</span>
                            @endswitch
                        </td>
                        <td class="text-muted small">{{ optional($u->created_at)->format('Y-m-d') }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.users.show', $u) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil-square"></i> 보기
                            </a>
                            @if($u->status_code === 'pending' && $canSuspend)
                                <form method="POST" action="{{ route('admin.users.approve', $u) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success">승인</button>
                                </form>
                            @elseif($u->status_code === 'active' && $canSuspend)
                                <form method="POST" action="{{ route('admin.users.suspend', $u) }}" class="d-inline"
                                      onsubmit="return confirm('일시정지 처리할까요?')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">일시정지</button>
                                </form>
                            @elseif($u->status_code === 'suspended' && $canSuspend)
                                <form method="POST" action="{{ route('admin.users.activate', $u) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success">정상화</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">데이터가 없습니다.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        {{ $users->links() }}
    </div>
</div>
@endsection
