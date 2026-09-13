# Update: Mobile Camera Scanner + Supervisor OTP UX

Copy file update ini ke root project:

`C:\xampp82\htdocs\Checker`

Replace file yang sama.

File yang berubah:

- `resources/views/checks/show.blade.php`
- `resources/views/supervisor/otp.blade.php`
- `public/css/warehouse.css`

Tidak ada migration SQL baru dan tidak perlu `php artisan migrate`.

## Setelah copy

```powershell
cd C:\xampp82\htdocs\Checker
php artisan optimize:clear
```

Jalankan:

```powershell
php artisan serve --host=0.0.0.0 --port=9000
```

Desktop:

`http://127.0.0.1:9000`

Mobile satu jaringan:

`http://IP-PC-ANDA:9000`

## Catatan kamera mobile

Browser modern mewajibkan `getUserMedia()` berada pada secure context.

- `http://127.0.0.1` / `http://localhost` dianggap aman pada device itu sendiri.
- Jika HP membuka `http://192.168.x.x:9000`, kamera dapat ditolak karena bukan HTTPS.
- UI tetap dapat dites lewat HTTP, tetapi untuk test kamera HP gunakan HTTPS.

Scanner kamera menggunakan `html5-qrcode 2.3.8` dari cdnjs, sehingga device juga perlu akses internet untuk memuat library tersebut.

## Flow Supervisor OTP

1. Login sebagai Supervisor.
2. Buka `Supervisor OTP`.
3. Klik `Generate New OTP`.
4. OTP 6 digit akan tampil dan aktif 2 jam.
5. Pada halaman Checker, item yang masih `PENDING` memiliki tombol `Supervisor Override`.
6. Klik tombol tersebut.
7. Supervisor mengetik OTP 6 digit ke popup di device Checker.
8. Pilih alasan override.
9. Klik `Verifikasi OTP & Override`.
10. Sistem menyimpan supervisor, checker, detail item, alasan, OTP id dan waktu override.

## Flow Scan Kamera

1. Login Checker.
2. Load invoice.
3. Klik `Scan via Kamera`.
4. Klik `Mulai Kamera`.
5. Izinkan permission kamera.
6. Kamera belakang dipilih secara default.
7. Arahkan barcode ke area scan.
8. Barcode yang terbaca otomatis dikirim ke endpoint scan yang sama seperti scanner USB.
9. Jika item selesai, status menjadi `OK`.
10. Jika item membutuhkan override, kamera berhenti dan popup Supervisor Override otomatis dibuka.
