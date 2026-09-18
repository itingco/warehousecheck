@extends('layouts.app')

@section('title', 'Change Password')

@section('content')
<div class="page-head">
    <div>
        <div class="eyebrow">ACCOUNT SECURITY</div>
        <h1>Change Password</h1>
        <p class="muted">
            Ubah password akun yang sedang Anda gunakan.
        </p>
    </div>
</div>

<div class="card" style="max-width: 620px;">
    <form method="POST" action="{{ route('account.password.update') }}">
        @csrf
        @method('PUT')

        <div class="form-grid" style="grid-template-columns: 1fr;">
            <label>
                Current Password
                <input
                    type="password"
                    name="current_password"
                    autocomplete="current-password"
                    required
                    autofocus
                >
            </label>

            <label>
                New Password
                <input
                    type="password"
                    name="password"
                    autocomplete="new-password"
                    minlength="8"
                    required
                >
                <span class="muted" style="font-size: 12px;">
                    Minimal 8 karakter.
                </span>
            </label>

            <label>
                Confirm New Password
                <input
                    type="password"
                    name="password_confirmation"
                    autocomplete="new-password"
                    minlength="8"
                    required
                >
            </label>
        </div>

        <div style="margin-top: 18px; display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="btn btn-secondary" type="submit">
                Update Password
            </button>
            <a class="btn btn-light" href="{{ route('dashboard') }}">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
