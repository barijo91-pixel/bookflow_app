@extends('admin.layouts.admin')
@section('title', '재고 미리보기')

@section('content')
@php
    $okCount = collect($rows)->filter(fn ($r) => empty($r['_errors']))->count();
    $newCount = collect($rows)->filter(fn ($r) => empty($r['_errors']) && empty($r['_exists']))->count();
    $existCount = collect($rows)->filter(fn ($r) => empty($r['_errors']) && ! empty($r['_exists']))->count();
@endphp
<div class="page-header d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div>
        <a href="{{ route('admin.stocks.import.show') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 업로드로 돌아가기
        </a>
        <h1 class="h4 mb-0 mt-1">미리보기 · <small class="text-muted">{{ $file }}</small></h1>
    </div>
    @if($okCount > 0)
        <button type="submit" form="stockRunForm" class="btn btn-primary btn-lg px-4">
            <i class="bi bi-cloud-upload"></i> 등록 실행 ({{ number_format($okCount) }}건)
        </button>
    @endif
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="stat-card py-2"><div class="stat-label small">총 행</div><div class="stat-value" style="font-size:1.3rem">{{ number_format($total) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card py-2"><div class="stat-label small">정상</div><div class="stat-value text-success" style="font-size:1.3rem">{{ number_format($okCount) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card py-2"><div class="stat-label small">신규</div><div class="stat-value" style="font-size:1.3rem">{{ number_format($newCount) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card py-2"><div class="stat-label small">기존(업데이트)</div><div class="stat-value text-warning" style="font-size:1.3rem">{{ number_format($existCount) }}</div></div></div>
</div>

@if(count($errors) > 0)
    <div class="alert alert-warning">
        <strong><i class="bi bi-exclamation-triangle"></i> {{ count($errors) }}개 행에 문제가 있습니다.</strong>
        오류 행은 import 시 자동 건너뜁니다.
        <ul class="mb-0 mt-2 small">
            @foreach(array_slice($errors, 0, 15) as $err)
                <li><strong>행 {{ $err['row'] ?? '?' }}:</strong> {{ $err['msg'] ?? '' }}</li>
            @endforeach
            @if(count($errors) > 15)<li class="text-muted">... 외 {{ count($errors) - 15 }}개</li>@endif
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('admin.stocks.import.run', $jobId) }}" id="stockRunForm">
    @csrf
    <div class="card section-card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>데이터</strong>
                <div class="d-flex gap-3">
                    <div class="form-check">
                        <input type="radio" name="mode" id="mode_upsert" value="upsert" class="form-check-input" checked>
                        <label for="mode_upsert" class="form-check-label small">
                            <strong>UPSERT</strong> <span class="text-muted">(기존 있으면 업데이트, 없으면 신규)</span>
                        </label>
                    </div>
                    <div class="form-check">
                        <input type="radio" name="mode" id="mode_skip" value="skip_existing" class="form-check-input">
                        <label for="mode_skip" class="form-check-label small">
                            <strong>신규만 등록</strong> <span class="text-muted">(기존 건너뛰기)</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="small text-muted">
                <i class="bi bi-info-circle"></i>
                <strong>UPSERT</strong>: 정기 보충 시 추천. 같은 ISBN + 총판 조합이 있으면 수량 갱신.
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>ISBN13</th><th>도서</th><th>총판</th>
                        <th class="text-end">수량</th><th class="text-end">안전재고</th><th>상태</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $r)
                        @php
                            $hasErr = ! empty($r['_errors']);
                            $exists = ! empty($r['_exists']);
                        @endphp
                        <tr class="{{ $hasErr ? 'table-danger' : ($exists ? 'table-warning' : '') }}">
                            <td class="small">
                                {{ $r['_row'] ?? '?' }}
                                @if($hasErr)
                                    <i class="bi bi-exclamation-triangle text-danger" title="{{ implode(', ', $r['_errors']) }}"></i>
                                @elseif($exists)
                                    <span class="badge bg-warning text-dark">기존</span>
                                @endif
                            </td>
                            <td><code class="small">{{ $r['isbn'] ?? '' }}</code></td>
                            <td class="small">{{ $r['_book']['title'] ?? '-' }}</td>
                            <td class="small">{{ $r['_dist_name'] ?? '' }}</td>
                            <td class="text-end small">{{ number_format((int) ($r['_qty'] ?? 0)) }}</td>
                            <td class="text-end small text-muted">{{ $r['_threshold'] ?? '-' }}</td>
                            <td>
                                @if($hasErr)<span class="badge bg-danger">에러</span>
                                @elseif($exists)<span class="badge bg-warning text-dark">업데이트</span>
                                @else<span class="badge bg-success">신규</span>
                                @endif
                            </td>
                        </tr>
                        @if($hasErr)
                            <tr class="table-danger">
                                <td></td>
                                <td colspan="6" class="small text-danger">
                                    <i class="bi bi-x-circle"></i> {{ implode(' / ', $r['_errors']) }}
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            @if($okCount > 0)
                <div class="alert alert-success py-2 small mb-3">
                    <i class="bi bi-check-circle"></i>
                    <strong>{{ number_format($okCount) }}건이 등록 대기 중입니다.</strong>
                    아래 <strong class="navy">[ ⬆️ 등록 실행 ]</strong> 버튼을 클릭해야 실제 DB에 저장됩니다.
                </div>
            @endif
            <div class="d-flex justify-content-between align-items-center">
                <a href="{{ route('admin.stocks.import.show') }}" class="btn btn-secondary">취소</a>
                <button class="btn btn-primary btn-lg px-4" {{ $okCount === 0 ? 'disabled' : '' }}>
                    <i class="bi bi-cloud-upload"></i> 등록 실행 ({{ number_format($okCount) }}건)
                </button>
            </div>
        </div>
    </div>
</form>
@endsection
