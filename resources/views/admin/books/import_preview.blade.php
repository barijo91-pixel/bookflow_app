@extends('admin.layouts.admin')
@section('title', '엑셀 미리보기')

@section('content')
@php
    $okCount = collect($rows)->filter(fn ($r) => empty($r['_errors']))->count();
    $newCount = collect($rows)->filter(fn ($r) => empty($r['_errors']) && empty($r['_exists']))->count();
    $existCount = collect($rows)->filter(fn ($r) => empty($r['_errors']) && ! empty($r['_exists']))->count();
@endphp
<div class="page-header">
    <div>
        <a href="{{ route('admin.books.import.show') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 업로드로 돌아가기
        </a>
        <h1 class="h4 mb-0 mt-1">미리보기 · <small class="text-muted">{{ $file }}</small></h1>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3"><div class="stat-card py-2"><div class="stat-label small">총 행</div><div class="stat-value" style="font-size:1.3rem">{{ number_format($total) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card py-2"><div class="stat-label small">정상</div><div class="stat-value text-success" style="font-size:1.3rem">{{ number_format($okCount) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card py-2"><div class="stat-label small">신규</div><div class="stat-value" style="font-size:1.3rem">{{ number_format($newCount) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card py-2"><div class="stat-label small">기존(ISBN 중복)</div><div class="stat-value text-warning" style="font-size:1.3rem">{{ number_format($existCount) }}</div></div></div>
</div>

@if(count($errors) > 0)
    <div class="alert alert-warning">
        <strong>{{ count($errors) }}개 행에 문제가 있습니다.</strong> 오류 행은 import 시 자동 건너뜁니다.
    </div>
@endif

<form method="POST" action="{{ route('admin.books.import.run', $jobId) }}">
    @csrf
    <div class="card section-card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>데이터</strong>
                <div class="d-flex gap-3">
                    <div class="form-check">
                        <input type="radio" name="mode" id="mode_skip" value="skip_existing" class="form-check-input" checked>
                        <label for="mode_skip" class="form-check-label small">
                            <strong>신규만 등록</strong> <span class="text-muted">(중복 ISBN은 건너뛰기)</span>
                        </label>
                    </div>
                    <div class="form-check">
                        <input type="radio" name="mode" id="mode_update" value="update_existing" class="form-check-input">
                        <label for="mode_update" class="form-check-label small">
                            <strong class="text-warning">기존 데이터 업데이트</strong> <span class="text-muted">(중복 ISBN은 부분 수정)</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="small text-muted">
                <i class="bi bi-info-circle"></i>
                <strong>부분 업데이트:</strong> 엑셀에 포함된 컬럼만 갱신됩니다.
                예) ISBN + 정가만 채워서 올리면 가격만 일괄 변경, 나머지 필드(시리즈명·학년 등)는 그대로 유지.
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>ISBN13</th>
                        <th>출판사 코드</th>
                        <th>제목</th>
                        <th>출판사</th>
                        <th class="text-end">정가</th>
                        <th>학교/과목</th>
                        <th>학년</th>
                        <th>상태</th>
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
                                    <span class="badge bg-warning text-dark">중복</span>
                                @endif
                            </td>
                            <td><code class="small">{{ $r['isbn'] ?? '' }}</code></td>
                            <td class="small">
                                @if(! empty($r['publisher_code']))
                                    <code>{{ $r['publisher_code'] }}</code>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="small">{{ $r['title'] ?? '' }}</td>
                            <td class="small">{{ $r['publisher_name'] ?? '' }}</td>
                            <td class="text-end small">{{ isset($r['price']) ? number_format($r['price']) : '' }}</td>
                            <td class="small">
                                @if(! empty($r['school_code'])) <span class="badge bg-light text-dark">{{ $r['school_code'] }}</span> @endif
                                @if(! empty($r['subject_code'])) <span class="badge bg-light text-dark">{{ $r['subject_code'] }}</span> @endif
                            </td>
                            <td class="small">{{ implode(', ', (array) ($r['grade_codes'] ?? [])) }}</td>
                            <td class="small">{{ $r['status_code'] ?? '' }}</td>
                        </tr>
                        @if($hasErr)
                            <tr class="table-danger">
                                <td></td>
                                <td colspan="8" class="small text-danger">
                                    <i class="bi bi-x-circle"></i> {{ implode(' / ', $r['_errors']) }}
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <small class="text-muted">정상 {{ $okCount }}건만 등록됩니다. 오류 행은 자동 제외.</small>
            <div>
                <a href="{{ route('admin.books.import.show') }}" class="btn btn-secondary">취소</a>
                <button class="btn btn-primary" {{ $okCount === 0 ? 'disabled' : '' }}>
                    <i class="bi bi-cloud-upload"></i> 등록 실행
                </button>
            </div>
        </div>
    </div>
</form>
@endsection
