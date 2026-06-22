<?php
/**
 * Force-delete a tenant company and all rows in tables scoped by company_id.
 *
 * Why: Repro scripts and PHPUnit teardown need the same destructive cleanup as
 * scripts/force_delete_company.php without loading browser/CLI entry logic.
 */

if (!function_exists('itm_force_delete_company')) {
    function itm_force_delete_company(mysqli $conn, int $companyId): string
    {
        if ($companyId <= 0) {
            return 'Error: Invalid company ID.';
        }

        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS = 0');

            $schemaName = mysqli_real_escape_string($conn, (string)DB_NAME);
            $tablesQuery = "SELECT TABLE_NAME
                            FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = '{$schemaName}'
                            AND COLUMN_NAME = 'company_id'";
            $tablesResult = mysqli_query($conn, $tablesQuery);
            $tablesDeleted = [];

            while ($tablesResult && ($row = mysqli_fetch_assoc($tablesResult))) {
                $tableName = $row['TABLE_NAME'];
                if (!itm_is_safe_identifier($tableName)) {
                    continue;
                }
                $deleteSql = "DELETE FROM `$tableName` WHERE `company_id` = ?";
                $stmt = mysqli_prepare($conn, $deleteSql);
                if (!$stmt) {
                    throw new RuntimeException(mysqli_error($conn));
                }
                mysqli_stmt_bind_param($stmt, 'i', $companyId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $tablesDeleted[] = $tableName;
            }

            $deleteCompanySql = 'DELETE FROM `companies` WHERE `id` = ?';
            $stmt = mysqli_prepare($conn, $deleteCompanySql);
            if (!$stmt) {
                throw new RuntimeException(mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS = 1');
            mysqli_commit($conn);

            return 'Successfully deleted company ID ' . $companyId . ' and records from ' . count($tablesDeleted) . ' related tables.';
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS = 1');

            return 'Error during deletion: ' . $e->getMessage();
        }
    }
}
