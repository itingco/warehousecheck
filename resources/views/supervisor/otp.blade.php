@extends('layouts.app')

@section('title', 'Supervisor OTP')

@section('content')

<div class="page-head">

    <div>
        <h1>Supervisor Override OTP</h1>

        <p class="muted">
            Generate OTP dari device Supervisor.
            Kode aktif kemudian dimasukkan pada popup
            <b>Supervisor Override</b> di halaman Checker.
        </p>
    </div>

</div>


<div class="otp-layout">

    <div class="card otp-card">

        @if($active)

            <div class="eyebrow">
                OTP AKTIF
            </div>

            <div
                id="activeOtpCode"
                class="otp-code"
            >
                {{ $active['code'] }}
            </div>

            <div class="otp-validity">

                <span>Aktif sampai</span>

                <strong>
                    {{ $active['otp']->expires_at->format('d-m-Y H:i:s') }}
                </strong>

            </div>

            <div class="otp-action-grid">

                <button
                    type="button"
                    id="copyOtpBtn"
                    class="btn btn-primary"
                    data-otp="{{ $active['code'] }}"
                >
                    Salin OTP
                </button>

                <form
                    method="POST"
                    action="{{ route('supervisor.otp.revoke') }}"
                >
                    @csrf

                    <button
                        class="btn btn-danger btn-block"
                    >
                        Revoke OTP
                    </button>
                </form>

            </div>

            <div
                id="copyOtpMessage"
                class="scan-message"
            ></div>

        @else

            <div class="eyebrow">
                BELUM ADA OTP AKTIF
            </div>

            <h2>Generate OTP baru</h2>

            <p class="muted">
                OTP berlaku selama 120 menit.
            </p>

        @endif

    </div>


    <div class="card">

        <h2>Cara menggunakan OTP</h2>

        <div class="otp-steps">

            <div class="otp-step">
                <span>1</span>

                <div>
                    <strong>Supervisor generate OTP</strong>

                    <p>
                        Klik tombol Generate New OTP di halaman ini.
                    </p>
                </div>
            </div>

            <div class="otp-step">
                <span>2</span>

                <div>
                    <strong>Buka item di device Checker</strong>

                    <p>
                        Pada item PENDING klik
                        <b>Supervisor Override</b>.
                    </p>
                </div>
            </div>

            <div class="otp-step">
                <span>3</span>

                <div>
                    <strong>Supervisor masukkan OTP</strong>

                    <p>
                        Ketik kode 6 digit pada popup di device Checker,
                        pilih alasan, lalu submit.
                    </p>
                </div>
            </div>

            <div class="otp-step">
                <span>4</span>

                <div>
                    <strong>Audit otomatis tersimpan</strong>

                    <p>
                        Sistem menyimpan supervisor, checker,
                        item, alasan dan waktu override.
                    </p>
                </div>
            </div>

        </div>


        <form
            method="POST"
            action="{{ route('supervisor.otp.generate') }}"
            class="otp-generate-form"
        >
            @csrf

            <button
                class="btn btn-primary btn-lg btn-block"
            >
                {{ $active ? 'Generate OTP Baru' : 'Generate New OTP' }}
            </button>
        </form>

        @if($active)
            <p class="muted small otp-warning">
                Generate baru akan me-revoke OTP aktif saat ini.
            </p>
        @endif

    </div>

</div>

@endsection


@push('scripts')

@if($active)

<script>
(() => {

    const button =
        document.getElementById(
            'copyOtpBtn'
        );

    const message =
        document.getElementById(
            'copyOtpMessage'
        );

    if (!button) {
        return;
    }

    button.addEventListener(
        'click',
        async () => {

            const otp =
                button.dataset.otp;

            try {

                await navigator.clipboard.writeText(
                    otp
                );

                message.textContent =
                    'OTP berhasil disalin.';

                message.className =
                    'scan-message success';

            } catch (error) {

                message.textContent =
                    'Tidak bisa copy otomatis. '
                    + 'Kode OTP: '
                    + otp;

                message.className =
                    'scan-message warning';
            }
        }
    );

})();
</script>

@endif

@endpush
