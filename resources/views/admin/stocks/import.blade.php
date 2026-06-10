@extends('admin.layouts.admin')
@section('title', '재고 일괄 등록')

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.stocks.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 재고 관리
        </a>
        <h1 class="h4 mb-0 mt-1"><i class="bi bi-box-arrow-in-down"></i> 재고 일괄 등록</h1>
    </div>
    <a href="{{ route('admin.stocks.import.template') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-download"></i> 템플릿 다운로드
    </a>
</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card section-card">
            <div class="card-header"><strong>업로드</strong></div>
            <form method="POST" action="{{ route('admin.stocks.import.preview') }}" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted">엑셀 파일 (.xlsx, .xls, 최대 10MB)</label>
                        <input type="file" name="file" accept=".xlsx,.xls" class="form-control" required>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <strong>컬럼 순서 (A → D):</strong>
                        <code>ISBN13 | 총판명 | 수량 | 안전재고</code><br>
                        <span class="text-muted">※ 헤더 이름은 무관 — 컬럼 위치로 인식합니다.</span><br>
                        <strong>필수:</strong> ISBN(A) · 총판명(B) · 수량(C)<br>
                        <span class="text-muted">총판명은 사용자 목록에 등록된 총판(role=distributor) 이름과 정확히 일치해야 합니다.</span>
                        <hr class="my-2">
                        <strong class="text-warning"><i class="bi bi-pencil-square"></i> 기존 재고 업데이트</strong><br>
                        같은 ISBN + 총판 조합이 이미 있으면 자동으로 수량/안전재고 갱신.
                        <br><span class="text-muted">예) 정기 보충 시 같은 엑셀 형식으로 재업로드.</span>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button class="btn btn-primary"><i class="bi bi-upload"></i> 업로드 후 미리보기</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card section-card">
            <div class="card-header"><strong>최근 작업 이력</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr>
                        <th>#</th><th>파일</th><th>건수</th><th>상태</th><th>일시</th>
                    </tr></thead>
                    <tbody>
                        @forelse($recentJobs as $j)
                            <tr>
                                <td class="small">{{ $j->id }}</td>
                                <td class="small text-truncate" style="max-width:140px">{{ $j->original_name }}</td>
                                <td class="small">{{ $j->success_rows ?? 0 }} / {{ $j->total_rows }}</td>
                                <td>
                                    @switch($j->status)
                                        @case('pending')<span class="badge bg-warning text-dark">대기</span>@break
                                        @case('running')<span class="badge bg-info">진행</span>@break
                                        @case('done')<span class="badge bg-success">완료</span>@break
                                        @default <span class="badge bg-light text-dark">{{ $j->status }}</span>
                                    @endswitch
                                </td>
                                <td class="small text-muted">{{ \Carbon\Carbon::parse($j->created_at)->format('m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3 small">이력 없음</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
