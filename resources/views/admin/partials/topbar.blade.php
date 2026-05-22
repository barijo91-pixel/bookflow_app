@php $u = auth()->user(); @endphp
<header class="admin-topbar">
    <button class="btn btn-sm btn-outline-secondary d-md-none" id="sidebar-toggle">
        <i class="bi bi-list"></i>
    </button>
    <div class="ms-auto d-flex align-items-center gap-3">
        <span class="text-muted small d-none d-sm-inline">
            <i class="bi bi-person-circle"></i>
            {{ $u?->name }} ({{ $u?->email }})
        </span>
        <form method="POST" action="{{ route('admin.logout') }}" class="m-0">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-box-arrow-right"></i> 로그아웃
            </button>
        </form>
    </div>
</header>
