<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Warehouse Checking')</title>

    <link rel="stylesheet" href="{{ asset('css/warehouse.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}">
</head>
<body>
@auth
<div class="app-shell" id="appShell">
    <aside class="app-sidebar" id="appSidebar" aria-label="Main navigation">
        <div class="sidebar-brand">
            <a href="{{ route('dashboard') }}" class="sidebar-brand-link">
                <span class="sidebar-brand-mark">WC</span>
                <span class="sidebar-brand-text">
                    <strong>Warehouse</strong>
                    <small>Checking</small>
                </span>
            </a>
        </div>

        <nav class="sidebar-nav">
            <div class="sidebar-section-label">MAIN</div>

            <a
                href="{{ route('dashboard') }}"
                class="sidebar-link {{ request()->routeIs('dashboard') && !request('document_type') ? 'is-active' : '' }}"
                title="Dashboard"
            >
                <span class="sidebar-icon">⌂</span>
                <span class="sidebar-label">Dashboard</span>
            </a>

            <a
                href="{{ route('dashboard', ['document_type' => 'invoice']) }}"
                class="sidebar-link {{ request()->routeIs('dashboard') && request('document_type') === 'invoice' ? 'is-active' : '' }}"
                title="Sales Invoice"
            >
                <span class="sidebar-icon">SI</span>
                <span class="sidebar-label">Sales Invoice</span>
            </a>

            <a
                href="{{ route('dashboard', ['document_type' => 'good_transfer']) }}"
                class="sidebar-link {{ request()->routeIs('dashboard') && request('document_type') === 'good_transfer' ? 'is-active' : '' }}"
                title="Goods Transfer"
            >
                <span class="sidebar-icon">GT</span>
                <span class="sidebar-label">Goods Transfer</span>
            </a>

            @if(auth()->user()->is_active && auth()->user()->role !== 'admin')
                <a
                    href="{{ route('checks.create') }}"
                    class="sidebar-link {{ request()->routeIs('checks.create') ? 'is-active' : '' }}"
                    title="New Check"
                >
                    <span class="sidebar-icon">＋</span>
                    <span class="sidebar-label">New Check</span>
                </a>
            @endif

            @if(auth()->user()->hasAnyRole(['supervisor','admin']))
                <div class="sidebar-section-label">SUPERVISOR</div>

                <a
                    href="{{ route('supervisor.otp') }}"
                    class="sidebar-link {{ request()->routeIs('supervisor.otp*') ? 'is-active' : '' }}"
                    title="Supervisor OTP"
                >
                    <span class="sidebar-icon">OTP</span>
                    <span class="sidebar-label">Supervisor OTP</span>
                </a>
            @endif

            @if(auth()->user()->role === 'admin')
                <div class="sidebar-section-label">ADMIN</div>

                <a
                    href="{{ route('admin.scan-errors') }}"
                    class="sidebar-link {{ request()->routeIs('admin.scan-errors*') ? 'is-active' : '' }}"
                    title="Scan Errors"
                >
                    <span class="sidebar-icon">!</span>
                    <span class="sidebar-label">Scan Errors</span>
                </a>

                <a
                    href="{{ route('admin.qty-overrides') }}"
                    class="sidebar-link {{ request()->routeIs('admin.qty-overrides*') || request()->routeIs('admin.*.qty-override') ? 'is-active' : '' }}"
                    title="Qty Override"
                >
                    <span class="sidebar-icon">±</span>
                    <span class="sidebar-label">Qty Override</span>
                </a>

                <a
                    href="{{ route('admin.users') }}"
                    class="sidebar-link {{ request()->routeIs('admin.users*') ? 'is-active' : '' }}"
                    title="Users"
                >
                    <span class="sidebar-icon">U</span>
                    <span class="sidebar-label">Users</span>
                </a>
            @endif

            <div class="sidebar-section-label">ACCOUNT</div>

            <a
                href="{{ route('account.password.edit') }}"
                class="sidebar-link {{ request()->routeIs('account.password.*') ? 'is-active' : '' }}"
                title="Change Password"
            >
                <span class="sidebar-icon">⚙</span>
                <span class="sidebar-label">Change Password</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-avatar">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="sidebar-user-info">
                    <strong>{{ auth()->user()->name }}</strong>
                    <span>{{ strtoupper(auth()->user()->role) }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="sidebar-logout-form">
                @csrf
                <button class="sidebar-logout" type="submit" title="Logout">
                    <span class="sidebar-icon">⇥</span>
                    <span class="sidebar-label">Logout</span>
                </button>
            </form>
        </div>
    </aside>

    <button
        type="button"
        class="sidebar-backdrop"
        id="sidebarBackdrop"
        aria-label="Close menu"
    ></button>

    <div class="app-main">
        <header class="app-header">
            <div class="app-header-left">
                <button
                    type="button"
                    class="sidebar-toggle"
                    id="sidebarToggle"
                    aria-controls="appSidebar"
                    aria-expanded="true"
                    title="Buka / tutup menu"
                >
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <div class="app-header-title">
                    <strong>@yield('title', 'Warehouse Checking')</strong>
                    <span>Warehouse Checking System</span>
                </div>
            </div>

            <div class="app-header-user">
                <span>{{ auth()->user()->name }}</span>
                <strong>{{ strtoupper(auth()->user()->role) }}</strong>
            </div>
        </header>
@endauth

@guest
<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand" href="{{ route('dashboard') }}">
            Warehouse Checking
        </a>
    </div>
</header>
@endguest

<main class="container page">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @yield('content')
</main>

@auth
    </div>
</div>

<script>
(function () {
    const body = document.body;
    const toggle = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebarBackdrop');
    const desktopBreakpoint = 900;
    const storageKey = 'warehouse_sidebar_collapsed';

    if (!toggle || !backdrop) {
        return;
    }

    const isMobile = () => window.innerWidth <= desktopBreakpoint;

    const syncAria = () => {
        const isOpen = isMobile()
            ? body.classList.contains('sidebar-mobile-open')
            : !body.classList.contains('sidebar-collapsed');

        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    if (!isMobile() && localStorage.getItem(storageKey) === '1') {
        body.classList.add('sidebar-collapsed');
    }

    syncAria();

    toggle.addEventListener('click', function () {
        if (isMobile()) {
            body.classList.toggle('sidebar-mobile-open');
        } else {
            body.classList.toggle('sidebar-collapsed');
            localStorage.setItem(
                storageKey,
                body.classList.contains('sidebar-collapsed') ? '1' : '0'
            );
        }

        syncAria();
    });

    backdrop.addEventListener('click', function () {
        body.classList.remove('sidebar-mobile-open');
        syncAria();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            body.classList.remove('sidebar-mobile-open');
            syncAria();
        }
    });

    window.addEventListener('resize', function () {
        if (!isMobile()) {
            body.classList.remove('sidebar-mobile-open');

            if (localStorage.getItem(storageKey) === '1') {
                body.classList.add('sidebar-collapsed');
            } else {
                body.classList.remove('sidebar-collapsed');
            }
        } else {
            body.classList.remove('sidebar-collapsed');
        }

        syncAria();
    });
})();
</script>
@endauth

@stack('scripts')
</body>
</html>
