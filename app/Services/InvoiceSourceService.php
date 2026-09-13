<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceSourceService
{
    public function connectionForCompany(string $company): string
    {
        return match (strtoupper(trim($company))) {
            'INGCO' => 'as_ingco',
            'SMI' => 'as_smi',
            default => throw new InvalidArgumentException('Company harus INGCO atau SMI.'),
        };
    }

    public function databaseForCompany(string $company): string
    {
        return match (strtoupper(trim($company))) {
            'INGCO' => (string) config('database.connections.as_ingco.database'),
            'SMI' => (string) config('database.connections.as_smi.database'),
            default => throw new InvalidArgumentException('Company harus INGCO atau SMI.'),
        };
    }

    public function getInvoice(string $company, string $invoiceNumber): Collection
    {
        $connection = $this->connectionForCompany($company);

        $sql = <<<'SQL'
SELECT
    INV.InvoiceID,
    DET.InvoiceDetailID,
    INV.InvoiceNumber,
    INV.InvoiceDate,
    CUST.CustomerName,
    CUST.ShippingAddressLine1,
    CUST.ShippingAddressLine2,
    ITEM.ItemCode,
    ITEM.ItemName,
    UOM.UOMCode,
    DET.Quantity
FROM AR_Invoices INV
LEFT JOIN AR_InvoiceDetails DET
    ON DET.InvoiceID = INV.InvoiceID
LEFT JOIN IC_Items ITEM
    ON ITEM.ItemID = DET.ItemID
LEFT JOIN AR_Customers CUST
    ON CUST.CustomerID = INV.CustomerID
LEFT JOIN IC_UOM UOM
    ON UOM.UOMID =
        CASE DET.UOMLevel
            WHEN 1 THEN ITEM.UOMID1
            WHEN 2 THEN ITEM.UOMID2
            WHEN 3 THEN ITEM.UOMID3
            WHEN 4 THEN ITEM.UOMID4
        END
WHERE INV.InvoiceNumber = ?
ORDER BY DET.InvoiceDetailID;
SQL;

        return collect(
            DB::connection($connection)->select($sql, [$invoiceNumber])
        );
    }

    public function markChecked(
        string $company,
        int $invoiceId,
        string $checkerName
    ): void {
        $connection = $this->connectionForCompany($company);

        // ERP field CheckedBy = varchar(30)
        $safeCheckerName = mb_substr($checkerName, 0, 30);

        $affected = DB::connection($connection)
            ->table('AR_Invoices')
            ->where('InvoiceID', $invoiceId)
            ->update([
                'CheckedBy' => $safeCheckerName,
                'CheckedDateTime' => now(),
            ]);

        if ($affected < 1) {
            throw new \RuntimeException(
                'AR_Invoices gagal di-update atau InvoiceID tidak ditemukan.'
            );
        }
    }
}
