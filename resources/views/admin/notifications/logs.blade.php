@extends('admin.layouts.admin')
@section('title', '알림 발송 이력')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">알림 발송 이력 <small class="text-muted fs-6">전체 {{ number_format($logs->total()) }}건</small></h1>
    <a href="{{ route('admin.notifications.templates') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-bell"></i> 템플릿 관리
    </a>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="stat-card py-2"><div class="stat-label small">전체</div><div class="stat-value" style="font-size:1.3rem">{{ number_format($summary['total']) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card py-2"><div class="stat-label small">발송 성공</div><div class="stat-value text-success" style="font-size:1.3rem">{{ number_format($summary['sent']) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card py-2"><div class="stat-label small">실패</div><div class="stat-value text-danger" style="font-size:1.3rem">{{ number_format($summary['failed']) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card py-2"><div class="stat-label small">건너뜀(키없음)</div><div class="stat-value text-warning" style="font-size:1.3rem">{{ number_format($summary['skipped']) }}</div></div></div>
</div>

<form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">이벤트</label>
                <select name="event" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($events as $e)
                        <option value="{{ $e->code }}" @selected($event === $e->code)>{{ $e->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">채널</label>
                <select name="channel" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($channels as $c)
                        <option value="{{ $c->code }}" @selected($channel === $c->code)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">상태</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">전체</option>
                    <option value="sent" @selected($status === 'sent')>발송</option>
                    <option value="failed" @selected($status === 'failed')>실패</option>
                    <option value="skipped" @selected($status === 'skipped')>건너뜀</option>
                    <option value="queued" @selected($status === 'queued')>대기</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">검색 (전화/이메일/본문)</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>이벤트</th>
                    <th>채널</th>
                    <th>수신</th>
                    <th>본문 (요약)</th>
                    <th>상태</th>
                    <th>발송</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $l)
                    <tr>
                        <td class="small">{{ $l->id }}</td>
                        <td class="small"><code>{{ $l->event_code }}</code></td>
                        <td class="small"><span class="badge bg-light text-dark">{{ $l->channel }}</span></td>
                        <td class="small text-muted">
                            {{ $l->recipient_phone }}
                            @if($l->recipient_email)<div>{{ $l->recipient_email }}</div>@endif
                        </td>
                        <td class="small" style="max-width:400px;">
                            <div style="white-space:pre-wrap;font-family:inherit;font-size:.85em;">{{ Str::limit($l->payload, 120) }}</div>
                        </td>
                        <td>
                            @switch($l->status)
                                @case('sent')    <span class="badge bg-success">발송</span> @break
                                @case('failed')  <span class="badge bg-danger">실패</span> @break
                                @case('skipped') <span class="badge bg-warning text-dark">건너뜀</span> @break
                                @case('queued')  <span class="badge bg-info">대기</span> @break
                                @default <span class="badge bg-light text-dark">{{ $l->status }}</span>
                            @endswitch
                        </td>
                        <td class="text-muted small">
                            @if($l->sent_at){{ \Carbon\Carbon::parse($l->sent_at)->format('m-d H:i') }}
                            @elseif($l->failed_at){{ \Carbon\Carbon::parse($l->failed_at)->format('m-d H:i') }}
                            @else {{ \Carbon\Carbon::parse($l->created_at)->format('m-d H:i') }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">발송 이력이 없습니다.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $logs->links() }}</div>
</div>
@endsection
