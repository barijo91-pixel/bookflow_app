@extends('public.layouts.app')
@section('title', '재고 일괄 등록')
@section('max_width', '1000px')

@section('content')
<div class="mb-3">
    <a href="{{ route('my.stocks.index') }}" class="text-muted small text-decoration-none">
        <i class="bi bi-arrow-left"></i> 재고관리
    </a>
    <h1 class="h4 navy mt-1 mb-1"><i class="bi bi-box-seam"></i> 재고 엑셀 일괄 등록</h1>
    <p class="text-muted small mb-0">현재 등록된 재고 <strong>{{ $currentCount }}</strong>건 · 같은 ISBN은 덮어쓰기</p>
</div>

@if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card section-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>1. 엑셀 파일 업로드</strong>
                <a href="{{ route('my.stocks.import.template') }}" class="btn btn-sm btn-outline-navy">
                    <i class="bi bi-download"></i> 템플릿 다운로드
                </a>
            </div>
            <form method="POST" action="{{ route('my.stocks.import.preview') }}" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted">파일 (.xlsx, .xls / 최대 10MB)</label>
                        <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <strong><i class="bi bi-info-circle"></i> 진행</strong>
                        <ol class="mb-0 mt-2 ps-3">
                            <li>템플릿 받기 → ISBN·보유수량·안전재고 입력</li>
                            <li>파일 선택 → <strong>미리보기</strong></li>
                            <li>검증 통과 행만 <strong>확정 등록</strong></li>
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
                <p class="mb-2"><strong>필수</strong>: ISBN, 보유수량</p>
                <p class="mb-2"><strong>선택</strong>: 안전재고(기본 0), 메모</p>
                <ul class="mb-2 ps-3">
                    <li>ISBN은 BookSys에 <strong>이미 등록된 도서</strong>여야 합니다.</li>
                    <li>없는 도서는 관리자에게 등록 요청 후 다시 시도.</li>
                    <li>본인 재고에 이미 같은 ISBN이 있으면 <strong>수량·안전재고가 덮어쓰기</strong>됩니다.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

@if($recentJobs->isNotEmpty())
    <div class="card section-card mt-3">
        <div class="card-header"><strong>최근 일괄 등록 이력</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
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
