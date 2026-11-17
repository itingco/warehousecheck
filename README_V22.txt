WAREHOUSECHECK — V22 INPUT MANUAL KODE BARANG + QTY + FOTO WAJIB
Target: itingco/warehousecheck (Invoice Check & Good Transfer Check).
Prasyarat: Warehouse Check yang sudah diuji dengan scanner V21; V22 juga dapat dipasang pada branch main jika struktur view sesuai.

KETERANGAN
- V21 tidak memerlukan migration karena hanya baca barcode/foto AI, tidak menyimpan bukti foto.
- V22 MEMERLUKAN migration. Tabel baru DB_AppHub: warehouse_check_manual_photos.
- Scanner USB/Bluetooth, kamera V21, OTP override, dan Final Save tidak diubah.
- Input kode barang > lookup di detail dokumen aktif > modal Qty + ambil foto langsung kamera > simpan.
- Qty menggunakan base/smallest UOM, DIBATASI maksimal sisa qty pada dokumen, dan akan menambah scanned_base_qty.
- Bila ratio dokumen tidak tersedia / qty berlebih, input manual ditolak; Supervisor Override tetap tersedia.
- Foto disimpan PRIVATE pada storage/app (disk local); bukan upload galeri.
- Foto bisa dilihat Admin maupun Checker pencatat dari bagian Riwayat Input Manual + Foto Bukti.
- Hanya checker pemilik dokumen DRAFT dapat input manual, validasi selalu di server.

INSTALASI LOKAL (PowerShell)
1. Backup project dan DB_AppHub dahulu.
2. Extract SELURUH ISI folder v22_work ke root Laravel (INSTALL_V22.php dan folder patch sejajar artisan).
3. cd C:\xampp82\htdocs\warehousecheck
4. php INSTALL_V22.php
5. php artisan migrate
6. php artisan optimize:clear
7. php artisan route:list --name=manual-photo
8. Ctrl+F5 pada browser. Untuk kamera HP, gunakan HTTPS; localhost di komputer sendiri adalah secure context.

UJI
- Invoice DRAFT > masukkan item kode sesuai detail invoice > cek > Qty + kamera > foto > Simpan.
- Lihat progress scanned_base_qty bertambah. Pastikan foto terlihat di Riwayat Input Manual.
- Good Transfer DRAFT: tes yang sama.
- Qty melebihi remaining harus gagal; tidak ada transaksi parsial.
- Item yang bukan bagian dokumen harus gagal.
- Tanpa foto tombol Simpan tidak aktif.
- Supervisor Override, scan barcode kamera, dan Final Save harus tetap bekerja.

TIDAK PERLU php artisan storage:link karena foto privat disajikan melalui route login yang dibatasi.
TIDAK PERLU mengubah database AS_INGCO atau AS_SMI.

BATASAN
- Hasil tes sintaks PHP/JS + simulasi installer saja; database SQL Server dan kamera real belum diuji.
- Foto disimpan filesystem server sedangkan metadata di DB_AppHub; backup harus meliputi keduanya.
- Jangan jalankan migrasi ini pada produksi sebelum test lokal.
