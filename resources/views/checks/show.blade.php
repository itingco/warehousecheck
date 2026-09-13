@extends('layouts.app')

@section(
    'title',
    'Check ' . $check->invoice_number
)

@section('content')

<div class="page-head">

    <div>

        <div class="eyebrow">
            {{ $check->company }}
            ·
            {{ $check->source_database }}
        </div>

        <h1>
            {{ $check->invoice_number }}
        </h1>

        <p class="muted">
            {{ $check->customer_name }}

            ·

            {{ optional($check->invoice_date)->format('d-m-Y') }}
        </p>

    </div>

    <span
        class="badge badge-{{ strtolower($check->status) }}"
    >
        {{ $check->status }}
    </span>

</div>


<div class="summary-grid">

    <div class="stat">
        <span>Picker</span>
        <strong>
            {{ $check->picker->name }}
        </strong>
    </div>

    <div class="stat">
        <span>Checker</span>
        <strong>
            {{ $check->checker->name }}
        </strong>
    </div>

    <div class="stat">
        <span>Alamat Kirim</span>
        <strong>
            {{ $check->shipping_address ?: '-' }}
        </strong>
    </div>

</div>


@if($canOperate)

    <div class="scanner-card">

        <div>

            <div class="eyebrow">
                BARCODE INPUT
            </div>

            <h2>
                Scan barang sekarang
            </h2>

            <p class="muted">
                Scanner USB/Bluetooth dapat langsung
                digunakan seperti keyboard.
            </p>

        </div>

        <div class="scanner-input-wrap">

            <input
                id="barcodeInput"
                class="scanner-input"
                autocomplete="off"
                placeholder="Scan barcode + Enter"
                autofocus
            >

            <div
                id="scanMessage"
                class="scan-message"
            >
                Menunggu scan...
            </div>

        </div>

    </div>

@endif


<div class="card table-card">

    <div class="table-scroll">

        <table>

            <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>UOM</th>
                <th>Qty Invoice</th>
                <th>Ratio Invoice</th>
                <th>Progress Base</th>
                <th>Status</th>

                @if($canOperate)
                    <th>Supervisor Override</th>
                @endif
            </tr>
            </thead>

            <tbody>

            @foreach($check->details as $line)

                <tr
                    id="line-{{ $line->id }}"
                    data-line-id="{{ $line->id }}"
                >

                    <td>
                        {{ $loop->iteration }}
                    </td>

                    <td>

                        <strong>
                            {{ $line->item_code }}
                        </strong>

                        <div class="muted small">
                            {{ $line->item_name }}
                        </div>

                    </td>

                    <td>
                        {{ $line->uom_code }}
                    </td>

                    <td>
                        {{ number_format((float) $line->expected_qty, 4) }}
                    </td>

                    <td>

                        @if($line->invoice_uom_ratio === null)

                            <span class="badge badge-warning">
                                NO RATIO
                            </span>

                        @else

                            {{ $line->invoice_uom_ratio }}

                        @endif

                    </td>

                    <td>

                        <strong class="progress-text">

                            @if($line->expected_base_qty === null)

                                N/A

                            @else

                                {{ number_format((float) $line->scanned_base_qty, 4) }}
                                /
                                {{ number_format((float) $line->expected_base_qty, 4) }}

                            @endif

                        </strong>

                    </td>

                    <td>

                        <span
                            class="badge line-status badge-{{ strtolower($line->status) }}"
                        >
                            {{ $line->status }}
                        </span>

                    </td>


                    @if($canOperate)

                        <td>

                            @if($line->status === 'PENDING')

                                <details class="override-box">

                                    <summary>
                                        Override
                                    </summary>

                                    <form
                                        method="POST"
                                        action="{{ route('checks.override', [$check, $line]) }}"
                                        class="override-form"
                                    >

                                        @csrf

                                        <input
                                            name="otp"
                                            inputmode="numeric"
                                            maxlength="6"
                                            placeholder="OTP 6 digit"
                                            required
                                        >

                                        <select
                                            name="reason_code"
                                            required
                                        >

                                            <option value="">
                                                Alasan
                                            </option>

                                            <option value="NO_BARCODE">
                                                Tidak punya barcode
                                            </option>

                                            <option value="SPECIAL_ITEM">
                                                Special item
                                            </option>

                                            <option value="DAMAGED_BARCODE">
                                                Barcode rusak
                                            </option>

                                            <option value="NO_RATIO">
                                                Ratio tidak tersedia
                                            </option>

                                            <option value="OTHER">
                                                Lainnya
                                            </option>

                                        </select>

                                        <input
                                            name="reason_note"
                                            placeholder="Catatan (opsional)"
                                        >

                                        <button
                                            class="btn btn-warning btn-sm"
                                        >
                                            Approve Override
                                        </button>

                                    </form>

                                </details>

                            @else

                                -

                            @endif

                        </td>

                    @endif

                </tr>

            @endforeach

            </tbody>

        </table>

    </div>

</div>


@if($canOperate)

    <div class="save-bar">

        <div>

            <strong>
                Final Save
            </strong>

            <div class="muted small">
                Aktif setelah semua baris OK / OVERRIDE.
            </div>

        </div>

        <form
            method="POST"
            action="{{ route('checks.finalize', $check) }}"
        >

            @csrf

            <button
                id="finalizeBtn"
                class="btn btn-success btn-lg"
                @disabled(!$ready)
            >
                Save & Update AR_Invoices
            </button>

        </form>

    </div>

@endif

@endsection


@push('scripts')

@if($canOperate)

<script>

(() => {

    const input =
        document.getElementById('barcodeInput');

    const message =
        document.getElementById('scanMessage');

    const finalizeBtn =
        document.getElementById('finalizeBtn');

    const csrf =
        document.querySelector(
            'meta[name="csrf-token"]'
        ).content;

    let busy = false;


    function setMessage(text, type = '') {

        message.textContent = text;

        message.className =
            'scan-message ' + type;
    }


    function focusScanner() {

        setTimeout(() => {

            if (!busy) {
                input.focus();
            }

        }, 80);
    }


    async function submitBarcode(barcode) {

        if (busy || !barcode) {
            return;
        }

        busy = true;

        input.disabled = true;

        setMessage(
            'Memproses barcode...'
        );


        try {

            const response = await fetch(
                @json(route('checks.scan', $check)),
                {
                    method: 'POST',

                    headers: {
                        'Content-Type':
                            'application/json',

                        'Accept':
                            'application/json',

                        'X-CSRF-TOKEN':
                            csrf
                    },

                    body: JSON.stringify({
                        barcode
                    })
                }
            );


            const data =
                await response.json();


            if (
                !response.ok
                || !data.ok
            ) {

                setMessage(
                    data.message || 'Scan gagal.',
                    'error'
                );

                return;
            }


            if (
                data.requires_override
            ) {

                setMessage(
                    data.message,
                    'warning'
                );

                if (data.line) {

                    const row =
                        document.getElementById(
                            'line-' + data.line.id
                        );

                    if (row) {
                        row.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }
                }

                return;
            }


            if (data.line) {

                const row =
                    document.getElementById(
                        'line-' + data.line.id
                    );


                if (row) {

                    const progress =
                        row.querySelector(
                            '.progress-text'
                        );

                    if (
                        progress
                        && data.line.expected_base_qty !== null
                    ) {

                        progress.textContent =
                            Number(
                                data.line.scanned_base_qty
                            ).toFixed(4)
                            + ' / '
                            + Number(
                                data.line.expected_base_qty
                            ).toFixed(4);
                    }


                    const badge =
                        row.querySelector(
                            '.line-status'
                        );

                    if (badge) {

                        badge.textContent =
                            data.line.status;

                        badge.className =
                            'badge line-status badge-'
                            + data.line.status.toLowerCase();
                    }


                    if (
                        data.line.status === 'OK'
                    ) {

                        const override =
                            row.querySelector(
                                '.override-box'
                            );

                        if (override) {
                            override.remove();
                        }
                    }
                }
            }


            if (
                data.session_complete
                && finalizeBtn
            ) {
                finalizeBtn.disabled = false;
            }


            setMessage(
                data.message,
                'success'
            );

        } catch (error) {

            setMessage(
                'Koneksi gagal. Coba scan ulang.',
                'error'
            );

        } finally {

            input.value = '';

            input.disabled = false;

            busy = false;

            focusScanner();
        }
    }


    input.addEventListener(
        'keydown',
        event => {

            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();

            submitBarcode(
                input.value.trim()
            );
        }
    );


    document.addEventListener(
        'click',
        event => {

            if (
                !event.target.closest(
                    'input, select, button, summary, details'
                )
            ) {
                focusScanner();
            }
        }
    );


    focusScanner();

})();

</script>

@endif

@endpush
