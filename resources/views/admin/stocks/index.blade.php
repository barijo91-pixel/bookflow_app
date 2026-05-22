@extends('admin.layouts.admin')
@section('title', '재고 관리')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">재고 관리 <small class="text-muted fs-6">총판별 도서 재고</small></h1>
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#stockAddModal">
        <i class="bi bi-plus-lg"></i> 재고 추가
    </button>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="stat-card py-2">
            <div class="stat-label small">매핑 라인</div>
            <div class="stat-value" style="font-size:1.3rem">{{ number_format($summary['total_lines']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card py-2">
            <div class="stat-label small">총 재고 수량</div>
            <div class="stat-value" style="font-size:1.3rem">{{ number_format($summary['total_qty']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card py-2">
            <div class="stat-label small">안전재고 이하</div>
            <div class="stat-value text-warning" style="font-size:1.3rem">{{ number_format($summary['low_stock']) }}</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card py-2">
            <div class="stat-label small">재고 0</div>
            <div class="stat-value text-danger" style="font-size:1.3rem">{{ number_format($summary['zero_stock']) }}</div>
        </div>
    </div>
</div>

<form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">총판</label>
                <select name="distributor" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($distributors as $d)
                        <option value="{{ $d->id }}" @selected($distributor == $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">학교</label>
                <select name="school" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($schoolOptions as $s)
                        <option value="{{ $s->code }}" @selected($school === $s->code)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">과목</label>
                <select name="subject" class="form-select form-select-sm">
                    <option value="">전체</option>
                    @foreach($subjectOptions as $s)
                        <option value="{{ $s->code }}" @selected($subject === $s->code)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">검색 (제목/ISBN)</label>
                <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-1">
                <div class="form-check pt-3">
                    <input type="checkbox" id="low_chk" name="low" value="1" class="form-check-input" @checked($low)>
                    <label for="low_chk" class="form-check-label small">부족만</label>
                </div>
            </div>
            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
            </div>
        </div>
    </div>
</form>

<form method="POST" action="{{ route('admin.stocks.bulk-update') }}">
    @csrf @method('PUT')
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px;">표지</th>
                        <th>도서</th>
                        <th>ISBN</th>
                        <th>출판사</th>
                        <th>총판</th>
                        <th class="text-end" style="width:120px;">재고</th>
                        <th class="text-end" style="width:90px;">예약</th>
                        <th class="text-end" style="width:120px;">안전재고</th>
                        <th class="text-center" style="width:60px;">삭제</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stocks as $s)
                        <tr class="{{ $s->qty <= $s->low_stock_threshold ? 'table-warning' : '' }}">
                            <td>
                                @if($s->cover_path)
                                    <img src="{{ str_starts_with($s->cover_path, 'http') ? $s->cover_path : asset('storage/'.$s->cover_path) }}" style="height:32px;border-radius:3px">
                                @else
                                    <i class="bi bi-book text-muted"></i>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.books.show', $s->book_id) }}" class="text-decoration-none">{{ $s->title }}</a>
                                @if($s->subtitle)<div class="text-muted small">{{ $s->subtitle }}</div>@endif
                            </td>
                            <td><code class="small">{{ $s->isbn }}</code></td>
                            <td class="small">{{ $s->publisher_name }}</td>
                            <td><a href="{{ route('admin.users.show', $s->distributor_id) }}">{{ $s->distributor_name }}</a></td>
                            <td>
                                <input type="number" min="0" name="stocks[{{ $s->id }}][qty]" value="{{ $s->qty }}"
                                       class="form-control form-control-sm text-end {{ $s->qty == 0 ? 'border-danger' : ($s->qty <= $s->low_stock_threshold ? 'border-warning' : '') }}">
                            </td>
                            <td class="text-end text-muted">{{ number_format($s->reserved_qty) }}</td>
                            <td>
                                <input type="number" min="0" name="stocks[{{ $s->id }}][low_stock_threshold]" value="{{ $s->low_stock_threshold }}"
                                       class="form-control form-control-sm text-end">
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-link text-danger p-0"
                                        onclick="if(confirm('이 재고 매핑을 삭제할까요?')) {
                                            const f = document.createElement('form');
                                            f.method = 'POST'; f.action = '{{ url('admin/stocks') }}/{{ $s->id }}';
                                            f.innerHTML = '@csrf @method('DELETE')';
                                            document.body.appendChild(f); f.submit();
                                        }">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">데이터가 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <div>{{ $stocks->links() }}</div>
            <button class="btn btn-primary"><i class="bi bi-save"></i> 변경사항 일괄 저장</button>
        </div>
    </div>
</form>

{{-- Add Stock Modal --}}
<div class="modal fade" id="stockAddModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('admin.stocks.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">재고 추가 (도서 × 총판 매핑)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">총판</label>
                        <select name="distributor_user_id" class="form-select" required>
                            <option value="">선택</option>
                            @foreach($distributors as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">도서 ID</label>
                        <input type="number" name="book_id" class="form-control" required placeholder="도서 상세 페이지에서 #ID 확인">
                        <small class="text-muted">도서 목록에서 "보기" 들어가 URL의 ID 확인 또는 도서 ID 직접 입력</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">초기 수량</label>
                        <input type="number" min="0" name="qty" class="form-control text-end" value="0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">안전재고 임계값</label>
                        <input type="number" min="0" name="low_stock_threshold" class="form-control text-end" value="5">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button class="btn btn-primary">추가</button>
            </div>
        </form>
    </div>
</div>
@endsection
