@extends('admin.layouts.admin')
@section('title', '사용자 일괄 등록')

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.users.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 사용자 목록
        </a>
        <h1 class="h4 mb-0 mt-1"><i class="bi bi-file-earmark-spreadsheet"></i> 사용자 엑셀 일괄 등록</h1>
    </div>
    <a href="{{ route('admin.users.import.template') }}" class="btn btn-sm btn-outline-navy">
        <i class="bi bi-download"></i> 템플릿 다운로드
    </a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card section-card">
            <div class="card-header"><strong>1. 엑셀 파일 업로드</strong></div>
            <form method="POST" action="{{ route('admin.users.import.preview') }}" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted">파일 (.xlsx, .xls / 최대 10MB)</label>
                        <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                    </div>
                    <div class="alert alert-info small">
                        <strong><i class="bi bi-info-circle"></i> 진행 순서</strong>
                        <ol class="mb-0 mt-2 ps-3">
                            <li>위의 <strong>"템플릿 다운로드"</strong> 클릭 → 엑셀 양식 받기</li>
                            <li>엑셀에 사용자 정보 입력 (시트1 헤더 유지)</li>
                            <li>파일 선택 후 <strong>"미리보기"</strong> 클릭</li>
                            <li>검증 결과 확인 후 <strong>"확정 등록"</strong></li>
                        </ol>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button class="btn btn-navy"><i class="bi bi-eye"></i> 미리보기</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card section-card">
            <div class="card-header"><strong>안내사항</strong></div>
            <div class="card-body small">
                <p class="mb-2"><strong>필수 컬럼</strong>: 아이디, 이름, 휴대폰, 역할</p>
                <ul class="mb-2 ps-3">
                    <li>아이디: <strong>영문+숫자 6~50자</strong> (대소문자 무관)</li>
                    <li>휴대폰: 숫자만 (자동 정제)</li>
                    <li>역할: <strong>총판 / 영업자 / 학원</strong></li>
                </ul>
                <p class="mb-2"><strong>선택 컬럼</strong>: 이메일, 시도, 시군구, 주소, 상세주소, 초기비밀번호</p>
                <hr>
                <p class="mb-1 text-muted">등록된 사용자는:</p>
                <ul class="mb-0 ps-3 text-muted">
                    <li>자동으로 활성화 (status=active)</li>
                    <li>첫 로그인 시 비밀번호 변경 강제</li>
                    <li>초기 비번 미지정 시 자동 8자 생성 (등록 후 1회 화면 표시)</li>
                </ul>
            </div>
        </div>
    </div>
</div>

@if($recentJobs->isNotEmpty())
    <div class="card section-card mt-3">
        <div class="card-header"><strong>최근 일괄 등록 이력</strong></div>
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
