<?php

namespace App\Services;

use App\Support\SqlIdentifier;
use Illuminate\Support\Facades\DB;

class BarcodeService
{
    public function lookup(string $barcode): ?object
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

        // ItemCode/UOM/Barcode adalah NCHAR pada DB_AppHub,
        // sehingga RTRIM digunakan agar aplikasi mendapat value bersih.
        //
        // Ratio sengaja TIDAK COALESCE ke 1.
        // Jika tidak ada ratio => NULL => harus Supervisor Override.
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

        return DB::connection('apphub')->selectOne($sql, [trim($barcode)]);
    }

    public function ratioForInvoiceUom(
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
}
