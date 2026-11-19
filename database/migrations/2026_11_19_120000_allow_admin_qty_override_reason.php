<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Allow the ADMIN_QTY_OVERRIDE audit reason used by AdminQtyOverrideService.
     *
     * Some existing DB_AppHub databases were created with a CHECK constraint
     * that only accepts the original supervisor override reason codes. The
     * current application also writes ADMIN_QTY_OVERRIDE for the admin-specific
     * quantity override flow, so the legacy constraint must be expanded.
     */
    public function up(): void
    {
        $this->applyReasonConstraint(
            'warehouse_check_overrides',
            'CK_warehouse_check_overrides_reason'
        );

        $this->applyReasonConstraint(
            'good_transfer_check_overrides',
            'CK_good_transfer_check_overrides_reason'
        );
    }

    /**
     * Keep the expanded constraint on rollback.
     *
     * Removing ADMIN_QTY_OVERRIDE after records have already been written can
     * make a rollback fail and would invalidate historical audit data.
     */
    public function down(): void
    {
        // Intentionally left compatible with existing ADMIN_QTY_OVERRIDE rows.
    }

    private function applyReasonConstraint(string $table, string $constraint): void
    {
        $connection = DB::connection('apphub');

        $exists = $connection->selectOne(
            "SELECT CASE WHEN OBJECT_ID(?, 'U') IS NULL THEN 0 ELSE 1 END AS table_exists",
            ['dbo.' . $table]
        );

        if ((int) ($exists->table_exists ?? 0) !== 1) {
            return;
        }

        // Drop every CHECK constraint on this table that validates reason_code.
        // This also handles older installations where the constraint name may
        // differ from the current standard name.
        $constraints = $connection->select(
            "SELECT cc.name
             FROM sys.check_constraints AS cc
             WHERE cc.parent_object_id = OBJECT_ID(?)
               AND (
                    cc.name = ?
                    OR LOWER(cc.definition) LIKE '%reason_code%'
               )",
            ['dbo.' . $table, $constraint]
        );

        foreach ($constraints as $row) {
            $name = str_replace(']', ']]', (string) $row->name);
            $connection->statement(
                "ALTER TABLE dbo.[{$table}] DROP CONSTRAINT [{$name}]"
            );
        }

        $connection->statement(
            "ALTER TABLE dbo.[{$table}] WITH CHECK
             ADD CONSTRAINT [{$constraint}]
             CHECK ([reason_code] IN (
                'NO_BARCODE',
                'SPECIAL_ITEM',
                'DAMAGED_BARCODE',
                'NO_RATIO',
                'OTHER',
                'ADMIN_QTY_OVERRIDE'
             ))"
        );

        $connection->statement(
            "ALTER TABLE dbo.[{$table}] CHECK CONSTRAINT [{$constraint}]"
        );
    }
};
