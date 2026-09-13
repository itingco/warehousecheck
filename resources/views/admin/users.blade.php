@extends('layouts.app')

@section('title', 'Users')

@section('content')

<div class="page-head">

    <div>

        <h1>
            User Management
        </h1>

        <p class="muted">
            Picker, Checker, Supervisor, Admin.
        </p>

    </div>

</div>


<div class="two-col">

    <div class="card">

        <h2>
            Tambah User
        </h2>

        <form
            method="POST"
            action="{{ route('admin.users.store') }}"
            class="stack"
        >

            @csrf

            <label>
                Username
                <input
                    name="username"
                    maxlength="50"
                    required
                >
            </label>

            <label>
                Nama
                <input
                    name="name"
                    maxlength="100"
                    required
                >
            </label>

            <label>
                Role

                <select
                    name="role"
                    required
                >
                    <option value="picker">
                        Picker
                    </option>

                    <option value="checker">
                        Checker
                    </option>

                    <option value="supervisor">
                        Supervisor
                    </option>

                    <option value="admin">
                        Admin
                    </option>
                </select>
            </label>

            <label>
                Password

                <input
                    type="password"
                    name="password"
                    minlength="8"
                    required
                >
            </label>

            <button
                class="btn btn-primary"
            >
                Save User
            </button>

        </form>

    </div>


    <div class="card table-card">

        <div class="table-scroll">

            <table>

                <thead>
                <tr>
                    <th>Username</th>
                    <th>Nama</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>

                <tbody>

                @foreach($users as $user)

                    <tr>

                        <td>
                            {{ $user->username }}
                        </td>

                        <td>
                            {{ $user->name }}
                        </td>

                        <td>
                            {{ strtoupper($user->role) }}
                        </td>

                        <td>

                            <span
                                class="badge {{ $user->is_active ? 'badge-ok' : 'badge-danger' }}"
                            >
                                {{ $user->is_active ? 'ACTIVE' : 'INACTIVE' }}
                            </span>

                        </td>

                        <td>

                            <form
                                method="POST"
                                action="{{ route('admin.users.toggle', $user) }}"
                            >

                                @csrf

                                <button
                                    class="btn btn-light btn-sm"
                                >
                                    {{ $user->is_active ? 'Disable' : 'Enable' }}
                                </button>

                            </form>

                        </td>

                    </tr>

                @endforeach

                </tbody>

            </table>

        </div>

    </div>

</div>

@endsection
