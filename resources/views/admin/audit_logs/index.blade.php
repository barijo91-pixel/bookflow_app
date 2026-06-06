@extends('admin.layouts.admin')
@section('title', '감사 로그')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">감사 로그 <small class="text-muted fs-6">전체 {{ number_format($logs->total()) }}건</small></h1>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="stat-card py-2"><div class="stat-label small">전체</div><div class="stat-value" style="font-size:1.3rem">{{ number_format($summary['total']) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card py-2"><div class="stat-label small">오늘</div><div class="stat-value" style="font-size:1.3rem">{{ number_format($summary['today']) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card py-2"><div class="stat-label small">최근 7일</div><div class="stat-value" style="font-size:1.3rem">{{ number_format($summary['week']) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card py-2"><div class="stat-label small">활성 사용자</div><div class="stat-value" style="font-size:1.3rem">{{ number_format($summary['users']) }}</div></div></div>
</div>

<form method="GET" class="card section-card mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">엔티티</label>
                <select name="entity" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($entities as $e)
                        <option value="{{ $e }}" @selected($entity === $e)>{{ $e }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">액션</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($actions as $a)
                        <option value="{{ $a }}" @selected($action === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">사용자</label>
                <select name="user" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($userOptions as $u)
                        <option value="{{ $u->id }}" @selected($userId == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">시작일</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">종료일</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">검색 (엔티티/액션/IP)</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
            </div>
        </div>
    </div>
</form>

<div class="card section-card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>일시</th>
                    <th>사용자</th>
                    <th>엔티티</th>
                    <th>ID</th>
                    <th>액션</th>
                    <th>IP</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $l)
                    <tr>
                        <td class="small">{{ $l->id }}</td>
                        <td class="small text-muted">{{ \Carbon\Carbon::parse($l->created_at)->format('Y-m-d H:i:s') }}</td>
                        <td class="small">
                            @if($l->user_id)
                                <a href="{{ route('admin.users.show', $l->user_id) }}" class="text-decoration-none">{{ $l->user_name }}</a>
                                <div class="text-muted">{{ $l->user_email }}</div>
                            @else
                                <span class="text-muted">시스템</span>
                            @endif
                        </td>
                        <td><code class="small">{{ $l->entity }}</code></td>
                        <td class="small text-muted">{{ $l->entity_id }}</td>
                        <td>
                            @php $cls = match($l->action) {
                                'create','add'    => 'bg-success',
                                'update','modify' => 'bg-primary',
                                'delete','remove' => 'bg-danger',
                                'approve'         => 'bg-info',
                                'cancel'          => 'bg-secondary',
                                default           => 'bg-light text-dark',
                            }; @endphp
                            <span class="badge {{ $cls }}">{{ $l->action }}</span>
                        </td>
                        <td class="small text-muted">{{ $l->ip_address }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.audit-logs.show', $l->id) }}" class="btn btn-sm btn-link p-0">상세</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">
                        <i class="bi bi-shield-check" style="font-size:2rem"></i>
                        <div class="mt-2">감사 로그가 없습니다.</div>
                        <small class="text-muted">관리자 행동이 자동 기록됩니다 (AuditLog::log()).</small>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $logs->links() }}</div>
</div>
@endsection
