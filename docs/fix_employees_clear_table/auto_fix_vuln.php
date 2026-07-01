<?php
/**
 * Auto-fix script for Employees Clear Table Vulnerability
 */
$fixedFile = __DIR__ . '/../fixed_files_vulnerability_employees_clear_table/fixed_files/delete_clear_table.php';
$content = <<<'PHP'
<?php
/**
 * Fixed clear table logic for employees.
 */
function employees_clear_table_for_company(mysqli $conn, int $companyId): ?string {
    if ($companyId <= 0) return 'Invalid company scope for clear table.';
    $idList = [];
    $listResult = mysqli_query($conn, 'SELECT id FROM employees WHERE company_id = ' . (int)$companyId . ' AND is_hidden = 0');
    if ($listResult === false) return 'Unable to load employee records: ' . mysqli_error($conn);
    while ($listRow = mysqli_fetch_assoc($listResult)) {
        $rowId = (int)($listRow['id'] ?? 0);
        if ($rowId > 0) $idList[$rowId] = $rowId;
    }
    $deleteErrors = [];
    foreach ($idList as $employeeId) {
        $deleteError = employees_delete_record($conn, $companyId, $employeeId);
        if ($deleteError !== null) $deleteErrors[] = 'ID ' . $employeeId . ': ' . $deleteError;
    }
    return $deleteErrors !== [] ? implode(' ', $deleteErrors) : null;
}
PHP;
file_put_contents($fixedFile, $content);
echo "Generated fixed file.\n";
