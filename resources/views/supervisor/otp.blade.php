@extends('layouts.app')

@section('title', 'Supervisor OTP')

@section('content')

<div class="page-head">

    <div>

        <h1>
            Supervisor Override OTP
        </h1>

        <p class="muted">
            OTP aktif selama 2 jam dan dapat digunakan
            untuk beberapa override selama belum expired/revoke.
        </p>

    </div>

</div>


<div class="otp-layout">

    <div class="card otp-card">

        @if($active)

            <div class="eyebrow">
                OTP AKTIF
            </div>

            <div class="otp-code">
                {{ $active['code'] }}
            </div>

            <p>
                Expired:

                <strong>
                    {{ $active['otp']->expires_at->format('d-m-Y H:i:s') }}
                </strong>
            </p>

            <form
                method="POST"
                action="{{ route('supervisor.otp.revoke') }}"
            >

                @csrf

                <button class="btn btn-danger">
                    Revoke OTP
                </button>

            </form>

        @else

            <div class="eyebrow">
                BELUM ADA OTP AKTIF
            </div>

            <h2>
                Generate OTP baru
            </h2>

            <p class="muted">
                OTP baru berlaku 120 menit.
            </p>

        @endif

    </div>


    <div class="card">

        <h2>
            Generate OTP
        </h2>

        <p class="muted">
            Jika ada OTP aktif, OTP lama otomatis di-revoke.
        </p>

        <form
            method="POST"
            action="{{ route('supervisor.otp.generate') }}"
        >

            @csrf

            <button
                class="btn btn-primary btn-lg"
            >
                Generate New OTP
            </button>

        </form>

    </div>

</div>

@endsection
