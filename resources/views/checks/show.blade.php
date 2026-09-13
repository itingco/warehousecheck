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

        <h1>{{ $check->invoice_number }}</h1>

        <p class="muted">
            {{ $check->customer_name }}
            ·
            {{ optional($check->invoice_date)->format('d-m-Y') }}
        </p>
    </div>

    <span class="badge badge-{{ strtolower($check->status) }}">
        {{ $check->status }}
    </span>

</div>


<div class="summary-grid">

    <div class="stat">
        <span>Picker</span>
        <strong>{{ $check->picker->name }}</strong>
    </div>

    <div class="stat">
        <span>Checker</span>
        <strong>{{ $check->checker->name }}</strong>
    </div>

    <div class="stat">
        <span>Alamat Kirim</span>
        <strong>{{ $check->shipping_address ?: '-' }}</strong>
    </div>

</div>


@if($canOperate)

    <div class="scanner-card">

        <div>
            <div class="eyebrow">BARCODE CHECKING</div>

            <h2>Scan barang</h2>

            <p class="muted">
                Bisa menggunakan scanner USB/Bluetooth,
                input manual, atau kamera HP.
            </p>
        </div>

        <div class="scanner-actions">

            <div class="scanner-input-wrap">

                <input
                    id="barcodeInput"
                    class="scanner-input"
                    autocomplete="off"
                    placeholder="Scan barcode + Enter"
                    inputmode="numeric"
                    autofocus
                >

                <div
                    id="scanMessage"
                    class="scan-message"
                >
                    Menunggu scan...
                </div>

            </div>

            <button
                type="button"
                id="openCameraBtn"
                class="btn btn-camera btn-lg"
            >
                <span aria-hidden="true">📷</span>
                Scan via Kamera
            </button>

        </div>

    </div>

@endif


<div class="card table-card">

    <div class="table-scroll">

        <table class="responsive-check-table">

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
                    <th>Override</th>
                @endif
            </tr>
            </thead>

            <tbody>

            @foreach($check->details as $line)

                <tr
                    id="line-{{ $line->id }}"
                    data-line-id="{{ $line->id }}"
                    data-item-code="{{ $line->item_code }}"
                    data-item-name="{{ $line->item_name }}"
                >

                    <td data-label="#">
                        {{ $loop->iteration }}
                    </td>

                    <td data-label="Item">
                        <strong>{{ $line->item_code }}</strong>

                        <div class="muted small">
                            {{ $line->item_name }}
                        </div>
                    </td>

                    <td data-label="UOM">
                        {{ $line->uom_code }}
                    </td>

                    <td data-label="Qty Invoice">
                        {{ number_format((float) $line->expected_qty, 4) }}
                    </td>

                    <td data-label="Ratio">

                        @if($line->invoice_uom_ratio === null)

                            <span class="badge badge-warning">
                                NO RATIO
                            </span>

                        @else

                            {{ $line->invoice_uom_ratio }}

                        @endif

                    </td>

                    <td data-label="Progress">

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

                    <td data-label="Status">

                        <span
                            class="badge line-status badge-{{ strtolower($line->status) }}"
                        >
                            {{ $line->status }}
                        </span>

                    </td>


                    @if($canOperate)

                        <td data-label="Override">

                            @if($line->status === 'PENDING')

                                <button
                                    type="button"
                                    class="btn btn-warning btn-sm js-open-override"
                                    data-detail-id="{{ $line->id }}"
                                    data-item-code="{{ $line->item_code }}"
                                    data-item-name="{{ $line->item_name }}"
                                    data-override-url="{{ route('checks.override', [$check, $line]) }}"
                                >
                                    Supervisor Override
                                </button>

                            @else

                                <span class="muted">-</span>

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
            <strong>Final Save</strong>

            <div class="muted small">
                Aktif setelah semua baris berstatus OK / OVERRIDE.
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


    {{-- ============================================================= --}}
    {{-- CAMERA MODAL                                                  --}}
    {{-- ============================================================= --}}

    <div
        id="cameraModal"
        class="app-modal"
        aria-hidden="true"
    >
        <div
            class="app-modal-backdrop"
            data-close-camera
        ></div>

        <div class="app-modal-panel camera-modal-panel">

            <div class="modal-head">
                <div>
                    <div class="eyebrow">MOBILE CAMERA</div>
                    <h2>Scan Barcode</h2>
                </div>

                <button
                    type="button"
                    class="modal-close"
                    data-close-camera
                    aria-label="Tutup"
                >
                    ×
                </button>
            </div>

            <div
                id="cameraSecureWarning"
                class="alert alert-warning-soft"
                hidden
            >
                Kamera browser membutuhkan HTTPS pada HP.
                Jika halaman dibuka melalui IP lokal dengan HTTP,
                browser dapat menolak akses kamera.
            </div>

            <div class="camera-reader-shell">

                <div id="cameraReader"></div>

                <div class="camera-guide">
                    <div class="camera-guide-line"></div>
                </div>

            </div>

            <div
                id="cameraStatus"
                class="camera-status"
            >
                Tekan "Mulai Kamera".
            </div>

            <div class="camera-controls">

                <button
                    type="button"
                    id="startCameraBtn"
                    class="btn btn-primary"
                >
                    Mulai Kamera
                </button>

                <button
                    type="button"
                    id="stopCameraBtn"
                    class="btn btn-light"
                    disabled
                >
                    Stop Kamera
                </button>

            </div>

            <p class="muted small camera-note">
                Arahkan kamera belakang ke barcode.
                Setelah barcode terbaca, hasil langsung diproses
                seperti scanner biasa.
            </p>

        </div>
    </div>


    {{-- ============================================================= --}}
    {{-- SUPERVISOR OVERRIDE MODAL                                     --}}
    {{-- ============================================================= --}}

    <div
        id="overrideModal"
        class="app-modal"
        aria-hidden="true"
    >
        <div
            class="app-modal-backdrop"
            data-close-override
        ></div>

        <div class="app-modal-panel override-modal-panel">

            <div class="modal-head">
                <div>
                    <div class="eyebrow">SUPERVISOR APPROVAL</div>
                    <h2>Override Item</h2>
                </div>

                <button
                    type="button"
                    class="modal-close"
                    data-close-override
                    aria-label="Tutup"
                >
                    ×
                </button>
            </div>

            <div class="override-item-box">
                <span>Item</span>
                <strong id="overrideItemCode">-</strong>
                <div
                    id="overrideItemName"
                    class="muted small"
                >
                    -
                </div>
            </div>

            <div class="otp-help">
                <strong>Bagaimana menggunakan OTP?</strong>
                <p>
                    Supervisor login dari device-nya lalu buka
                    <b>Supervisor OTP</b> → Generate OTP.
                    Setelah itu supervisor memasukkan kode 6 digit
                    aktif pada form ini di device Checker.
                </p>
            </div>

            <form
                id="overrideForm"
                method="POST"
                class="stack"
            >
                @csrf

                <label>
                    OTP Supervisor

                    <input
                        id="overrideOtp"
                        name="otp"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        autocomplete="one-time-code"
                        placeholder="6 digit OTP"
                        required
                    >
                </label>

                <label>
                    Alasan Override

                    <select
                        name="reason_code"
                        required
                    >
                        <option value="">
                            -- Pilih alasan --
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
                </label>

                <label>
                    Catatan

                    <textarea
                        name="reason_note"
                        rows="3"
                        maxlength="500"
                        placeholder="Catatan tambahan (opsional)"
                    ></textarea>
                </label>

                <button
                    type="submit"
                    class="btn btn-warning btn-lg btn-block"
                >
                    Verifikasi OTP & Override
                </button>

            </form>

        </div>
    </div>

@endif

@endsection


@push('scripts')

@if($canOperate)

{{-- Library untuk QR + barcode 1D/2D dari kamera browser --}}
<script
    src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"
    integrity="sha512-r6rDA7W6ZeQhvl8S7yRVQUKVHdexq+GAlNkNNqVC7YyIV+NwqCTJe2hDWCiffTyRNOeGEzRRJ9ifvRm/HCzGYg=="
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
></script>

<script>
(() => {

    const barcodeInput =
        document.getElementById('barcodeInput');

    const scanMessage =
        document.getElementById('scanMessage');

    const finalizeBtn =
        document.getElementById('finalizeBtn');

    const csrf =
        document.querySelector(
            'meta[name="csrf-token"]'
        ).content;


    /*
    |--------------------------------------------------------------------------
    | Shared scan submit
    |--------------------------------------------------------------------------
    */

    let submitBusy = false;

    function setScanMessage(text, type = '') {
        scanMessage.textContent = text;
        scanMessage.className =
            'scan-message ' + type;
    }

    function focusManualScanner() {
        setTimeout(() => {
            if (!submitBusy) {
                barcodeInput.focus();
            }
        }, 100);
    }

    function updateLineUI(line) {

        if (!line) {
            return;
        }

        const row =
            document.getElementById(
                'line-' + line.id
            );

        if (!row) {
            return;
        }

        const progress =
            row.querySelector(
                '.progress-text'
            );

        if (
            progress
            && line.expected_base_qty !== null
        ) {
            progress.textContent =
                Number(
                    line.scanned_base_qty
                ).toFixed(4)
                + ' / '
                + Number(
                    line.expected_base_qty
                ).toFixed(4);
        }

        const badge =
            row.querySelector(
                '.line-status'
            );

        if (badge) {
            badge.textContent =
                line.status;

            badge.className =
                'badge line-status badge-'
                + line.status.toLowerCase();
        }

        if (line.status === 'OK') {
            const overrideBtn =
                row.querySelector(
                    '.js-open-override'
                );

            if (overrideBtn) {
                overrideBtn.remove();
            }
        }
    }

    async function submitBarcode(
        barcode,
        source = 'manual'
    ) {

        barcode = String(barcode || '').trim();

        if (
            submitBusy
            || barcode === ''
        ) {
            return {
                ok: false,
                ignored: true
            };
        }

        submitBusy = true;

        barcodeInput.disabled = true;

        setScanMessage(
            'Memproses barcode '
            + barcode
            + '...'
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
                setScanMessage(
                    data.message || 'Scan gagal.',
                    'error'
                );

                return {
                    ok: false,
                    data
                };
            }

            updateLineUI(
                data.line
            );

            if (
                data.session_complete
                && finalizeBtn
            ) {
                finalizeBtn.disabled = false;
            }

            if (data.requires_override) {

                setScanMessage(
                    data.message,
                    'warning'
                );

                if (data.line) {

                    await stopCamera();

                    closeCameraModal();

                    openOverrideByLine(
                        data.line.id
                    );
                }

                return {
                    ok: true,
                    requiresOverride: true,
                    data
                };
            }

            if (
                navigator.vibrate
                && source === 'camera'
            ) {
                navigator.vibrate(80);
            }

            setScanMessage(
                data.message,
                'success'
            );

            return {
                ok: true,
                data
            };

        } catch (error) {

            setScanMessage(
                'Koneksi gagal. Coba scan ulang.',
                'error'
            );

            return {
                ok: false,
                error
            };

        } finally {

            barcodeInput.value = '';

            barcodeInput.disabled = false;

            submitBusy = false;

            if (
                !cameraModal.classList.contains(
                    'is-open'
                )
            ) {
                focusManualScanner();
            }
        }
    }


    barcodeInput.addEventListener(
        'keydown',
        event => {

            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();

            submitBarcode(
                barcodeInput.value,
                'manual'
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Supervisor Override modal
    |--------------------------------------------------------------------------
    */

    const overrideModal =
        document.getElementById(
            'overrideModal'
        );

    const overrideForm =
        document.getElementById(
            'overrideForm'
        );

    const overrideOtp =
        document.getElementById(
            'overrideOtp'
        );

    const overrideItemCode =
        document.getElementById(
            'overrideItemCode'
        );

    const overrideItemName =
        document.getElementById(
            'overrideItemName'
        );


    function openOverrideModal(button) {

        overrideForm.action =
            button.dataset.overrideUrl;

        overrideItemCode.textContent =
            button.dataset.itemCode || '-';

        overrideItemName.textContent =
            button.dataset.itemName || '-';

        overrideForm.reset();

        overrideModal.classList.add(
            'is-open'
        );

        overrideModal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.classList.add(
            'modal-open'
        );

        setTimeout(() => {
            overrideOtp.focus();
        }, 150);
    }


    function openOverrideByLine(lineId) {

        const row =
            document.getElementById(
                'line-' + lineId
            );

        if (!row) {
            return;
        }

        const button =
            row.querySelector(
                '.js-open-override'
            );

        if (button) {
            openOverrideModal(
                button
            );
        }
    }


    function closeOverrideModal() {

        overrideModal.classList.remove(
            'is-open'
        );

        overrideModal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.classList.remove(
            'modal-open'
        );

        focusManualScanner();
    }


    document.querySelectorAll(
        '.js-open-override'
    ).forEach(button => {

        button.addEventListener(
            'click',
            () => openOverrideModal(button)
        );
    });


    document.querySelectorAll(
        '[data-close-override]'
    ).forEach(element => {

        element.addEventListener(
            'click',
            closeOverrideModal
        );
    });


    overrideOtp.addEventListener(
        'input',
        () => {

            overrideOtp.value =
                overrideOtp.value
                    .replace(/\D/g, '')
                    .slice(0, 6);
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Mobile camera scanner
    |--------------------------------------------------------------------------
    */

    const cameraModal =
        document.getElementById(
            'cameraModal'
        );

    const openCameraBtn =
        document.getElementById(
            'openCameraBtn'
        );

    const startCameraBtn =
        document.getElementById(
            'startCameraBtn'
        );

    const stopCameraBtn =
        document.getElementById(
            'stopCameraBtn'
        );

    const cameraStatus =
        document.getElementById(
            'cameraStatus'
        );

    const cameraSecureWarning =
        document.getElementById(
            'cameraSecureWarning'
        );

    let html5QrCode = null;
    let cameraRunning = false;
    let cameraScanLocked = false;
    let lastCameraCode = '';
    let lastCameraAt = 0;


    function setCameraStatus(
        text,
        type = ''
    ) {
        cameraStatus.textContent = text;
        cameraStatus.className =
            'camera-status ' + type;
    }


    function openCameraModal() {

        cameraModal.classList.add(
            'is-open'
        );

        cameraModal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.classList.add(
            'modal-open'
        );

        cameraSecureWarning.hidden =
            window.isSecureContext;

        setCameraStatus(
            'Tekan "Mulai Kamera".'
        );
    }


    async function closeCameraModal() {

        await stopCamera();

        cameraModal.classList.remove(
            'is-open'
        );

        cameraModal.setAttribute(
            'aria-hidden',
            'true'
        );

        document.body.classList.remove(
            'modal-open'
        );

        focusManualScanner();
    }


    async function startCamera() {

        if (cameraRunning) {
            return;
        }

        if (
            typeof Html5Qrcode === 'undefined'
        ) {
            setCameraStatus(
                'Library scanner gagal dimuat. '
                + 'Pastikan koneksi internet tersedia.',
                'error'
            );

            return;
        }

        if (!window.isSecureContext) {
            setCameraStatus(
                'Browser HP dapat menolak kamera karena halaman belum HTTPS.',
                'warning'
            );
        } else {
            setCameraStatus(
                'Meminta izin kamera...'
            );
        }

        try {

            html5QrCode =
                html5QrCode
                || new Html5Qrcode(
                    'cameraReader'
                );

            await html5QrCode.start(
                {
                    facingMode:
                        'environment'
                },
                {
                    fps: 12,

                    qrbox: (
                        viewfinderWidth,
                        viewfinderHeight
                    ) => {

                        const width =
                            Math.floor(
                                Math.min(
                                    viewfinderWidth * 0.88,
                                    420
                                )
                            );

                        const height =
                            Math.floor(
                                Math.min(
                                    viewfinderHeight * 0.34,
                                    180
                                )
                            );

                        return {
                            width,
                            height
                        };
                    },

                    aspectRatio: 1.777778
                },

                async (
                    decodedText,
                    decodedResult
                ) => {

                    const now =
                        Date.now();

                    if (
                        cameraScanLocked
                        || (
                            decodedText === lastCameraCode
                            && now - lastCameraAt < 1600
                        )
                    ) {
                        return;
                    }

                    cameraScanLocked = true;

                    lastCameraCode =
                        decodedText;

                    lastCameraAt =
                        now;

                    setCameraStatus(
                        'Barcode: '
                        + decodedText
                        + ' — memproses...'
                    );

                    const result =
                        await submitBarcode(
                            decodedText,
                            'camera'
                        );

                    if (
                        result.ok
                        && !result.requiresOverride
                    ) {
                        setCameraStatus(
                            'Berhasil: '
                            + decodedText,
                            'success'
                        );
                    }
                    else if (
                        !result.ok
                        && !result.ignored
                    ) {
                        setCameraStatus(
                            'Scan terbaca tetapi tidak diterima. '
                            + 'Lihat pesan di halaman.',
                            'error'
                        );
                    }

                    setTimeout(() => {
                        cameraScanLocked = false;
                    }, 900);
                },

                () => {
                    // Decode failure pada setiap frame sengaja diabaikan.
                }
            );

            cameraRunning = true;

            startCameraBtn.disabled =
                true;

            stopCameraBtn.disabled =
                false;

            setCameraStatus(
                'Kamera aktif. Arahkan barcode ke kotak scan.',
                'success'
            );

        } catch (error) {

            cameraRunning = false;

            startCameraBtn.disabled =
                false;

            stopCameraBtn.disabled =
                true;

            setCameraStatus(
                'Tidak bisa membuka kamera: '
                + (
                    error?.message
                    || String(error)
                ),
                'error'
            );
        }
    }


    async function stopCamera() {

        if (
            !html5QrCode
            || !cameraRunning
        ) {
            return;
        }

        try {
            await html5QrCode.stop();
            await html5QrCode.clear();
        } catch (error) {
            // Abaikan error stop agar modal tetap bisa ditutup.
        }

        cameraRunning = false;

        cameraScanLocked = false;

        startCameraBtn.disabled =
            false;

        stopCameraBtn.disabled =
            true;

        setCameraStatus(
            'Kamera dihentikan.'
        );
    }


    openCameraBtn.addEventListener(
        'click',
        openCameraModal
    );

    startCameraBtn.addEventListener(
        'click',
        startCamera
    );

    stopCameraBtn.addEventListener(
        'click',
        stopCamera
    );


    document.querySelectorAll(
        '[data-close-camera]'
    ).forEach(element => {

        element.addEventListener(
            'click',
            closeCameraModal
        );
    });


    /*
    |--------------------------------------------------------------------------
    | Global modal / focus behavior
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'keydown',
        event => {

            if (event.key !== 'Escape') {
                return;
            }

            if (
                cameraModal.classList.contains(
                    'is-open'
                )
            ) {
                closeCameraModal();
            }

            if (
                overrideModal.classList.contains(
                    'is-open'
                )
            ) {
                closeOverrideModal();
            }
        }
    );


    document.addEventListener(
        'click',
        event => {

            if (
                cameraModal.classList.contains(
                    'is-open'
                )
                || overrideModal.classList.contains(
                    'is-open'
                )
            ) {
                return;
            }

            if (
                !event.target.closest(
                    'input, textarea, select, button, a'
                )
            ) {
                focusManualScanner();
            }
        }
    );


    focusManualScanner();

})();
</script>

@endif

@endpush
