# Warehouse Checking - Laravel 10 Update Files

Folder ini **hanya berisi file baru / file yang perlu diganti** pada fresh Laravel 10.

Database SQL Server diasumsikan SUDAH dibuat:
- DB_AppHub
  - warehouse_users
  - warehouse_check_headers
  - warehouse_check_details
  - warehouse_check_scan_logs
  - warehouse_check_scan_errors
  - supervisor_otps
  - warehouse_check_overrides
  - ItemBarcode
  - ItemRatio
- AS_INGCO
  - AR_Invoices.CheckedBy
  - AR_Invoices.CheckedDateTime
- AS_SMI
  - AR_Invoices.CheckedBy
  - AR_Invoices.CheckedDateTime

## Penting

Update ini sudah menyesuaikan hasil pengecekan database terakhir:

1. User menggunakan tabel `warehouse_users`
2. Field ERP yang benar adalah `CheckedDateTime`
3. `ItemBarcode.UOM` dan `ItemRatio.UOM`
4. `ItemBarcode` / `ItemRatio` memakai kolom `nchar`, sehingga query menggunakan `RTRIM`
5. Barcode/item yang tidak memiliki ratio **tidak dianggap ratio = 1**
6. Item tanpa ratio tidak dicatat sebagai kesalahan picker; item tersebut harus diselesaikan lewat Supervisor Override
7. `CheckedBy` dibatasi maksimal 30 karakter karena field ERP adalah `varchar(30)`

## Cara copy

Copy seluruh isi folder update ini ke root Laravel 10 Anda.

Contoh:

warehouse-check-laravel10-update/app
-> replace/merge ke
warehouse-check/app

warehouse-check-laravel10-update/config
-> replace/merge ke
warehouse-check/config

dan seterusnya.

File `.env` tidak disertakan agar password database tidak tertimpa.
Gunakan `ENV_SNIPPET.txt`.

## Setelah copy

```bash
php artisan optimize:clear
php artisan config:clear
```

Kemudian test koneksi:

```bash
php artisan warehouse:test-db
```

Buat admin pertama:

```bash
php artisan warehouse:user admin admin
```

Command akan menanyakan password secara hidden.

Lalu:

```bash
php artisan serve
```

Buka:

http://127.0.0.1:8000
