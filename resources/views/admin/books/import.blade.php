@extends('admin.layouts.admin')
@section('title', '도서 엑셀 일괄 등록')

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.books.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 도서 목록
        </a>
        <h1 class="h4 mb-0 mt-1">도서 엑셀 일괄 등록</h1>
    </div>
    <a href="{{ route('admin.books.import.template') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-file-earmark-excel"></i> 빈 템플릿 다운로드
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong>업로드</strong></div>
            <form method="POST" action="{{ route('admin.books.import.preview') }}" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted">엑셀 파일 (.xlsx, .xls, 최대 10MB)</label>
                        <input type="file" name="file" accept=".xlsx,.xls" class="form-control" required>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <strong>형식</strong> — 첫 행은 헤더. 필수 컬럼: <code>ISBN13</code>, <code>제목</code>, <code>정가</code>.
                        선택 컬럼: <code>부제목 / 시리즈명 / 출판사 / 저자 / 출간일 / 학교 / 과목 / 학년 / 난이도 / 상태 / 표지URL / 규격 / 판쇄</code>.<br>
                        학년·난이도는 쉼표로 여러 개 입력 가능 (예: <code>초3, 초4</code>). 학교·과목·상태는 코드테이블의 <strong>한글명</strong> 그대로.
                    </div>
                </div>
                <div class="card-footer bg-white text-end">
                    <button class="btn btn-primary"><i class="bi bi-upload"></i> 업로드 후 미리보기</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong>최근 작업 이력</strong></div>
            <div class="card-body p-0">
                @if($recentJobs->isEmpty())
                    <div class="text-muted text-center py-3 small">작업 이력이 없습니다.</div>
                @else
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr>
                            <th>#</th><th>파일</th><th class="text-end">건수</th><th>상태</th><th>일시</th>
                        </tr></thead>
                        <tbody>
                            @foreach($recentJobs as $j)
                                <tr>
                                    <td>{{ $j->id }}</td>
                                    <td class="small">{{ $j->original_name }}</td>
                                    <td class="text-end small">
                                        <span class="text-success">{{ $j->success_rows }}</span>
                                        / <span class="text-danger">{{ $j->failed_rows }}</span>
                                    </td>
                                    <td><span class="badge bg-light text-dark">{{ $j->status }}</span></td>
                                    <td class="text-muted small">{{ \Carbon\Carbon::parse($j->created_at)->format('m-d H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
