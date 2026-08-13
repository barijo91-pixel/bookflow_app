@extends('public.layouts.app')
@section('title', '사용자관리')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-people"></i> 사용자관리
        <small class="text-muted fs-6">{{ $users->total() }}명</small>
        @if($pendingCount > 0)
            <span class="badge bg-danger align-middle">승인대기 {{ $pendingCount }}</span>
        @endif
    </h1>
    <p class="text-muted small mb-0">본 총판 산하 영업자 · 학원 계정만 표시됩니다.</p>
</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if(session('new_password'))
    <div class="alert alert-warning py-2 small">
        새 비밀번호 <strong>{{ session('new_password') }}</strong> — 이 창을 벗어나면 다시 볼 수 없습니다. 본인에게 전달해주세요.
    </div>
@endif

<form method="GET" action="{{ route('my.users.index') }}" class="card section-card mb-3" id="filterForm">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">역할</label>
                <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    <option value="agent"   @selected($role === 'agent')>영업자</option>
                    <option value="academy" @selected($role === 'academy')>학원</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">상태</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    @foreach($statusOptions as $s)
                        <option value="{{ $s->code }}" @selected($status === $s->code)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1">이름 / 아이디 / 연락처</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="이름·아이디·연락처 일부">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search"></i> 조회</button>
                <a href="{{ route('my.users.index') }}" class="btn btn-sm btn-outline-secondary" title="초기화"><i class="bi bi-x-lg"></i></a>
            </div>
        </div>
    </div>
</form>

<div class="card section-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    <th><x-sort-link field="name" label="이름" :sort="$sort" :dir="$dir" /></th>
                    <th><x-sort-link field="login_id" label="아이디" :sort="$sort" :dir="$dir" /></th>
                    <th><x-sort-link field="role_code" label="역할" :sort="$sort" :dir="$dir" /></th>
                    <th>소속</th>
                    <th>연락처</th>
                    <th><x-sort-link field="status_code" label="상태" :sort="$sort" :dir="$dir" /></th>
                    <th><x-sort-link field="last_login_at" label="최근 로그인" :sort="$sort" :dir="$dir" /></th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                    <tr>
                        <td class="text-nowrap">
                            <a href="{{ route('my.users.edit', $u->id) }}" class="link-name">{{ $u->name }}</a>
                        </td>
                        <td class="text-nowrap text-muted">{{ $u->login_id }}</td>
                        <td class="text-nowrap">
                            @if($u->role_code === 'agent')
                                <span class="badge bg-navy">영업자</span>
                            @else
                                <span class="badge bg-secondary">학원</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $affiliations[$u->id] ?? '-' }}</td>
                        <td class="text-nowrap text-muted">{{ $u->phone ? format_phone($u->phone) : '-' }}</td>
                        <td class="text-nowrap">
                            @switch($u->status_code)
                                @case('active')     <span class="badge bg-success">정상</span> @break
                                @case('pending')    <span class="badge bg-warning text-dark">승인대기</span> @break
                                @case('suspended')  <span class="badge bg-danger">정지</span> @break
                                @case('terminated') <span class="badge bg-dark">거래종료</span> @break
                                @default            <span class="badge bg-light text-dark">{{ $u->status_code }}</span>
                            @endswitch
                        </td>
                        <td class="text-nowrap text-muted small">
                            {{ $u->last_login_at ? \Illuminate\Support\Carbon::parse($u->last_login_at)->format('Y-m-d H:i') : '-' }}
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('my.users.edit', $u->id) }}" class="btn btn-sm btn-outline-navy" title="계정 수정">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @if($u->status_code === 'pending')
                                <form method="POST" action="{{ route('my.users.approve', $u->id) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> 승인</button>
                                </form>
                                <form method="POST" action="{{ route('my.users.reject', $u->id) }}" class="d-inline"
                                      onsubmit="return confirm('{{ $u->name }} 가입을 거절할까요?')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger">거절</button>
                                </form>
                            @elseif($u->status_code === 'active')
                                <form method="POST" action="{{ route('my.users.suspend', $u->id) }}" class="d-inline"
                                      onsubmit="return confirm('{{ $u->name }} 계정을 일시정지할까요? 로그인이 막힙니다.')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">정지</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('my.users.activate', $u->id) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-navy">정상화</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('my.users.reset_password', $u->id) }}" class="d-inline"
                                  onsubmit="return confirm('{{ $u->name }} 비밀번호를 초기화할까요? 임시 비밀번호가 한 번만 표시됩니다.')">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary" title="비밀번호 초기화">
                                    <i class="bi bi-key"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            산하 사용자가 없습니다.<br>
                            <small>영업자를 등록하면 그 영업자와 담당 학원 계정이 여기에 표시됩니다.</small>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($users->hasPages())
    <div class="mt-3">{{ $users->links() }}</div>
@endif
@endsection
