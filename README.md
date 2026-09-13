# Warehouse Barcode Checking - Laravel 10

Starter project untuk checking barang gudang berdasarkan Sales Invoice, barcode, picker, checker, dan supervisor OTP override.

## Target
- Laravel 10
- PHP 8.1+
- SQL Server / `pdo_sqlsrv`
- Database aplikasi/audit: `DB_AppHub`
- Database source invoice: `as_ingco` dan `as_smi`
- Responsive desktop/mobile
- Scanner barcode USB/Bluetooth yang bertindak seperti keyboard + Enter

> Penting: password database **tidak** ditaruh di source code. Isi sendiri pada `.env`.

## Alur utama

1. Checker login.
2. Checker memilih Company: `INGCO` atau `SMI`.
3. Checker memilih nama Picker.
4. Checker memasukkan nomor invoice.
5. Sistem mengambil invoice dan membuat session `DRAFT`.
6. Scan barcode:
   - barcode tidak dikenal -> langsung dicatat ke `warehouse_check_scan_errors`
   - item tidak ada di invoice -> langsung dicatat ke error
   - qty scan melebihi kebutuhan -> dicatat ke error
   - item sesuai -> `scanned_base_qty` ditambah berdasarkan ratio barcode
   - jika qty sudah pas -> baris otomatis `OK`
7. Item tanpa barcode/special:
   - Supervisor generate OTP 6 digit, aktif 2 jam
   - Checker memasukkan OTP + alasan di baris invoice
   - Baris menjadi `OVERRIDE`
   - supervisor yang melakukan override tercatat
8. Tombol Final Save hanya aktif jika semua baris `OK`/`OVERRIDE`.
9. Final Save:
   - session menjadi `COMPLETED`
   - update `AR_Invoices.CheckedBy`
   - update `AR_Invoices.CheckDateTime`

## Kenapa dibuat DRAFT sejak invoice dibuka?

Karena salah scan harus tersimpan **langsung**, termasuk sebelum Final Save. Dengan draft session, seluruh aktivitas tetap punya relasi ke invoice, picker, checker dan waktu.

## Instalasi

Buat fresh Laravel 10:

```bash
composer create-project laravel/laravel warehouse-check "10.*"
cd warehouse-check
```

Copy isi bundle ini ke project Laravel fresh dan replace file yang sama.

Lalu:

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Linux:
```bash
cp .env.example .env
php artisan key:generate
```

Pastikan extension SQL Server aktif:

```bash
php -m | grep -E "sqlsrv|pdo_sqlsrv"
```

Windows PowerShell:
```powershell
php -m | Select-String "sqlsrv|pdo_sqlsrv"
```

Isi koneksi di `.env`, lalu jalankan:

```bash
php artisan migrate --database=apphub
php artisan warehouse:user admin admin@local admin
php artisan serve
```

Command `warehouse:user` akan meminta password secara hidden.

## Konfigurasi `.env`

```env
DB_CONNECTION=apphub

SQLSERVER_HOST=YOUR_SERVER
SQLSERVER_PORT=YOUR_PORT
SQLSERVER_USERNAME=YOUR_USER
SQLSERVER_PASSWORD=YOUR_PASSWORD
SQLSERVER_ENCRYPT=no
SQLSERVER_TRUST_SERVER_CERTIFICATE=true

APPHUB_DATABASE=DB_AppHub
INGCO_DATABASE=as_ingco
SMI_DATABASE=as_smi
```

### Mapping kolom barcode

Karena contoh yang tersedia hanya:

```sql
SELECT TOP 5 *
FROM ItemBarcode
LEFT JOIN ItemRatio ON ItemRatio.ItemCode = ItemBarcode.ItemCode;
```

nama kolom barcode/ratio belum bisa dipastikan. Default project mengasumsikan:

```env
BARCODE_COLUMN=Barcode
BARCODE_ITEM_COLUMN=ItemCode
BARCODE_UOM_COLUMN=UOMCode

RATIO_ITEM_COLUMN=ItemCode
RATIO_UOM_COLUMN=UOMCode
RATIO_VALUE_COLUMN=Ratio
```

Jalankan `sql/00_inspect_barcode_schema.sql`. Jika nama kolom berbeda, cukup ubah `.env`; tidak perlu ubah controller.

## Catatan quantity / ratio

Sistem menormalkan quantity menjadi **base quantity**:

```text
expected_base_qty = Invoice Quantity × ratio UOM invoice
scan_base_qty     = scan sebelumnya + ratio barcode
```

Jika ratio invoice tidak ditemukan di `ItemRatio`, sistem fallback ke `1`.

Contoh:
- Invoice 2 CTN
- Ratio CTN = 12
- Expected base = 24
- Scan PCS ratio 1 sebanyak 24 kali -> OK
- Scan barcode CTN ratio 12 sebanyak 2 kali -> OK

## SQL script

Jika ingin membuat tabel manual dari SSMS, jalankan:

`sql/01_create_warehouse_check_tables.sql`

Jika memakai migration Laravel, cukup jalankan:

```bash
php artisan migrate --database=apphub
```

## Role
- `picker`: nama picker dapat dipilih pada checking
- `checker`: melakukan checking dan final save
- `supervisor`: generate OTP override
- `admin`: akses semua modul + manajemen user

## Keamanan OTP
OTP tidak disimpan plaintext:
- `code_hash` digunakan untuk validasi
- `code_encrypted` digunakan agar supervisor yang sedang login dapat melihat kode aktif miliknya
- masa aktif default 2 jam
- generate OTP baru otomatis revoke OTP aktif lama milik supervisor tersebut

## File penting
- `app/Services/InvoiceSourceService.php`
- `app/Services/BarcodeService.php`
- `app/Services/WarehouseCheckService.php`
- `app/Http/Controllers/WarehouseCheckController.php`
- `app/Http/Controllers/SupervisorOtpController.php`
- `resources/views/checks/show.blade.php`
- `sql/01_create_warehouse_check_tables.sql`
