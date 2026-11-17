<script
    src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"
    integrity="sha512-r6rDA7W6ZeQhvl8S7yRVQUKVHdexq+GAlNkNNqVC7YyIV+NwqCTJe2hDWCiffTyRNOeGEzRRJ9ifvRm/HCzGYg=="
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
></script>

<script>
(() => {
    const barcodeInput = document.getElementById('barcodeInput');
    const scanMessage = document.getElementById('scanMessage');
    const finalizeBtn = document.getElementById('finalizeBtn');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    // Always force the scan endpoint to same-origin/relative URL.
    // This prevents HTTPS pages behind a reverse proxy from trying to POST
    // to an accidentally generated http:// URL (mixed-content block).
    const rawScanUrl = @json($scanUrl);
    const scanUrl = (() => {
        try {
            const parsed = new URL(rawScanUrl, window.location.origin);
            return parsed.pathname + parsed.search + parsed.hash;
        } catch (error) {
            return rawScanUrl;
        }
    })();

    const overrideModal = document.getElementById('overrideModal');
    const overrideForm = document.getElementById('overrideForm');
    const overrideOtp = document.getElementById('overrideOtp');
    const overrideItemCode = document.getElementById('overrideItemCode');
    const overrideItemName = document.getElementById('overrideItemName');

    const cameraModal = document.getElementById('cameraModal');
    const openCameraBtn = document.getElementById('openCameraBtn');
    const startCameraBtn = document.getElementById('startCameraBtn');
    const stopCameraBtn = document.getElementById('stopCameraBtn');
    const cameraStatus = document.getElementById('cameraStatus');
    const cameraSecureWarning = document.getElementById('cameraSecureWarning');

    let submitBusy = false;
    let html5QrCode = null;
    let cameraRunning = false;
    let cameraSubmitting = false;

    // IMPORTANT:
    // Setelah satu barcode berhasil/ditolak, scanner TIDAK boleh membaca lagi
    // sampai barcode benar-benar keluar dari frame selama beberapa saat.
    // Ini mencegah kasus video: scan pertama masuk, barcode masih terlihat,
    // lalu scan kedua terkirim dan menghasilkan OVER_QTY/error.
    let cameraNeedsClear = false;
    let clearStartedAt = null;
    const CAMERA_CLEAR_MS = 700;

    // ---------------------------------------------------------------------
    // Scanner audio feedback
    // ---------------------------------------------------------------------
    // Web Audio dipakai supaya tidak perlu file MP3/WAV tambahan. Volume
    // maksimum tetap mengikuti volume media perangkat/browser milik user.
    let scannerAudioContext = null;

    function getScannerAudioContext() {
        const AudioContextClass =
            window.AudioContext || window.webkitAudioContext;

        if (!AudioContextClass) {
            return null;
        }

        if (!scannerAudioContext) {
            scannerAudioContext = new AudioContextClass();
        }

        return scannerAudioContext;
    }

    async function unlockScannerAudio() {
        const context = getScannerAudioContext();
        if (!context) return;

        if (context.state === 'suspended') {
            try {
                await context.resume();
            } catch (error) {
                console.debug('Scanner audio unlock ignored:', error);
            }
        }
    }

    function playScannerTone(
        frequency,
        durationMs,
        volume,
        delayMs = 0,
        type = 'square'
    ) {
        const context = getScannerAudioContext();
        if (!context || context.state !== 'running') return;

        const oscillator = context.createOscillator();
        const gain = context.createGain();
        const startAt = context.currentTime + (delayMs / 1000);
        const endAt = startAt + (durationMs / 1000);

        oscillator.type = type;
        oscillator.frequency.setValueAtTime(frequency, startAt);

        // Attack cepat + release pendek membuat bunyi terasa tegas dan keras
        // tanpa klik digital yang mengganggu.
        gain.gain.setValueAtTime(0.0001, startAt);
        gain.gain.exponentialRampToValueAtTime(
            Math.max(0.0001, volume),
            startAt + 0.008
        );
        gain.gain.setValueAtTime(
            Math.max(0.0001, volume),
            Math.max(startAt + 0.009, endAt - 0.025)
        );
        gain.gain.exponentialRampToValueAtTime(0.0001, endAt);

        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.start(startAt);
        oscillator.stop(endAt + 0.02);
    }

    function playSuccessBeep() {
        unlockScannerAudio().then(() => {
            // Dua frekuensi tinggi bersamaan agar mudah terdengar di gudang.
            playScannerTone(1650, 150, 0.55, 0, 'square');
            playScannerTone(2200, 150, 0.38, 0, 'square');
        });
    }

    function playErrorBeep() {
        unlockScannerAudio().then(() => {
            // Dua beep rendah: jelas berbeda dari bunyi scan sukses.
            playScannerTone(340, 135, 0.50, 0, 'sawtooth');
            playScannerTone(220, 170, 0.50, 175, 'sawtooth');
        });
    }

    // Mobile browser biasanya hanya mengizinkan AudioContext setelah gesture
    // user. Unlock sedini mungkin ketika user menyentuh halaman / keyboard.
    document.addEventListener('pointerdown', unlockScannerAudio, {
        once: true,
        capture: true
    });
    document.addEventListener('keydown', unlockScannerAudio, {
        once: true,
        capture: true
    });

    function setScanMessage(text, type = '') {
        scanMessage.textContent = text;
        scanMessage.className = 'scan-message ' + type;
    }

    function setCameraStatus(text, type = '') {
        cameraStatus.textContent = text;
        cameraStatus.className = 'camera-status ' + type;
    }

    function focusManualScanner() {
        setTimeout(() => {
            if (!submitBusy && barcodeInput && !barcodeInput.disabled) {
                barcodeInput.focus();
            }
        }, 100);
    }

    function updateLineUI(line) {
        if (!line) return;

        const row = document.getElementById('line-' + line.id);
        if (!row) return;

        const progress = row.querySelector('.progress-text');
        if (progress && line.expected_base_qty !== null) {
            progress.textContent =
                Number(line.scanned_base_qty).toFixed(4)
                + ' / '
                + Number(line.expected_base_qty).toFixed(4);
        }

        const badge = row.querySelector('.line-status');
        if (badge) {
            badge.textContent = line.status;
            badge.className =
                'badge line-status badge-' + String(line.status).toLowerCase();
        }

        if (line.status === 'OK') {
            const overrideBtn = row.querySelector('.js-open-override');
            if (overrideBtn) overrideBtn.remove();
        }
    }

    async function parseResponse(response) {
        // Read as text first so an HTML/error response cannot make response.json()
        // throw and hide the real HTTP status from the user.
        const raw = await response.text();

        if (raw) {
            try {
                const parsed = JSON.parse(raw);
                if (parsed && typeof parsed === 'object') {
                    return parsed;
                }
            } catch (error) {
                // Continue below and show a safe preview of the non-JSON response.
            }
        }

        if (response.status === 419) {
            return {
                ok: false,
                message: 'HTTP 419: session login / CSRF sudah kedaluwarsa. Refresh halaman lalu login kembali.'
            };
        }

        const preview = String(raw || '')
            .replace(/<[^>]*>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .slice(0, 180);

        return {
            ok: false,
            message:
                'HTTP ' + response.status
                + (response.statusText ? ' ' + response.statusText : '')
                + ': response server bukan JSON'
                + (preview ? ' — ' + preview : '. Cek laravel.log untuk detail.')
        };
    }

    async function submitBarcode(barcode, source = 'manual') {
        barcode = String(barcode || '').trim();

        if (submitBusy || barcode === '') {
            return { ok: false, ignored: true };
        }

        submitBusy = true;
        barcodeInput.disabled = true;
        setScanMessage('Memproses barcode ' + barcode + '...');

        try {
            const response = await fetch(scanUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ barcode })
            });

            const data = await parseResponse(response);

            if (!response.ok || !data.ok) {
                playErrorBeep();
                setScanMessage(data.message || 'Scan gagal.', 'error');
                return { ok: false, data };
            }

            updateLineUI(data.line);

            if (data.session_complete && finalizeBtn) {
                finalizeBtn.disabled = false;
            }

            if (data.requires_override) {
                playErrorBeep();
                setScanMessage(data.message, 'warning');

                if (data.line) {
                    await stopCamera();
                    await closeCameraModal();
                    openOverrideByLine(data.line.id);
                }

                return {
                    ok: true,
                    accepted: false,
                    requiresOverride: true,
                    data
                };
            }

            playSuccessBeep();

            if (navigator.vibrate && source === 'camera') {
                navigator.vibrate(80);
            }

            setScanMessage(data.message || 'Scan diterima.', 'success');

            return {
                ok: true,
                accepted: data.accepted !== false,
                requiresOverride: false,
                data
            };
        } catch (error) {
            console.error('Barcode submit error:', error);
            playErrorBeep();

            const detail = error?.message || String(error);
            const message =
                'Request scan gagal sebelum response diterima: ' + detail;

            setScanMessage(message, 'error');

            return {
                ok: false,
                error,
                message
            };
        } finally {
            barcodeInput.value = '';
            barcodeInput.disabled = false;
            submitBusy = false;

            if (!cameraModal.classList.contains('is-open')) {
                focusManualScanner();
            }
        }
    }

    barcodeInput.addEventListener('keydown', event => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        submitBarcode(barcodeInput.value, 'manual');
    });

    function openOverrideModal(button) {
        overrideForm.action = button.dataset.overrideUrl;
        overrideItemCode.textContent = button.dataset.itemCode || '-';
        overrideItemName.textContent = button.dataset.itemName || '-';
        overrideForm.reset();
        overrideModal.classList.add('is-open');
        overrideModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        setTimeout(() => overrideOtp.focus(), 150);
    }

    function openOverrideByLine(lineId) {
        const row = document.getElementById('line-' + lineId);
        if (!row) return;
        const button = row.querySelector('.js-open-override');
        if (button) openOverrideModal(button);
    }

    function closeOverrideModal() {
        overrideModal.classList.remove('is-open');
        overrideModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        focusManualScanner();
    }

    document.querySelectorAll('.js-open-override').forEach(button => {
        button.addEventListener('click', () => openOverrideModal(button));
    });

    document.querySelectorAll('[data-close-override]').forEach(element => {
        element.addEventListener('click', closeOverrideModal);
    });

    overrideOtp.addEventListener('input', () => {
        overrideOtp.value = overrideOtp.value.replace(/\D/g, '').slice(0, 6);
    });

    function openCameraModal() {
        cameraModal.classList.add('is-open');
        cameraModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        cameraSecureWarning.hidden = window.isSecureContext;
        cameraNeedsClear = false;
        clearStartedAt = null;
        setCameraStatus('Tekan "Mulai Kamera".');
    }

    async function closeCameraModal() {
        await stopCamera();
        cameraModal.classList.remove('is-open');
        cameraModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        focusManualScanner();
    }

    async function startCamera() {
        if (cameraRunning) return;

        if (typeof Html5Qrcode === 'undefined') {
            setCameraStatus(
                'Library scanner gagal dimuat. Pastikan koneksi internet tersedia.',
                'error'
            );
            return;
        }

        setCameraStatus(
            window.isSecureContext
                ? 'Meminta izin kamera...'
                : 'Browser HP dapat menolak kamera karena halaman belum HTTPS.',
            window.isSecureContext ? '' : 'warning'
        );

        try {
            html5QrCode = html5QrCode || new Html5Qrcode('cameraReader');

            await html5QrCode.start(
                { facingMode: 'environment' },
                {
                    fps: 12,
                    qrbox: (viewfinderWidth, viewfinderHeight) => {
                        const width = Math.floor(
                            Math.min(viewfinderWidth * 0.88, 420)
                        );
                        const height = Math.floor(
                            Math.min(viewfinderHeight * 0.34, 180)
                        );
                        return { width, height };
                    },
                    aspectRatio: 1.777778
                },
                async decodedText => {
                    // Selama barcode lama belum keluar dari frame, setiap decode
                    // berikutnya diabaikan. Success callback juga me-reset timer
                    // clear sehingga dibutuhkan gap tanpa barcode yang nyata.
                    if (cameraNeedsClear) {
                        clearStartedAt = null;
                        return;
                    }

                    if (cameraSubmitting) return;
                    cameraSubmitting = true;

                    setCameraStatus(
                        'Barcode: ' + decodedText + ' — memproses...'
                    );

                    const result = await submitBarcode(decodedText, 'camera');

                    // Tidak peduli accepted/error, tunggu barcode keluar dari frame
                    // sebelum mengizinkan decode selanjutnya agar tidak double scan.
                    cameraNeedsClear = true;
                    clearStartedAt = null;
                    cameraSubmitting = false;

                    if (result.ok && !result.requiresOverride) {
                        setCameraStatus(
                            'Berhasil: ' + decodedText
                            + '. Jauhkan barcode sebentar untuk scan berikutnya.',
                            'success'
                        );
                    } else if (!result.ok && !result.ignored) {
                        setCameraStatus(
                            'Scan terbaca tetapi ditolak: '
                            + (
                                result.data?.message
                                || result.message
                                || result.error?.message
                                || 'response server tidak dapat diproses.'
                            ),
                            'error'
                        );
                    }
                },
                () => {
                    if (!cameraNeedsClear || cameraSubmitting) return;

                    if (clearStartedAt === null) {
                        clearStartedAt = Date.now();
                        return;
                    }

                    if (Date.now() - clearStartedAt >= CAMERA_CLEAR_MS) {
                        cameraNeedsClear = false;
                        clearStartedAt = null;
                        setCameraStatus(
                            'Siap scan berikutnya.',
                            'success'
                        );
                    }
                }
            );

            cameraRunning = true;
            startCameraBtn.disabled = true;
            stopCameraBtn.disabled = false;
            setCameraStatus(
                'Kamera aktif. Arahkan barcode ke kotak scan.',
                'success'
            );
        } catch (error) {
            cameraRunning = false;
            startCameraBtn.disabled = false;
            stopCameraBtn.disabled = true;
            setCameraStatus(
                'Tidak bisa membuka kamera: ' + (error?.message || String(error)),
                'error'
            );
        }
    }

    async function stopCamera() {
        if (!html5QrCode || !cameraRunning) return;

        try {
            await html5QrCode.stop();
            await html5QrCode.clear();
        } catch (error) {
            console.debug('Camera stop ignored:', error);
        }

        cameraRunning = false;
        cameraSubmitting = false;
        cameraNeedsClear = false;
        clearStartedAt = null;
        startCameraBtn.disabled = false;
        stopCameraBtn.disabled = true;
        setCameraStatus('Kamera dihentikan.');
    }

    openCameraBtn.addEventListener('click', openCameraModal);
    startCameraBtn.addEventListener('click', startCamera);
    stopCameraBtn.addEventListener('click', stopCamera);

    document.querySelectorAll('[data-close-camera]').forEach(element => {
        element.addEventListener('click', closeCameraModal);
    });

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;

        if (overrideModal.classList.contains('is-open')) {
            closeOverrideModal();
            return;
        }

        if (cameraModal.classList.contains('is-open')) {
            closeCameraModal();
        }
    });

    focusManualScanner();
})();
</script>
