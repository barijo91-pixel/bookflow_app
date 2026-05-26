@extends('public.layouts.app')
@section('title', '재고 일괄 등록 미리보기')
@section('max_width', '1100px')

@section('content')
@php
    $validRows = collect($rows)->filter(fn ($r) => empty($r['_errors']))->values();
    $errorRows = collect($rows)->filter(fn ($r) => ! empty($r['_errors']))->values();
@endphp

<div class="mb-3">
    <a href="{{ route('my.stocks.import.show') }}" class="text-muted small text-decoration-none">
        <i class="bi bi-arrow-left"></i> 다시 업로드
    </a>
    <h1 class="h4 navy mt-1 mb-1"><i class="bi bi-eye"></i> 미리보기 · {{ $file }}</h1>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3">
                <div class="small text-muted">전체</div>
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
            <form method="POST" action="{{ route('my.stocks.import.run', $jobId) }}" class="w-100"
                  onsubmit="return confirm('{{ $validRows->count() }}건을 등록할까요? (기존 항목은 덮어쓰기)')">
                @csrf
                <button class="btn btn-primary w-100 btn-lg"><i class="bi bi-check-lg"></i> 확정 등록 ({{ $validRows->count() }}건)</button>
            </form>
        @else
            <div class="alert alert-warning small mb-0 w-100">등록 가능한 행이 없습니다.</div>
        @endif
    </div>
</div>

@if($errorRows->isNotEmpty())
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-danger text-white">
            <strong><i class="bi bi-exclamation-triangle"></i> 오류 {{ $errorRows->count() }}건</strong>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>행</th><th>ISBN</th><th>오류</th></tr></thead>
                <tbody>
                    @foreach($errorRows as $r)
                        <tr class="table-danger">
                            <td>{{ $r['_row'] }}</td>
                            <td class="small"><code>{{ $r['isbn'] ?? '-' }}</code></td>
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
        <div class="card-header bg-white"><strong>등록 예정 ({{ $validRows->count() }}건)</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr>
                    <th>행</th><th>ISBN</th><th>도서</th>
                    <th class="text-end">수량</th><th class="text-end">안전재고</th><th>처리</th>
                </tr></thead>
                <tbody>
                    @foreach($validRows as $r)
                        <tr>
                            <td>{{ $r['_row'] }}</td>
                            <td class="small"><code>{{ $r['isbn'] }}</code></td>
                            <td class="small">{{ $r['book_title'] ?? '-' }}</td>
                            <td class="text-end">{{ $r['qty'] }}</td>
                            <td class="text-end">{{ $r['low_stock_threshold'] }}</td>
                            <td class="small">
                                @if($r['already_exists'])
                                    <span class="badge bg-warning text-dark">덮어쓰기</span>
                                @else
                                    <span class="badge bg-success">신규</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
