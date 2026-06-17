@php
    $route = request()->route() ? request()->route()->getName() : '';
    $is = fn($prefix) => str_starts_with($route, $prefix) ? 'active' : '';
@endphp
<aside class="admin-sidebar">
    <div class="sidebar-brand">
        <a href="{{ route('admin.dashboard') }}">
            <i class="bi bi-book-half"></i>
            <span>BookSys <small>admin</small></span>
        </a>
    </div>
    <nav class="admin-nav">
        <a href="{{ route('admin.dashboard') }}" class="nav-item {{ $is('admin.dashboard') }}">
            <i class="bi bi-speedometer2"></i> 대시보드
        </a>
        <div class="nav-section">거래·도서</div>
        <a href="{{ route('admin.orders.index') }}" class="nav-item {{ $is('admin.orders') }}">
            <i class="bi bi-receipt"></i> 주문관리
        </a>
        <a href="{{ route('admin.books.index') }}" class="nav-item {{ $is('admin.books') }}">
            <i class="bi bi-journals"></i> 도서관리
        </a>
        <a href="{{ route('admin.stocks.index') }}" class="nav-item {{ $is('admin.stocks') }}">
            <i class="bi bi-box-seam"></i> 재고관리
        </a>
        <a href="{{ route('admin.vendors.index') }}" class="nav-item {{ $is('admin.vendors') }}">
            <i class="bi bi-building"></i> 거래처(학원)
        </a>
        <a href="{{ route('admin.settlement.records') }}" class="nav-item {{ $is('admin.settlement.records') . $is('admin.settlement.record_show') }}">
            <i class="bi bi-cash-stack"></i> 정산 레코드
        </a>
        <a href="{{ route('admin.settlement.simulator') }}" class="nav-item {{ $is('admin.settlement.simulator') . $is('admin.settlement.order_preview') }}">
            <i class="bi bi-calculator"></i> 정산 시뮬레이터
        </a>
        <div class="nav-section">회원</div>
        <a href="{{ route('admin.users.index') }}" class="nav-item {{ $is('admin.users.index') . $is('admin.users.import') }}">
            <i class="bi bi-people"></i> 사용자 목록
        </a>
        <a href="{{ route('admin.users.pending') }}" class="nav-item {{ $is('admin.users.pending') }}">
            <i class="bi bi-person-plus"></i> 승인 대기열
        </a>
        <div class="nav-section">운영</div>
        <a href="{{ route('admin.code-groups.index') }}" class="nav-item {{ $is('admin.code-groups') . $is('admin.codes') }}">
            <i class="bi bi-tags"></i> 코드 테이블
        </a>
        <a href="{{ route('admin.regions.index') }}" class="nav-item {{ $is('admin.regions') }}">
            <i class="bi bi-geo-alt"></i> 지역
        </a>
        <a href="{{ route('admin.notifications.compose') }}" class="nav-item {{ $is('admin.notifications.compose') . $is('admin.notifications.send') }}">
            <i class="bi bi-chat-dots"></i> 문자 발송
        </a>
        <a href="{{ route('admin.notifications.templates') }}" class="nav-item {{ $is('admin.notifications.templates') . $is('admin.notifications.logs') }}">
            <i class="bi bi-bell"></i> 알림 템플릿/이력
        </a>
        <a href="{{ route('admin.settings.edit') }}" class="nav-item {{ $is('admin.settings') }}">
            <i class="bi bi-gear"></i> 사이트 설정
        </a>
        <a href="{{ route('admin.audit-logs.index') }}" class="nav-item {{ $is('admin.audit-logs') }}">
            <i class="bi bi-shield-check"></i> 감사 로그
        </a>
        <a href="{{ route('admin.operations_checklist') }}" class="nav-item {{ $is('admin.operations_checklist') }}">
            <i class="bi bi-clipboard-check"></i> 운영 준비도
        </a>
    </nav>
</aside>
