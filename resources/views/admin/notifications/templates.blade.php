@extends('admin.layouts.admin')
@section('title', '알림 템플릿')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">알림 템플릿 <small class="text-muted fs-6">{{ $templates->count() }}개</small></h1>
    <a href="{{ route('admin.notifications.logs') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-list-ul"></i> 발송 이력
    </a>
</div>

<div class="alert alert-info small">
    <strong>변수 치환</strong> — <code>#{변수명}</code> 패턴 사용. 예: <code>#{order_no}, #{vendor_name}, #{total_amount}</code><br>
    <strong>알림톡 발송</strong> — 알리고 <code>tpl_code</code>(템플릿 코드)가 입력되어 있어야 정상 동작합니다. 알리고에 사전 등록·승인 필요.
</div>

@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

@php
    $grouped = $templates->groupBy('event_code');
    $eventLabels = $events->pluck('name', 'code');
    $channelLabels = $channels->pluck('name', 'code');
@endphp

@foreach($grouped as $eventCode => $items)
    <div class="card section-card mb-3">
        <div class="card-header">
            <strong>{{ $eventLabels[$eventCode] ?? $eventCode }}</strong>
            <code class="ms-2">{{ $eventCode }}</code>
        </div>
        <div class="card-body">
            @foreach($items as $tpl)
                <details class="border rounded mb-2">
                    <summary class="p-2 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="badge bg-light text-dark me-2">{{ $channelLabels[$tpl->channel] ?? $tpl->channel }}</span>
                            {{ $tpl->name }}
                            @if(! $tpl->is_active)<span class="badge bg-secondary ms-1">비활성</span>@endif
                            @if($tpl->channel === 'alimtalk' && empty($tpl->aligo_template_code))
                                <span class="badge bg-warning text-dark ms-1">tpl_code 미입력</span>
                            @endif
                        </div>
                        <small class="text-muted">{{ Str::limit($tpl->body, 50) }}</small>
                    </summary>
                    <form method="POST" action="{{ route('admin.notifications.templates.update', $tpl->id) }}" class="p-3">
                        @csrf @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small text-muted">템플릿 이름</label>
                                <input type="text" name="name" class="form-control form-control-sm" value="{{ $tpl->name }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">알리고 tpl_code (알림톡 시 필수)</label>
                                <input type="text" name="aligo_template_code" class="form-control form-control-sm" value="{{ $tpl->aligo_template_code }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">제목 (이메일/푸시)</label>
                                <input type="text" name="subject" class="form-control form-control-sm" value="{{ $tpl->subject }}">
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input type="checkbox" name="is_active" value="1" id="active-{{ $tpl->id }}" class="form-check-input" @checked($tpl->is_active)>
                                    <label for="active-{{ $tpl->id }}" class="form-check-label small">활성</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted">본문</label>
                                <textarea name="body" rows="4" class="form-control form-control-sm" required>{{ $tpl->body }}</textarea>
                            </div>
                        </div>
                        <div class="mt-2 text-end">
                            <button class="btn btn-sm btn-primary"><i class="bi bi-save"></i> 저장</button>
                        </div>
                    </form>
                </details>
            @endforeach
        </div>
    </div>
@endforeach
@endsection
