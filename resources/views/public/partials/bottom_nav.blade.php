@php
    use Illuminate\Support\Facades\DB;
    $user  = auth()->user();
    $route = request()->route() ? request()->route()->getName() : '';
    $rs = fn($prefix) => str_starts_with($route, $prefix);

    // 미처리 주문 뱃지 (사이드바와 동일 기준)
    $navBadge = 0;
    if ($user->role_code === 'agent') {
        $navBadge = DB::table('orders')->where('agent_user_id', $user->id)
            ->where('status_code', 'requested')->whereNull('deleted_at')->count();
    } elseif ($user->role_code === 'distributor') {
        $navBadge = DB::table('orders')->where('distributor_user_id', $user->id)
            ->where('status_code', 'confirmed')->whereNull('deleted_at')->count();
    } elseif ($user->role_code === 'academy') {
        $vendorIds = DB::table('vendor_users')->where('user_id', $user->id)->pluck('vendor_id');
        if ($vendorIds->isNotEmpty()) {
            $navBadge = DB::table('orders')->whereIn('vendor_id', $vendorIds)
                ->whereIn('status_code', ['confirmed', 'accepted', 'shipped'])
                ->whereNull('deleted_at')->count();
        }
    }
@endphp
<nav class="mobile-bottom-nav">
    {{-- 공통: 홈 --}}
    <a href="{{ route('mypage') }}" class="mbn-item {{ $route === 'mypage' ? 'active' : '' }}">
        <i class="bi bi-house-door"></i><span>홈</span>
    </a>

    @switch($user->role_code)
        @case('distributor')
            <a href="{{ route('my.orders.index') }}" class="mbn-item {{ $rs('my.orders') ? 'active' : '' }}">
                <i class="bi bi-receipt"></i><span>주문</span>
                @if($navBadge > 0)<span class="mbn-badge">{{ $navBadge > 99 ? '99+' : $navBadge }}</span>@endif
            </a>
            <a href="{{ route('my.stocks.index') }}" class="mbn-item {{ $rs('my.stocks') ? 'active' : '' }}">
                <i class="bi bi-box-seam"></i><span>재고</span>
            </a>
            <a href="{{ route('mypage.settlements') }}" class="mbn-item {{ $route === 'mypage.settlements' ? 'active' : '' }}">
                <i class="bi bi-cash-stack"></i><span>정산</span>
            </a>
            @break

        @case('agent')
            <a href="{{ route('my.orders.index') }}" class="mbn-item {{ $rs('my.orders') ? 'active' : '' }}">
                <i class="bi bi-receipt"></i><span>주문</span>
                @if($navBadge > 0)<span class="mbn-badge">{{ $navBadge > 99 ? '99+' : $navBadge }}</span>@endif
            </a>
            <a href="{{ route('my.vendors.index') }}" class="mbn-item {{ $rs('my.vendors') ? 'active' : '' }}">
                <i class="bi bi-building"></i><span>거래처</span>
            </a>
            <a href="{{ route('mypage.settlements') }}" class="mbn-item {{ $route === 'mypage.settlements' ? 'active' : '' }}">
                <i class="bi bi-cash-stack"></i><span>정산</span>
            </a>
            @break

        @case('academy')
            <a href="{{ route('my.order_new') }}" class="mbn-item {{ $route === 'my.order_new' ? 'active' : '' }}">
                <i class="bi bi-bag-plus"></i><span>주문하기</span>
            </a>
            <a href="{{ route('my.orders.index') }}" class="mbn-item {{ $rs('my.orders') ? 'active' : '' }}">
                <i class="bi bi-clipboard-data"></i><span>주문내역</span>
                @if($navBadge > 0)<span class="mbn-badge">{{ $navBadge > 99 ? '99+' : $navBadge }}</span>@endif
            </a>
            <a href="{{ route('my.classes.index') }}" class="mbn-item {{ $rs('my.classes') ? 'active' : '' }}">
                <i class="bi bi-mortarboard"></i><span>학급</span>
            </a>
            @break
    @endswitch

    {{-- 공통: 더보기 (전체 메뉴 오프캔버스) --}}
    <button type="button" class="mbn-item" onclick="toggleMobileMenu()">
        <i class="bi bi-list"></i><span>더보기</span>
    </button>
</nav>
