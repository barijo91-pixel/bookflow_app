@extends('public.layouts.app')
@section('title', '학급&학생 (담당 학원)')
@section('max_width', '900px')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-people"></i> 학급&학생</h1>
    <p class="text-muted small mb-0">담당 학원의 학급을 만들고, 학급별로 학생을 엑셀로 일괄 등록합니다.</p>
</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
@if($errors->any())
    <div class="alert alert-danger py-2 small">
        <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@if($vendors->isEmpty())
    <div class="card section-card">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-building-x" style="font-size:2rem"></i>
            @if($wholesaleHidden > 0)
                <p class="mb-0 mt-2">담당 학원이 모두 도매입니다.</p>
                <p class="small mb-0">도매 학원은 학원이 일괄 매입하므로 학급·학생 등록이 필요 없습니다.</p>
            @else
                <p class="mb-0 mt-2">담당 학원이 없습니다.</p>
                <p class="small mb-0">관리자에게 학원 매핑 요청을 해주세요.</p>
            @endif
        </div>
    </div>
@else
    @foreach($vendors as $v)
        <div class="card section-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-building"></i> {{ $v->name }}</strong>
                <small class="text-muted">활성 학급 {{ count($vendorClasses[$v->id] ?? []) }}개</small>
            </div>
            @if(empty($vendorClasses[$v->id]) || count($vendorClasses[$v->id]) === 0)
                <div class="card-body small text-muted text-center py-3">
                    활성 학급이 없습니다. 아래에서 학급을 먼저 만들어주세요.
                </div>
            @else
                <div class="list-group list-group-flush">
                    @foreach($vendorClasses[$v->id] as $c)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <strong>{{ $c->name }}</strong>
                                    @if($c->grade_code)
                                        <span class="badge bg-light text-dark ms-2">{{ $c->grade_code }}</span>
                                    @endif
                                    <span class="text-muted small ms-2">학생 {{ $c->student_count }}명</span>
                                </div>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="collapse" data-bs-target="#classEdit{{ $c->id }}" title="학급 수정">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="{{ route('my.classes.students.import.show', $c->id) }}"
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-people-fill"></i> 학생 등록
                                    </a>
                                </div>
                            </div>

                            <div class="collapse mt-2" id="classEdit{{ $c->id }}">
                                <form method="POST" action="{{ route('my.agent.classes.update', $c->id) }}"
                                      class="row g-2 align-items-end">
                                    @csrf
                                    @method('PUT')
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted mb-1">학급명 *</label>
                                        <input type="text" name="name" value="{{ $c->name }}" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted mb-1">학년</label>
                                        <select name="grade_code" class="form-select form-select-sm">
                                            <option value="">선택 안 함</option>
                                            @foreach($grades as $g)
                                                <option value="{{ $g->code }}" @selected($c->grade_code === $g->code)>{{ $g->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted mb-1">메모</label>
                                        <input type="text" name="memo" value="{{ $c->memo }}" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-sm btn-primary w-100"><i class="bi bi-check-lg"></i> 저장</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- 학급 추가 --}}
            <div class="card-footer bg-white">
                <a class="link-name" style="font-size:1.05rem" data-bs-toggle="collapse"
                   href="#classNew{{ $v->id }}" role="button">
                    <i class="bi bi-plus-circle-fill"></i> 학급 추가
                </a>
                <div class="collapse mt-2" id="classNew{{ $v->id }}">
                    <form method="POST" action="{{ route('my.agent.classes.store', $v->id) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">학급명 *</label>
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="예: 초등 3학년 A반" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">학년</label>
                            <select name="grade_code" class="form-select form-select-sm">
                                <option value="">선택 안 함</option>
                                @foreach($grades as $g)
                                    <option value="{{ $g->code }}">{{ $g->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">메모</label>
                            <input type="text" name="memo" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i> 등록</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endif

<div class="alert alert-light border small text-muted mb-0">
    <i class="bi bi-info-circle"></i>
    <strong>안내</strong>:
    @if($wholesaleHidden > 0)
        도매 학원 {{ $wholesaleHidden }}곳은 학원이 일괄 매입하는 구조라 목록에서 제외됩니다.<br>
    @endif
    여기서 만든 학급은 학원 계정 화면(학급/학생)에도 그대로 보입니다.
    학원과 학급 구성을 맞춘 뒤 진행하세요. 잘못 등록된 학생은 학원 측 학급 상세에서 개별 제거 가능합니다.
</div>
@endsection
