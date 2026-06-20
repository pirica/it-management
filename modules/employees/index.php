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
itm_require_admin($conn, $_SESSION['employee_id'] ?? 0);
// Handle Excel/CSV database import requests from table-tools.js.
if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $itmImportRawBody = (string)@file_get_contents('php://input');
    $itmImportJsonBody = json_decode((string)$itmImportRawBody, true);
    if (is_array($itmImportJsonBody) && isset($itmImportJsonBody['import_excel_rows'])) {
        itm_handle_json_table_import($conn, 'employees', (int)($company_id ?? 0));
    }
}

require '../../includes/employee_system_access.php';
require_once '../../includes/employee_profile_photo.php';
require_once '../../includes/itm_employees_auth_sensitive_fields.php';
require_once '../../includes/itm_employees_hidden_accounts.php';
require_once '../../includes/itm_employees_search.php';

// Lazy-initialize required tables if missing
esa_ensure_table($conn);

/**
 * Escapes database identifiers
 */
if (!function_exists("emp_escape_identifier")) {
function emp_escape_identifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}
}

/**
 * Parses pasted delimited data (tab or comma) into rows
 */
if (!function_exists("emp_parse_delimited_rows")) {
function emp_parse_delimited_rows($content) {
    $lines = preg_split('/\r\n|\n|\r/', trim((string)$content));
    if (!$lines || count($lines) < 2) {
        return [];
    }

    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') { continue; }
        // Handle tab-separated data (common when pasting from Excel)
        if (str_contains($line, "\t")) {
            $rows[] = explode("\t", $line);
        } else {
            $rows[] = str_getcsv($line, ',');
        }
    }
    return $rows;
}
}

/**
 * Maps varying import header names to internal canonical database columns
 */
if (!function_exists("emp_canonical_header")) {
function emp_canonical_header($header) {
    $normalized = strtolower(trim((string)$header));
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    $normalized = str_replace(['_', '-'], ' ', $normalized);

    $map = [
        'hilton id' => 'external_id',
        'external id' => 'external_id',
        'employee code' => 'employee_code',
        'user name' => 'username',
        'username' => 'username',
        'display name' => 'display_name',
        'work email' => 'work_email',
        'email' => 'work_email',
        'personal email' => 'personal_email',
        'mobile phone' => 'mobile_phone',
        'external number' => 'external_number',
        'work phone' => 'external_number',
        'dect' => 'dect',
        'deck' => 'dect',
        'extension' => 'extension',
        'on contacts' => 'on_contacts',
        'employee status' => 'raw_status_code',
        'raw status code' => 'raw_status_code',
        'status' => 'raw_status_code',
        'first name' => 'first_name',
        'last name' => 'last_name',
        'job code' => 'job_code',
        'title' => 'employee_position_id',
        'job title' => 'employee_position_id',
        'comments' => 'comments',
        'comment' => 'comments',
        'termination date' => 'termination_date',
        'request date' => 'request_date',
        'start date' => 'start_date',
        'admission date' => 'start_date',
        'employee type' => 'employee_type_id',
        'requested by' => 'requested_by',
        'termination requested by' => 'termination_requested_by',
        'department name' => 'department_name',
        'it location' => 'location_id',
        'location' => 'location_id',
        'location name' => 'location_id',
        'on orgchart' => 'on_orgchart',
        'on org chart' => 'on_orgchart',
        'position title' => 'employee_position_id',
        'reports to' => 'reports_to',
        'employment status' => 'employment_status_id',
        'workstation mode' => 'workstation_mode_id',
        'assignment type' => 'assignment_type_id',
        'role' => 'role_id',
        'role name' => 'role_id',
        'employee role' => 'role_id',
        'access level' => 'access_level_id',
        'access level name' => 'access_level_id',
        'office key card department id' => 'office_key_card_department_id',
        'id' => 'id',
        'id▼' => 'id'
    ];

    return $map[$normalized] ?? null;
}
}

/**
 * Resolves or creates an employment status ID from raw import codes (A, I, L, T)
 */
if (!function_exists("emp_status_id_from_raw")) {
function emp_status_id_from_raw($conn, $rawStatus) {
    $status = strtoupper(trim((string)$rawStatus));
    $name = 'Active';
    if ($status === 'I' || $status === 'INACTIVE') {
        $name = 'Inactive';
    } elseif ($status === 'L' || $status === 'ON LEAVE') {
        $name = 'On Leave';
    } elseif ($status === 'T' || $status === 'TERMINATED') {
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
}

/**
 * Cleanup legacy unique indexes to allow for duplicate flagging logic instead of hard errors
 */
if (!function_exists("emp_drop_email_unique_if_exists")) {
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
}

if (!function_exists("emp_ensure_is_hidden_column")) {
function emp_ensure_is_hidden_column($conn) {
    return itm_employees_ensure_is_hidden_column($conn);
}
}

/**
 * Ensures the 'duplicate' column exists for UI flagging
 */
if (!function_exists("emp_ensure_duplicate_column")) {
function emp_ensure_duplicate_column($conn) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM employees LIKE 'duplicate'");
    if ($res && mysqli_num_rows($res) === 0) {
        mysqli_query($conn, "ALTER TABLE employees ADD COLUMN `duplicate` TINYINT(1) NOT NULL DEFAULT 0 AFTER `id`");
    }
}
}

/**
 * Removes deprecated employees.active column if still present
 */
if (!function_exists("emp_drop_active_column_if_exists")) {
function emp_drop_active_column_if_exists($conn) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM employees LIKE 'active'");
    if ($res && mysqli_num_rows($res) === 1) {
        mysqli_query($conn, 'ALTER TABLE employees DROP COLUMN `active`');
    }
}
}

/**
 * Generates a helpful label for identify skipped rows during import
 */
if (!function_exists("emp_import_identity_label")) {
function emp_import_identity_label($mapped) {
    $parts = [];
    if (!empty($mapped['display_name'])) { $parts[] = 'Name: ' . (string)$mapped['display_name']; }
    if (!empty($mapped['work_email'])) { $parts[] = 'Work Email: ' . (string)$mapped['work_email']; }
    if (!empty($mapped['personal_email'])) { $parts[] = 'Personal Email: ' . (string)$mapped['personal_email']; }
    if (!empty($mapped['employee_code'])) { $parts[] = 'Employee Code: ' . (string)$mapped['employee_code']; }
    if (!empty($mapped['external_id'])) { $parts[] = 'External ID: ' . (string)$mapped['external_id']; }
    if (!$parts) { return 'No identifying data'; }
    return implode(' | ', $parts);
}
}

/**
 * Generates tokens for identity matching (Email, Code, External ID)
 */
if (!function_exists("emp_identifier_tokens")) {
function emp_identifier_tokens($mapped) {
    $tokens = [];
    if (!empty($mapped['work_email'])) { $tokens[] = 'work_email:' . strtolower(trim((string)$mapped['work_email'])); }
    if (!empty($mapped['personal_email'])) { $tokens[] = 'personal_email:' . strtolower(trim((string)$mapped['personal_email'])); }
    if (!empty($mapped['employee_code'])) { $tokens[] = 'employee_code:' . strtolower(trim((string)$mapped['employee_code'])); }
    if (!empty($mapped['external_id'])) { $tokens[] = 'external_id:' . strtolower(trim((string)$mapped['external_id'])); }
    sort($tokens);
    return $tokens;
}
}

/**
 * Determines if an email address is likely personal or corporate
 */
if (!function_exists("emp_is_personal_email")) {
function emp_is_personal_email($email) {
    $personalDomains = [
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com',
        'aol.com', 'msn.com', 'live.com', 'me.com', 'yandex.com', 'mail.ru'
    ];
    $domain = strtolower(substr(strrchr((string)$email, "@"), 1));
    return in_array($domain, $personalDomains, true);
}
}


/**
 * Runs a cross-check within the database to find and flag duplicate records
 */
if (!function_exists("emp_recalculate_duplicates")) {
function emp_recalculate_duplicates($conn, $company_id) {
    $cid = (int)$company_id;
    mysqli_query($conn, 'UPDATE employees SET duplicate=0 WHERE company_id=' . $cid);

    $duplicateConditions = [];
    foreach (['work_email', 'personal_email', 'employee_code', 'external_id'] as $field) {
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
}

/**
 * Collects actual duplicate values to show specific reasons in the UI
 */
if (!function_exists("emp_collect_duplicate_values")) {
function emp_collect_duplicate_values($conn, $company_id) {
    $cid = (int)$company_id;
    $maps = ['work_email' => [], 'personal_email' => [], 'employee_code' => [], 'external_id' => []];

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
}

/**
 * Identifies why a specific row was flagged as a duplicate
 */
if (!function_exists("emp_duplicate_reasons_for_row")) {
function emp_duplicate_reasons_for_row($row, $duplicateValueMaps) {
    $reasons = [];
    foreach (['work_email' => 'Work Email', 'personal_email' => 'Personal Email', 'employee_code' => 'Employee Code', 'external_id' => 'External ID'] as $field => $label) {
        $value = strtolower(trim((string)($row[$field] ?? '')));
        if ($value !== '' && !empty($duplicateValueMaps[$field][$value])) {
            $reasons[] = $label;
        }
    }
    return $reasons;
}
}

$messages = [];
$errors = [];
$skippedDetails = [];
$csrfToken = itm_get_csrf_token();
emp_drop_active_column_if_exists($conn);

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
        if (!in_array('work_email', $headers, true) && !in_array('personal_email', $headers, true) && !in_array('employee_code', $headers, true) && !in_array('external_id', $headers, true)) {
            $errors[] = 'Include at least one unique identifier column: Work Email, Personal Email, Employee Code, or External ID.';
        }

        if (empty($errors)) {
            // Ensure "Geral" department exists for this company
            $geralDeptId = 0;
            $geralNameEsc = mysqli_real_escape_string($conn, 'Geral');
            $geralCodeEsc = mysqli_real_escape_string($conn, 'GER');
            $deptCheck = mysqli_query($conn, "SELECT id FROM departments WHERE company_id=" . (int)$company_id . " AND (name='Geral' OR code='GER') LIMIT 1");
            if ($deptCheck && mysqli_num_rows($deptCheck) > 0) {
                $geralDeptId = (int)mysqli_fetch_assoc($deptCheck)['id'];
            } else {
                if (mysqli_query($conn, "INSERT INTO departments (company_id, name, code, active) VALUES (" . (int)$company_id . ", '{$geralNameEsc}', '{$geralCodeEsc}', 1)")) {
                    $geralDeptId = (int)mysqli_insert_id($conn);
                }
            }

            $created = 0; $updated = 0; $skipped = 0; $deleted = 0;
            $matchedIds = [];
            $importErrors = [];
            $existingIndex = [];
            $processedIdMap = [];
            $importIdentitySeen = [];

            // Build an in-memory index of existing employees for fast lookup during import
            $existingRowMap = [];
            $existingSql = 'SELECT * FROM employees WHERE company_id=' . (int)$company_id;
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
                    'company_id' => (int)$company_id, 'first_name' => '', 'last_name' => '', 'work_email' => '', 'personal_email' => '', 'employee_code' => '',
                    'external_id' => '', 'username' => '', 'display_name' => '', 'job_code' => '',
                    'comments' => '', 'raw_status_code' => '', 'termination_date' => '', 'request_date' => '', 'start_date' => '', 'requested_by' => '',
                    'termination_requested_by' => '', 'department_name' => '', 'employment_status_id' => 1, 'employee_type_id' => '', 'duplicate' => 0,
                    'employee_position_id' => null, 'on_orgchart' => 0, 'on_contacts' => 0, 'id' => null
                ];

                $providedFields = ['company_id', 'duplicate'];
                $importEmail = '';
                foreach ($validIdx as $idx => $field) {
                    if ($field === 'work_email') {
                        $importEmail = trim((string)($row[$idx] ?? ''));
                    } else {
                        $mapped[$field] = trim((string)($row[$idx] ?? ''));
                        $providedFields[] = $field;
                    }
                }

                // Distinguish between work and personal email
                if ($importEmail !== '') {
                    if (emp_is_personal_email($importEmail)) {
                        $mapped['personal_email'] = $importEmail;
                        $providedFields[] = 'personal_email';
                    } else {
                        $mapped['work_email'] = $importEmail;
                        $providedFields[] = 'work_email';
                    }
                }

                // Auto-fill names
                if ($mapped['display_name'] === '' && ($mapped['first_name'] !== '' || $mapped['last_name'] !== '')) {
                    $mapped['display_name'] = trim($mapped['first_name'] . ' ' . $mapped['last_name']);
                    if (!in_array('display_name', $providedFields, true)) { $providedFields[] = 'display_name'; }
                }
                if ($mapped['first_name'] === '' && $mapped['display_name'] !== '') {
                    $parts = preg_split('/\s+/', $mapped['display_name']);
                    $mapped['first_name'] = $parts[0] ?? '';
                    if (!in_array('first_name', $providedFields, true)) { $providedFields[] = 'first_name'; }
                    if ($mapped['last_name'] === '') {
                        $mapped['last_name'] = trim(implode(' ', array_slice($parts, 1)));
                        if (!in_array('last_name', $providedFields, true)) { $providedFields[] = 'last_name'; }
                    }
                }

                // Skip completely empty rows
                if ($mapped['first_name'] === '' && $mapped['last_name'] === '' && $mapped['work_email'] === '' && $mapped['personal_email'] === '' && $mapped['employee_code'] === '' && $mapped['external_id'] === '') {
                    $skipped += 1; $skippedDetails[] = ['row' => $sourceRowNumber, 'reason' => 'Missing data.', 'identity' => 'No identifying data'];
                    continue;
                }

                // Map status flag
                if (empty($mapped['raw_status_code']) && !empty($mapped['employment_status_id']) && !is_numeric($mapped['employment_status_id'])) {
                    $mapped['raw_status_code'] = $mapped['employment_status_id'];
                }
                if (!empty($mapped['raw_status_code'])) {
                    $mapped['employment_status_id'] = emp_status_id_from_raw($conn, $mapped['raw_status_code']);
                    if (!in_array('employment_status_id', $providedFields, true)) { $providedFields[] = 'employment_status_id'; }
                } else {
                    $mapped['employment_status_id'] = 1;
                }

                // Auto-map departments (Default to Geral if missing)
                if (!empty($mapped['department_name']) || (!empty($mapped['department_id']) && !is_numeric($mapped['department_id']))) {
                    $depName = !empty($mapped['department_name']) ? $mapped['department_name'] : $mapped['department_id'];
                    $depNameEsc = mysqli_real_escape_string($conn, (string)$depName);
                    $depSql = "SELECT id FROM departments WHERE company_id=" . (int)$company_id . " AND (name='" . $depNameEsc . "' OR code='" . $depNameEsc . "') LIMIT 1";
                    $depRes = mysqli_query($conn, $depSql);
                    if ($depRes && mysqli_num_rows($depRes) === 1) {
                        $mapped['department_id'] = (int)(mysqli_fetch_assoc($depRes)['id'] ?? 0);
                    } else {
                        if (mysqli_query($conn, "INSERT INTO departments (company_id,name,active) VALUES (" . (int)$company_id . ", '" . $depNameEsc . "', 1)")) {
                            $mapped['department_id'] = (int)mysqli_insert_id($conn);
                        }
                    }
                    if (!in_array('department_id', $providedFields, true)) { $providedFields[] = 'department_id'; }
                } else {
                    $mapped['department_id'] = $geralDeptId;
                }

                // Resolve other names to IDs
                $lookupMaps = [
                    'workstation_mode_id' => ['table' => 'workstation_modes', 'col' => 'mode_name'],
                    'assignment_type_id' => ['table' => 'assignment_types', 'col' => 'name'],
                    'employee_type_id' => ['table' => 'employee_type', 'col' => 'name_type'],
                    'location_id' => ['table' => 'it_locations', 'col' => 'name'],
                    'office_key_card_department_id' => ['table' => 'departments', 'col' => 'name'],
                    'role_id' => ['table' => 'employee_roles', 'col' => 'name'],
                    'access_level_id' => ['table' => 'access_levels', 'col' => 'name'],
                ];
                foreach ($lookupMaps as $targetField => $info) {
                    if (!empty($mapped[$targetField]) && !is_numeric($mapped[$targetField])) {
                        $valEsc = mysqli_real_escape_string($conn, (string)$mapped[$targetField]);
                        if ($info['table'] === 'departments') {
                            $res = mysqli_query($conn, "SELECT id FROM departments WHERE company_id=" . (int)$company_id . " AND (name='{$valEsc}' OR code='{$valEsc}') LIMIT 1");
                        } elseif ($info['table'] === 'it_locations') {
                            $res = mysqli_query($conn, "SELECT id FROM it_locations WHERE company_id=" . (int)$company_id . " AND (name='{$valEsc}' OR location_code='{$valEsc}') LIMIT 1");
                        } else {
                            $res = mysqli_query($conn, "SELECT id FROM {$info['table']} WHERE company_id=" . (int)$company_id . " AND {$info['col']}='{$valEsc}' LIMIT 1");
                        }
                        if ($res && mysqli_num_rows($res) > 0) {
                            $mapped[$targetField] = (int)mysqli_fetch_assoc($res)['id'];
                        } else {
                            $mapped[$targetField] = null;
                        }
                        if (!in_array($targetField, $providedFields, true)) { $providedFields[] = $targetField; }
                    }
                }

                // Auto-map positions (linked to department, default to Geral if no dept specified)
                if (!empty($mapped['employee_position_id'])) {
                    $posName = (string)$mapped['employee_position_id'];
                    $posNameEsc = mysqli_real_escape_string($conn, $posName);
                    $posDeptId = (int)$mapped['department_id'];

                    $posSql = "SELECT id, department_id FROM employee_positions WHERE company_id=" . (int)$company_id . " AND name='" . $posNameEsc . "' LIMIT 1";
                    $posRes = mysqli_query($conn, $posSql);
                    if ($posRes && mysqli_num_rows($posRes) === 1) {
                        $posRow = mysqli_fetch_assoc($posRes);
                        $mapped['employee_position_id'] = (int)$posRow['id'];
                        // If position exists but department is not linked, update it
                        if (empty($posRow['department_id'])) {
                            mysqli_query($conn, "UPDATE employee_positions SET department_id=" . $posDeptId . " WHERE id=" . (int)$mapped['employee_position_id']);
                        }
                    } else {
                        if (mysqli_query($conn, "INSERT INTO employee_positions (company_id, name, department_id) VALUES (" . (int)$company_id . ", '" . $posNameEsc . "', " . $posDeptId . ")")) {
                            $mapped['employee_position_id'] = (int)mysqli_insert_id($conn);
                        } else {
                            $mapped['employee_position_id'] = null;
                        }
                    }
                    if (!in_array('employee_position_id', $providedFields, true)) { $providedFields[] = 'employee_position_id'; }
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
                if (!empty($mapped['id']) && is_numeric($mapped['id'])) {
                    $candidateId = (int)$mapped['id'];
                    if (isset($existingRowMap[$candidateId]) && !isset($processedIdMap[$candidateId])) {
                        $existingId = $candidateId;
                    }
                }

                if ($existingId <= 0) {
                    foreach ($identifierTokens as $token) {
                        foreach ($existingIndex[$token] ?? [] as $candidateId) {
                            if (!isset($processedIdMap[$candidateId])) {
                                $existingId = (int)$candidateId;
                                break 2;
                            }
                        }
                    }
                }

                if (empty($mapped['employee_type_id'])) {
                    $teamMemberRes = mysqli_query($conn, "SELECT id FROM employee_type WHERE company_id=" . (int)$company_id . " AND name_type='Team member' LIMIT 1");
                    if ($teamMemberRes && mysqli_num_rows($teamMemberRes) === 1) {
                        $mapped['employee_type_id'] = (int)(mysqli_fetch_assoc($teamMemberRes)['id'] ?? 0);
                    }
                } else {
                    if (!in_array('employee_type_id', $providedFields, true)) { $providedFields[] = 'employee_type_id'; }
                }

                // Prepare values for SQL
                $columns = ['company_id','duplicate','first_name','last_name','work_email','personal_email','mobile_phone','external_number','dect','extension','employee_code','external_id','username','display_name','job_code','employee_position_id','comments','raw_status_code','termination_date','request_date','start_date','requested_by','termination_requested_by','department_id','location_id','employment_status_id','employee_type_id','on_orgchart', 'on_contacts', 'reports_to', 'workstation_mode_id', 'assignment_type_id', 'office_key_card_department_id', 'role_id', 'access_level_id'];
                $mapped['duplicate'] = $isDuplicateInFile ? 1 : 0;
                $values = [];
                foreach ($columns as $col) {
                    $value = trim((string)($mapped[$col] ?? ''));
                    if ($value === '' || strcasecmp($value, 'null') === 0 || $value === '—') {
                        $values[$col] = 'NULL';
                    } elseif (in_array($col, ['duplicate', 'on_contacts', 'on_orgchart'], true) || (isset($columnTypes[$col]) && strpos($columnTypes[$col], 'tinyint') !== false)) {
                        $normalizedBool = strtolower($value);
                        if (in_array($normalizedBool, ['1', 'active', 'yes', 'true', 'on', '✅'], true)) {
                            $values[$col] = '1';
                        } else {
                            $values[$col] = '0';
                        }
                    } elseif (in_array($col, ['company_id', 'employment_status_id', 'department_id', 'location_id', 'employee_position_id', 'reports_to', 'workstation_mode_id', 'assignment_type_id', 'employee_type_id', 'office_key_card_department_id', 'role_id', 'access_level_id'], true)) {
                        $values[$col] = (string)(int)$value;
                    } else {
                        $values[$col] = "'" . mysqli_real_escape_string($conn, $value) . "'";
                    }
                }

                if ($existingId > 0) {
                    if (itm_employees_is_hidden_account($existingRowMap[$existingId] ?? null)) {
                        $skipped += 1;
                        $skippedDetails[] = ['row' => $sourceRowNumber, 'reason' => 'Protected hidden account cannot be updated via import.', 'identity' => emp_import_identity_label($mapped)];
                        continue;
                    }
                    // Update if record changed
                    $hasChanges = false;
                    $existingCurrent = $existingRowMap[$existingId] ?? [];
                    $updateColumns = array_values(array_intersect($columns, $providedFields));

                    foreach ($updateColumns as $col) {
                        if ($col === 'company_id') continue;
                        $incomingNorm = ($mapped[$col] === '') ? null : $mapped[$col];
                        $existingNorm = ($existingCurrent[$col] === '') ? null : $existingCurrent[$col];
                        if ($incomingNorm !== $existingNorm) { $hasChanges = true; break; }
                    }

                    if ($hasChanges) {
                        $sets = [];
                        foreach ($updateColumns as $col) { if ($col !== 'company_id') { $sets[] = emp_escape_identifier($col) . '=' . $values[$col]; } }
                        $sql = 'UPDATE employees SET ' . implode(',', $sets) . ' WHERE id=' . $existingId . ' AND company_id=' . (int)$company_id . ' LIMIT 1';
                        if (mysqli_query($conn, $sql)) {
                            $updated += 1; $matchedIds[] = $existingId; $processedIdMap[$existingId] = true;
                        } else {
                            $importErrors[] = "Row {$sourceRowNumber} (Update): " . mysqli_error($conn);
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
                    } else {
                        $importErrors[] = "Row {$sourceRowNumber} (Insert): " . mysqli_error($conn);
                    }
                }
            }

            // Sync duplicate flags
            emp_recalculate_duplicates($conn, $company_id);

            $messages[] = "Import complete: {$created} created, {$updated} updated, {$skipped} skipped.";
            if (!empty($importErrors)) {
                $errors = array_merge($errors, array_slice($importErrors, 0, 10));
            }
        }
    }
}

// --- DATA PREPARATION FOR UI ---
emp_ensure_duplicate_column($conn);
emp_ensure_is_hidden_column($conn);
$where = ' WHERE e.company_id=' . (int)$company_id . itm_employees_sql_visible_only_predicate('e');
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

$preferredOrder = ['id','duplicate','external_id','employee_code','username','display_name','work_email','personal_email','mobile_phone','external_number','dect','extension','raw_status_code','first_name','last_name','job_code','role_id','access_level_id','employee_position_id','reports_to','on_contacts','on_orgchart','department_id','location_id','request_date','start_date','requested_by','termination_requested_by','employment_status_id','employee_type_id','termination_date','birthday','hide_year','photo','workstation_mode_id','assignment_type_id','comments'];
$hiddenColumns = array_merge(['company_id', 'location'], itm_employees_hidden_account_column_names());
$hiddenColumns = array_merge($hiddenColumns, array_keys(esa_ability_fields()), itm_employees_auth_sensitive_field_names());
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
    $searchConditions = itm_employees_build_search_conditions($conn, $columns, $searchRaw);
    if (!empty($searchConditions)) {
        $where .= ' AND (' . implode(' OR ', $searchConditions) . ')';
    }
}

$sortSql = 'e.`' . str_replace('`', '``', $sort) . '` ' . $dir;
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countSql = 'SELECT COUNT(*) AS total
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN departments okd ON okd.id = e.office_key_card_department_id
             LEFT JOIN employee_statuses es ON es.id = e.employment_status_id
             LEFT JOIN employee_type et ON et.id = e.employee_type_id AND et.company_id = e.company_id
             LEFT JOIN it_locations il ON il.id = e.location_id AND il.company_id = e.company_id
             LEFT JOIN workstation_modes wm ON wm.id = e.workstation_mode_id AND wm.company_id = e.company_id
             LEFT JOIN assignment_types at ON at.id = e.assignment_type_id AND at.company_id = e.company_id
             LEFT JOIN employee_system_access esa ON esa.company_id = e.company_id AND esa.employee_id = e.id
             LEFT JOIN employee_positions ep ON ep.id = e.employee_position_id
             LEFT JOIN employees m ON m.id = e.reports_to
             LEFT JOIN employee_roles er ON er.id = e.role_id AND er.company_id = e.company_id
             LEFT JOIN access_levels al ON al.id = e.access_level_id AND al.company_id = e.company_id'
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
    'SELECT e.*, d.name AS department_name, okd.name AS office_key_card_department_name, es.name AS employment_status_name, et.name_type AS employee_type_name, il.name AS location_name, wm.mode_name AS workstation_mode_name, at.name AS assignment_type_name,
            ep.name AS position_name, m.display_name AS manager_name, er.name AS role_name, al.name AS access_level_name,
            esa.network_access, esa.micros_emc, esa.opera_username, esa.micros_card, esa.pms_id, esa.synergy_mms,
            esa.hu_the_lobby, esa.navision, esa.onq_ri, esa.birchstreet, esa.delphi, esa.omina, esa.vingcard_system,
            esa.digital_rev, esa.office_key_card
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     LEFT JOIN departments okd ON okd.id = e.office_key_card_department_id
     LEFT JOIN employee_statuses es ON es.id = e.employment_status_id
     LEFT JOIN employee_type et ON et.id = e.employee_type_id AND et.company_id = e.company_id
     LEFT JOIN it_locations il ON il.id = e.location_id AND il.company_id = e.company_id
     LEFT JOIN workstation_modes wm ON wm.id = e.workstation_mode_id AND wm.company_id = e.company_id
     LEFT JOIN assignment_types at ON at.id = e.assignment_type_id AND at.company_id = e.company_id
     LEFT JOIN employee_system_access esa ON esa.company_id = e.company_id AND esa.employee_id = e.id
     LEFT JOIN employee_positions ep ON ep.id = e.employee_position_id
     LEFT JOIN employees m ON m.id = e.reports_to
     LEFT JOIN employee_roles er ON er.id = e.role_id AND er.company_id = e.company_id
     LEFT JOIN access_levels al ON al.id = e.access_level_id AND al.company_id = e.company_id'
    . $where .
    ' ORDER BY ' . $sortSql . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset
);

/**
 * Humanizes labels specifically for the employee module
 */
if (!function_exists("emp_label")) {
function emp_label($field) {
    if ($field === 'department_id') return 'Department Name';
    if ($field === 'employee_position_id') return 'Position Title';
    if ($field === 'reports_to') return 'Reports To';
    if ($field === 'employment_status_id') return 'Employment Status';
    if ($field === 'employee_type_id') return 'Employee Type';
    if ($field === 'termination_date') return 'Termination Date';
    if ($field === 'start_date') return 'Start Date';
    if ($field === 'hide_year') return 'Hide Year';
    if ($field === 'workstation_mode_id') return 'Workstation Mode';
    if ($field === 'assignment_type_id') return 'Assignment Type';
    if ($field === 'external_id') return 'External ID';
    if ($field === 'employee_code') return 'Employee Code';
    if ($field === 'role_id') return 'Role';
    if ($field === 'access_level_id') return 'Access Level';
    if ($field === 'location_id') return 'IT Location';
    if ($field === 'on_contacts') return 'On Contacts';
    if ($field === 'on_orgchart') return 'On Org Chart';
    if ($field === 'external_number') return 'External Number';
    return ucwords(str_replace('_', ' ', trim((string)$field)));
}
}

/**
 * Build clean URL queries
 */
if (!function_exists("emp_build_query")) {
function emp_build_query($params) {
    $normalized = [];
    foreach ($params as $key => $value) { if ($value !== null && $value !== '') { $normalized[$key] = $value; } }
    return http_build_query($normalized);
}
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
            <?php echo itm_render_alert_errors($errors); ?>
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
                </div>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                    <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all employees for this company? This cannot be undone.');">Clear Table</button>
                </form>
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
                        <div id="employeeImportTarget" class="itm-photo-upload-target" role="button" tabindex="0" aria-label="Upload Employee file">
                            <p class="itm-dropzone-hint">Drag and drop file here, or click to browse.</p>
                            <input type="file" id="employeeImportFile" accept=".xlsx,.xls,.csv" />
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top: 10px;">📥 Import Employees</button>
                    </div>
                    <div class="form-group"><label>Or paste tabular data</label><textarea name="import_text" id="employeeImportText" rows="2" placeholder="Paste from Excel..."></textarea></div>
                </form>
            </div>

            <!-- DATA TABLE -->
            <div class="card" style="overflow:auto;">
                <table data-itm-db-import-endpoint="index.php">
                    <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th>
                        <?php foreach ($columns as $col): ?>
                            <?php $nextDir = ($sort === $col && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?<?php echo sanitize(emp_build_query(['sort' => $col, 'dir' => $nextDir, 'show' => $showDuplicatesOnly ? 'duplicates' : null, 'search' => $searchRaw])); ?>" style="text-decoration:none;color:inherit;"><?php echo sanitize(emp_label($col === 'work_email' ? 'email' : $col)); ?><?php if ($sort === $col): ?><?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows && mysqli_num_rows($rows) > 0): while ($row = mysqli_fetch_assoc($rows)): ?>
                        <?php $duplicateReasons = emp_duplicate_reasons_for_row($row, $duplicateValueMaps); ?>
                        <tr<?php echo ((int)($row['duplicate'] ?? 0) === 1) ? ' style="background:#ffe8e8;border-left:4px solid #d93025;"' : ''; ?>>
                            <td><input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" form="bulk-delete-form"></td>
                            <?php foreach ($columns as $col): ?>
                                <td>
                                    <?php if (($col === 'work_email' || $col === 'personal_email') && !empty($row[$col])): ?>
                                        <a href="mailto:<?php echo sanitize((string)$row[$col]); ?>" data-outlook-link="1" data-outlook-href="ms-outlook://compose?to=<?php echo sanitize((string)$row[$col]); ?>"><?php echo sanitize((string)$row[$col]); ?></a>
                                    <?php elseif ($col === 'department_id'): ?><?php echo sanitize((string)($row['department_name'] ?? '')); ?>
                                    <?php elseif ($col === 'office_key_card_department_id'): ?><?php echo sanitize((string)($row['office_key_card_department_name'] ?? '')); ?>
                                    <?php elseif ($col === 'location_id'): ?><?php echo sanitize((string)($row['location_name'] ?? '')); ?>
                                    <?php elseif ($col === 'employee_position_id'): ?><?php echo sanitize((string)($row['position_name'] ?? '')); ?>
                                    <?php elseif ($col === 'reports_to'): ?><?php echo sanitize((string)($row['manager_name'] ?? '')); ?>
                                    <?php elseif ($col === 'employment_status_id'): ?><?php echo sanitize((string)($row['employment_status_name'] ?? '')); ?>
                                    <?php elseif ($col === 'employee_type_id'): ?><?php echo sanitize((string)($row['employee_type_name'] ?? '')); ?>
                                    <?php elseif ($col === 'role_id'): ?><?php echo sanitize((string)($row['role_name'] ?? '')); ?>
                                    <?php elseif ($col === 'access_level_id'): ?><?php echo sanitize((string)($row['access_level_name'] ?? '')); ?>
                                    <?php elseif ($col === 'birthday'): ?><?php echo sanitize(emp_format_birthday_display($row['birthday'] ?? null, $row['hide_year'] ?? 0)); ?>
                                    <?php elseif ($col === 'photo'): ?>
                                        <?php $empListPhotoUrl = emp_profile_photo_url($row); ?>
                                        <?php if ($empListPhotoUrl !== ''): ?>
                                            <img src="<?= sanitize($empListPhotoUrl) ?>" alt="" class="rounded-circle" width="30" height="30" style="object-fit:cover;" onerror="this.onerror=null; this.src='../../images/5x5-pixel.png';">
                                        <?php else: ?>—<?php endif; ?>
                                    <?php elseif ($col === 'workstation_mode_id'): ?><?php echo sanitize((string)($row['workstation_mode_name'] ?? '')); ?>
                                    <?php elseif ($col === 'assignment_type_id'): ?><?php echo sanitize((string)($row['assignment_type_name'] ?? '')); ?>
                                    <?php elseif (str_starts_with($columnTypes[$col] ?? '', 'tinyint(1)')): ?>
                                        <?php if ($col === 'duplicate'): ?>
                                            <?php echo ((int)($row[$col] ?? 0) === 1) ? '⚠️ Duplicate (' . sanitize(implode(', ', $duplicateReasons)) . ')' : '—'; ?>
                                        <?php else: ?>
                                            <?php echo ((int)($row[$col] ?? 0) === 1) ? '✅' : '❌'; ?>
                                        <?php endif; ?>
                                    <?php elseif (itm_is_date_field_name($col)): ?><?php echo sanitize(itm_format_date_display($row[$col] ?? '')); ?>
                                    <?php elseif ($col === 'comments' && trim((string)($row[$col] ?? '')) !== ''): ?><span data-itm-export-value="<?php echo sanitize((string)($row[$col] ?? '')); ?>"><a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a></span>
                                    <?php else: ?><?php echo sanitize((string)$row[$col]); ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="itm-actions-cell" data-itm-actions-origin="1">
                                <div class="itm-actions-wrap">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">🔎</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                    <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this employee?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                        <input type="hidden" name="bulk_action" value="single_delete">
                                        <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="<?php echo count($columns) + 2; ?>" style="text-align:center;">No employees found.</td></tr>
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
<script src="../../js/itm-upload-helper.js"></script>
<script>
/**
 * Import Logic - Handles File Reading and CSV/Excel Parsing
 */
(function () {
    const fileInput = document.getElementById('employeeImportFile');
    if (typeof itmUploadHelper !== "undefined") {
        itmUploadHelper.setupById("employeeImportTarget", "employeeImportFile");
    }
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
            alert('Unsupported file type.');
            fileInput.value = '';
            payloadInput.value = '';
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
