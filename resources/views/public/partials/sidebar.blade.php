@php
    use Illuminate\Support\Facades\DB;
    $user  = auth()->user();
    $route = request()->route() ? request()->route()->getName() : '';
    $is = fn($name) => $route === $name ? 'active' : '';
    $startsWith = fn($prefix) => str_starts_with($route, $prefix) ? 'active' : '';

    // 역할별 미처리 주문 건수 (사이드바 뱃지)
    $orderBadge = 0;
    if ($user->role_code === 'agent') {
        $orderBadge = DB::table('orders')->where('agent_user_id', $user->id)
            ->where('status_code', 'requested')->whereNull('deleted_at')->count();
    } elseif ($user->role_code === 'distributor') {
        $orderBadge = DB::table('orders')->where('distributor_user_id', $user->id)
            ->where('status_code', 'confirmed')->whereNull('deleted_at')->count();
    } elseif ($user->role_code === 'academy') {
        $vendorIds = DB::table('vendor_users')->where('user_id', $user->id)->pluck('vendor_id');
        if ($vendorIds->isNotEmpty()) {
            // 진행 중 (requested~accepted) — 단순 시각 알림
            $orderBadge = DB::table('orders')->whereIn('vendor_id', $vendorIds)
                ->whereIn('status_code', ['confirmed', 'accepted', 'shipped'])
                ->whereNull('deleted_at')->count();
        }
    }

    // 학원 거래구분 (도매면 학급/학생 메뉴 숨김 — 소매 B2C 전용)
    $academyTradeType = null;
    if ($user->role_code === 'academy') {
        $vid = DB::table('vendor_users')->where('user_id', $user->id)->value('vendor_id');
        if ($vid) $academyTradeType = DB::table('vendors')->where('id', $vid)->value('trade_type');
    }
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
                    @if($orderBadge > 0)<span class="badge bg-danger ms-auto">{{ $orderBadge }}</span>@endif
                </a>
                <a href="{{ route('my.stocks.index') }}" class="nav-item {{ $startsWith('my.stocks') }}">
                    <i class="bi bi-box-seam"></i> 재고관리
                </a>
                <a href="{{ route('my.agents.index') }}" class="nav-item {{ $startsWith('my.agents') }}">
                    <i class="bi bi-person-badge"></i> 영업자 관리
                </a>
                <a href="{{ route('mypage.income_simulator') }}" class="nav-item {{ $is('mypage.income_simulator') }}">
                    <i class="bi bi-graph-up-arrow"></i> 수익 시뮬레이션
                </a>
                <a href="{{ route('mypage.settlements') }}" class="nav-item {{ $is('mypage.settlements') }}">
                    <i class="bi bi-cash-stack"></i> 정산 내역
                </a>
                @break

            @case('agent')
                <div class="nav-section">영업자 메뉴</div>
                <a href="{{ route('my.orders.index') }}" class="nav-item {{ $startsWith('my.orders') }}">
                    <i class="bi bi-receipt"></i> 주문확인
                    @if($orderBadge > 0)<span class="badge bg-danger ms-auto">{{ $orderBadge }}</span>@endif
                </a>
                <a href="{{ route('my.vendors.index') }}" class="nav-item {{ $is('my.vendors.index') }}">
                    <i class="bi bi-building"></i> 거래처(학원)
                </a>
                <a href="{{ route('my.vendors.create') }}" class="nav-item {{ $is('my.vendors.create') }}">
                    <i class="bi bi-building-add"></i> 학원등록
                </a>
                <a href="{{ route('my.agent.student.import') }}" class="nav-item d-none d-md-flex {{ $is('my.agent.student.import') }}">
                    <i class="bi bi-people"></i> 학생등록
                </a>
                <a href="{{ route('my.discounts.index') }}" class="nav-item {{ $startsWith('my.discounts') }}">
                    <i class="bi bi-percent"></i> 할인율 관리
                </a>
                <a href="{{ route('mypage.income_simulator') }}" class="nav-item {{ $is('mypage.income_simulator') }}">
                    <i class="bi bi-graph-up-arrow"></i> 수익 시뮬레이션
                </a>
                <a href="{{ route('mypage.tax') }}" class="nav-item {{ $is('mypage.tax') }}">
                    <i class="bi bi-receipt-cutoff"></i> 세무 정보
                </a>
                <a href="{{ route('mypage.settlements') }}" class="nav-item {{ $is('mypage.settlements') }}">
                    <i class="bi bi-cash-stack"></i> 정산 내역
                </a>
                @break

            @case('academy')
                <div class="nav-section">학원 메뉴</div>
                <a href="{{ route('my.order_new') }}" class="nav-item {{ $is('my.order_new') }}">
                    <i class="bi bi-bag-plus"></i> 도서주문
                </a>
                <a href="{{ route('my.orders.index') }}" class="nav-item {{ $startsWith('my.orders') }}">
                    <i class="bi bi-clipboard-data"></i> 주문내역
                    @if($orderBadge > 0)<span class="badge bg-secondary ms-auto">{{ $orderBadge }}</span>@endif
                </a>
                @if($academyTradeType !== 'wholesale')
                <a href="{{ route('my.classes.index') }}" class="nav-item {{ $startsWith('my.classes') }}">
                    <i class="bi bi-mortarboard"></i> 학급/학생
                </a>
                @endif
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
        {{-- PWA 설치 — JS가 미설치 시에만 표시 --}}
        <button type="button" id="sidebarInstallBtn" class="nav-item w-100 border-0 bg-transparent text-start" style="display:none; margin-top:1.5rem;">
            <i class="bi bi-download"></i> 북시스 앱설치
        </button>
        <div id="sidebarInstalledHint" class="small mt-2 px-3" style="display:none; color:#94a3b8;">
            <i class="bi bi-check-circle"></i> 설치 완료
        </div>
    </nav>
</aside>
