@extends('admin.layouts.admin')
@section('title', '운영 준비도 체크')

@section('content')
<div class="page-header">
    <h1 class="h4 mb-0">운영 준비도 체크 <small class="text-muted fs-6">실제 운영 진입 전 필수/권장 확인</small></h1>
</div>

<div class="alert alert-light border small mb-3">
    <i class="bi bi-info-circle"></i>
    실제 학원/학부모와의 거래를 시작하기 전에 아래 항목들을 점검해주세요.
    <strong>필수</strong> 항목은 미완료 시 정상 운영이 어렵습니다.
</div>

{{-- 필수 항목 --}}
<div class="card mb-3 border-danger">
    <div class="card-header bg-danger text-white">
        <strong><i class="bi bi-exclamation-octagon-fill"></i> 필수 항목 (운영 전 반드시 완료)</strong>
    </div>
    <div class="card-body">
        @include('admin.dashboard._checklist_items', ['items' => $checks['critical']])
    </div>
</div>

{{-- 권장 항목 --}}
<div class="card mb-3 border-warning">
    <div class="card-header bg-warning text-dark">
        <strong><i class="bi bi-exclamation-triangle-fill"></i> 권장 항목 (운영 품질 향상)</strong>
    </div>
    <div class="card-body">
        @include('admin.dashboard._checklist_items', ['items' => $checks['important']])
    </div>
</div>

{{-- 정리 항목 --}}
<div class="card border-info">
    <div class="card-header bg-info text-white">
        <strong><i class="bi bi-trash-fill"></i> 데모 데이터 정리</strong>
    </div>
    <div class="card-body">
        @include('admin.dashboard._checklist_items', ['items' => $checks['cleanup']])
        <hr class="my-3">
        <div class="small">
            <strong>데모 데이터 일괄 정리:</strong>
            <code class="ms-2">php artisan booksys:clean-demo</code>
            <p class="text-muted mt-1 mb-0">
                <i class="bi bi-info-circle"></i>
                서버에서 실행 (Docker: <code>docker compose exec app php artisan booksys:clean-demo --confirm</code>).
                기본 시드 사용자(agent1, academy1 등)와 데모 주문을 안전하게 삭제합니다.
            </p>
        </div>
    </div>
</div>
@endsection
