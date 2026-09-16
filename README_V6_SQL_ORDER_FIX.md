# WarehouseCheck V6 - SQL Server Scan ORDER BY Hotfix

Hotfix ini untuk project yang sudah memakai V5.

Error yang diperbaiki:

```text
SQLSTATE[42000]: A column has been specified more than once in the order by list.
```

Copy folder `app` ke root project Laravel dan overwrite file yang ada.

Tidak ada SQL / migration baru.

Setelah copy:

```powershell
cd C:\xampp82\htdocs\Checker
composer dump-autoload
php artisan optimize:clear
```

File runtime yang berubah hanya:

- `app/Models/WarehouseCheckHeader.php`
- `app/Models/GoodTransferCheckHeader.php`
- `app/Http/Controllers/WarehouseCheckController.php`
- `app/Http/Controllers/GoodTransferCheckController.php`
