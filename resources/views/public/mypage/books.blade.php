@extends('public.layouts.app')
@section('title', '교재 조회')
@section('max_width', '1400px')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-journals"></i> 교재 조회
        <small class="text-muted fs-6">{{ number_format($books->total()) }}권</small>
    </h1>
    <p class="text-muted small mb-0">
        판매 중인 교재입니다. 학원을 고르면 그 학원의 <strong>공급가</strong>까지 함께 보여줍니다.
    </p>
</div>

<form method="GET" action="{{ route('my.books.index') }}" class="card section-card mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            @if($vendors->isNotEmpty())
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">학원 (공급가 기준)</label>
                    <select name="vendor_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">정가만 보기</option>
                        @foreach($vendors as $v)
                            <option value="{{ $v->id }}" @selected($selectedVendor && $selectedVendor->id == $v->id)>{{ $v->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">출판사</label>
                <select name="publisher" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    @foreach($filterOptions['publisher'] as $p)
                        <option value="{{ $p->id }}" @selected($activeFilters['publisher'] == $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">분류</label>
                <select name="school" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    @foreach($filterOptions['school'] as $s)
                        <option value="{{ $s->code }}" @selected($activeFilters['school'] === $s->code)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">과목</label>
                <select name="subject" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">전체</option>
                    @foreach($filterOptions['subject'] as $s)
                        <option value="{{ $s->code }}" @selected($activeFilters['subject'] === $s->code)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">검색 (제목·ISBN·시리즈·저자)</label>
                <input type="text" name="q" value="{{ $activeFilters['q'] }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search"></i></button>
                <a href="{{ route('my.books.index') }}" class="btn btn-sm btn-outline-secondary" title="초기화"><i class="bi bi-x-lg"></i></a>
            </div>
        </div>
    </div>
</form>

@if($selectedVendor)
    <div class="alert alert-light border py-2 small mb-3">
        <i class="bi bi-percent"></i>
        <strong>{{ $selectedVendor->name }}</strong> 기준 공급가입니다.
        @if($generalRate !== null)
            기본 할인율 <strong class="navy">{{ rtrim(rtrim($generalRate, '0'), '.') }}%</strong>
            @if($bookDiscounts->isNotEmpty())
                · 도서별 할인율이 지정된 교재는 그 값이 우선 적용됩니다.
            @endif
        @endif
    </div>
@endif

<div class="card section-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    <th>교재</th>
                    <th>출판사</th>
                    <th>ISBN</th>
                    <th class="text-end">정가</th>
                    @if($selectedVendor)
                        <th class="text-end">할인율</th>
                        <th class="text-end">공급가</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($books as $b)
                    @php
                        $rate = $selectedVendor
                            ? (float) ($bookDiscounts[$b->id] ?? $generalRate ?? 0)
                            : null;
                        $supply = $rate !== null ? (int) round($b->price * (100 - $rate) / 100) : null;
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $b->title }}</strong>
                            @if($b->series_name)
                                <span class="text-muted small ms-1">{{ $b->series_name }}</span>
                            @endif
                        </td>
                        <td class="text-muted text-nowrap">{{ $b->publisher_name ?: '-' }}</td>
                        <td class="text-muted text-nowrap"><code>{{ $b->isbn ?: '-' }}</code></td>
                        <td class="text-end text-nowrap">{{ number_format($b->price) }}원</td>
                        @if($selectedVendor)
                            <td class="text-end text-nowrap">
                                {{ rtrim(rtrim(number_format($rate, 2), '0'), '.') }}%
                                @if($bookDiscounts->has($b->id))
                                    <span class="badge bg-secondary ms-1">개별</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap"><strong class="navy">{{ number_format($supply) }}원</strong></td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $selectedVendor ? 6 : 4 }}" class="text-center text-muted py-5">
                            조건에 맞는 교재가 없습니다.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($books->hasPages())
    <div class="mt-3">{{ $books->links() }}</div>
@endif
@endsection
