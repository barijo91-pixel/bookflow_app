@extends('admin.layouts.admin')
@section('title', '회원 승인 대기열')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">승인 대기열</h1>
    <span class="text-muted small">{{ number_format($users->total()) }}명 대기 중</span>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:60px;">#</th>
                    <th>이름</th>
                    <th>아이디</th>
                    <th>연락처</th>
                    <th>역할</th>
                    <th>가입일</th>
                    <th class="text-end" style="width:200px;">조치</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td>{{ $u->id }}</td>
                        <td>{{ $u->name }}</td>
                        <td class="text-muted small"><code>{{ $u->login_id }}</code></td>
                        <td class="text-muted small">{{ $u->phone }}</td>
                        <td><span class="badge bg-light text-dark">{{ $u->role_code }}</span></td>
                        <td class="text-muted small">{{ optional($u->created_at)->format('Y-m-d H:i') }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('admin.users.approve', $u) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-success">
                                    <i class="bi bi-check-lg"></i> 승인
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.users.reject', $u) }}" class="d-inline"
                                  onsubmit="return confirm('정말 거절할까요?')">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-x-lg"></i> 거절
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-check2-circle" style="font-size:2rem;"></i>
                        <div class="mt-2">대기 중인 신청이 없습니다.</div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">
        {{ $users->links() }}
    </div>
</div>
@endsection
