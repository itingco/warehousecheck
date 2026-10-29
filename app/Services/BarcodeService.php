<?php

namespace App\Services;

use App\Support\SqlIdentifier;
use Illuminate\Support\Facades\DB;

class BarcodeService
{
    /**
     * Resolution order:
     * 1. Exact master barcode, even when it contains '-'.
     * 2. BARCODE-QTY: multiply Qty of scanned UOM by its ratio to PCS.
     * 3. ITEMCODE-QTY: Qty is already in base units (PCS).
     * 4. No valid match: return null.
     *
     * This rule is shared by invoice and good-transfer scan engines.
     */
    public function lookup(string $barcode): ?object
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }

        $exact = $this->lookupBarcodeInTable($barcode);
        if ($exact !== null) {
            $exact->scan_source = 'MASTER_BARCODE';
            return $exact;
        }

        $position = strrpos($barcode, '-');
        if ($position === false) {
            return null;
        }

        $prefix = trim(substr($barcode, 0, $position));
        $qtyText = trim(substr($barcode, $position + 1));
        if ($prefix === '' || !preg_match('/^[0-9]+$/D', $qtyText)) {
            return null;
        }

        $qty = filter_var($qtyText, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 2147483647],
        ]);
        if ($qty === false) {
            return null;
        }

        $master = $this->lookupBarcodeInTable($prefix);
        if ($master !== null) {
            // We require a valid positive ratio. Never silently count 5 BOX as 5 PCS.
            $ratio = $master->ratio_qty;
            if ($ratio === null || (int) $ratio <= 0) {
                $master->barcode = $barcode;
                $master->ratio_qty = null;
                $master->scan_source = 'BARCODE_QTY_LABEL_MISSING_RATIO';
                return $master;
            }

            $base = (int) $qty * (int) $ratio;
            // Scan engines and DB scan log use INT for ratio_qty: reject overflow.
            if ($base > 2147483647) {
                throw new \RuntimeException('Qty label melebihi batas perhitungan. Kurangi Qty pada label.');
            }
            $master->barcode = $barcode;
            $master->ratio_qty = $base;
            $master->scan_source = 'BARCODE_QTY_LABEL';
            $master->label_qty = $qty;
            $master->unit_ratio = $ratio;
            return $master;
        }

        return (object) [
            'barcode' => $barcode,
            'item_code' => $prefix,
            'uom_code' => null,
            'ratio_qty' => $qty,
            'scan_source' => 'ITEM_QTY_LABEL',
            'label_qty' => $qty,
        ];
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
