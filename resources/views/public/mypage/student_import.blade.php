@extends('public.layouts.app')
@section('title', '학생 일괄 등록 · '.$class->name)
@section('max_width', '1000px')

@section('content')
<div class="mb-3">
    <a href="{{ route('my.classes.show', $class->id) }}" class="text-muted small text-decoration-none">
        <i class="bi bi-arrow-left"></i> 학급 상세
    </a>
    <h1 class="h4 navy mt-1 mb-1">
        <i class="bi bi-people"></i> 학생 일괄 등록
    </h1>
    <p class="text-muted small mb-0">
        <strong>{{ $vendor->name }}</strong> · {{ $class->name }}
        · 현재 등록 학생 <strong>{{ $currentCount }}명</strong>
    </p>
</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card section-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>1. 엑셀 파일 업로드</strong>
                <a href="{{ route('my.classes.students.import.template', $class->id) }}" class="btn btn-sm btn-outline-navy">
                    <i class="bi bi-download"></i> 템플릿 다운로드
                </a>
            </div>
            <form method="POST" action="{{ route('my.classes.students.import.preview', $class->id) }}" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted">파일 (.xlsx, .xls / 최대 10MB)</label>
                        <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <strong><i class="bi bi-info-circle"></i> 진행</strong>
                        <ol class="mb-0 mt-2 ps-3">
                            <li>템플릿 받기 → 엑셀 입력 (학생이름·학부모이름·학부모휴대폰 필수)</li>
                            <li>파일 선택 → <strong>미리보기</strong></li>
                            <li>검증 결과 확인 → <strong>확정 등록</strong></li>
                        </ol>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button class="btn btn-primary"><i class="bi bi-eye"></i> 미리보기</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card section-card">
            <div class="card-header"><strong>안내</strong></div>
            <div class="card-body small">
                <p class="mb-2"><strong>필수 컬럼</strong></p>
                <ul class="mb-2 ps-3">
                    <li>학생이름</li>
                    <li>학부모이름</li>
                    <li>학부모휴대폰 (숫자만, 자동 정제)</li>
                </ul>
                <p class="mb-2"><strong>선택 컬럼</strong></p>
                <ul class="mb-2 ps-3">
                    <li>학년 (예: 초3, 중1)</li>
                    <li>학부모이메일</li>
                    <li>메모</li>
                </ul>
                <hr>
                <p class="mb-0 text-muted">
                    <i class="bi bi-info"></i> 같은 휴대폰을 가진 학부모는 자동으로 하나로 묶임 (형제·자매 연결).
                    이미 등록된 학생 이름은 중복으로 처리.
                </p>
            </div>
        </div>
    </div>
</div>

@if($recentJobs->isNotEmpty())
    <div class="card section-card mt-3">
        <div class="card-header"><strong>이 학급의 최근 일괄 등록 이력</strong></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr>
                    <th>#</th><th>파일</th><th>상태</th>
                    <th class="text-end">전체</th><th class="text-end">성공</th><th class="text-end">실패</th>
                    <th>일시</th>
                </tr></thead>
                <tbody>
                    @foreach($recentJobs as $j)
                        <tr>
                            <td>{{ $j->id }}</td>
                            <td class="small">{{ $j->original_name }}</td>
                            <td><span class="badge bg-{{ $j->status === 'done' ? 'success' : 'warning' }}">{{ $j->status }}</span></td>
                            <td class="text-end">{{ $j->total_rows }}</td>
                            <td class="text-end text-success">{{ $j->success_rows ?? '-' }}</td>
                            <td class="text-end text-danger">{{ $j->failed_rows }}</td>
                            <td class="small text-muted">{{ \Carbon\Carbon::parse($j->created_at)->format('m-d H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
