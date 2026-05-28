@extends('public.layouts.app')
@section('title', '학생 일괄 등록 미리보기')
@section('max_width', '1100px')

@section('content')
@php
    $validRows = collect($rows)->filter(fn ($r) => empty($r['_errors']))->values();
    $errorRows = collect($rows)->filter(fn ($r) => ! empty($r['_errors']))->values();
@endphp

<div class="mb-3">
    <a href="{{ route('my.classes.students.import.show', $class->id) }}" class="text-muted small text-decoration-none">
        <i class="bi bi-arrow-left"></i> 다시 업로드
    </a>
    <h1 class="h4 navy mt-1 mb-1"><i class="bi bi-eye"></i> 미리보기 · {{ $file }}</h1>
    <p class="text-muted small mb-0">{{ $class->name }}</p>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="small text-muted">전체 행</div>
                <div class="h4 mb-0 navy">{{ $total }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="small text-muted">유효</div>
                <div class="h4 mb-0 text-success">{{ $validRows->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="small text-muted">오류</div>
                <div class="h4 mb-0 text-danger">{{ $errorRows->count() }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 d-flex align-items-center">
        @if($validRows->count() > 0)
            <form method="POST" action="{{ route('my.classes.students.import.run', [$class->id, $jobId]) }}" class="w-100"
                  onsubmit="return confirm('유효한 {{ $validRows->count() }}명을 등록할까요?')">
                @csrf
                <button class="btn btn-primary w-100 btn-lg"><i class="bi bi-check-lg"></i> 확정 등록 ({{ $validRows->count() }}명)</button>
            </form>
        @else
            <div class="alert alert-warning small mb-0 w-100">등록 가능한 학생이 없습니다.</div>
        @endif
    </div>
</div>

@if($errorRows->isNotEmpty())
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-danger text-white">
            <strong><i class="bi bi-exclamation-triangle"></i> 오류 {{ $errorRows->count() }}건 (등록되지 않음)</strong>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr>
                    <th>행</th><th>학생</th><th>학년</th><th>학부모</th><th>휴대폰</th><th>오류 사유</th>
                </tr></thead>
                <tbody>
                    @foreach($errorRows as $r)
                        <tr class="table-danger">
                            <td>{{ $r['_row'] }}</td>
                            <td class="small">{{ $r['name'] ?? '-' }}</td>
                            <td class="small">{{ $r['grade_code'] ?? '-' }}</td>
                            <td class="small">{{ $r['parent_name'] ?? '-' }}</td>
                            <td class="small">{{ format_phone($r['parent_phone'] ?? '') ?: '-' }}</td>
                            <td class="small text-danger">{{ implode(', ', $r['_errors']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@if($validRows->isNotEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong>등록 예정 ({{ $validRows->count() }}명)</strong></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr>
                    <th>행</th><th>학생</th><th>학년</th><th>학급</th><th>학부모</th><th>휴대폰</th><th>이메일</th><th>메모</th>
                </tr></thead>
                <tbody>
                    @foreach($validRows as $r)
                        <tr>
                            <td>{{ $r['_row'] }}</td>
                            <td class="small">{{ $r['name'] }}</td>
                            <td class="small">{{ $r['grade_code'] ?? '-' }}</td>
                            <td class="small text-muted">{{ $r['class_name'] ?? '-' }}</td>
                            <td class="small">{{ $r['parent_name'] }}</td>
                            <td class="small">{{ format_phone($r['parent_phone']) }}</td>
                            <td class="small text-muted">{{ $r['parent_email'] ?? '-' }}</td>
                            <td class="small text-muted">{{ \Illuminate\Support\Str::limit($r['memo'] ?? '', 40) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
