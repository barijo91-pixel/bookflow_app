@extends('public.layouts.app')
@section('title', '재고 관리')
@section('max_width', '1200px')

@section('content')
<div class="mb-3 d-flex justify-content-between align-items-center">
    <div>
        <h1 class="h4 navy mb-1"><i class="bi bi-box-seam"></i> 재고 관리</h1>
        <p class="text-muted small mb-0">{{ $user->name }} 총판의 보유 도서 재고</p>
    </div>
    {{-- 신규 등록은 데스크탑에서만 (모바일은 재고 조회·수량 조정 위주) --}}
    <div class="d-none d-md-flex gap-2">
        <button type="button" class="btn btn-navy btn-sm" data-bs-toggle="modal" data-bs-target="#stockAddModal">
            <i class="bi bi-plus-lg"></i> 신규 도서 재고 등록
        </button>
        <a href="{{ route('my.stocks.import.show') }}" class="btn btn-outline-navy btn-sm">
            <i class="bi bi-file-earmark-spreadsheet"></i> 신규 도서 등록 (엑셀)
        </a>
    </div>
</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

{{-- 요약 카드 --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card section-card">
            <div class="card-body py-3">
                <div class="small text-muted">취급 도서</div>
                <div class="h4 mb-0 navy">{{ number_format($summary['total_books']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card section-card">
            <div class="card-body py-3">
                <div class="small text-muted">총 재고 수량</div>
                <div class="h4 mb-0 navy">{{ number_format($summary['total_qty']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card section-card">
            <div class="card-body py-3">
                <div class="small text-muted">안전재고 이하 <i class="bi bi-exclamation-triangle text-warning"></i></div>
                <div class="h4 mb-0 {{ $summary['low_stock'] > 0 ? 'text-warning' : '' }}">{{ number_format($summary['low_stock']) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card section-card">
            <div class="card-body py-3">
                <div class="small text-muted">재고 0건 <i class="bi bi-x-circle text-danger"></i></div>
                <div class="h4 mb-0 {{ $summary['zero_stock'] > 0 ? 'text-danger' : '' }}">{{ number_format($summary['zero_stock']) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- 필터 --}}
<form method="GET" class="card section-card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-md-7">
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm"
                       placeholder="도서 제목 또는 ISBN 검색">
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input type="checkbox" name="low" value="1" id="lowOnly" class="form-check-input" @checked($low)>
                    <label for="lowOnly" class="form-check-label small">안전재고 이하만 보기</label>
                </div>
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-sm btn-navy"><i class="bi bi-search"></i> 검색</button>
            </div>
        </div>
    </div>
</form>

<div class="card section-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    <th>도서</th>
                    <th class="text-end">정가</th>
                    <th style="width:110px" class="text-end">수량</th>
                    <th style="width:120px" class="text-end">안전재고</th>
                    <th style="width:90px" class="text-end">예약</th>
                    <th style="width:140px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($stocks as $s)
                    @php
                        $isLow  = $s->qty <= $s->low_stock_threshold;
                        $isZero = $s->qty == 0;
                    @endphp
                    <tr class="{{ $isZero ? 'table-danger' : ($isLow ? 'table-warning' : '') }}">
                        <td class="small">
                            <strong>{{ $s->title }}</strong>
                            @if($s->subtitle)<span class="text-muted">— {{ $s->subtitle }}</span>@endif
                            <div class="text-muted small">
                                <code>{{ $s->isbn }}</code> · {{ $s->publisher_name ?? '-' }}
                            </div>
                        </td>
                        <td class="text-end small text-muted">{{ number_format($s->price) }}원</td>
                        <form method="POST" action="{{ route('my.stocks.update', $s->stock_id) }}">
                            @csrf @method('PUT')
                            <td>
                                <input type="number" name="qty" value="{{ $s->qty }}" min="0"
                                       class="form-control form-control-sm text-end">
                            </td>
                            <td>
                                <input type="number" name="low_stock_threshold" value="{{ $s->low_stock_threshold }}" min="0"
                                       class="form-control form-control-sm text-end">
                            </td>
                            <td class="text-end small text-muted">{{ $s->reserved_qty }}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-navy">저장</button>
                        </form>
                                <form method="POST" action="{{ route('my.stocks.destroy', $s->stock_id) }}"
                                      onsubmit="return confirm('이 도서 재고를 제거할까요?')" class="d-inline ms-1">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-box-seam" style="font-size:2rem"></i>
                            <p class="mb-0 mt-2">
                                @if($q || $low)검색 결과가 없습니다.
                                @else 등록된 재고가 없습니다. "신규 도서 재고 등록" 으로 시작하세요. @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($stocks->hasPages())
        <div class="card-footer">{{ $stocks->links() }}</div>
    @endif
</div>

{{-- 신규 도서 재고 등록 모달 --}}
<div class="modal fade" id="stockAddModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('my.stocks.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title navy"><i class="bi bi-plus-lg"></i> 신규 도서 재고 등록</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if($availableBooks->isEmpty())
                        <div class="text-muted text-center py-3">등록 가능한 새 도서가 없습니다.<br>(이미 모든 도서를 등록했거나 시스템에 책이 없음)</div>
                    @else
                        <div class="mb-3">
                            <label class="form-label small text-muted">도서 선택 *</label>
                            <select name="book_id" class="form-select" required>
                                <option value="">선택</option>
                                @foreach($availableBooks as $b)
                                    <option value="{{ $b->id }}">
                                        {{ \Illuminate\Support\Str::limit($b->title, 40) }} ({{ number_format($b->price) }}원)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small text-muted">초기 수량 *</label>
                                <input type="number" name="qty" value="50" min="0" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted">안전재고 임계값</label>
                                <input type="number" name="low_stock_threshold" value="5" min="0" class="form-control">
                            </div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">취소</button>
                    @if(! $availableBooks->isEmpty())
                        <button type="submit" class="btn btn-navy">등록</button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
