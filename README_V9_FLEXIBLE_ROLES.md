# V9 Hotfix - Flexible Picker / Checker

Gunakan hotfix ini jika project Anda sudah memakai V8.

Perubahan:
- semua user aktif non-admin dapat menjadi checker;
- semua user aktif non-admin dapat dipilih sebagai picker;
- checker tidak dapat memilih dirinya sendiri sebagai picker;
- admin tidak dapat menjadi picker/checker;
- supervisor termasuk user non-admin, jadi dapat menjadi picker/checker selama aktif;
- aturan berlaku untuk Invoice dan Good Transfer;
- filter Scan Errors admin tidak lagi mengasumsikan role picker/checker lama;
- tidak ada SQL atau migration baru.

Setelah copy isi ZIP ke root project:

```powershell
cd C:\xampp82\htdocs\Checker
composer dump-autoload
php artisan optimize:clear
```
