WAREHOUSECHECK SCANNER V21 — TEST LOKAL
Repository target: itingco/warehousecheck (main)
Scope: scanner bersama di Invoice Check dan Good Transfer Check saja.

Perubahan:
- Kamera belakang dengan ZXing Browser (jika tersedia), fallback BarcodeDetector.
- Tombol Ambil Gambar & Baca AI dari frame kamera aktif, tanpa galeri.
- Decoder lokal dicoba sebelum Vision AI.
- Konfirmasi kode barang, nama, UOM dan ratio sebelum submit scan foto.
- QR URL http:// atau https:// diabaikan oleh kamera (tidak dikirim ke endpoint scan).
- Suara sukses/gagal, progress, scan USB, OTP, Final Save dan aturan database tetap menggunakan kode aplikasi asli.

Cara pasang di local:
1. Backup project dan database. Extract ZIP SELURUH ISI ke root Laravel, sejajar dengan artisan.
2. Jalankan: php APPLY_SCANNER_V21.php
3. Jalankan: php artisan optimize:clear
4. Periksa: php artisan route:list --name=camera-preview
5. Buka Invoice Check atau Good Transfer Check dengan akun checker aktif.
6. Tekan Scan via Kamera > Mulai Kamera, lalu coba barcode biasa, QR URL, dan Ambil Gambar & Baca AI.

AI optional: tambahkan ke .env:
OPENAI_API_KEY=YOUR_SERVER_SIDE_KEY
OPENAI_VISION_MODEL=gpt-4.1-mini
Kemudian php artisan optimize:clear. Foto dikirim ke API hanya bila decoder lokal tidak berhasil.
API key jangan disimpan di JS atau Git.

Penting:
- Butuh HTTPS/localhost di browser mobile untuk akses kamera.
- ZXing diambil dari unpkg.com ketika browser online. Bila CDN tidak dapat diakses, fitur kamera fallback ke BarcodeDetector bawaan browser jika tersedia. Untuk penggunaan offline, host library ZXing UMD secara lokal dan ubah tag script pada partial.
- Preview hanya membaca master BarcodeService + item dokumen; penyimpanan tetap lewat endpoint scan lama setelah konfirmasi.
- Foto tidak disimpan pada server, hanya dipakai untuk proses pembacaan.
- Jika barcode ditemukan tetapi ratio tidak tersedia, alur OTP override bawaan tetap berlaku.
- Scan otomatis tetap sesuai perilaku asli dan tidak perlu konfirmasi; konfirmasi hanya untuk foto AI.
- File existing yang diganti installer dibackup dengan suffix .before-scanner-v21.
- Tidak ada migration. Belum diuji dengan database, device kamera, atau API AI lokal pengguna.

File yang dipasang:
resources/views/partials/check-scanner-script.blade.php (diubah installer)
routes/web.php (ditambah route spesifik)
app/Http/Controllers/CameraScanPreviewController.php (baru)
config/warehouse_scanner_ai.php (baru)
public/assets/js/warehouse-fast-camera-v21.js (baru)
