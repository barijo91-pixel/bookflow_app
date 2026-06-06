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

<div class="card section-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-row-highlight">
            <thead class="table-light">
                <tr>
                    <th>학급명</th>
                    <th>학년</th>
                    <th>학생 수</th>
                    <th>상태</th>
                    <th>기간</th>
                    <th style="width:90px;" class="text-end">삭제</th>
                </tr>
            </thead>
            <tbody>
                @forelse($classes as $c)
                    <tr style="cursor:pointer" onclick="location.href='{{ route('my.classes.show', $c->id) }}'">
                        <td>
                            <a href="{{ route('my.classes.show', $c->id) }}" class="text-decoration-none navy fw-bold" onclick="event.stopPropagation()">
                                {{ $c->name }} <i class="bi bi-chevron-right small"></i>
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
                        <td class="text-end" onclick="event.stopPropagation()">
                            <form method="POST" action="{{ route('my.classes.destroy', $c->id) }}" class="d-inline"
                                  onsubmit="return confirm('「{{ addslashes($c->name) }}」 학급을 삭제할까요?\n학생이 있으면 차단됩니다.')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="학급 삭제">
                                    <i class="bi bi-trash"></i> 삭제
                                </button>
                            </form>
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
                            <input type="date" name="started_at" id="newClassStartedAt" class="form-control" value="{{ now()->toDateString() }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted">종료일 <small class="text-muted">(기본 +6개월)</small></label>
                            <input type="date" name="ended_at" id="newClassEndedAt" class="form-control" value="{{ now()->addMonths(6)->toDateString() }}">
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
@push('scripts')
<script>
// 학급 추가 모달: 시작일 변경 시 종료일 자동 +6개월
(function () {
    const startEl = document.getElementById('newClassStartedAt');
    const endEl   = document.getElementById('newClassEndedAt');
    if (! startEl || ! endEl) return;
    startEl.addEventListener('change', function () {
        if (! this.value) return;
        const d = new Date(this.value);
        d.setMonth(d.getMonth() + 6);
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        endEl.value = `${y}-${m}-${day}`;
    });
})();
</script>
@endpush
@endsection
