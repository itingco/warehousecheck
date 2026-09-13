# Update alasan Supervisor Override

File yang berubah:

`resources/views/checks/show.blade.php`

Copy file tersebut ke:

`C:\xampp82\htdocs\Checker\resources\views\checks\show.blade.php`

lalu jalankan:

```powershell
cd C:\xampp82\htdocs\Checker
php artisan optimize:clear
```

Setelah refresh halaman detail invoice, item dengan status `OVERRIDE`
akan menampilkan alasan override di bawah badge status.

Mapping alasan:
- NO_BARCODE -> Tidak punya barcode
- SPECIAL_ITEM -> Special item
- DAMAGED_BARCODE -> Barcode rusak
- NO_RATIO -> Ratio tidak tersedia
- OTHER -> Lainnya

`reason_note` juga ditampilkan jika supervisor/checker mengisinya.

Tidak ada perubahan database dan tidak perlu migrate.
