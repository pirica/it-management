<?php
/**
 * Enrich db/ sample-data table report with per-company live row counts (MySQL).
 *
 * Used by list_db_tables_sample_data_company.php only.
 */

if (!function_exists('itm_database_tables_sample_data_company_enrich')) {
    /**
     * @param array<string, mixed> $baseReport
     * @return array<string, mixed>
     */
    function itm_database_tables_sample_data_company_enrich(mysqli $conn, array $baseReport, int $companyId): array
    {
        require_once __DIR__ . '/itm_list_empty_tables.php';

        $tenantSummary = [
            'empty' => 0,
            'populated' => 0,
            'n/a' => 0,
            'error' => 0,
        ];

        $tables = [];
        foreach ($baseReport['tables'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $tableName = (string) ($row['table'] ?? '');
            $hasCompanyId = itm_table_has_column($conn, $tableName, 'company_id');
            $tenantRows = null;
            $tenantStatus = 'n/a';

            if ($hasCompanyId && $companyId > 0) {
                $tenantRows = itm_list_empty_tables_tenant_live_row_count($conn, $tableName, $companyId);
                if ($tenantRows < 0) {
                    $tenantStatus = 'error';
                    $tenantSummary['error']++;
                } elseif ($tenantRows === 0) {
                    $tenantStatus = 'empty';
                    $tenantSummary['empty']++;
                } else {
                    $tenantStatus = 'populated';
                    $tenantSummary['populated']++;
                }
            } else {
                $tenantSummary['n/a']++;
            }

            $row['tenant_rows'] = $tenantRows;
            $row['tenant_status'] = $tenantStatus;
            $row['has_company_id'] = $hasCompanyId;
            $tables[] = $row;
        }

        $baseReport['company_id'] = $companyId;
        $baseReport['tables'] = $tables;
        $baseReport['tenant_summary'] = $tenantSummary;

        return $baseReport;
    }
}

if (!function_exists('itm_database_tables_sample_data_company_report')) {
    /**
     * @return array<string, mixed>
     */
    function itm_database_tables_sample_data_company_report(
        mysqli $conn,
        string $schemaPath,
        string $samplePath,
        int $companyId
    ): array {
        $sampleLib = __DIR__ . '/itm_database_tables_sample_data_report.php';
        if (is_readable($sampleLib)) {
            require_once $sampleLib;
        }

        $base = itm_database_tables_sample_data_report($schemaPath, $samplePath);

        return itm_database_tables_sample_data_company_enrich($conn, $base, $companyId);
    }
}
