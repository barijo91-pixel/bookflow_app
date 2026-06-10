@extends('admin.layouts.admin')
@section('title', '표지 이미지 일괄 업로드')

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.books.index') }}" class="text-muted small text-decoration-none">
            <i class="bi bi-arrow-left"></i> 도서 마스터
        </a>
        <h1 class="h4 mb-0 mt-1"><i class="bi bi-images"></i> 표지 이미지 일괄 업로드</h1>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">
        <strong><i class="bi bi-check-circle"></i> {{ session('success') }}</strong>
        @if(session('missing_total') > 0)
            <hr class="my-2">
            <div class="small">
                <strong>매칭 누락 ({{ number_format(session('missing_total')) }}건):</strong>
                @foreach(session('missing_sample', []) as $isbn)
                    <code class="me-1">{{ $isbn }}</code>
                @endforeach
                @if(session('missing_total') > 20)<span class="text-muted">... 외 {{ session('missing_total') - 20 }}개</span>@endif
            </div>
        @endif
    </div>
@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

{{-- 현황 --}}
<div class="row g-2 mb-3">
    <div class="col-md-4">
        <div class="stat-card py-2">
            <div class="stat-label small">총 도서</div>
            <div class="stat-value" style="font-size:1.3rem">{{ number_format($total) }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card py-2">
            <div class="stat-label small">표지 있음</div>
            <div class="stat-value text-success" style="font-size:1.3rem">{{ number_format($withCover) }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card py-2">
            <div class="stat-label small">표지 없음</div>
            <div class="stat-value text-warning" style="font-size:1.3rem">{{ number_format($noCover) }}</div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('admin.books.covers.upload') }}" enctype="multipart/form-data">
    @csrf
    <div class="card section-card">
        <div class="card-header"><strong><i class="bi bi-file-zip"></i> ZIP 파일 업로드</strong></div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label small text-muted">표지 이미지 ZIP (.zip, 최대 500MB)</label>
                <input type="file" name="zip" accept=".zip" class="form-control" required>
            </div>

            <div class="alert alert-info small mb-3">
                <strong><i class="bi bi-info-circle"></i> 매칭 규칙 (우선순위)</strong>
                <ol class="mb-0 mt-1 ps-3">
                    <li>도서 엑셀의 <code>표지파일명</code> 컬럼에 입력된 파일명</li>
                    <li>ZIP 안 <code>{ISBN}.jpg</code> / <code>{ISBN}.png</code> / <code>{ISBN}.webp</code></li>
                    <li>ZIP 안 <code>{출판사코드}.jpg</code> 등</li>
                </ol>
                <hr class="my-2">
                <span class="text-muted">※ ZIP 안 서브폴더도 자동 검색됩니다. 대소문자 무관.</span>
            </div>

            <div class="form-check mb-2">
                <input type="checkbox" name="try_aladdin" value="1" id="try_aladdin" class="form-check-input">
                <label for="try_aladdin" class="form-check-label small">
                    <strong>알라딘 API 보완</strong> — ZIP에서 못 찾은 도서는 알라딘에서 표지 URL 자동 조회
                </label>
            </div>
            <div class="form-check mb-2">
                <input type="checkbox" name="overwrite" value="1" id="overwrite" class="form-check-input">
                <label for="overwrite" class="form-check-label small">
                    <strong class="text-warning">기존 표지 덮어쓰기</strong> — 이미 표지가 있는 도서도 다시 매칭 (기본: 표지 없는 도서만)
                </label>
            </div>
        </div>
        <div class="card-footer text-end">
            <button class="btn btn-primary btn-lg px-4">
                <i class="bi bi-cloud-upload"></i> 업로드 + 매칭 실행
            </button>
        </div>
    </div>
</form>
@endsection
