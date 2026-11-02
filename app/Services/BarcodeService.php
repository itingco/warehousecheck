<?php

namespace App\Services;

use App\Support\SqlIdentifier;
use Illuminate\Support\Facades\DB;

class BarcodeService
{
    /**
     * Scan rules shared by Sales Invoice and Good Transfer checking:
     * - KODEBARANG~QTY: item code and positive base-unit quantity (PCS).
     * - Without '~': exact lookup in DB_AppHub ItemBarcode, with UOM ratio.
     * The '-' character is an ordinary part of a barcode/item code.
     */
    public function lookup(string $barcode): ?object
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }

        if (str_contains($barcode, '~')) {
            // The delimiter is unambiguous. Never fall back to a master barcode
            // when the QR/label payload is malformed.
            if (substr_count($barcode, '~') !== 1) {
                throw new \RuntimeException('Format label tidak valid. Gunakan KODEBARANG~QTY, contoh 00017~5.');
            }

            [$itemCode, $qtyText] = array_map('trim', explode('~', $barcode, 2));
            if ($itemCode === '' || $qtyText === '' || ! preg_match('/^[1-9][0-9]*$/D', $qtyText)) {
                throw new \RuntimeException('Format label tidak valid. Gunakan KODEBARANG~QTY dengan QTY bilangan bulat lebih dari 0.');
            }

            $qty = filter_var($qtyText, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 2147483647],
            ]);
            if ($qty === false) {
                throw new \RuntimeException('QTY label tidak valid atau melebihi 2147483647.');
            }

            return (object) [
                'barcode' => $barcode,
                'item_code' => $itemCode,
                'uom_code' => null,
                'ratio_qty' => $qty,
                'scan_source' => 'ITEM_QTY_LABEL',
                'label_qty' => $qty,
            ];
        }

        $exact = $this->lookupBarcodeInTable($barcode);
        if ($exact !== null) {
            $exact->scan_source = 'MASTER_BARCODE';
        }

        return $exact;
    }

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

    public function ratioForDocumentUom(string $itemCode, ?string $uomCode): ?int
    {
        if (!$uomCode) {
            return null;
        }

        $r = config('warehouse_check.ratio');
        $rt = SqlIdentifier::quote($r['table']);
        $ri = SqlIdentifier::quote($r['item_column']);
        $ru = SqlIdentifier::quote($r['uom_column']);
        $rv = SqlIdentifier::quote($r['value_column']);

        $sql = "
            SELECT TOP 1 CAST({$rv} AS int) AS ratio_qty
            FROM {$rt}
            WHERE RTRIM({$ri}) = ? AND RTRIM({$ru}) = ? AND {$rv} > 0
        ";
        $row = DB::connection('apphub')->selectOne($sql, [trim($itemCode), trim($uomCode)]);
        return $row ? (int) $row->ratio_qty : null;
    }

    public function ratioForInvoiceUom(string $itemCode, ?string $uomCode): ?int
    {
        return $this->ratioForDocumentUom($itemCode, $uomCode);
    }
}
