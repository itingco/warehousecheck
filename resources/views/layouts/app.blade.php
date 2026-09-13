<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>
        @yield('title', 'Warehouse Checking')
    </title>

    <link
        rel="stylesheet"
        href="{{ asset('css/warehouse.css') }}"
    >
</head>
<body>

<header class="topbar">
    <div class="container topbar-inner">

        <a
            class="brand"
            href="{{ route('dashboard') }}"
        >
            Warehouse Checking
        </a>

        @auth
            <nav class="nav">
                <a href="{{ route('dashboard') }}">
                    Dashboard
                </a>

                @if(auth()->user()->hasAnyRole(['checker','admin']))
                    <a href="{{ route('checks.create') }}">
                        New Check
                    </a>
                @endif

                @if(auth()->user()->hasAnyRole(['supervisor','admin']))
                    <a href="{{ route('supervisor.otp') }}">
                        Supervisor OTP
                    </a>
                @endif

                @if(auth()->user()->role === 'admin')
                    <a href="{{ route('admin.users') }}">
                        Users
                    </a>
                @endif

                <span class="user-pill">
                    {{ auth()->user()->name }}
                    ·
                    {{ strtoupper(auth()->user()->role) }}
                </span>

                <form
                    method="POST"
                    action="{{ route('logout') }}"
                >
                    @csrf

                    <button class="btn btn-light btn-sm">
                        Logout
                    </button>
                </form>
            </nav>
        @endauth

    </div>
</header>

<main class="container page">

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
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

@stack('scripts')

</body>
</html>
