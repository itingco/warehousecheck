# V10 Hotfix - Scan Errors + Change Password

Gunakan hotfix ini jika V9/V9.1 sudah terpasang.

Perubahan:
- Fix bentrok `$errors` pada `/admin/scan-errors` dengan `$scanErrors`.
- Tambah `Change Password` untuk semua user yang login.
- Current password wajib benar.
- Password baru minimal 8 karakter dan harus dikonfirmasi.
- Password disimpan dengan hash Laravel.
- Tidak ada SQL/migration baru.

Setelah copy ke root project:

```powershell
cd C:\xampp82\htdocs\Checker
composer dump-autoload
php artisan optimize:clear
```
