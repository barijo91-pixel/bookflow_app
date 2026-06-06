@extends('admin.layouts.admin')
@section('title', '감사 로그 #' . $log->id)

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.audit-logs.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 감사 로그 목록
        </a>
        <h1 class="h4 mb-0 mt-1">감사 로그 #{{ $log->id }}</h1>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card section-card">
            <div class="card-header"><strong>기본 정보</strong></div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-4 text-muted">일시</dt>
                    <dd class="col-8">{{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i:s') }}</dd>

                    <dt class="col-4 text-muted">사용자</dt>
                    <dd class="col-8">
                        @if($log->user_id)
                            <a href="{{ route('admin.users.show', $log->user_id) }}">{{ $log->user_name }}</a>
                            <div class="text-muted">{{ $log->user_email }}</div>
                        @else
                            <span class="text-muted">시스템</span>
                        @endif
                    </dd>

                    <dt class="col-4 text-muted">엔티티</dt>
                    <dd class="col-8"><code>{{ $log->entity }}</code> #{{ $log->entity_id }}</dd>

                    <dt class="col-4 text-muted">액션</dt>
                    <dd class="col-8"><span class="badge bg-light text-dark">{{ $log->action }}</span></dd>

                    <dt class="col-4 text-muted">IP</dt>
                    <dd class="col-8 text-muted">{{ $log->ip_address }}</dd>

                    <dt class="col-4 text-muted">User-Agent</dt>
                    <dd class="col-8 text-muted small" style="word-break:break-all">{{ $log->user_agent }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        @if(! empty($diff))
            <div class="card section-card mb-3">
                <div class="card-header"><strong><i class="bi bi-arrow-left-right"></i> 변경 사항 ({{ count($diff) }}개 필드)</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>필드</th><th>이전</th><th>이후</th></tr></thead>
                        <tbody>
                            @foreach($diff as $field => $d)
                                <tr>
                                    <td><code class="small">{{ $field }}</code></td>
                                    <td class="text-danger small" style="word-break:break-all">
                                        @if(is_array($d['before']))<pre class="mb-0 small">{{ json_encode($d['before'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre>
                                        @else {{ $d['before'] ?? '∅' }} @endif
                                    </td>
                                    <td class="text-success small" style="word-break:break-all">
                                        @if(is_array($d['after']))<pre class="mb-0 small">{{ json_encode($d['after'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre>
                                        @else {{ $d['after'] ?? '∅' }} @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($before)
            <div class="card section-card mb-3">
                <div class="card-header"><strong>before</strong></div>
                <div class="card-body p-3">
                    <pre class="mb-0 small" style="white-space:pre-wrap">{{ json_encode($before, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
        @endif

        @if($after)
            <div class="card section-card">
                <div class="card-header"><strong>after</strong></div>
                <div class="card-body p-3">
                    <pre class="mb-0 small" style="white-space:pre-wrap">{{ json_encode($after, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
        @endif

        @if(! $before && ! $after)
            <div class="card section-card">
                <div class="card-body text-muted text-center py-4">
                    저장된 변경 데이터가 없습니다.
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
