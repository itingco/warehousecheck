<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestWarehouseDatabase extends Command
{
    protected $signature = 'warehouse:test-db';

    protected $description =
        'Test DB_AppHub, AS_INGCO, AS_SMI and required tables';

    public function handle(): int
    {
        $connections = [
            'apphub' => 'DB_AppHub',
            'as_ingco' => 'AS_INGCO',
            'as_smi' => 'AS_SMI',
        ];

        foreach ($connections as $connection => $expected) {
            try {
                $row = DB::connection($connection)
                    ->selectOne('SELECT DB_NAME() AS db');

                $actual = $row->db ?? null;

                if (
                    strcasecmp((string) $actual, $expected) !== 0
                ) {
                    $this->error(
                        "{$connection}: connected to {$actual}, expected {$expected}"
                    );
                    return self::FAILURE;
                }

                $this->info(
                    "{$connection}: OK ({$actual})"
                );
            } catch (\Throwable $e) {
                $this->error(
                    "{$connection}: FAILED - {$e->getMessage()}"
                );

                return self::FAILURE;
            }
        }

        $requiredTables = [
            'warehouse_users',
            'warehouse_check_headers',
            'warehouse_check_details',
            'warehouse_check_scan_logs',
            'warehouse_check_scan_errors',
            'supervisor_otps',
            'warehouse_check_overrides',
            'ItemBarcode',
            'ItemRatio',
        ];

        foreach ($requiredTables as $table) {
            $exists = DB::connection('apphub')
                ->selectOne(
                    "SELECT CASE WHEN OBJECT_ID(?, 'U') IS NULL THEN 0 ELSE 1 END AS ok",
                    ['dbo.' . $table]
                );

            if (!(bool) $exists->ok) {
                $this->error(
                    "DB_AppHub table missing: {$table}"
                );

                return self::FAILURE;
            }

            $this->line("table {$table}: OK");
        }

        foreach (['as_ingco', 'as_smi'] as $connection) {
            $row = DB::connection($connection)->selectOne(
                "
                SELECT
                    SUM(CASE WHEN c.name = 'CheckedBy' THEN 1 ELSE 0 END) AS checked_by,
                    SUM(CASE WHEN c.name = 'CheckedDateTime' THEN 1 ELSE 0 END) AS checked_datetime
                FROM sys.columns c
                WHERE c.object_id = OBJECT_ID('dbo.AR_Invoices')
                  AND c.name IN ('CheckedBy', 'CheckedDateTime')
                "
            );

            if (
                (int) $row->checked_by !== 1
                || (int) $row->checked_datetime !== 1
            ) {
                $this->error(
                    "{$connection}: CheckedBy / CheckedDateTime tidak lengkap."
                );

                return self::FAILURE;
            }

            $this->line(
                "{$connection}.AR_Invoices fields: OK"
            );
        }

        $this->newLine();
        $this->info(
            'Semua koneksi dan struktur database utama PASS.'
        );

        return self::SUCCESS;
    }
}
