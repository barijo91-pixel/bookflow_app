@extends('admin.layouts.admin')
@section('title', '사용자 일괄 등록 미리보기')

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.users.import.show') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 다시 업로드
        </a>
        <h1 class="h4 mb-0 mt-1"><i class="bi bi-eye"></i> 미리보기 · {{ $file }}</h1>
    </div>
</div>

@php
    $validRows  = collect($rows)->filter(fn ($r) => empty($r['_errors']))->values();
    $errorRows  = collect($rows)->filter(fn ($r) => ! empty($r['_errors']))->values();
@endphp

{{-- 요약 --}}
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
                <div class="small text-muted">유효 (등록 가능)</div>
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
            <form method="POST" action="{{ route('admin.users.import.run', $jobId) }}" class="w-100"
                  onsubmit="return confirm('유효한 {{ $validRows->count() }}건을 등록하시겠습니까?')">
                @csrf
                <button class="btn btn-navy w-100 btn-lg"><i class="bi bi-check-lg"></i> 확정 등록 ({{ $validRows->count() }}건)</button>
            </form>
        @else
            <div class="alert alert-warning small mb-0 w-100">등록 가능한 행이 없습니다.</div>
        @endif
    </div>
</div>

{{-- 오류 목록 --}}
@if($errorRows->isNotEmpty())
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-danger text-white">
            <strong><i class="bi bi-exclamation-triangle"></i> 오류 {{ $errorRows->count() }}건 (등록되지 않음)</strong>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr>
                    <th>행</th><th>아이디</th><th>이름</th><th>휴대폰</th><th>역할</th><th>오류 사유</th>
                </tr></thead>
                <tbody>
                    @foreach($errorRows as $r)
                        <tr class="table-danger">
                            <td>{{ $r['_row'] }}</td>
                            <td class="small">{{ $r['login_id'] ?? '-' }}</td>
                            <td class="small">{{ $r['name'] ?? '-' }}</td>
                            <td class="small">{{ $r['phone'] ?? '-' }}</td>
                            <td class="small">{{ $r['role_code'] ?? '-' }}</td>
                            <td class="small text-danger">{{ implode(', ', $r['_errors']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

{{-- 유효 목록 --}}
@if($validRows->isNotEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong>등록 예정 ({{ $validRows->count() }}건)</strong></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr>
                    <th>행</th><th>아이디</th><th>이름</th><th>휴대폰</th><th>이메일</th><th>역할</th><th>지역</th><th>초기비번</th>
                </tr></thead>
                <tbody>
                    @foreach($validRows as $r)
                        <tr>
                            <td>{{ $r['_row'] }}</td>
                            <td class="small"><code>{{ $r['login_id'] }}</code></td>
                            <td class="small">{{ $r['name'] }}</td>
                            <td class="small">{{ $r['phone'] }}</td>
                            <td class="small text-muted">{{ $r['email'] ?? '-' }}</td>
                            <td><span class="badge bg-light text-dark">{{ $r['role_code'] }}</span></td>
                            <td class="small text-muted">{{ $r['region_id'] ? '#'.$r['region_id'] : '-' }}</td>
                            <td class="small text-muted">{{ $r['password'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
