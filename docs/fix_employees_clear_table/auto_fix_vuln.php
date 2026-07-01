<?php
/**
 * Auto-fix generator for Employees Clear Table Referential Integrity.
 *
 * Why: This script programmatically produces the fixed logic for bulk
 * employee deletion, ensuring that documentation and deployment artifacts
 * stay synchronized with the required fixing strategy.
 */

$fixedFile = __DIR__ . '/../fixed_files_vulnerability_employees_clear_table/fixed_files/delete_clear_table.php';

$content = <<<'PHP'
<?php
/**
 * Transactional clear-table delete for employees (tenant-scoped).
 *
 * Why: Employees have many dependencies (passwords, bookmarks, etc.) that
 * must be detached or deleted to maintain referential integrity. Reusing
 * the single-record delete helper ensures all application-level cleanup
 * rules are executed consistently for every record in the batch.
 */

/**
 * @param mysqli $conn Active database connection.
 * @param int $companyId The tenant ID to clear.
 * @return string|null Combined error message, or null when every row deleted successfully.
 */
function employees_clear_table_for_company(mysqli $conn, int $companyId): ?string
{
    if ($companyId <= 0) {
        return 'Invalid company scope for clear table.';
    }

    $idList = [];
    // Why: Use a prepared statement to prevent SQL injection and load IDs into an
    // array first to avoid keeping a result set open during subsequent per-row transactions.
    $stmt = mysqli_prepare($conn, 'SELECT id FROM employees WHERE company_id = ? AND is_hidden = 0');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rowId = (int)($row['id'] ?? 0);
            if ($rowId > 0) {
                $idList[$rowId] = $rowId;
            }
        }
        mysqli_stmt_close($stmt);
    } else {
        return 'Unable to prepare employee selection: ' . mysqli_error($conn);
    }

    $deleteErrors = [];
    // Why: Iterate through unique IDs and call the specialized single-delete helper.
    // This allows the system to handle individual Foreign Key blockers and
    // perform atomic detachment of child rows for each employee.
    foreach ($idList as $employeeId) {
        $deleteError = employees_delete_record($conn, $companyId, $employeeId);
        if ($deleteError !== null) {
            $deleteErrors[] = 'ID ' . $employeeId . ': ' . $deleteError;
        }
    }

    if ($deleteErrors !== []) {
        return implode(' ', $deleteErrors);
    }

    return null;
}
PHP;

if (file_put_contents($fixedFile, $content)) {
    echo "SUCCESS: Fixed version of delete_clear_table.php generated.\n";
} else {
    echo "ERROR: Failed to write fixed file.\n";
    exit(1);
}
