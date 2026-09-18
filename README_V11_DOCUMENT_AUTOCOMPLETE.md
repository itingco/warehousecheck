# WarehouseCheck V11 - Document Number Autocomplete

Prerequisite: project sudah menggunakan update V10.

Perubahan:
- Autocomplete nomor Invoice dan Good Transfer setelah user mengetik pola `YYMMDD/`, contoh `260915/`.
- Pilih Company terlebih dahulu.
- Hasil maksimal 20 nomor.
- Ketik lanjutan seperti `260915/0` untuk mempersempit hasil.
- Debounce 350 ms dan request sebelumnya dibatalkan agar tidak membanjiri server.
- Query hanya ke tabel header dan dibatasi satu range tanggal, tanpa join detail item.
- Endpoint hanya untuk user operasional aktif non-admin.
- Tidak ada SQL/migration baru.

Setelah copy file ke project:

```powershell
cd C:\xampp82\htdocs\Checker
composer dump-autoload
php artisan optimize:clear
```

Test cepat:
1. Login sebagai user operasional non-admin.
2. Buka New Check.
3. Pilih Invoice dan Company SMI.
4. Ketik `260915/` pada Nomor Invoice.
5. Pilih salah satu nomor yang muncul, misalnya `SMI/SI/CG-N/260915/007`.
