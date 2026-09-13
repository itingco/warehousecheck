# Implementation Notes

## 1. Query invoice
Query user dipertahankan, tetapi ditambah:
- `INV.InvoiceID`
- `DET.InvoiceDetailID`

Keduanya dibutuhkan sebagai key internal agar duplicate item code pada satu invoice tidak salah ter-update.

## 2. Data source
Mapping:
- INGCO => connection `as_ingco`
- SMI => connection `as_smi`
- Audit/app => connection `apphub` / DB `DB_AppHub`

## 3. Scan error
Error tersimpan segera:
- UNKNOWN_BARCODE
- ITEM_NOT_IN_INVOICE
- OVER_QTY

Karena header DRAFT sudah dibuat, error selalu punya:
- invoice
- picker
- checker
- waktu

## 4. Override
Override tidak "menghapus" quantity scan. Baris hanya berubah status ke `OVERRIDE`, sementara quantity scan aktual tetap tersimpan untuk audit.

## 5. Final save
Sistem tidak menggunakan distributed transaction lintas database.
Flow:
1. apphub status -> FINALIZING
2. update source AR_Invoices
3. apphub status -> COMPLETED
4. jika update source gagal -> kembali DRAFT

## 6. Duplicate invoice
Satu invoice/company yang sudah COMPLETED tidak bisa di-check ulang dari UI standar.
Jika butuh Re-Check, sebaiknya dibuat workflow khusus agar audit lama tidak tertimpa.

## 7. Barcode schema
Nama kolom `ItemBarcode` / `ItemRatio` belum diberikan. Karena itu mapping dibuat lewat `.env`.
Jalankan `sql/00_inspect_barcode_schema.sql` untuk memastikan nama kolom aktual.
