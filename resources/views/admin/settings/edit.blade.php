@extends('admin.layouts.admin')
@section('title', '사이트 설정')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">사이트 설정</h1>
</div>

<ul class="nav nav-tabs mb-3">
    @foreach($groupOrder as $g)
        <li class="nav-item">
            <a class="nav-link {{ $active === $g ? 'active' : '' }}"
               href="{{ route('admin.settings.edit', ['group' => $g]) }}">
                {{ $groupLabels[$g] ?? $g }}
                <span class="badge bg-light text-dark ms-1">{{ ($settings[$g] ?? collect())->count() }}</span>
            </a>
        </li>
    @endforeach
</ul>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@php $items = $settings[$active] ?? collect(); @endphp

<form method="POST" action="{{ route('admin.settings.update', ['group' => $active]) }}">
    @csrf @method('PUT')
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @if($items->isEmpty())
                <div class="text-muted text-center py-4">설정 항목이 없습니다.</div>
            @else
                @foreach($items as $s)
                    <div class="row g-3 mb-3 align-items-start">
                        <div class="col-md-3">
                            <label class="form-label mb-0">{{ $s->label ?: $s->key }}</label>
                            <div class="text-muted small"><code>{{ $s->key }}</code></div>
                        </div>
                        <div class="col-md-9">
                            @switch($s->type)
                                @case('textarea')
                                    <textarea name="settings[{{ $s->key }}]" rows="3"
                                              class="form-control">{{ $s->value }}</textarea>
                                    @break
                                @case('boolean')
                                    <div class="form-check form-switch">
                                        <input type="checkbox" name="settings[{{ $s->key }}]" value="1"
                                               class="form-check-input"
                                               @checked($s->value === '1' || $s->value === 'true' || $s->value === '1')>
                                    </div>
                                    @break
                                @case('number')
                                    <input type="number" name="settings[{{ $s->key }}]" value="{{ $s->value }}"
                                           class="form-control">
                                    @break
                                @case('password')
                                    <input type="password" name="settings[{{ $s->key }}]" value="{{ $s->value }}"
                                           class="form-control" autocomplete="off">
                                    @break
                                @default
                                    <input type="text" name="settings[{{ $s->key }}]" value="{{ $s->value }}"
                                           class="form-control">
                            @endswitch
                            @if($s->description)
                                <small class="text-muted">{{ $s->description }}</small>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
        <div class="card-footer bg-white text-end">
            <button class="btn btn-primary"><i class="bi bi-save"></i> 저장</button>
        </div>
    </div>
</form>

@if($active === 'integration')
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-body py-3">
            <small class="text-muted">
                <i class="bi bi-info-circle"></i>
                <strong>알라딘 TTB Key</strong>는 알라딘에 회원가입 후 <a href="https://blog.aladin.co.kr/openapi" target="_blank">API 신청</a>에서 발급.
                <strong>알리고</strong>는 회원가입 후 알림톡 발신프로필 + 템플릿 사전 등록 필요.
                <strong>카카오 OAuth</strong>는 <a href="https://developers.kakao.com" target="_blank">카카오디벨로퍼스</a>에서 앱 생성.
            </small>
        </div>
    </div>
@endif
@endsection
