<?php

namespace App\Services;

use App\Support\SqlIdentifier;
use Illuminate\Support\Facades\DB;

class BarcodeService
{
    /**
     * Prioritas:
     * 1. Cari seluruh nilai scan di tabel barcode (termasuk tanda -).
     * 2. Jika tidak ditemukan dan format berakhir -qty, cari prefix sebagai barcode.
     * 3. Jika prefix barcode ditemukan, qty dari label menggantikan ratio.
     * 4. Jika prefix bukan barcode, gunakan prefix sebagai kode barang.
     */
    public function lookup(string $barcode): ?object
    {
        $barcode = trim($barcode);

        if ($barcode === '') {
            return null;
        }

        // Barcode utuh selalu menang, termasuk jika memiliki karakter '-'.
        $resolved = $this->lookupBarcodeInTable($barcode);
        if ($resolved !== null) {
            return $resolved;
        }

        $separatorPosition = strrpos($barcode, '-');
        if ($separatorPosition === false) {
            return null;
        }

        $prefix = trim(substr($barcode, 0, $separatorPosition));
        $qtyText = trim(substr($barcode, $separatorPosition + 1));

        // Jangan menganggap label dengan qty tidak valid sebagai scan qty.
        // Qty harus bilangan bulat positif yang muat di kolom ratio_qty INT.
        if ($prefix === '' || !preg_match('/^[0-9]+$/D', $qtyText)) {
            return null;
        }

        $qty = filter_var($qtyText, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 2147483647],
        ]);
        if ($qty === false) {
            return null;
        }

        $prefixBarcode = $this->lookupBarcodeInTable($prefix);
        if ($prefixBarcode !== null) {
            // Jumlah pada label dipakai langsung, bukan ratio UOM barcode.
            $prefixBarcode->barcode = $barcode;
            $prefixBarcode->ratio_qty = $qty;
            $prefixBarcode->scan_source = 'BARCODE_QTY_LABEL';
            return $prefixBarcode;
        }

        return (object) [
            'barcode' => $barcode,
            'item_code' => $prefix,
            'uom_code' => null,
            'ratio_qty' => $qty,
            'scan_source' => 'ITEM_QTY_LABEL',
        ];
    }

    /** Cari satu kode barcode persis di tabel sumber. */
    private function lookupBarcodeInTable(string $barcode): ?object
    {
        $b = config('warehouse_check.barcode');
        $r = config('warehouse_check.ratio');

        $bt = SqlIdentifier::quote($b['table']);
        $bc = SqlIdentifier::quote($b['barcode_column']);
        $bi = SqlIdentifier::quote($b['item_column']);
        $bu = SqlIdentifier::quote($b['uom_column']);

        $rt = SqlIdentifier::quote($r['table']);
        $ri = SqlIdentifier::quote($r['item_column']);
        $ru = SqlIdentifier::quote($r['uom_column']);
        $rv = SqlIdentifier::quote($r['value_column']);

        $sql = "
            SELECT TOP 1
                RTRIM(B.{$bc}) AS barcode,
                RTRIM(B.{$bi}) AS item_code,
                RTRIM(B.{$bu}) AS uom_code,
                CAST(R.{$rv} AS int) AS ratio_qty
            FROM {$bt} B
            LEFT JOIN {$rt} R
                ON RTRIM(R.{$ri}) = RTRIM(B.{$bi})
               AND RTRIM(R.{$ru}) = RTRIM(B.{$bu})
            WHERE RTRIM(B.{$bc}) = ?
        ";

        return DB::connection('apphub')->selectOne($sql, [$barcode]);
    }

    /**
     * Generic ratio lookup for any source document UOM.
     */
    public function ratioForDocumentUom(
        string $itemCode,
        ?string $uomCode
    ): ?int {
        if (!$uomCode) {
            return null;
        }

        $r = config('warehouse_check.ratio');

        $rt = SqlIdentifier::quote($r['table']);
        $ri = SqlIdentifier::quote($r['item_column']);
        $ru = SqlIdentifier::quote($r['uom_column']);
        $rv = SqlIdentifier::quote($r['value_column']);

        $sql = "
            SELECT TOP 1
                CAST({$rv} AS int) AS ratio_qty
            FROM {$rt}
            WHERE RTRIM({$ri}) = ?
              AND RTRIM({$ru}) = ?
              AND {$rv} > 0
        ";

        $row = DB::connection('apphub')->selectOne(
            $sql,
            [trim($itemCode), trim($uomCode)]
        );

        return $row ? (int) $row->ratio_qty : null;
    }

    /**
     * Backward-compatible method used by the existing invoice module.
     */
    public function ratioForInvoiceUom(
        string $itemCode,
        ?string $uomCode
    ): ?int {
        return $this->ratioForDocumentUom($itemCode, $uomCode);
    }
}
