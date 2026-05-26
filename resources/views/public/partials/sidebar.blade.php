@php
    $user  = auth()->user();
    $route = request()->route() ? request()->route()->getName() : '';
    $is = fn($name) => $route === $name ? 'active' : '';
    $startsWith = fn($prefix) => str_starts_with($route, $prefix) ? 'active' : '';
@endphp
<aside class="public-sidebar">
    <div class="public-sidebar-brand">
        <a href="/">
            <i class="bi bi-book-half"></i>
            <span>BookSys</span>
        </a>
    </div>

    <nav class="public-nav">
        <a href="{{ route('mypage') }}" class="nav-item {{ $is('mypage') }}">
            <i class="bi bi-speedometer2"></i> 대시보드
        </a>

        @switch($user->role_code)
            @case('distributor')
                <div class="nav-section">총판 메뉴</div>
                <a href="{{ route('my.orders.index') }}" class="nav-item {{ $startsWith('my.orders') }}">
                    <i class="bi bi-receipt"></i> 주문관리
                </a>
                <a href="{{ route('my.stocks.index') }}" class="nav-item {{ $startsWith('my.stocks') }}">
                    <i class="bi bi-box-seam"></i> 재고관리
                </a>
                <a href="{{ route('my.agents.index') }}" class="nav-item {{ $startsWith('my.agents') }}">
                    <i class="bi bi-person-badge"></i> 소속 영업자
                </a>
                @break

            @case('agent')
                <div class="nav-section">영업자 메뉴</div>
                <a href="{{ route('my.vendors.index') }}" class="nav-item {{ $is('my.vendors.index') }}">
                    <i class="bi bi-building"></i> 거래처(학원)
                </a>
                <a href="{{ route('my.vendors.create') }}" class="nav-item {{ $is('my.vendors.create') }}">
                    <i class="bi bi-building-add"></i> 학원등록
                </a>
                <a href="{{ route('my.orders.index') }}" class="nav-item {{ $startsWith('my.orders') }}">
                    <i class="bi bi-receipt"></i> 주문확인
                </a>
                <a href="{{ route('my.discounts.index') }}" class="nav-item {{ $startsWith('my.discounts') }}">
                    <i class="bi bi-percent"></i> 할인율 관리
                </a>
                <a href="{{ route('my.agent.student.import') }}" class="nav-item {{ $is('my.agent.student.import') }}">
                    <i class="bi bi-people"></i> 학생등록
                </a>
                @break

            @case('academy')
                <div class="nav-section">학원 메뉴</div>
                <a href="{{ route('my.order_new') }}" class="nav-item {{ $is('my.order_new') }}">
                    <i class="bi bi-bag-plus"></i> 도서주문
                </a>
                <a href="{{ route('my.orders.index') }}" class="nav-item {{ $startsWith('my.orders') }}">
                    <i class="bi bi-clipboard-data"></i> 주문내역
                </a>
                <a href="{{ route('my.classes.index') }}" class="nav-item {{ $startsWith('my.classes') }}">
                    <i class="bi bi-mortarboard"></i> 학급/학생
                </a>
                @break
        @endswitch

        <div class="nav-section">계정</div>
        <a href="{{ route('mypage.profile') }}" class="nav-item {{ $is('mypage.profile') }}">
            <i class="bi bi-person"></i> 정보수정
        </a>
        <form method="POST" action="{{ route('public.logout') }}" class="m-0">
            @csrf
            <button class="nav-item w-100 border-0 bg-transparent text-start" type="submit">
                <i class="bi bi-box-arrow-right"></i> 로그아웃
            </button>
        </form>
    </nav>
</aside>
