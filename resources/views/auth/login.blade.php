@extends('layouts.app')

@section('title', 'Login')

@section('content')

<div class="auth-wrap">

    <div class="card auth-card">

        <h1>Warehouse Checking</h1>

        <p class="muted">
            Login untuk mengakses aplikasi gudang.
        </p>

        <form
            method="POST"
            action="{{ route('login.submit') }}"
            class="stack"
        >
            @csrf

            <label>
                Username

                <input
                    type="text"
                    name="username"
                    value="{{ old('username') }}"
                    required
                    autofocus
                >
            </label>

            <label>
                Password

                <input
                    type="password"
                    name="password"
                    required
                >
            </label>

            <label class="inline-check">
                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                >

                Remember me
            </label>

            <button
                class="btn btn-primary btn-block"
            >
                Login
            </button>

        </form>

    </div>

</div>

@endsection
