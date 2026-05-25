@extends('public.layouts.app')
@section('title', '학급/학생')

@section('content')
<div class="mb-3 d-flex justify-content-between align-items-center">
    <div>
        <h1 class="h4 navy mb-1"><i class="bi bi-mortarboard"></i> 학급/학생</h1>
        <p class="text-muted small mb-0">{{ $vendor->name ?? '' }} · 총 {{ $classes->count() }}개 학급</p>
    </div>
    <button type="button" class="btn btn-navy btn-sm" data-bs-toggle="modal" data-bs-target="#classCreateModal">
        <i class="bi bi-plus-lg"></i> 학급 추가
    </button>
</div>

@if(session('success'))
    <div class="alert alert-success py-2 small">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger py-2 small">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>학급명</th>
                    <th>학년</th>
                    <th>학생 수</th>
                    <th>상태</th>
                    <th>기간</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($classes as $c)
                    <tr style="cursor:pointer" onclick="location.href='{{ route('my.classes.show', $c->id) }}'">
                        <td>
                            <a href="{{ route('my.classes.show', $c->id) }}" class="text-decoration-none navy fw-bold" onclick="event.stopPropagation()">
                                {{ $c->name }}
                            </a>
                        </td>
                        <td class="small text-muted">
                            @php $g = $grades->firstWhere('code', $c->grade_code); @endphp
                            {{ $g->name ?? '-' }}
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">{{ $c->student_count }}명</span>
                        </td>
                        <td>
                            @if($c->status === 'active')
                                <span class="badge bg-success">진행중</span>
                            @else
                                <span class="badge bg-secondary">종료</span>
                            @endif
                        </td>
                        <td class="small text-muted">
                            {{ $c->started_at ? \Carbon\Carbon::parse($c->started_at)->format('Y-m-d') : '-' }}
                            @if($c->ended_at) ~ {{ \Carbon\Carbon::parse($c->ended_at)->format('Y-m-d') }} @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('my.classes.show', $c->id) }}" class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation()">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-mortarboard" style="font-size:2rem"></i>
                            <p class="mb-0 mt-2">아직 학급이 없습니다.</p>
                            <p class="small">우측 상단의 "학급 추가" 버튼으로 시작하세요.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- 학급 추가 모달 --}}
<div class="modal fade" id="classCreateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('my.classes.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title navy"><i class="bi bi-plus-lg"></i> 새 학급 추가</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted">학급명 *</label>
                        <input type="text" name="name" class="form-control" placeholder="예: 초3 영어반 A" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">학년</label>
                        <select name="grade_code" class="form-select">
                            <option value="">선택 안 함</option>
                            @foreach($grades as $g)
                                <option value="{{ $g->code }}">{{ $g->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small text-muted">시작일</label>
                            <input type="date" name="started_at" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted">종료일</label>
                            <input type="date" name="ended_at" class="form-control">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label small text-muted">메모</label>
                        <textarea name="memo" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-navy">학급 추가</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
