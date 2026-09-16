# WarehouseCheck V7 - Scanner Beep Hotfix

Untuk project yang sudah menggunakan V6.

File yang diubah hanya:

`resources/views/partials/check-scanner-script.blade.php`

Perilaku baru:
- Scan sukses/diterima backend: beep tinggi pendek dan tegas.
- Scan gagal/ditolak/harus override: dua beep rendah.
- Berlaku untuk Invoice dan Good Transfer karena keduanya memakai partial scanner yang sama.
- Tidak perlu file audio tambahan.

Setelah copy file ke project Laravel:

```powershell
cd C:\xampp82\htdocs\Checker
php artisan optimize:clear
```

Tidak ada perubahan SQL/migration.

Catatan: volume akhir mengikuti volume media HP/PC dan batas output browser/perangkat.
