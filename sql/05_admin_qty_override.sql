USE DB_AppHub;
GO

/* ========================================================================== */
/* ADMIN QTY OVERRIDE - INVOICE                                                */
/* ========================================================================== */

IF OBJECT_ID('dbo.warehouse_check_overrides', 'U') IS NOT NULL
BEGIN
    IF COL_LENGTH('dbo.warehouse_check_overrides', 'admin_user_id') IS NULL
    BEGIN
        ALTER TABLE dbo.warehouse_check_overrides
            ADD admin_user_id BIGINT NULL;
    END;

    IF COL_LENGTH('dbo.warehouse_check_overrides', 'override_qty') IS NULL
    BEGIN
        ALTER TABLE dbo.warehouse_check_overrides
            ADD override_qty DECIMAL(18,4) NULL;
    END;

    IF COL_LENGTH('dbo.warehouse_check_overrides', 'override_base_qty') IS NULL
    BEGIN
        ALTER TABLE dbo.warehouse_check_overrides
            ADD override_base_qty DECIMAL(18,4) NULL;
    END;

    IF NOT EXISTS (
        SELECT 1
        FROM sys.foreign_keys
        WHERE name = 'FK_wco_admin'
          AND parent_object_id = OBJECT_ID('dbo.warehouse_check_overrides')
    )
    BEGIN
        ALTER TABLE dbo.warehouse_check_overrides
            ADD CONSTRAINT FK_wco_admin
            FOREIGN KEY (admin_user_id)
            REFERENCES dbo.warehouse_users(id);
    END;

    /*
     * Database lama dapat memiliki CHECK constraint yang hanya menerima
     * reason override supervisor. AdminQtyOverrideService menggunakan
     * ADMIN_QTY_OVERRIDE, jadi constraint harus diselaraskan dengan aplikasi.
     */
    DECLARE @wco_constraint SYSNAME;

    WHILE 1 = 1
    BEGIN
        SET @wco_constraint = NULL;

        SELECT TOP (1) @wco_constraint = cc.name
        FROM sys.check_constraints AS cc
        WHERE cc.parent_object_id = OBJECT_ID('dbo.warehouse_check_overrides')
          AND (
                cc.name = 'CK_warehouse_check_overrides_reason'
                OR LOWER(cc.definition) LIKE '%reason_code%'
              );

        IF @wco_constraint IS NULL
            BREAK;

        EXEC(
            'ALTER TABLE dbo.warehouse_check_overrides DROP CONSTRAINT ['
            + REPLACE(@wco_constraint, ']', ']]')
            + ']'
        );
    END;

    ALTER TABLE dbo.warehouse_check_overrides WITH CHECK
        ADD CONSTRAINT CK_warehouse_check_overrides_reason
        CHECK (reason_code IN (
            'NO_BARCODE',
            'SPECIAL_ITEM',
            'DAMAGED_BARCODE',
            'NO_RATIO',
            'OTHER',
            'ADMIN_QTY_OVERRIDE'
        ));

    ALTER TABLE dbo.warehouse_check_overrides
        CHECK CONSTRAINT CK_warehouse_check_overrides_reason;
END;
GO

/* ========================================================================== */
/* ADMIN QTY OVERRIDE - GOOD TRANSFER                                          */
/* ========================================================================== */

IF OBJECT_ID('dbo.good_transfer_check_overrides', 'U') IS NOT NULL
BEGIN
    IF COL_LENGTH('dbo.good_transfer_check_overrides', 'admin_user_id') IS NULL
    BEGIN
        ALTER TABLE dbo.good_transfer_check_overrides
            ADD admin_user_id BIGINT NULL;
    END;

    IF COL_LENGTH('dbo.good_transfer_check_overrides', 'override_qty') IS NULL
    BEGIN
        ALTER TABLE dbo.good_transfer_check_overrides
            ADD override_qty DECIMAL(18,4) NULL;
    END;

    IF COL_LENGTH('dbo.good_transfer_check_overrides', 'override_base_qty') IS NULL
    BEGIN
        ALTER TABLE dbo.good_transfer_check_overrides
            ADD override_base_qty DECIMAL(18,4) NULL;
    END;

    IF NOT EXISTS (
        SELECT 1
        FROM sys.foreign_keys
        WHERE name = 'FK_gtco_admin'
          AND parent_object_id = OBJECT_ID('dbo.good_transfer_check_overrides')
    )
    BEGIN
        ALTER TABLE dbo.good_transfer_check_overrides
            ADD CONSTRAINT FK_gtco_admin
            FOREIGN KEY (admin_user_id)
            REFERENCES dbo.warehouse_users(id);
    END;

    DECLARE @gtco_constraint SYSNAME;

    WHILE 1 = 1
    BEGIN
        SET @gtco_constraint = NULL;

        SELECT TOP (1) @gtco_constraint = cc.name
        FROM sys.check_constraints AS cc
        WHERE cc.parent_object_id = OBJECT_ID('dbo.good_transfer_check_overrides')
          AND (
                cc.name = 'CK_good_transfer_check_overrides_reason'
                OR LOWER(cc.definition) LIKE '%reason_code%'
              );

        IF @gtco_constraint IS NULL
            BREAK;

        EXEC(
            'ALTER TABLE dbo.good_transfer_check_overrides DROP CONSTRAINT ['
            + REPLACE(@gtco_constraint, ']', ']]')
            + ']'
        );
    END;

    ALTER TABLE dbo.good_transfer_check_overrides WITH CHECK
        ADD CONSTRAINT CK_good_transfer_check_overrides_reason
        CHECK (reason_code IN (
            'NO_BARCODE',
            'SPECIAL_ITEM',
            'DAMAGED_BARCODE',
            'NO_RATIO',
            'OTHER',
            'ADMIN_QTY_OVERRIDE'
        ));

    ALTER TABLE dbo.good_transfer_check_overrides
        CHECK CONSTRAINT CK_good_transfer_check_overrides_reason;
END;
GO

PRINT 'Patch 05_admin_qty_override.sql selesai.';
GO
