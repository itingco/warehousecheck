USE DB_AppHub;
GO

IF OBJECT_ID('dbo.warehouse_users', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.warehouse_users (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        name NVARCHAR(120) NOT NULL,
        email NVARCHAR(150) NOT NULL UNIQUE,
        password NVARCHAR(255) NOT NULL,
        role NVARCHAR(20) NOT NULL,
        is_active BIT NOT NULL CONSTRAINT DF_warehouse_users_is_active DEFAULT 1,
        remember_token NVARCHAR(100) NULL,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,
        CONSTRAINT CK_warehouse_users_role CHECK (role IN ('picker','checker','supervisor','admin'))
    );
END;
GO

IF OBJECT_ID('dbo.warehouse_check_headers', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.warehouse_check_headers (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        company NVARCHAR(10) NOT NULL,
        source_database NVARCHAR(100) NOT NULL,
        invoice_id BIGINT NOT NULL,
        invoice_number NVARCHAR(100) NOT NULL,
        invoice_date DATE NULL,
        customer_name NVARCHAR(255) NULL,
        shipping_address NVARCHAR(500) NULL,
        picker_user_id BIGINT NOT NULL,
        checker_user_id BIGINT NOT NULL,
        status NVARCHAR(20) NOT NULL CONSTRAINT DF_wch_status DEFAULT 'DRAFT',
        started_at DATETIME2 NOT NULL,
        completed_at DATETIME2 NULL,
        source_updated_at DATETIME2 NULL,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,

        CONSTRAINT FK_wch_picker FOREIGN KEY (picker_user_id) REFERENCES dbo.warehouse_users(id),
        CONSTRAINT FK_wch_checker FOREIGN KEY (checker_user_id) REFERENCES dbo.warehouse_users(id),
        CONSTRAINT CK_wch_status CHECK (status IN ('DRAFT','FINALIZING','COMPLETED'))
    );

    CREATE INDEX IX_wch_company_invoice
        ON dbo.warehouse_check_headers(company, invoice_number);

    CREATE INDEX IX_wch_status_started
        ON dbo.warehouse_check_headers(status, started_at);
END;
GO

IF OBJECT_ID('dbo.warehouse_check_details', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.warehouse_check_details (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        check_header_id BIGINT NOT NULL,
        source_detail_id BIGINT NOT NULL,
        item_code NVARCHAR(100) NOT NULL,
        item_name NVARCHAR(255) NULL,
        uom_code NVARCHAR(50) NULL,
        expected_qty DECIMAL(18,4) NOT NULL,
        invoice_uom_ratio DECIMAL(18,4) NOT NULL CONSTRAINT DF_wcd_ratio DEFAULT 1,
        expected_base_qty DECIMAL(18,4) NOT NULL,
        scanned_base_qty DECIMAL(18,4) NOT NULL CONSTRAINT DF_wcd_scanned DEFAULT 0,
        status NVARCHAR(20) NOT NULL CONSTRAINT DF_wcd_status DEFAULT 'PENDING',
        completed_at DATETIME2 NULL,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,

        CONSTRAINT FK_wcd_header FOREIGN KEY (check_header_id)
            REFERENCES dbo.warehouse_check_headers(id) ON DELETE CASCADE,

        CONSTRAINT UQ_wcd_source UNIQUE(check_header_id, source_detail_id),
        CONSTRAINT CK_wcd_status CHECK (status IN ('PENDING','OK','OVERRIDE'))
    );

    CREATE INDEX IX_wcd_header_item_status
        ON dbo.warehouse_check_details(check_header_id, item_code, status);
END;
GO

IF OBJECT_ID('dbo.warehouse_check_scan_logs', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.warehouse_check_scan_logs (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        check_header_id BIGINT NOT NULL,
        check_detail_id BIGINT NOT NULL,
        barcode NVARCHAR(200) NOT NULL,
        item_code NVARCHAR(100) NOT NULL,
        uom_code NVARCHAR(50) NULL,
        ratio_qty DECIMAL(18,4) NOT NULL,
        checker_user_id BIGINT NOT NULL,
        scanned_at DATETIME2 NOT NULL,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,

        CONSTRAINT FK_wcsl_header FOREIGN KEY (check_header_id)
            REFERENCES dbo.warehouse_check_headers(id) ON DELETE CASCADE,
        CONSTRAINT FK_wcsl_detail FOREIGN KEY (check_detail_id)
            REFERENCES dbo.warehouse_check_details(id),
        CONSTRAINT FK_wcsl_checker FOREIGN KEY (checker_user_id)
            REFERENCES dbo.warehouse_users(id)
    );

    CREATE INDEX IX_wcsl_header_scanned
        ON dbo.warehouse_check_scan_logs(check_header_id, scanned_at);
END;
GO

IF OBJECT_ID('dbo.warehouse_check_scan_errors', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.warehouse_check_scan_errors (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        check_header_id BIGINT NOT NULL,
        barcode NVARCHAR(200) NOT NULL,
        resolved_item_code NVARCHAR(100) NULL,
        error_type NVARCHAR(50) NOT NULL,
        message NVARCHAR(500) NULL,
        picker_user_id BIGINT NOT NULL,
        checker_user_id BIGINT NOT NULL,
        scanned_at DATETIME2 NOT NULL,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,

        CONSTRAINT FK_wcse_header FOREIGN KEY (check_header_id)
            REFERENCES dbo.warehouse_check_headers(id) ON DELETE CASCADE,
        CONSTRAINT FK_wcse_picker FOREIGN KEY (picker_user_id)
            REFERENCES dbo.warehouse_users(id),
        CONSTRAINT FK_wcse_checker FOREIGN KEY (checker_user_id)
            REFERENCES dbo.warehouse_users(id)
    );

    CREATE INDEX IX_wcse_header_type
        ON dbo.warehouse_check_scan_errors(check_header_id, error_type);

    CREATE INDEX IX_wcse_picker_scanned
        ON dbo.warehouse_check_scan_errors(picker_user_id, scanned_at);
END;
GO

IF OBJECT_ID('dbo.supervisor_otps', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.supervisor_otps (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        supervisor_user_id BIGINT NOT NULL,
        code_hash CHAR(64) NOT NULL,
        code_encrypted NVARCHAR(MAX) NOT NULL,
        generated_at DATETIME2 NOT NULL,
        expires_at DATETIME2 NOT NULL,
        revoked_at DATETIME2 NULL,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,

        CONSTRAINT FK_sotp_supervisor FOREIGN KEY (supervisor_user_id)
            REFERENCES dbo.warehouse_users(id)
    );

    CREATE INDEX IX_sotp_hash ON dbo.supervisor_otps(code_hash);
    CREATE INDEX IX_sotp_expires ON dbo.supervisor_otps(expires_at);
END;
GO

IF OBJECT_ID('dbo.warehouse_check_overrides', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.warehouse_check_overrides (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        check_header_id BIGINT NOT NULL,
        check_detail_id BIGINT NOT NULL,
        supervisor_user_id BIGINT NOT NULL,
        checker_user_id BIGINT NOT NULL,
        otp_id BIGINT NOT NULL,
        reason_code NVARCHAR(50) NOT NULL,
        reason_note NVARCHAR(500) NULL,
        overridden_at DATETIME2 NOT NULL,
        created_at DATETIME2 NULL,
        updated_at DATETIME2 NULL,

        CONSTRAINT FK_wco_header FOREIGN KEY (check_header_id)
            REFERENCES dbo.warehouse_check_headers(id) ON DELETE CASCADE,
        CONSTRAINT FK_wco_detail FOREIGN KEY (check_detail_id)
            REFERENCES dbo.warehouse_check_details(id),
        CONSTRAINT FK_wco_supervisor FOREIGN KEY (supervisor_user_id)
            REFERENCES dbo.warehouse_users(id),
        CONSTRAINT FK_wco_checker FOREIGN KEY (checker_user_id)
            REFERENCES dbo.warehouse_users(id),
        CONSTRAINT FK_wco_otp FOREIGN KEY (otp_id)
            REFERENCES dbo.supervisor_otps(id)
    );

    CREATE INDEX IX_wco_header_time
        ON dbo.warehouse_check_overrides(check_header_id, overridden_at);
END;
GO
