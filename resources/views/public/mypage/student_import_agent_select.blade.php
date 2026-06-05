@extends('public.layouts.app')
@section('title', '학생 일괄 등록 (담당 학원)')
@section('max_width', '900px')

@section('content')
<div class="mb-3">
    <h1 class="h4 navy mb-1"><i class="bi bi-people"></i> 학생 일괄 등록</h1>
    <p class="text-muted small mb-0">담당 학원의 학급을 선택하면 엑셀 일괄 등록을 진행할 수 있습니다.</p>
</div>

@if($vendors->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-building-x" style="font-size:2rem"></i>
            <p class="mb-0 mt-2">담당 학원이 없습니다.</p>
            <p class="small mb-0">관리자에게 학원 매핑 요청을 해주세요.</p>
        </div>
    </div>
@else
    @foreach($vendors as $v)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-building"></i> {{ $v->name }}</strong>
                <small class="text-muted">활성 학급 {{ count($vendorClasses[$v->id] ?? []) }}개</small>
            </div>
            @if(empty($vendorClasses[$v->id]) || count($vendorClasses[$v->id]) === 0)
                <div class="card-body small text-muted text-center py-3">활성 학급이 없습니다.</div>
            @else
                <div class="list-group list-group-flush">
                    @foreach($vendorClasses[$v->id] as $c)
                        <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <strong>{{ $c->name }}</strong>
                                @if($c->grade_code)
                                    <span class="badge bg-light text-dark ms-2">{{ $c->grade_code }}</span>
                                @endif
                                <span class="text-muted small ms-2">학생 {{ $c->student_count }}명</span>
                            </div>
                            <a href="{{ route('my.classes.students.import.show', $c->id) }}"
                               class="btn btn-sm btn-primary">
                                <i class="bi bi-people-fill"></i> 학생 등록
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
@endif

<div class="alert alert-light border small text-muted mb-0">
    <i class="bi bi-info-circle"></i>
    <strong>안내</strong>:
    학원이 영업자에게 학생 등록을 요청한 경우, 학원 측 학급 정보 확인 후 진행하세요.
    잘못 등록된 학생은 학급 상세 페이지에서 개별 제거 가능합니다.
</div>
@endsection
