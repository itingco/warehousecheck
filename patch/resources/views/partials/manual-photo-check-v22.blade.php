@php
    $manualDoc = isset($transfer) ? 'transfer' : 'invoice';
    $manualHeader = isset($transfer) ? $transfer : $check;
@endphp
<section id="wc-manual-v22" class="wc-manual-card"
    data-lookup-url="{{ $manualDoc === 'invoice' ? route('checks.manual-photo.lookup', $manualHeader, false) : route('good-transfers.manual-photo.lookup', $manualHeader, false) }}"
    data-save-url="{{ $manualDoc === 'invoice' ? route('checks.manual-photo.store', $manualHeader, false) : route('good-transfers.manual-photo.store', $manualHeader, false) }}">
    <h3>Barang tanpa barcode</h3>
    <p>Isi kode barang dan cek terlebih dahulu. Setelah valid, foto langsung dari kamera dan Qty fisik wajib diisi.</p>
    <form id="wc-manual-lookup-form" class="wc-manual-row">
        <input id="wc-manual-code" maxlength="100" required autocomplete="off" placeholder="Kode barang pada dokumen">
        <button class="btn btn-primary" type="submit">Cek Barang</button>
    </form>
    <div id="wc-manual-message" role="status" aria-live="polite"></div>
</section>
<div id="wc-manual-modal" class="app-modal" aria-hidden="true">
    <div class="app-modal-backdrop" data-wc-manual-close></div>
    <div class="app-modal-panel wc-manual-modal-content">
        <div class="modal-head"><div><div class="eyebrow">FOTO BUKTI WAJIB</div><h2>Input Barang Tanpa Barcode</h2></div>
            <button class="modal-close" type="button" data-wc-manual-close aria-label="Tutup">×</button></div>
        <div class="wc-item-preview"><strong id="wc-manual-item"></strong><p id="wc-manual-name"></p><small id="wc-manual-remaining"></small></div>
        <form id="wc-manual-save-form">
            <label>Qty Fisik (base / smallest UOM)
                <input id="wc-manual-qty" type="number" min="0.0001" step="0.0001" max="999999999" required placeholder="Contoh: 3">
            </label>
            <div class="wc-manual-video"><video id="wc-manual-video" autoplay muted playsinline></video><span id="wc-manual-camera-hint">Kamera belum aktif</span></div>
            <div class="wc-manual-row"><button type="button" id="wc-manual-camera-start" class="btn btn-light">Aktifkan Kamera</button>
                <button type="button" id="wc-manual-capture" class="btn btn-primary" disabled>Ambil Foto</button>
                <button type="button" id="wc-manual-retake" class="btn btn-light" hidden>Foto Ulang</button></div>
            <img id="wc-manual-photo-preview" alt="Foto bukti yang diambil dari kamera" hidden>
            <div id="wc-manual-modal-message" role="status" aria-live="polite"></div>
            <div class="wc-manual-row wc-actions"><button type="button" class="btn btn-light" data-wc-manual-close>Batal</button>
                <button id="wc-manual-submit" class="btn btn-primary" type="submit" disabled>Simpan Qty + Foto</button></div>
        </form>
    </div>
</div>
@push('scripts')
<script src="{{ asset('assets/js/warehouse-manual-photo-v22.js') }}?v=22.0" defer></script>
@endpush
@push('styles')
<style>
.wc-manual-card{margin:18px 0;padding:18px;background:white;border:1px solid #d9e2ef;border-radius:14px}.wc-manual-card h3{margin:0 0 6px}.wc-manual-card p{font-size:13px;color:#64748b;margin:0 0 12px}
.wc-manual-row{display:flex;gap:9px;align-items:center;flex-wrap:wrap}.wc-manual-row input{flex:1;min-width:170px;padding:12px;border:1px solid #cbd5e1;border-radius:8px}.wc-manual-modal-content{max-width:620px;max-height:90vh;overflow-y:auto}.wc-manual-modal-content form{display:grid;gap:12px;margin-top:12px}.wc-manual-modal-content label{display:grid;gap:6px;font-weight:600}.wc-manual-modal-content label input{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:8px}.wc-manual-video{position:relative;background:#111827;border-radius:12px;min-height:210px;overflow:hidden}.wc-manual-video video{width:100%;height:auto;max-height:350px;display:block}.wc-manual-video span{position:absolute;bottom:10px;left:10px;padding:5px 8px;background:#0009;color:white;border-radius:6px;font-size:12px}.wc-item-preview{padding:13px;border-radius:10px;background:#eff6ff}.wc-item-preview p{margin:5px 0}.wc-manual-modal-content img{display:block;max-height:230px;max-width:100%;margin:auto;border-radius:10px}.wc-manual-modal-content img[hidden]{display:none}.wc-actions{justify-content:flex-end;margin-top:8px}
</style>
@endpush
