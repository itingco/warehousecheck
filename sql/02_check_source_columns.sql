-- Jalankan di as_ingco dan as_smi untuk memastikan field target tersedia.

SELECT
    c.name AS ColumnName,
    t.name AS DataType,
    c.max_length,
    c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('dbo.AR_Invoices')
  AND c.name IN ('CheckedBy', 'CheckDateTime')
ORDER BY c.name;
GO

-- Jika BELUM ada, gunakan ini dengan hati-hati:
-- ALTER TABLE dbo.AR_Invoices ADD CheckedBy NVARCHAR(120) NULL;
-- ALTER TABLE dbo.AR_Invoices ADD CheckDateTime DATETIME2 NULL;
