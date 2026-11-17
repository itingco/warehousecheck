<script src="https://unpkg.com/@zxing/browser@0.1.5/umd/index.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="{{ asset('assets/js/warehouse-fast-camera-v21.js') }}?v=21.1"></script>

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

    // V21: camera decoder changes only. Existing submitBarcode(),
    // updateLineUI(), OTP and final-save remain untouched.
    const fastCamera = window.WarehouseFastCameraV21(
        document.getElementById('cameraReader'),
        async decodedText => {
            decodedText = String(decodedText || '').trim();
            if (/^https?:\/\//i.test(decodedText)) return; // Ignore QR website.
            if (cameraNeedsClear || cameraSubmitting || submitBusy) return;
            cameraSubmitting = true;
            cameraNeedsClear = true;
            clearStartedAt = null;
            setCameraStatus('Barcode terbaca: ' + decodedText + '. Menyimpan...');
            const result = await submitBarcode(decodedText, 'camera');
            if (result.ok && !result.requiresOverride) {
                setCameraStatus('Scan berhasil. Jauhkan barcode dari kamera.', 'success');
            } else if (!result.ok && !result.ignored) {
                setCameraStatus('Barcode ditolak: ' + (result.data?.message || result.message || 'tidak valid'), 'error');
            }
            cameraSubmitting = false;
        },
        () => {
            if (!cameraNeedsClear || cameraSubmitting) return;
            if (clearStartedAt === null) { clearStartedAt = Date.now(); return; }
            if (Date.now() - clearStartedAt >= CAMERA_CLEAR_MS) {
                clearStartedAt = null;
                cameraNeedsClear = false;
                setCameraStatus('Siap scan berikutnya.', 'success');
            }
        },
        message => setCameraStatus(message)
    );

    const photoButton = document.createElement('button');
    photoButton.type = 'button';
    photoButton.className = 'btn btn-primary';
    photoButton.textContent = '📷 Ambil Gambar & Baca AI';
    photoButton.disabled = true;
    stopCameraBtn.parentNode.appendChild(photoButton);
    const previewPanel = document.createElement('section');
    previewPanel.hidden = true;
    previewPanel.style.cssText = 'margin-top:12px;padding:14px;border:1px solid #93c5fd;border-radius:10px;background:#eff6ff;color:#0f172a';
    previewPanel.innerHTML = '<strong>Konfirmasi hasil pembacaan</strong><div id="wc-v21-preview-detail" style="margin:9px 0;white-space:pre-wrap"></div><button id="wc-v21-confirm" class="btn btn-primary" type="button">Konfirmasi & Proses Scan</button> <button id="wc-v21-cancel" class="btn btn-light" type="button">Batal</button>';
    photoButton.parentNode.parentNode.appendChild(previewPanel);
    let previewBarcode = '';
    const previewDetail = previewPanel.querySelector('#wc-v21-preview-detail');
    previewPanel.querySelector('#wc-v21-cancel').addEventListener('click', () => { previewPanel.hidden = true; previewBarcode = ''; });
    previewPanel.querySelector('#wc-v21-confirm').addEventListener('click', async () => {
        if (!previewBarcode || submitBusy || cameraSubmitting) return;
        const value = previewBarcode;
        previewPanel.hidden = true;
        previewBarcode = '';
        cameraNeedsClear = true;
        clearStartedAt = null;
        await submitBarcode(value, 'camera');
    });
    photoButton.addEventListener('click', async () => {
        if (!cameraRunning || cameraSubmitting || submitBusy) return;
        photoButton.disabled = true;
        previewPanel.hidden = true;
        try {
            setCameraStatus('Mengambil gambar kamera, membaca barcode...');
            const snap = await fastCamera.snapshot();
            const payload = snap.barcode ? {barcode:snap.barcode} : {image:snap.image};
            if (snap.barcode && /^https?:\/\//i.test(snap.barcode)) { setCameraStatus('QR website diabaikan.'); return; }
            if (!snap.barcode) setCameraStatus('Decoder lokal belum berhasil. Meminta Vision AI membaca gambar...');
            const res = await fetch(scanUrl.replace(/\/scan(?:\?.*)?$/, '/camera-preview'), {
                method:'POST', credentials:'same-origin',
                headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
                body:JSON.stringify(payload)
            });
            const data = await parseResponse(res);
            if (!res.ok || !data.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Kode tidak dikenal.');
            previewBarcode = data.barcode;
            previewDetail.textContent = 'Barcode: ' + data.barcode + '\nKode Barang: ' + data.item_code + '\nNama Barang: ' + data.item_name + '\nUOM: ' + data.uom_code + '\nRatio / Qty per scan: ' + (data.ratio_qty ?? 'Tidak tersedia — perlu OTP Override') + '\nSumber: ' + data.scan_source;
            previewPanel.hidden = false;
            setCameraStatus('Barang cocok dengan dokumen. Konfirmasi sebelum menyimpan.', 'success');
        } catch (error) {
            const text = String(error.message || error);
            setCameraStatus('Baca foto gagal: ' + text, 'error');
        } finally { photoButton.disabled = !cameraRunning; }
    });

    async function startCamera() {
        if (cameraRunning) return;
        try {
            await unlockScannerAudio();
            await fastCamera.start();
            cameraRunning = true;
            cameraNeedsClear = false;
            clearStartedAt = null;
            startCameraBtn.disabled = true;
            stopCameraBtn.disabled = false;
            photoButton.disabled = false;
        } catch (error) {
            cameraRunning = false;
            startCameraBtn.disabled = false;
            stopCameraBtn.disabled = true;
            photoButton.disabled = true;
            setCameraStatus('Kamera gagal: ' + (error.message || error), 'error');
        }
    }

    async function stopCamera() {
        fastCamera.stop();
        cameraRunning = false;
        cameraSubmitting = false;
        cameraNeedsClear = false;
        clearStartedAt = null;
        previewPanel.hidden = true;
        previewBarcode = '';
        startCameraBtn.disabled = false;
        stopCameraBtn.disabled = true;
        photoButton.disabled = true;
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
