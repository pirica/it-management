<?php
/**
 * Employees Module - Index
 * 
 * Provides a comprehensive management interface for employee records.
 * Features:
 * - Secure CRUD operations
 * - Excel/CSV import with header mapping and validation
 * - Automatic duplicate detection across Email, Employee Code, and External ID
 * - Integrated system access management (permissions matrix)
 * - Department and status lookups
 */

require '../../config/config.php';
// Handle Excel/CSV database import requests from table-tools.js.
if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $itmImportRawBody = file_get_contents('php://input');
    $itmImportJsonBody = json_decode((string)$itmImportRawBody, true);
    if (is_array($itmImportJsonBody) && isset($itmImportJsonBody['import_excel_rows'])) {
        itm_handle_json_table_import($conn, 'employees', (int)($company_id ?? 0));
    }
}

require '../../includes/employee_system_access.php';

// Lazy-initialize required tables if missing
esa_ensure_table($conn);

/**
 * Escapes database identifiers
 */
function emp_escape_identifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

/**
 * Parses pasted delimited data (tab or comma) into rows
 */
function emp_parse_delimited_rows($content) {
    $lines = preg_split('/\r\n|\n|\r/', trim((string)$content));
    if (!$lines || count($lines) < 2) {
        return [];
    }

    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') { continue; }
        $delimiter = str_contains($line, "\t") ? "\t" : ',';
        $rows[] = str_getcsv($line, $delimiter);
    }
    return $rows;
}

/**
 * Maps varying import header names to internal canonical database columns
 */
function emp_canonical_header($header) {
    $normalized = strtolower(trim((string)$header));
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    $normalized = str_replace(['_', '-'], ' ', $normalized);

    $map = [
        'external id' => 'external_id',
        'employee code' => 'employee_code',
        'user name' => 'username',
        'username' => 'username',
        'display name' => 'display_name',
        'email' => 'email',
        'employee status' => 'raw_status_code',
        'status' => 'raw_status_code',
        'first name' => 'first_name',
        'last name' => 'last_name',
        'job code' => 'job_code',
        'title' => 'job_title',
        'comments' => 'comments',
        'comment' => 'comments',
        'termination date' => 'termination_date',
        'request date' => 'request_date',
        'requested by' => 'requested_by',
        'termination requested by' => 'termination_requested_by',
        'department name' => 'department_name'
    ];

    return $map[$normalized] ?? null;
}

/**
 * Resolves or creates an employment status ID from raw import codes (A, I, L, T)
 */
function emp_status_id_from_raw($conn, $rawStatus) {
    $status = strtoupper(trim((string)$rawStatus));
    $name = 'Active';
    if ($status === 'I') {
        $name = 'Inactive';
    } elseif ($status === 'L') {
        $name = 'On Leave';
    } elseif ($status === 'T') {
        $name = 'Terminated';
    }

    $nameEsc = mysqli_real_escape_string($conn, $name);
    $q = mysqli_query($conn, "SELECT id FROM employee_statuses WHERE name='{$nameEsc}' LIMIT 1");
    if ($q && mysqli_num_rows($q) === 1) {
        $row = mysqli_fetch_assoc($q);
        return (int)$row['id'];
    }

    if (mysqli_query($conn, "INSERT INTO employee_statuses (name) VALUES ('{$nameEsc}')")) {
        return (int)mysqli_insert_id($conn);
    }

    return 1;
}

/**
 * Cleanup legacy unique indexes to allow for duplicate flagging logic instead of hard errors
 */
function emp_drop_email_unique_if_exists($conn) {
    $legacyUniqueIndexes = [
        'uq_employees_email_per_company',
        'uq_employees_code_per_company'
    ];

    foreach ($legacyUniqueIndexes as $indexName) {
        $sql = "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'employees' AND index_name = '" . mysqli_real_escape_string($conn, $indexName) . "' LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if ($res && mysqli_num_rows($res) === 1) {
            mysqli_query($conn, 'ALTER TABLE employees DROP INDEX ' . emp_escape_identifier($indexName));
        }
    }
}

/**
 * Ensures the 'duplicate' column exists for UI flagging
 */
function emp_ensure_duplicate_column($conn) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM employees LIKE 'duplicate'");
    if ($res && mysqli_num_rows($res) === 0) {
        mysqli_query($conn, "ALTER TABLE employees ADD COLUMN `duplicate` TINYINT(1) NOT NULL DEFAULT 0 AFTER `id`");
    }
}

/**
 * Removes deprecated employees.active column if still present
 */
function emp_drop_active_column_if_exists($conn) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM employees LIKE 'active'");
    if ($res && mysqli_num_rows($res) === 1) {
        mysqli_query($conn, 'ALTER TABLE employees DROP COLUMN `active`');
    }
}

/**
 * Generates a helpful label for identify skipped rows during import
 */
function emp_import_identity_label($mapped) {
    $parts = [];
    if (!empty($mapped['display_name'])) { $parts[] = 'Name: ' . (string)$mapped['display_name']; }
    if (!empty($mapped['email'])) { $parts[] = 'Email: ' . (string)$mapped['email']; }
    if (!empty($mapped['employee_code'])) { $parts[] = 'Employee Code: ' . (string)$mapped['employee_code']; }
    if (!empty($mapped['external_id'])) { $parts[] = 'External ID: ' . (string)$mapped['external_id']; }
    if (!$parts) { return 'No identifying data'; }
    return implode(' | ', $parts);
}

/**
 * Generates tokens for identity matching (Email, Code, External ID)
 */
function emp_identifier_tokens($mapped) {
    $tokens = [];
    if (!empty($mapped['email'])) { $tokens[] = 'email:' . strtolower(trim((string)$mapped['email'])); }
    if (!empty($mapped['employee_code'])) { $tokens[] = 'employee_code:' . strtolower(trim((string)$mapped['employee_code'])); }
    if (!empty($mapped['external_id'])) { $tokens[] = 'external_id:' . strtolower(trim((string)$mapped['external_id'])); }
    sort($tokens);
    return $tokens;
}


/**
 * Runs a cross-check within the database to find and flag duplicate records
 */
function emp_recalculate_duplicates($conn, $company_id) {
    $cid = (int)$company_id;
    mysqli_query($conn, 'UPDATE employees SET duplicate=0 WHERE company_id=' . $cid);

    $duplicateConditions = [];
    foreach (['email', 'employee_code', 'external_id'] as $field) {
        $fieldEsc = emp_escape_identifier($field);
        $duplicateConditions[] = "(LOWER(TRIM(COALESCE(" . $fieldEsc . ", ''))) IN (SELECT ident FROM (SELECT LOWER(TRIM(" . $fieldEsc . ")) AS ident FROM employees WHERE company_id=" . $cid . " AND " . $fieldEsc . " IS NOT NULL AND TRIM(" . $fieldEsc . ") <> '' GROUP BY LOWER(TRIM(" . $fieldEsc . ")) HAVING COUNT(*) > 1) dups) AND " . $fieldEsc . " IS NOT NULL AND TRIM(" . $fieldEsc . ") <> '')";
    }

    if (!empty($duplicateConditions)) {
        $sql = 'UPDATE employees SET duplicate=1 WHERE company_id=' . $cid . ' AND (' . implode(' OR ', $duplicateConditions) . ')';
        mysqli_query($conn, $sql);
    }

    $countRes = mysqli_query($conn, 'SELECT COUNT(*) AS count FROM employees WHERE company_id=' . $cid . ' AND duplicate=1');
    return (int)($countRes ? (mysqli_fetch_assoc($countRes)['count'] ?? 0) : 0);
}

/**
 * Collects actual duplicate values to show specific reasons in the UI
 */
function emp_collect_duplicate_values($conn, $company_id) {
    $cid = (int)$company_id;
    $maps = ['email' => [], 'employee_code' => [], 'external_id' => []];

    foreach (array_keys($maps) as $field) {
        $fieldEsc = emp_escape_identifier($field);
        $sql = 'SELECT LOWER(TRIM(' . $fieldEsc . ')) AS ident FROM employees WHERE company_id=' . $cid . ' AND ' . $fieldEsc . " IS NOT NULL AND TRIM(" . $fieldEsc . ") <> '' GROUP BY LOWER(TRIM(" . $fieldEsc . ')) HAVING COUNT(*) > 1';
        $res = mysqli_query($conn, $sql);
        while ($res && ($r = mysqli_fetch_assoc($res))) {
            $ident = (string)($r['ident'] ?? '');
            if ($ident !== '') { $maps[$field][$ident] = true; }
        }
    }
    return $maps;
}

/**
 * Identifies why a specific row was flagged as a duplicate
 */
function emp_duplicate_reasons_for_row($row, $duplicateValueMaps) {
    $reasons = [];
    foreach (['email' => 'Email', 'employee_code' => 'Employee Code', 'external_id' => 'External ID'] as $field => $label) {
        $value = strtolower(trim((string)($row[$field] ?? '')));
        if ($value !== '' && !empty($duplicateValueMaps[$field][$value])) {
            $reasons[] = $label;
        }
    }
    return $reasons;
}

$messages = [];
$errors = [];
$skippedDetails = [];
$csrfToken = itm_get_csrf_token();
emp_drop_active_column_if_exists($conn);

// --- ACTION: DELETE ALL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'delete_all_employees')) {
    itm_require_post_csrf();
    $deleteAllSql = 'DELETE FROM employees WHERE company_id=' . (int)$company_id;
    if (mysqli_query($conn, $deleteAllSql)) {
        if (mysqli_query($conn, 'ALTER TABLE employees AUTO_INCREMENT = 1')) {
            $messages[] = 'All employees were deleted for this company, and ID numbering was reset.';
        } else {
            $messages[] = 'All employees were deleted for this company.';
            $errors[] = 'Employees were deleted, but resetting ID numbering failed.';
        }
    } else {
        $errors[] = 'Could not delete all employees. Please try again.';
    }
}

// --- ACTION: IMPORT EMPLOYEES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'import_employees')) {
    itm_require_post_csrf();
    emp_ensure_duplicate_column($conn);
    emp_drop_email_unique_if_exists($conn);
    $payload = trim((string)($_POST['import_payload'] ?? ''));
    $rows = [];

    // Parse JSON payload (from XLSX library) or raw text
    if ($payload !== '') {
        $decoded = json_decode($payload, true);
        if (is_array($decoded)) { $rows = $decoded; }
    }
    if (!$rows && !empty($_POST['import_text'])) {
        $rows = emp_parse_delimited_rows((string)$_POST['import_text']);
    }

    if (!$rows || count($rows) < 2) {
        $errors[] = 'No importable rows were found. Upload an Excel/CSV file or paste tabular content.';
    } else {
        // Map headers to DB columns
        $headers = array_map('emp_canonical_header', $rows[0]);
        $validIdx = [];
        foreach ($headers as $i => $field) {
            if ($field !== null) { $validIdx[$i] = $field; }
        }

        // Validate that we have at least one identifier
        if (!in_array('email', $headers, true) && !in_array('employee_code', $headers, true) && !in_array('external_id', $headers, true)) {
            $errors[] = 'Include at least one unique identifier column: Email, Employee Code, or External ID.';
        }

        if (empty($errors)) {
            $created = 0; $updated = 0; $skipped = 0; $deleted = 0;
            $matchedIds = [];
            $existingIndex = [];
            $processedIdMap = [];
            $importIdentitySeen = [];

            // Build an in-memory index of existing employees for fast lookup during import
            $existingRowMap = [];
            $existingSql = 'SELECT id,email,employee_code,external_id,duplicate,first_name,last_name,username,display_name,job_code,job_title,comments,raw_status_code,termination_date,request_date,requested_by,termination_requested_by,department_id,employment_status_id FROM employees WHERE company_id=' . (int)$company_id;
            $existingRes = mysqli_query($conn, $existingSql);
            while ($existingRes && ($existingRow = mysqli_fetch_assoc($existingRes))) {
                $existingId = (int)($existingRow['id'] ?? 0);
                $existingRowMap[$existingId] = $existingRow;
                $tokens = emp_identifier_tokens($existingRow);
                foreach ($tokens as $token) {
                    if (!isset($existingIndex[$token])) { $existingIndex[$token] = []; }
                    $existingIndex[$token][] = $existingId;
                }
            }

            // Process each row in the import file
            foreach (array_slice($rows, 1) as $rowOffset => $row) {
                $sourceRowNumber = $rowOffset + 2;
                $mapped = [
                    'company_id' => (int)$company_id, 'first_name' => '', 'last_name' => '', 'email' => '', 'employee_code' => '',
                    'external_id' => '', 'username' => '', 'display_name' => '', 'job_code' => '', 'job_title' => '',
                    'comments' => '', 'raw_status_code' => '', 'termination_date' => '', 'request_date' => '', 'requested_by' => '',
                    'termination_requested_by' => '', 'department_name' => '', 'employment_status_id' => 1, 'duplicate' => 0
                ];

                foreach ($validIdx as $idx => $field) {
                    $mapped[$field] = trim((string)($row[$idx] ?? ''));
                }

                // Auto-fill names
                if ($mapped['display_name'] === '' && ($mapped['first_name'] !== '' || $mapped['last_name'] !== '')) {
                    $mapped['display_name'] = trim($mapped['first_name'] . ' ' . $mapped['last_name']);
                }
                if ($mapped['first_name'] === '' && $mapped['display_name'] !== '') {
                    $parts = preg_split('/\s+/', $mapped['display_name']);
                    $mapped['first_name'] = $parts[0] ?? '';
                    if ($mapped['last_name'] === '') { $mapped['last_name'] = trim(implode(' ', array_slice($parts, 1))); }
                }

                // Skip completely empty rows
                if ($mapped['first_name'] === '' && $mapped['last_name'] === '' && $mapped['email'] === '' && $mapped['employee_code'] === '' && $mapped['external_id'] === '') {
                    $skipped += 1; $skippedDetails[] = ['row' => $sourceRowNumber, 'reason' => 'Missing data.', 'identity' => 'No identifying data'];
                    continue;
                }

                // Map status flag
                $mapped['employment_status_id'] = emp_status_id_from_raw($conn, $mapped['raw_status_code']);

                // Auto-map departments
                if (!empty($mapped['department_name'])) {
                    $depNameEsc = mysqli_real_escape_string($conn, (string)$mapped['department_name']);
                    $depSql = "SELECT id FROM departments WHERE company_id=" . (int)$company_id . " AND name='" . $depNameEsc . "' LIMIT 1";
                    $depRes = mysqli_query($conn, $depSql);
                    if ($depRes && mysqli_num_rows($depRes) === 1) {
                        $mapped['department_id'] = (int)(mysqli_fetch_assoc($depRes)['id'] ?? 0);
                    } else {
                        if (mysqli_query($conn, "INSERT INTO departments (company_id,name,active) VALUES (" . (int)$company_id . ", '" . $depNameEsc . "', 1)")) {
                            $mapped['department_id'] = (int)mysqli_insert_id($conn);
                        }
                    }
                }

                // Check for duplicates within the file itself
                $identifierTokens = emp_identifier_tokens($mapped);
                if (!$identifierTokens) {
                    $skipped += 1; $skippedDetails[] = ['row' => $sourceRowNumber, 'reason' => 'No unique identifier.', 'identity' => emp_import_identity_label($mapped)];
                    continue;
                }

                $isDuplicateInFile = false;
                foreach ($identifierTokens as $token) {
                    if (($importIdentitySeen[$token] ?? 0) > 0) { $isDuplicateInFile = true; }
                }
                foreach ($identifierTokens as $token) {
                    $importIdentitySeen[$token] = ($importIdentitySeen[$token] ?? 0) + 1;
                }

                // Attempt to find an existing record in the database
                $existingId = 0;
                foreach ($identifierTokens as $token) {
                    foreach ($existingIndex[$token] ?? [] as $candidateId) {
                        if (!isset($processedIdMap[$candidateId])) {
                            $existingId = (int)$candidateId;
                            break 2;
                        }
                    }
                }

                // Prepare values for SQL
                $columns = ['company_id','duplicate','first_name','last_name','email','employee_code','external_id','username','display_name','job_code','job_title','comments','raw_status_code','termination_date','request_date','requested_by','termination_requested_by','department_id','employment_status_id'];
                $mapped['duplicate'] = $isDuplicateInFile ? 1 : 0;
                $values = [];
                foreach ($columns as $col) {
                    $value = $mapped[$col] ?? null;
                    if ($value === '' || $value === null) { $values[$col] = 'NULL'; }
                    elseif (in_array($col, ['company_id','employment_status_id','duplicate'], true)) { $values[$col] = (string)(int)$value; }
                    else { $values[$col] = "'" . mysqli_real_escape_string($conn, (string)$value) . "'"; }
                }

                if ($existingId > 0) {
                    // Update if record changed
                    $hasChanges = false;
                    $existingCurrent = $existingRowMap[$existingId] ?? [];
                    foreach ($columns as $col) {
                        if ($col === 'company_id') continue;
                        $incomingNorm = ($mapped[$col] === '') ? null : $mapped[$col];
                        $existingNorm = ($existingCurrent[$col] === '') ? null : $existingCurrent[$col];
                        if ($incomingNorm !== $existingNorm) { $hasChanges = true; break; }
                    }

                    if ($hasChanges) {
                        $sets = [];
                        foreach ($columns as $col) { if ($col !== 'company_id') { $sets[] = emp_escape_identifier($col) . '=' . $values[$col]; } }
                        $sql = 'UPDATE employees SET ' . implode(',', $sets) . ' WHERE id=' . $existingId . ' AND company_id=' . (int)$company_id . ' LIMIT 1';
                        if (mysqli_query($conn, $sql)) {
                            $updated += 1; $matchedIds[] = $existingId; $processedIdMap[$existingId] = true;
                        }
                    } else {
                        $skipped += 1; $matchedIds[] = $existingId; $processedIdMap[$existingId] = true;
                    }
                } else {
                    // Insert new record
                    $sql = 'INSERT INTO employees (' . implode(',', array_map('emp_escape_identifier', $columns)) . ') VALUES (' . implode(',', array_values($values)) . ')';
                    if (mysqli_query($conn, $sql)) {
                        $created += 1; $newId = (int)mysqli_insert_id($conn);
                        $matchedIds[] = $newId; $processedIdMap[$newId] = true;
                    }
                }
            }

            // Sync duplicate flags and handle deletions of missing records (full sync)
            emp_recalculate_duplicates($conn, $company_id);
            if (!empty($matchedIds)) {
                $matchedIds = array_values(array_unique(array_map('intval', $matchedIds)));
                $deleteSql = 'DELETE FROM employees WHERE company_id=' . (int)$company_id . ' AND id NOT IN (' . implode(',', $matchedIds) . ')';
                if (mysqli_query($conn, $deleteSql)) { $deleted = (int)mysqli_affected_rows($conn); }
            }

            $messages[] = "Import complete: {$created} created, {$updated} updated, {$deleted} removed, {$skipped} skipped.";
        }
    }
}

// --- DATA PREPARATION FOR UI ---
emp_ensure_duplicate_column($conn);
$where = ' WHERE e.company_id=' . (int)$company_id;
$showDuplicatesOnly = (($_GET['show'] ?? '') === 'duplicates');
if ($showDuplicatesOnly) { $where .= ' AND e.duplicate=1'; }

// Recalculate duplicate markers before display
$duplicatesCount = emp_recalculate_duplicates($conn, $company_id);
$duplicateValueMaps = emp_collect_duplicate_values($conn, $company_id);

// Determine dynamic column order and visibility
$columnsRes = mysqli_query($conn, 'SHOW COLUMNS FROM employees');
$columns = []; $columnTypes = [];
while ($columnsRes && ($c = mysqli_fetch_assoc($columnsRes))) {
    $columns[] = $c['Field'];
    $columnTypes[$c['Field']] = strtolower((string)($c['Type'] ?? ''));
}

$preferredOrder = ['id','duplicate','external_id','username','display_name','email','raw_status_code','first_name','last_name','job_code','job_title','department_id','request_date','requested_by','termination_requested_by','termination_date','employment_status_id','comments'];
$hiddenColumns = ['company_id','employee_code','location','phone','location_id','user_id'];
$hiddenColumns = array_merge($hiddenColumns, array_keys(esa_ability_fields()));
$columns = array_values(array_filter($columns, function ($c) use ($hiddenColumns) { return !in_array($c, $hiddenColumns, true); }));

usort($columns, function ($a, $b) use ($preferredOrder) {
    $ia = array_search($a, $preferredOrder, true); $ib = array_search($b, $preferredOrder, true);
    return (($ia === false) ? 999 : $ia) <=> (($ib === false) ? 999 : $ib) ?: strcmp($a, $b);
});

// Handling sort and search
$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $columns, true)) { $sort = 'id'; }
if (!in_array($dir, ['ASC', 'DESC'], true)) { $dir = 'DESC'; }

$searchRaw = trim((string)($_GET['search'] ?? ''));
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchValue = mysqli_real_escape_string($conn, $searchPattern);
    $searchConditions = [];
    foreach ($columns as $col) { $searchConditions[] = "CAST(e.`" . str_replace('`', '``', $col) . "` AS CHAR) LIKE '" . $searchValue . "'"; }
    if (!empty($searchConditions)) { $where .= ' AND (' . implode(' OR ', $searchConditions) . ')'; }
}

$sortSql = 'e.`' . str_replace('`', '``', $sort) . '` ' . $dir;
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countSql = 'SELECT COUNT(*) AS total
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN employee_statuses es ON es.id = e.employment_status_id
             LEFT JOIN employee_system_access esa ON esa.company_id = e.company_id AND esa.employee_id = e.id'
             . $where;
$countResult = mysqli_query($conn, $countSql);
$countRow = $countResult ? mysqli_fetch_assoc($countResult) : null;
$totalRows = (int)($countRow['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Final Fetch including lookups and system access data
$rows = mysqli_query(
    $conn,
    'SELECT e.*, d.name AS department_name, es.name AS employment_status_name,
            esa.network_access, esa.micros_emc, esa.opera_username, esa.micros_card, esa.pms_id, esa.synergy_mms,
            esa.hu_the_lobby, esa.navision, esa.onq_ri, esa.birchstreet, esa.delphi, esa.omina, esa.vingcard_system,
            esa.digital_rev, esa.office_key_card
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     LEFT JOIN employee_statuses es ON es.id = e.employment_status_id
     LEFT JOIN employee_system_access esa ON esa.company_id = e.company_id AND esa.employee_id = e.id'
    . $where .
    ' ORDER BY ' . $sortSql . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset
);

/**
 * Humanizes labels specifically for the employee module
 */
function emp_label($field) {
    if ($field === 'department_id') return 'Department Name';
    if ($field === 'employment_status_id') return 'Employment Status';
    if ($field === 'external_id') return 'External ID';
    return ucwords(str_replace('_', ' ', trim((string)$field)));
}

/**
 * Build clean URL queries
 */
function emp_build_query($params) {
    $normalized = [];
    foreach ($params as $key => $value) { if ($value !== null && $value !== '') { $normalized[$key] = $value; } }
    return http_build_query($normalized);
}

$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: '👤 Employees';
$newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <!-- NOTIFICATION AREA -->
            <?php foreach ($errors as $error): ?><div class="alert alert-error"><?php echo sanitize($error); ?></div><?php endforeach; ?>
            <?php foreach ($messages as $msg): ?><div class="alert alert-success"><?php echo sanitize($msg); ?></div><?php endforeach; ?>
            
            <?php if (!empty($skippedDetails)): ?>
                <div class="card" style="margin-bottom:16px;border:1px solid #f4c2c2;background:#fff6f6;">
                    <h4 style="margin:0 0 8px 0;">Skipped Rows during Import</h4>
                    <ul style="margin:0;padding-left:20px;font-size:13px;">
                        <?php foreach (array_slice($skippedDetails, 0, 10) as $detail): ?>
                            <li>Row <?php echo (int)($detail['row'] ?? 0); ?> — <?php echo sanitize((string)($detail['reason'] ?? 'Skipped')); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- HEADER ACTIONS -->
            <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px;min-height:40px;flex-wrap:wrap;">
                <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                    <a href="create.php" class="btn btn-primary">➕</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?><a href="create.php" class="btn btn-primary">➕</a><?php endif; ?>
                    <?php if ($showDuplicatesOnly): ?>
                        <a href="index.php?<?php echo sanitize(emp_build_query(['search' => $searchRaw])); ?>" class="btn btn-sm">Show All</a>
                    <?php else: ?>
                        <a href="index.php?<?php echo sanitize(emp_build_query(['show' => 'duplicates', 'search' => $searchRaw])); ?>" class="btn btn-sm">⚠️ Duplicates (<?php echo (int)$duplicatesCount; ?>)</a>
                    <?php endif; ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete ALL employees for this company? This cannot be undone.');">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>"><input type="hidden" name="action" value="delete_all_employees">
                        <button type="submit" class="btn btn-danger">✖ Delete ALL</button>
                    </form>
                </div>
            </div>

            <!-- SEARCH FILTER -->
            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <?php if ($showDuplicatesOnly): ?><input type="hidden" name="show" value="duplicates"><?php endif; ?>
                    <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>"><input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                    <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                        <label for="employeeSearch">Search (all fields)</label>
                        <input type="text" id="employeeSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Type to search...">
                    </div>
                    <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php" class="btn">🔙</a>
                    </div>
                </form>
            </div>

            <!-- IMPORT SECTION -->
            <div class="card" style="margin-bottom:16px;">
                <h3 style="margin-top:0;">Import from Excel / CSV</h3>
                <form method="POST" id="employeeImportForm">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>"><input type="hidden" name="action" value="import_employees"><input type="hidden" name="import_payload" id="employeeImportPayload" value="">
                    <div class="form-group">
                        <label>Upload file (.xlsx, .xls, .csv)</label>
                        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                            <input type="file" id="employeeImportFile" accept=".xlsx,.xls,.csv" />
                            <button type="submit" class="btn btn-primary">📥 Import Employees</button>
                        </div>
                    </div>
                    <div class="form-group"><label>Or paste tabular data</label><textarea name="import_text" id="employeeImportText" rows="2" placeholder="Paste from Excel..."></textarea></div>
                </form>
            </div>

            <!-- DATA TABLE -->
            <div class="card" style="overflow:auto;">
                <table>
                    <thead>
                    <tr>
                        <?php foreach ($columns as $col): ?>
                            <?php $nextDir = ($sort === $col && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?<?php echo sanitize(emp_build_query(['sort' => $col, 'dir' => $nextDir, 'show' => $showDuplicatesOnly ? 'duplicates' : null, 'search' => $searchRaw])); ?>" style="text-decoration:none;color:inherit;"><?php echo sanitize(emp_label($col)); ?><?php if ($sort === $col): ?><?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows && mysqli_num_rows($rows) > 0): while ($row = mysqli_fetch_assoc($rows)): ?>
                        <?php $duplicateReasons = emp_duplicate_reasons_for_row($row, $duplicateValueMaps); ?>
                        <tr<?php echo ((int)($row['duplicate'] ?? 0) === 1) ? ' style="background:#ffe8e8;border-left:4px solid #d93025;"' : ''; ?>>
                            <?php foreach ($columns as $col): ?>
                                <td>
                                    <?php if ($col === 'email' && !empty($row[$col])): ?>
                                        <a href="mailto:<?php echo sanitize((string)$row[$col]); ?>" data-outlook-link="1" data-outlook-href="ms-outlook://compose?to=<?php echo sanitize((string)$row[$col]); ?>"><?php echo sanitize((string)$row[$col]); ?></a>
                                    <?php elseif ($col === 'department_id'): ?><?php echo sanitize((string)($row['department_name'] ?? '')); ?>
                                    <?php elseif ($col === 'employment_status_id'): ?><?php echo sanitize((string)($row['employment_status_name'] ?? '')); ?>
                                    <?php elseif (str_starts_with($columnTypes[$col] ?? '', 'tinyint(1)')): ?>
                                        <?php if ($col === 'duplicate'): ?>
                                            <?php echo ((int)($row[$col] ?? 0) === 1) ? '⚠️ Duplicate (' . sanitize(implode(', ', $duplicateReasons)) . ')' : '—'; ?>
                                        <?php else: ?>
                                            <?php echo ((int)($row[$col] ?? 0) === 1) ? '✅' : '❌'; ?>
                                        <?php endif; ?>
                                    <?php elseif ($col === 'comments' && trim((string)($row[$col] ?? '')) !== ''): ?><a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                    <?php else: ?><?php echo sanitize((string)$row[$col]); ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td>
                                <div class="itm-actions-wrap">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">🔎</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                    <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this employee?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>"><input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="<?php echo count($columns) + 1; ?>" style="text-align:center;">No employees found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($totalPages > 1): ?>
                    <div style="display:flex;justify-content:center;gap:8px;margin-top:14px;flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="?<?php echo sanitize(emp_build_query(['sort' => $sort, 'dir' => $dir, 'show' => $showDuplicatesOnly ? 'duplicates' : null, 'search' => $searchRaw, 'page' => $page - 1])); ?>">« Prev</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.85;">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="?<?php echo sanitize(emp_build_query(['sort' => $sort, 'dir' => $dir, 'show' => $showDuplicatesOnly ? 'duplicates' : null, 'search' => $searchRaw, 'page' => $page + 1])); ?>">Next »</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
/**
 * Import Logic - Handles File Reading and CSV/Excel Parsing
 */
(function () {
    const fileInput = document.getElementById('employeeImportFile');
    const payloadInput = document.getElementById('employeeImportPayload');
    const textInput = document.getElementById('employeeImportText');

    function parseCsv(text) {
        const rows = []; let row = []; let cell = ''; let inQuotes = false;
        for (let i = 0; i < text.length; i += 1) {
            const char = text[i]; const next = text[i + 1];
            if (inQuotes) {
                if (char === '"' && next === '"') { cell += '"'; i += 1; }
                else if (char === '"') { inQuotes = false; }
                else { cell += char; }
            } else if (char === '"') { inQuotes = true; }
            else if (char === ',') { row.push(cell.trim()); cell = ''; }
            else if (char === '\n' || char === '\r') {
                if (char === '\r' && next === '\n') i += 1;
                row.push(cell.trim()); if (row.some((v) => v !== '')) rows.push(row);
                row = []; cell = '';
            } else { cell += char; }
        }
        if (cell.length || row.length) { row.push(cell.trim()); if (row.some((v) => v !== '')) rows.push(row); }
        return rows;
    }

    if (!fileInput) return;

    fileInput.addEventListener('change', () => {
        const file = fileInput.files && fileInput.files[0];
        if (!file) { payloadInput.value = ''; return; }
        const extension = (file.name.split('.').pop() || '').toLowerCase();
        const reader = new FileReader();

        if (extension === 'csv') {
            reader.onload = () => { payloadInput.value = JSON.stringify(parseCsv(reader.result)); textInput.value = ''; };
            reader.readAsText(file);
        } else if (window.XLSX && (extension === 'xlsx' || extension === 'xls')) {
            reader.onload = () => {
                const workbook = window.XLSX.read(new Uint8Array(reader.result), { type: 'array' });
                payloadInput.value = JSON.stringify(window.XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]], { header: 1, defval: '' }));
                textInput.value = '';
            };
            reader.readAsArrayBuffer(file);
        } else {
            alert('Unsupported file type.'); fileInput.value = '';
        }
    });

    document.addEventListener('click', function (event) {
        const link = event.target.closest('a[data-outlook-link="1"]');
        if (link) { window.location.href = link.getAttribute('data-outlook-href'); }
    });
})();
</script>
</body>
</html>
