<?php
/**
 * Employee Onboarding Requests Module - Index
 * 
 * Main list view for employee onboarding requests. Users can view, create, edit, and delete
 * records defined for the company.
 */

$crud_table = $crud_table ?? 'employee_onboarding_requests';
$crud_title = $crud_title ?? 'Employee Onboarding Requests';
$crud_action = $crud_action ?? 'index';
?>
<?php
require_once '../../config/config.php';

// Validate table configuration to prevent unauthorized access to other tables
if (!isset($crud_table) || !preg_match('/^[a-zA-Z0-9_]+$/', $crud_table)) {
    die('Invalid table configuration');
}

$crud_title = $crud_title ?? ucwords(str_replace('_', ' ', $crud_table));
$crud_action = $crud_action ?? 'index';
$pk = 'id';

/**
 * Escapes a database identifier (table or column name)
 */
function cr_escape_identifier($name) {
    return '`' . str_replace('`', '``', $name) . '`';
}

/**
 * Fetches column metadata for the current table using DESCRIBE
 */
function cr_table_columns($conn, $table) {
    $cols = [];
    $res = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $cols[] = $row;
    }
    return $cols;
}

/**
 * Maps foreign key columns to their referenced tables using INFORMATION_SCHEMA
 */
function cr_fk_map($conn, $table) {
    $tableEsc = mysqli_real_escape_string($conn, $table);
    $sql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$tableEsc}'
              AND REFERENCED_TABLE_NAME IS NOT NULL";
    $map = [];
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $map[$row['COLUMN_NAME']] = $row;
    }
    return $map;
}

/**
 * Fetches available options for a foreign key dropdown, scoped by company if applicable
 */
function cr_fk_options($conn, $fk, $company_id) {
    $table = $fk['REFERENCED_TABLE_NAME'];
    $col = $fk['REFERENCED_COLUMN_NAME'];

    $fkMeta = cr_fk_metadata($conn, $table);
    $labelCol = $fkMeta['label_col'];
    $available = $fkMeta['available'];

    $where = '';
    if (in_array('company_id', $available, true) && $company_id > 0) {
        $where = ' WHERE company_id=' . (int)$company_id;
    }

    $labelExpr = ($table === 'employees')
        ? "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(NULLIF(`first_name`,''),''), ' ', COALESCE(NULLIF(`last_name`,''),''))), ''), NULLIF(`display_name`, ''), NULLIF(`username`, ''), CONCAT('Employee #', " . cr_escape_identifier($col) . "))"
        : cr_escape_identifier($labelCol);
    $sql = 'SELECT ' . cr_escape_identifier($col) . ' AS id, ' . $labelExpr . " AS label FROM " . cr_escape_identifier($table) . $where . ' ORDER BY label';
    $rows = [];
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Detects metadata (like label column) for a referenced table
 */
function cr_fk_metadata($conn, $table) {
    $labelCol = 'name';
    $des = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    $available = [];
    while ($des && ($d = mysqli_fetch_assoc($des))) {
        $available[] = $d['Field'];
    }
    // Preferred label columns in order of priority
    foreach (['name', 'title', 'username', 'code', 'mode_name'] as $candidate) {
        if (in_array($candidate, $available, true)) {
            $labelCol = $candidate;
            break;
        }
    }
    return [
        'label_col' => $labelCol,
        'available' => $available,
    ];
}

/**
 * Filters out system-managed columns from the list of manageable fields
 */
function cr_manageable_columns($columns) {
    return array_values(array_filter($columns, function ($c) {
        return !in_array($c['Field'], ['id', 'created_at', 'updated_at'], true);
    }));
}

/**
 * Converts a database field name into a human-readable label with overrides
 */
function cr_humanize_field($field) {
    $label = trim((string)$field);
    if ($label === '') {
        return '';
    }

    $map = [
        'department_id' => 'Department Name',
        'office_key_card_department_id' => 'Office Key Card Department',
        'office_key_card_dep' => 'Department',
        'hod_approval' => 'HOD Approval',
        'hrd_approval' => 'HRD Approval',
        'ism_approval' => 'ISM Approval',
        'opera_username' => 'OPERA Username',
        'onq_ri' => 'OnQ R&I',
        'hu_the_lobby' => 'HU & The Lobby',
    ];

    if (isset($map[$label])) {
        return $map[$label];
    }

    if ($label === 'id') {
        return 'ID';
    }

    $label = preg_replace('/_id$/', '', $label);
    $label = str_replace('_', ' ', (string)$label);
    return ucwords($label);
}

/**
 * Checks if a field should be hidden specifically in the employee module view
 */
function cr_is_hidden_employee_field($field) {
    $crudTable = (string)($GLOBALS['crud_table'] ?? '');
    if ($crudTable === 'employees') {
        $hidden = ['company_id', 'user_id', 'location_id', 'phone', 'location', 'employee_code'];
        return in_array($field, $hidden, true);
    }
    if ($crudTable === 'employee_onboarding_requests') {
        $hidden = ['gm_approval', 'gm_approval_date', 'fin_approval', 'fin_approval_date'];
        return in_array($field, $hidden, true);
    }
    return false;
}

/**
 * Renders a specific table cell value with formatting based on field type/module
 */
function cr_render_cell_value($table, $field, $value) {
    if ($field === 'active') {
        $isActive = ((int)$value === 1);
        return '<span class="badge ' . ($isActive ? 'badge-success' : 'badge-danger') . '">' . ($isActive ? 'Active' : 'Inactive') . '</span>';
    }

    if (($GLOBALS['crud_table'] ?? '') === 'employees') {
        $employeeBoolFields = ['network_access', 'micros_emc', 'opera_username', 'micros_card', 'pms_id', 'synergy_mms', 'hu_the_lobby', 'navision', 'onq_ri', 'birchstreet', 'delphi', 'omina', 'vingcard_system', 'digital_rev', 'office_key_card'];
        if (in_array($field, $employeeBoolFields, true)) {
            return ((int)$value === 1) ? '✅' : '❌';
        }
    }

    $text = (string)($value ?? '');
    if ($table === 'employees' && $field === 'email' && $text !== '') {
        $safeEmail = sanitize($text);
        $mailto = 'mailto:' . $text;
        $outlook = 'ms-outlook://compose?to=' . $text;
        return '<a href="' . sanitize($mailto) . '" data-outlook-link="1" data-outlook-href="' . sanitize($outlook) . '">' . $safeEmail . '</a>';
    }

    return sanitize($text);
}

function cr_is_employee_onboarding_module() {
    return (($GLOBALS['crud_table'] ?? '') === 'employee_onboarding_requests');
}

function cr_onboarding_normalize_system_access_code($code) {
    $normalized = trim((string)$code);
    if ($normalized === '') {
        return '';
    }

    // Why: admins may enter codes with spaces/hyphens/camel case; onboarding fields are snake_case.
    $normalized = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $normalized);
    $normalized = strtolower($normalized);
    $normalized = str_replace(['-', ' '], '_', $normalized);
    $normalized = preg_replace('/[^a-z0-9_]+/', '_', $normalized);
    $normalized = preg_replace('/_+/', '_', $normalized);
    return trim((string)$normalized, '_');
}

function cr_onboarding_system_access_labels($conn, $company_id) {
    if (!cr_is_employee_onboarding_module()) {
        return [];
    }

    $fieldAliasMap = [
        'opera_username' => 'opera',
        'opera' => 'opera',
    ];
    $labels = [];
    $company_id = (int)$company_id;

    if ($company_id > 0) {
        $sql = "SELECT code, name
                FROM `system_access`
                WHERE company_id={$company_id} AND active=1
                ORDER BY name ASC";
        $res = mysqli_query($conn, $sql);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rawCode = cr_onboarding_normalize_system_access_code((string)($row['code'] ?? ''));
            if ($rawCode === '') {
                continue;
            }
            $fieldName = $fieldAliasMap[$rawCode] ?? $rawCode;
            $labels[$fieldName] = trim((string)($row['name'] ?? ''));
        }
    }

    return $labels;
}

function cr_current_user_display_name($conn, $company_id) {
    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($currentUserId <= 0) {
        return '';
    }

    $companyFilter = ($company_id > 0)
        ? ' WHERE id=' . $currentUserId . ' AND company_id=' . (int)$company_id
        : ' WHERE id=' . $currentUserId;
    $sql = 'SELECT username, first_name, last_name FROM `users`' . $companyFilter . ' LIMIT 1';
    $result = mysqli_query($conn, $sql);

    // Why: preserve persisted sessions for legacy shared users not fully mapped to tenant rows.
    if ((!$result || mysqli_num_rows($result) === 0) && $company_id > 0) {
        $result = mysqli_query($conn, 'SELECT username, first_name, last_name FROM `users` WHERE id=' . $currentUserId . ' LIMIT 1');
    }

    if ($result && ($row = mysqli_fetch_assoc($result))) {
        $fullName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        $username = trim((string)($row['username'] ?? ''));
        if ($username !== '') {
            return $username;
        }
    }

    return trim((string)($_SESSION['username'] ?? ''));
}

function cr_onboarding_find_active_approver_name($conn, $company_id, $departmentName, $approverTypeDescription) {
    $company_id = (int)$company_id;
    $departmentName = trim((string)$departmentName);
    $approverTypeDescription = trim((string)$approverTypeDescription);

    if ($company_id <= 0 || $departmentName === '' || $approverTypeDescription === '') {
        return '';
    }

    $sql = "SELECT e.first_name, e.last_name, e.display_name, e.username
            FROM `approvers` a
            INNER JOIN `departments` d
                ON d.id = a.department_id
               AND d.company_id = a.company_id
            INNER JOIN `approver_type` at
                ON at.id = a.approver_type_id
               AND at.company_id = a.company_id
            LEFT JOIN `employees` e
                ON e.id = a.employee_id
               AND e.company_id = a.company_id
            WHERE a.company_id = ?
              AND a.active = 1
              AND d.active = 1
              AND at.active = 1
              AND d.name = ?
              AND at.approver_type_description = ?
            ORDER BY a.id ASC
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return '';
    }

    mysqli_stmt_bind_param($stmt, 'iss', $company_id, $departmentName, $approverTypeDescription);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return '';
    }

    $fullName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
    if ($fullName !== '') {
        return $fullName;
    }

    $displayName = trim((string)($row['display_name'] ?? ''));
    if ($displayName !== '') {
        return $displayName;
    }

    return trim((string)($row['username'] ?? ''));
}

function cr_onboarding_find_active_approver_name_by_type($conn, $company_id, $approverTypeDescription) {
    $company_id = (int)$company_id;
    $approverTypeDescription = trim((string)$approverTypeDescription);

    if ($company_id <= 0 || $approverTypeDescription === '') {
        return '';
    }

    $sql = "SELECT e.first_name, e.last_name, e.display_name, e.username
            FROM `approvers` a
            INNER JOIN `approver_type` at
                ON at.id = a.approver_type_id
               AND at.company_id = a.company_id
            LEFT JOIN `employees` e
                ON e.id = a.employee_id
               AND e.company_id = a.company_id
            WHERE a.company_id = ?
              AND a.active = 1
              AND at.active = 1
              AND at.approver_type_description = ?
            ORDER BY a.id ASC
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return '';
    }

    mysqli_stmt_bind_param($stmt, 'is', $company_id, $approverTypeDescription);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return '';
    }

    $fullName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
    if ($fullName !== '') {
        return $fullName;
    }

    $displayName = trim((string)($row['display_name'] ?? ''));
    if ($displayName !== '') {
        return $displayName;
    }

    return trim((string)($row['username'] ?? ''));
}

function cr_onboarding_find_active_approver_contact($conn, $company_id, $departmentName, $approverTypeDescription) {
    $company_id = (int)$company_id;
    $departmentName = trim((string)$departmentName);
    $approverTypeDescription = trim((string)$approverTypeDescription);

    if ($company_id <= 0 || $departmentName === '' || $approverTypeDescription === '') {
        return ['name' => '', 'email' => ''];
    }

    $sql = "SELECT e.first_name, e.last_name, e.display_name, e.username, e.email
            FROM `approvers` a
            INNER JOIN `departments` d
                ON d.id = a.department_id
               AND d.company_id = a.company_id
            INNER JOIN `approver_type` at
                ON at.id = a.approver_type_id
               AND at.company_id = a.company_id
            LEFT JOIN `employees` e
                ON e.id = a.employee_id
               AND e.company_id = a.company_id
            WHERE a.company_id = ?
              AND a.active = 1
              AND d.active = 1
              AND at.active = 1
              AND d.name = ?
              AND at.approver_type_description = ?
            ORDER BY a.id ASC
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return ['name' => '', 'email' => ''];
    }

    mysqli_stmt_bind_param($stmt, 'iss', $company_id, $departmentName, $approverTypeDescription);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return ['name' => '', 'email' => ''];
    }

    $fullName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
    $displayName = trim((string)($row['display_name'] ?? ''));
    $username = trim((string)($row['username'] ?? ''));
    $email = trim((string)($row['email'] ?? ''));

    return [
        'name' => $fullName !== '' ? $fullName : ($displayName !== '' ? $displayName : $username),
        'email' => $email,
    ];
}

function cr_onboarding_find_active_approver_contact_by_type($conn, $company_id, $approverTypeDescription) {
    $company_id = (int)$company_id;
    $approverTypeDescription = trim((string)$approverTypeDescription);

    if ($company_id <= 0 || $approverTypeDescription === '') {
        return ['name' => '', 'email' => ''];
    }

    $sql = "SELECT e.first_name, e.last_name, e.display_name, e.username, e.email
            FROM `approvers` a
            INNER JOIN `approver_type` at
                ON at.id = a.approver_type_id
               AND at.company_id = a.company_id
            LEFT JOIN `employees` e
                ON e.id = a.employee_id
               AND e.company_id = a.company_id
            WHERE a.company_id = ?
              AND a.active = 1
              AND at.active = 1
              AND at.approver_type_description = ?
            ORDER BY a.id ASC
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return ['name' => '', 'email' => ''];
    }

    mysqli_stmt_bind_param($stmt, 'is', $company_id, $approverTypeDescription);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    if (!$row) {
        return ['name' => '', 'email' => ''];
    }

    $fullName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
    $displayName = trim((string)($row['display_name'] ?? ''));
    $username = trim((string)($row['username'] ?? ''));
    $email = trim((string)($row['email'] ?? ''));

    return [
        'name' => $fullName !== '' ? $fullName : ($displayName !== '' ? $displayName : $username),
        'email' => $email,
    ];
}

function cr_onboarding_send_approval_email_via_api($toEmail, $toName, $subject, $htmlBody, &$errorMessage) {
    $toEmail = trim((string)$toEmail);
    $toName = trim((string)$toName);
    $subject = trim((string)$subject);
    $htmlBody = trim((string)$htmlBody);

    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Approver email is missing or invalid.';
        return false;
    }

    if (!defined('MAILERLITE_API_KEY') || !defined('MAILERLITE_URL') || trim((string)MAILERLITE_API_KEY) === '' || trim((string)MAILERLITE_API_KEY) === 'YOUR_MAILERLITE_API_KEY_HERE') {
        $errorMessage = 'Email API is not configured in config/config.php.';
        return false;
    }
    if (!function_exists('curl_init')) {
        $errorMessage = 'Email API call failed: cURL extension is not available on this server.';
        return false;
    }

    $payload = json_encode([
        'from' => 'verified@yourdomain.com',
        'to' => $toName !== '' ? [$toEmail => $toName] : $toEmail,
        'subject' => $subject,
        'html' => $htmlBody,
    ]);
    if ($payload === false) {
        $errorMessage = 'Unable to encode email request payload.';
        return false;
    }

    $ch = curl_init(MAILERLITE_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . MAILERLITE_API_KEY,
    ]);
    $responseBody = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrNo = (int)curl_errno($ch);
    $curlErrText = curl_error($ch);
    curl_close($ch);

    if ($curlErrNo !== 0) {
        $errorMessage = 'Email API call failed: ' . $curlErrText;
        return false;
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        $responseSnippet = trim((string)$responseBody);
        if ($responseSnippet !== '') {
            $responseSnippet = preg_replace('/\s+/', ' ', $responseSnippet);
            $responseSnippet = substr($responseSnippet, 0, 240);
            $errorMessage = 'Email API returned HTTP ' . $httpCode . ': ' . $responseSnippet;
        } else {
            $errorMessage = 'Email API returned HTTP ' . $httpCode . ' with an empty response body.';
        }
        return false;
    }

    $errorMessage = '';
    return true;
}

function cr_onboarding_status_field_by_target($approvalTarget) {
    $map = [
        'hod' => 'status_hod',
        'hrd' => 'status_hrd',
        'ism' => 'status_ism',
        'gm' => 'status_gm',
        'fin' => 'status_fin',
    ];
    return (string)($map[(string)$approvalTarget] ?? '');
}

function cr_onboarding_status_badge($value) {
    $status = strtolower(trim((string)$value));
    if ($status === 'approved') {
        return '<span class="badge badge-success">Approved</span>';
    }
    if ($status === 'declined') {
        return '<span class="badge badge-danger">Declined</span>';
    }
    return '<span class="badge">Waiting</span>';
}

function cr_onboarding_email_status_text($sentFlag, $sentAt) {
    if ((int)$sentFlag !== 1) {
        return 'Not sent';
    }

    $stamp = trim((string)$sentAt);
    if ($stamp === '' || $stamp === '0000-00-00 00:00:00') {
        return 'Sent';
    }

    $ts = strtotime($stamp);
    if ($ts === false) {
        return 'Sent (' . $stamp . ')';
    }

    return 'Sent (' . date('d/m/Y H:i', $ts) . ')';
}

function cr_onboarding_sign_approval_action($recordId, $companyId, $approvalTarget, $approvalAction) {
    $data = (int)$recordId . '|' . (int)$companyId . '|' . trim((string)$approvalTarget) . '|' . trim((string)$approvalAction);
    $secret = (string)(defined('MAILERLITE_API_KEY') ? MAILERLITE_API_KEY : '');
    if ($secret === '') {
        $secret = 'it-management-approval-secret';
    }
    return hash_hmac('sha256', $data, $secret);
}

function cr_onboarding_employee_email($conn, $company_id, $employeeId) {
    $employeeId = (int)$employeeId;
    $company_id = (int)$company_id;
    if ($employeeId <= 0) {
        return '';
    }

    $sql = 'SELECT email FROM `employees` WHERE id=?';
    if ($company_id > 0) {
        $sql .= ' AND company_id=?';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return '';
    }

    if ($company_id > 0) {
        mysqli_stmt_bind_param($stmt, 'ii', $employeeId, $company_id);
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $employeeId);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    // Why: preserve email fill for legacy shared employee rows not duplicated per tenant.
    if ((!$row || trim((string)($row['email'] ?? '')) === '') && $company_id > 0) {
        $fallbackStmt = mysqli_prepare($conn, 'SELECT email FROM `employees` WHERE id=? LIMIT 1');
        if ($fallbackStmt) {
            mysqli_stmt_bind_param($fallbackStmt, 'i', $employeeId);
            mysqli_stmt_execute($fallbackStmt);
            $fallbackResult = mysqli_stmt_get_result($fallbackStmt);
            $row = $fallbackResult ? mysqli_fetch_assoc($fallbackResult) : $row;
            mysqli_stmt_close($fallbackStmt);
        }
    }

    return trim((string)($row['email'] ?? ''));
}

function cr_onboarding_comment_with_employee_email($conn, $company_id, $employeeId, $existingComment) {
    $email = cr_onboarding_employee_email($conn, $company_id, $employeeId);
    $existingComment = trim((string)$existingComment);

    $commentLines = preg_split('/\r\n|\r|\n/', $existingComment);
    $cleanLines = [];
    foreach ((array)$commentLines as $commentLine) {
        $trimmedLine = trim((string)$commentLine);
        if ($trimmedLine === '') {
            continue;
        }
        if (in_array($trimmedLine, ['(Email:)', "'(Email:)'", 'Email:'], true)) {
            continue;
        }
        $cleanLines[] = $commentLine;
    }
    $existingComment = trim(implode("\n", $cleanLines));

    if ($email === '') {
        return $existingComment;
    }

    if ($existingComment === '') {
        return 'Email: ' . $email;
    }

    if (stripos($existingComment, 'Email:') === 0) {
        return 'Email: ' . $email;
    }

    return 'Email: ' . $email . "\n" . $existingComment;
}

function cr_onboarding_resolve_approvals($conn, $company_id, $departmentNameRaw) {
    $departmentName = trim((string)$departmentNameRaw);
    $resolved = [
        'hod_approval' => '',
        'hrd_approval' => '',
        'ism_approval' => '',
        'gm_approval' => '',
        'fin_approval' => '',
    ];
    if ((int)$company_id <= 0) {
        return $resolved;
    }

    if ($departmentName !== '') {
        $resolved['hod_approval'] = cr_onboarding_find_active_approver_name($conn, (int)$company_id, $departmentName, 'HOD Approval');
        $resolved['hrd_approval'] = cr_onboarding_find_active_approver_name($conn, (int)$company_id, $departmentName, 'HRD Approval');
        $resolved['ism_approval'] = cr_onboarding_find_active_approver_name($conn, (int)$company_id, $departmentName, 'ISM Approval');
    }

    // Why: HRD/ISM approvers are often configured as one active approver company-wide.
    if ($resolved['hrd_approval'] === '') {
        $resolved['hrd_approval'] = cr_onboarding_find_active_approver_name_by_type($conn, (int)$company_id, 'HRD Approval');
    }
    if ($resolved['ism_approval'] === '') {
        $resolved['ism_approval'] = cr_onboarding_find_active_approver_name_by_type($conn, (int)$company_id, 'ISM Approval');
    }
    $resolved['gm_approval'] = cr_onboarding_find_active_approver_name_by_type($conn, (int)$company_id, 'GM Approval');
    $resolved['fin_approval'] = cr_onboarding_find_active_approver_name_by_type($conn, (int)$company_id, 'FIN Approval');
    // Why: HOD approval must stay department-specific; do not auto-fill from other departments.

    return $resolved;
}

function cr_onboarding_auto_fill_approvals($conn, $company_id, &$data, $departmentNameRaw) {
    if (!cr_is_employee_onboarding_module()) {
        return;
    }

    $departmentName = trim((string)$departmentNameRaw);
    if ($departmentName === '' && isset($data['department_name'])) {
        $departmentName = trim((string)$data['department_name'], " \t\n\r\0\x0B'");
    }
    if ($departmentName === '') {
        return;
    }

    $resolvedApprovals = cr_onboarding_resolve_approvals($conn, (int)$company_id, $departmentName);
    foreach ($resolvedApprovals as $approvalField => $approverName) {
        if (!array_key_exists($approvalField, $data)) {
            continue;
        }
        if ($approverName === '') {
            continue;
        }

        $data[$approvalField] = "'" . mysqli_real_escape_string($conn, $approverName) . "'";
    }
}

function cr_sync_onboarding_system_access_columns($conn, $company_id) {
    $company_id = (int)$company_id;
    if ($company_id <= 0) {
        return;
    }

    $table = 'employee_onboarding_requests';
    $existingColumns = [];
    $columnsRes = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    while ($columnsRes && ($columnRow = mysqli_fetch_assoc($columnsRes))) {
        $existingColumns[(string)($columnRow['Field'] ?? '')] = true;
    }

    $fieldAliasMap = [
        'opera_username' => 'opera',
        'opera' => 'opera',
    ];
    $catalogSql = "SELECT code
                   FROM `system_access`
                   WHERE company_id={$company_id} AND active=1";
    $catalogRes = mysqli_query($conn, $catalogSql);
    while ($catalogRes && ($catalogRow = mysqli_fetch_assoc($catalogRes))) {
        $rawCode = cr_onboarding_normalize_system_access_code((string)($catalogRow['code'] ?? ''));
        if ($rawCode === '') {
            continue;
        }
        $fieldName = (string)($fieldAliasMap[$rawCode] ?? $rawCode);
        if ($fieldName === '' || isset($existingColumns[$fieldName])) {
            continue;
        }
        if (!function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($fieldName)) {
            continue;
        }

        $alterSql = 'ALTER TABLE ' . cr_escape_identifier($table)
            . ' ADD COLUMN ' . cr_escape_identifier($fieldName)
            . " VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL";
        if (mysqli_query($conn, $alterSql)) {
            $existingColumns[$fieldName] = true;
        }
    }
}

function cr_sync_onboarding_status_columns($conn) {
    if (!cr_is_employee_onboarding_module()) {
        return;
    }

    $table = 'employee_onboarding_requests';
    $requiredColumns = ['status_hod', 'status_hrd', 'status_ism', 'status_gm', 'status_fin'];
    $existingColumns = [];
    $columnsRes = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    while ($columnsRes && ($columnRow = mysqli_fetch_assoc($columnsRes))) {
        $existingColumns[(string)($columnRow['Field'] ?? '')] = true;
    }

    foreach ($requiredColumns as $columnName) {
        if (isset($existingColumns[$columnName])) {
            continue;
        }
        if (!function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($columnName)) {
            continue;
        }

        $alterSql = 'ALTER TABLE ' . cr_escape_identifier($table)
            . ' ADD COLUMN ' . cr_escape_identifier($columnName)
            . " VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Waiting'";
        if (mysqli_query($conn, $alterSql)) {
            $existingColumns[$columnName] = true;
        }
    }
}

function cr_sync_onboarding_email_tracking_columns($conn) {
    if (!cr_is_employee_onboarding_module()) {
        return;
    }

    $table = 'employee_onboarding_requests';
    $requiredDefinitions = [
        'gm_approval' => " VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL",
        'gm_approval_date' => " DATE DEFAULT NULL",
        'fin_approval' => " VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL",
        'fin_approval_date' => " DATE DEFAULT NULL",
        'email_sent_hod' => " TINYINT NOT NULL DEFAULT '0'",
        'email_sent_hod_at' => " DATETIME DEFAULT NULL",
        'email_sent_hrd' => " TINYINT NOT NULL DEFAULT '0'",
        'email_sent_hrd_at' => " DATETIME DEFAULT NULL",
        'email_sent_ism' => " TINYINT NOT NULL DEFAULT '0'",
        'email_sent_ism_at' => " DATETIME DEFAULT NULL",
        'email_sent_gm' => " TINYINT NOT NULL DEFAULT '0'",
        'email_sent_gm_at' => " DATETIME DEFAULT NULL",
        'email_sent_fin' => " TINYINT NOT NULL DEFAULT '0'",
        'email_sent_fin_at' => " DATETIME DEFAULT NULL",
    ];
    $existingColumns = [];
    $columnsRes = mysqli_query($conn, 'DESCRIBE ' . cr_escape_identifier($table));
    while ($columnsRes && ($columnRow = mysqli_fetch_assoc($columnsRes))) {
        $existingColumns[(string)($columnRow['Field'] ?? '')] = true;
    }

    foreach ($requiredDefinitions as $columnName => $columnSql) {
        if (isset($existingColumns[$columnName])) {
            continue;
        }
        if (!function_exists('itm_is_safe_identifier') || !itm_is_safe_identifier($columnName)) {
            continue;
        }

        $alterSql = 'ALTER TABLE ' . cr_escape_identifier($table)
            . ' ADD COLUMN ' . cr_escape_identifier($columnName) . $columnSql;
        if (mysqli_query($conn, $alterSql)) {
            $existingColumns[$columnName] = true;
        }
    }
}

function cr_is_truthy_checkbox_value($value) {
    $text = strtolower(trim((string)$value));
    if ($text === '') {
        return false;
    }
    return in_array($text, ['1', 'yes', 'true', 'on', 'active', 'y', '✅'], true);
}

function cr_format_onboarding_date($value) {
    $text = trim((string)$value);
    if ($text === '' || $text === '0000-00-00') {
        return '';
    }
    $ts = strtotime($text);
    if ($ts === false) {
        return $text;
    }
    return date('d/m/Y', $ts);
}

function cr_onboarding_display_value($value, $isDateField = false) {
    $text = trim((string)$value);
    if ($text === '' || $text === '0000-00-00') {
        return 'N/A';
    }
    if ($isDateField) {
        $formatted = cr_format_onboarding_date($text);
        return $formatted === '' ? 'N/A' : $formatted;
    }
    return sanitize($text);
}

function cr_onboarding_field_label($fieldName, $systemAccessLabels = []) {
    $fieldName = (string)$fieldName;
    $custom = trim((string)($systemAccessLabels[$fieldName] ?? ''));
    if ($custom !== '') {
        return $custom;
    }
    $customLabels = [
        'requested_by_date' => 'Date',
        'hod_approval_date' => 'Date',
        'hrd_approval_date' => 'Date',
        'ism_approval_date' => 'Date',
        'gm_approval_date' => 'Date',
        'fin_approval_date' => 'Date',
    ];
    if (isset($customLabels[$fieldName])) {
        return $customLabels[$fieldName];
    }
    if ($fieldName === 'employee_position_id') {
        return 'Position Title';
    }
    return cr_humanize_field($fieldName);
}

function cr_fk_label_by_id($conn, $fk, $id, $company_id) {
    $table = (string)$fk['REFERENCED_TABLE_NAME'];
    $col = (string)$fk['REFERENCED_COLUMN_NAME'];
    $id = (int)$id;
    if ($id <= 0) {
        return '';
    }

    $meta = cr_fk_metadata($conn, $table);
    $available = $meta['available'];
    $labelExpr = ($table === 'employees')
        ? "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(NULLIF(`first_name`,''),''), ' ', COALESCE(NULLIF(`last_name`,''),''))), ''), NULLIF(`display_name`, ''), NULLIF(`username`, ''), CONCAT('Employee #', " . cr_escape_identifier($col) . "))"
        : cr_escape_identifier($meta['label_col']);
    $baseSql = 'SELECT ' . $labelExpr . ' AS label FROM ' . cr_escape_identifier($table) . ' WHERE ' . cr_escape_identifier($col) . '=' . $id;

    if (in_array('company_id', $available, true) && $company_id > 0) {
        $companyRes = mysqli_query($conn, $baseSql . ' AND company_id=' . (int)$company_id . ' LIMIT 1');
        if ($companyRes && ($companyRow = mysqli_fetch_assoc($companyRes))) {
            $companyLabel = trim((string)($companyRow['label'] ?? ''));
            if ($companyLabel !== '') {
                return $companyLabel;
            }
        }
    }

    $fallbackRes = mysqli_query($conn, $baseSql . ' LIMIT 1');
    if ($fallbackRes && ($fallbackRow = mysqli_fetch_assoc($fallbackRes))) {
        return trim((string)($fallbackRow['label'] ?? ''));
    }

    return '';
}

function cr_department_name_options($conn, $company_id) {
    $rows = [];
    $company_id = (int)$company_id;
    if ($company_id <= 0) {
        return $rows;
    }

    $sql = "SELECT name FROM `departments` WHERE company_id={$company_id} ORDER BY name ASC";
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $name = trim((string)($row['name'] ?? ''));
        if ($name !== '') {
            $rows[] = $name;
        }
    }

    return array_values(array_unique($rows));
}

function cr_ensure_fk_selected_option($conn, &$opts, $fk, $selectedId, $company_id) {
    $selectedId = (int)$selectedId;
    if ($selectedId <= 0) {
        return;
    }
    foreach ($opts as $opt) {
        if ((int)($opt['id'] ?? 0) === $selectedId) {
            return;
        }
    }

    $label = cr_fk_label_by_id($conn, $fk, $selectedId, $company_id);
    if ($label !== '') {
        $opts[] = ['id' => $selectedId, 'label' => $label];
    }
}


function cr_get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function cr_require_valid_csrf_token() {
    $token = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        echo 'Forbidden: invalid CSRF token.';
        exit;
    }
}

function cr_numeric_validation_error($field, $message) {
    return cr_humanize_field($field) . ' ' . $message . '.';
}

/**
 * Validates and normalizes numeric input for database safety and range compliance
 */
function cr_validate_numeric_value($rawValue, $column, $fieldName, &$normalizedValue, &$error) {
    $type = strtolower((string)$column['Type']);
    $isUnsigned = str_contains($type, 'unsigned');
    $raw = trim((string)$rawValue);

    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/', $type, $match)) {
        $intVal = filter_var($raw, FILTER_VALIDATE_INT);
        if ($intVal === false) {
            $error = cr_numeric_validation_error($fieldName, 'must be a valid integer');
            return false;
        }

        $ranges = [
            'tinyint' => [-128, 127, 0, 255],
            'smallint' => [-32768, 32767, 0, 65535],
            'mediumint' => [-8388608, 8388607, 0, 16777215],
            'int' => [-2147483648, 2147483647, 0, 4294967295],
        ];
        $typeName = $match[1];

        if (isset($ranges[$typeName])) {
            [$signedMin, $signedMax, $unsignedMin, $unsignedMax] = $ranges[$typeName];
            $min = $isUnsigned ? $unsignedMin : $signedMin;
            $max = $isUnsigned ? $unsignedMax : $signedMax;
            if ($intVal < $min || $intVal > $max) {
                $error = cr_numeric_validation_error($fieldName, 'is out of range');
                return false;
            }
        } elseif ($typeName === 'bigint' && $isUnsigned && $intVal < 0) {
            $error = cr_numeric_validation_error($fieldName, 'must be zero or greater');
            return false;
        }

        $normalizedValue = (string)$intVal;
        return true;
    }

    if (preg_match('/^(decimal|float|double)\b/', $type)) {
        if (!is_numeric($raw)) {
            $error = cr_numeric_validation_error($fieldName, 'must be a valid number');
            return false;
        }

        $floatVal = (float)$raw;
        if (!is_finite($floatVal)) {
            $error = cr_numeric_validation_error($fieldName, 'must be a finite number');
            return false;
        }

        if ($isUnsigned && $floatVal < 0) {
            $error = cr_numeric_validation_error($fieldName, 'must be zero or greater');
            return false;
        }

        $normalizedValue = (string)$raw;
        return true;
    }

    $error = cr_numeric_validation_error($fieldName, 'has an unsupported numeric type');
    return false;
}

// Module initialization: load columns and foreign key maps
if (cr_is_employee_onboarding_module()) {
    cr_sync_onboarding_status_columns($conn);
    cr_sync_onboarding_email_tracking_columns($conn);
    cr_sync_onboarding_system_access_columns($conn, (int)$company_id);
}
$columns = cr_table_columns($conn, $crud_table);
$fkMap = cr_fk_map($conn, $crud_table);
$fieldColumns = cr_manageable_columns($columns);
$fieldColumnsByName = [];
foreach ($fieldColumns as $itmFieldColumnMeta) {
    $fieldColumnsByName[(string)($itmFieldColumnMeta['Field'] ?? '')] = $itmFieldColumnMeta;
}
$fieldColumns = array_values(array_filter($fieldColumns, function ($col) {
    return !cr_is_hidden_employee_field($col['Field']);
}));
$hasCompany = false;
foreach ($fieldColumns as $c) {
    if ($c['Field'] === 'company_id') { $hasCompany = true; break; }
}


$hideCompanyIdTables = ['workstation_ram', 'workstation_os_versions', 'workstation_os_types', 'workstation_office', 'workstation_modes', 'workstation_device_types', 'warranty_types', 'user_roles', 'ui_configuration', 'switch_port_types', 'switch_port_numbering_layout', 'sidebar_layout', 'role_module_permissions', 'role_hierarchy', 'role_assignment_rights', 'printer_device_types', 'inventory_items', 'inventory_categories', 'idf_positions', 'idf_ports', 'idf_links', 'equipment_rj45', 'equipment_poe', 'equipment_fiber_rack', 'equipment_fiber_patch', 'equipment_fiber_count', 'equipment_fiber', 'equipment_environment', 'assignment_types', 'access_levels', 'employee_onboarding_requests', 'ticket_priorities', 'ticket_statuses', 'ticket_categories', 'switch_status', 'rack_statuses', 'racks', 'supplier_statuses', 'suppliers', 'manufacturers', 'equipment_statuses', 'equipment_types', 'location_types', 'it_locations', 'users', 'departments'];
$uiColumns = array_values(array_filter($fieldColumns, function ($col) use ($hideCompanyIdTables) {
    if (($col['Field'] ?? '') !== 'company_id') {
        return true;
    }
    return !in_array((string)($GLOBALS['crud_table'] ?? ''), $hideCompanyIdTables, true);
}));
if (cr_is_employee_onboarding_module()) {
    $uiColumns = array_values(array_filter($uiColumns, static function ($col) {
        return !in_array((string)($col['Field'] ?? ''), ['requested_on', 'status_hod', 'status_hrd', 'status_ism', 'status_gm', 'status_fin', 'email_sent_hod', 'email_sent_hod_at', 'email_sent_hrd', 'email_sent_hrd_at', 'email_sent_ism', 'email_sent_ism_at', 'email_sent_gm', 'email_sent_gm_at', 'email_sent_fin', 'email_sent_fin_at'], true);
    }));
}
$listColumns = $uiColumns;
if (cr_is_employee_onboarding_module()) {
    $listColumns = array_values(array_filter($listColumns, static function ($col) {
        $field = (string)($col['Field'] ?? '');
        return !in_array($field, ['employee', 'employee_id'], true);
    }));
}

$modulePath = dirname($_SERVER['PHP_SELF']);
$listUrl = $modulePath . '/index.php';
$csrfToken = cr_get_csrf_token();
$onboardingSystemAccessLabels = cr_onboarding_system_access_labels($conn, (int)$company_id);
$onboardingSystemAccessFields = array_fill_keys([
    'network_access',
    'micros_emc',
    'opera',
    'micros_card',
    'pms_id',
    'synergy_mms',
    'email_account',
    'landline_phone',
    'hu_the_lobby',
    'mobile_phone',
    'navision',
    'mobile_email',
    'onq_ri',
    'birchstreet',
    'delphi',
    'omina',
    'vingcard_system',
    'digital_rev',
    'office_key_card',
], true);
foreach (array_keys($onboardingSystemAccessLabels) as $itmOnboardingDynamicField) {
    $onboardingSystemAccessFields[(string)$itmOnboardingDynamicField] = true;
}

$onboardingVisibleFields = [];
foreach ($fieldColumns as $itmOnboardingColumn) {
    $onboardingVisibleFields[(string)($itmOnboardingColumn['Field'] ?? '')] = true;
}

$onboardingAccessFieldsOrdered = [
    'network_access',
    'micros_emc',
    'opera',
    'micros_card',
    'pms_id',
    'synergy_mms',
    'email_account',
    'landline_phone',
    'hu_the_lobby',
    'mobile_phone',
    'navision',
    'mobile_email',
    'onq_ri',
    'birchstreet',
    'delphi',
    'omina',
    'vingcard_system',
    'digital_rev',
    'office_key_card',
];
$onboardingDynamicAccessFields = [];
foreach ($onboardingAccessFieldsOrdered as $itmAccessFieldName) {
    if ($itmAccessFieldName === 'office_key_card') {
        // Keep Office Key Card aligned with its department selector in a dedicated paired row.
        continue;
    }
    if (!isset($onboardingVisibleFields[$itmAccessFieldName]) || !isset($onboardingSystemAccessFields[$itmAccessFieldName])) {
        continue;
    }
    $onboardingDynamicAccessFields[] = $itmAccessFieldName;
}
foreach (array_keys($onboardingSystemAccessLabels) as $itmAccessFieldName) {
    $itmAccessFieldName = (string)$itmAccessFieldName;
    if ($itmAccessFieldName === 'office_key_card') {
        continue;
    }
    if (!isset($onboardingVisibleFields[$itmAccessFieldName]) || !isset($onboardingSystemAccessFields[$itmAccessFieldName])) {
        continue;
    }
    if (!in_array($itmAccessFieldName, $onboardingDynamicAccessFields, true)) {
        $onboardingDynamicAccessFields[] = $itmAccessFieldName;
    }
}

$onboardingAccessFieldPairs = [];
for ($itmAccessIndex = 0; $itmAccessIndex < count($onboardingDynamicAccessFields); $itmAccessIndex += 2) {
    $onboardingAccessFieldPairs[] = [
        $onboardingDynamicAccessFields[$itmAccessIndex],
        $onboardingDynamicAccessFields[$itmAccessIndex + 1] ?? null,
    ];
}

$onboardingRowsShared = [
    ['first_name', 'last_name'],
    ['employee_position_id', 'department_name'],
    ['request_date', 'termination_date'],
];
foreach ($onboardingAccessFieldPairs as $itmAccessPair) {
    $onboardingRowsShared[] = $itmAccessPair;
}
foreach ([
    ['office_key_card', 'office_key_card_dep'],
    ['employee_id', 'starting_date'],
    ['requested_by', 'requested_by_date'],
    ['hod_approval', 'hod_approval_date'],
    ['hrd_approval', 'hrd_approval_date'],
    ['ism_approval', 'ism_approval_date'],
    ['gm_approval', 'gm_approval_date'],
    ['fin_approval', 'fin_approval_date'],
    ['active', null],
] as $itmTailPair) {
    $onboardingRowsShared[] = $itmTailPair;
}

$onboardingRowsFiltered = [];
foreach ($onboardingRowsShared as $itmRowPair) {
    $firstField = (string)($itmRowPair[0] ?? '');
    $secondField = (string)($itmRowPair[1] ?? '');

    $firstVisible = ($firstField !== '' && isset($onboardingVisibleFields[$firstField]));
    $secondVisible = ($secondField !== '' && isset($onboardingVisibleFields[$secondField]));

    if (!$firstVisible && !$secondVisible) {
        continue;
    }

    if (!$firstVisible && $secondVisible) {
        $onboardingRowsFiltered[] = [$secondField, null];
        continue;
    }

    $onboardingRowsFiltered[] = [$firstField, $secondVisible ? $secondField : null];
}
$onboardingRowsShared = $onboardingRowsFiltered;

// Handle Excel/CSV database import requests from table-tools.js.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['index', 'list_all'], true) && strpos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    $jsonBody = json_decode((string)$rawBody, true);
    if (is_array($jsonBody) && isset($jsonBody['resolve_onboarding_approvals'])) {
        header('Content-Type: application/json');

        $requestToken = (string)($jsonBody['csrf_token'] ?? '');
        if (!itm_validate_csrf_token($requestToken)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.']);
            exit;
        }

        if (!$hasCompany || $company_id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Approver detection requires an active company.']);
            exit;
        }

        $departmentName = trim((string)($jsonBody['department_name'] ?? ''));
        $resolvedApprovals = cr_onboarding_resolve_approvals($conn, (int)$company_id, $departmentName);
        echo json_encode([
            'ok' => true,
            'department_name' => $departmentName,
            'approvals' => $resolvedApprovals,
        ]);
        exit;
    }

    if (is_array($jsonBody) && isset($jsonBody['resolve_onboarding_employee_email'])) {
        header('Content-Type: application/json');

        $requestToken = (string)($jsonBody['csrf_token'] ?? '');
        if (!itm_validate_csrf_token($requestToken)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.']);
            exit;
        }

        $employeeId = isset($jsonBody['employee_id']) ? (int)$jsonBody['employee_id'] : 0;
        $email = cr_onboarding_employee_email($conn, (int)$company_id, $employeeId);
        echo json_encode([
            'ok' => true,
            'employee_id' => $employeeId,
            'email' => $email,
        ]);
        exit;
    }

    if (is_array($jsonBody) && isset($jsonBody['import_excel_rows'])) {
        header('Content-Type: application/json');

        $requestToken = (string)($jsonBody['csrf_token'] ?? '');
        if (!itm_validate_csrf_token($requestToken)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.']);
            exit;
        }

        if (!$hasCompany || $company_id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Import requires an active company.']);
            exit;
        }

        $importRows = $jsonBody['import_excel_rows'];
        if (!is_array($importRows) || count($importRows) < 2) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'The uploaded file has no data rows.']);
            exit;
        }

        $headerRow = array_map('trim', array_map('strval', (array)($importRows[0] ?? [])));
        $columnKeys = [];
        foreach ($headerRow as $headerValue) {
            $columnKeys[] = strtolower(preg_replace('/\s+/', ' ', $headerValue));
        }

        $fieldByLabel = [];
        foreach ($fieldColumns as $col) {
            $fieldName = (string)$col['Field'];
            $fieldByLabel[strtolower((string)cr_humanize_field($fieldName))] = $col;
            $fieldByLabel[strtolower(str_replace('_', ' ', $fieldName))] = $col;
        }
        $fieldByLabel['id'] = null;

        $importColumns = [];
        foreach ($columnKeys as $labelKey) {
            $importColumns[] = $fieldByLabel[$labelKey] ?? null;
        }

        $insertedRows = 0;
        for ($rowIndex = 1; $rowIndex < count($importRows); $rowIndex++) {
            $sourceRow = (array)$importRows[$rowIndex];
            if (empty(array_filter($sourceRow, function ($v) { return trim((string)$v) !== ''; }))) {
                continue;
            }

            $rowData = [];
            foreach ($fieldColumns as $col) {
                $rowData[$col['Field']] = 'NULL';
            }

            foreach ($importColumns as $idx => $columnMeta) {
                if (!is_array($columnMeta)) {
                    continue;
                }

                $fieldName = (string)$columnMeta['Field'];
                $rawValue = trim((string)($sourceRow[$idx] ?? ''));
                if ($rawValue === '' || $rawValue === '—') {
                    continue;
                }

                if ($fieldName === 'company_id' || $fieldName === 'id') {
                    continue;
                }

                $isTinyInt = (bool)preg_match('/^tinyint(\(\d+\))?/i', (string)$columnMeta['Type']);
                if ($isTinyInt) {
                    $normalizedBool = strtolower($rawValue);
                    if (in_array($normalizedBool, ['1', 'active', 'yes', 'true', 'on', '✅'], true)) {
                        $rowData[$fieldName] = '1';
                    } elseif (in_array($normalizedBool, ['0', 'inactive', 'no', 'false', 'off', '❌'], true)) {
                        $rowData[$fieldName] = '0';
                    }
                    continue;
                }

                if (isset($fkMap[$fieldName])) {
                    $fk = $fkMap[$fieldName];
                    $options = cr_fk_options($conn, $fk, (int)$company_id);
                    $resolvedId = 0;
                    foreach ($options as $option) {
                        if (strcasecmp((string)$option['label'], $rawValue) === 0) {
                            $resolvedId = (int)$option['id'];
                            break;
                        }
                    }
                    if ($resolvedId <= 0 && ctype_digit($rawValue)) {
                        $resolvedId = (int)$rawValue;
                    }
                    $rowData[$fieldName] = $resolvedId > 0 ? (string)$resolvedId : 'NULL';
                    continue;
                }

                if (preg_match('/int|decimal|float|double/', (string)$columnMeta['Type'])) {
                    $normalizedNumeric = null; $numericError = '';
                    if (cr_validate_numeric_value($rawValue, $columnMeta, $fieldName, $normalizedNumeric, $numericError)) {
                        $rowData[$fieldName] = $normalizedNumeric;
                    }
                    continue;
                }

                $rowData[$fieldName] = "'" . mysqli_real_escape_string($conn, $rawValue) . "'";
            }

            if ($hasCompany) {
                $rowData['company_id'] = (string)(int)$company_id;
            }

            $fields = [];
            $values = [];
            foreach ($fieldColumns as $col) {
                $name = (string)$col['Field'];
                $fields[] = cr_escape_identifier($name);
                $values[] = $rowData[$name] ?? 'NULL';
            }

            $sql = 'INSERT INTO ' . cr_escape_identifier($crud_table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
            $dbErrorCode = 0; $dbErrorMessage = '';
            if (itm_run_query($conn, $sql, $dbErrorCode, $dbErrorMessage)) {
                $insertedRows++;
            }
        }

        echo json_encode(['ok' => true, 'inserted' => $insertedRows]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['approval_api'])) {
    $recordId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $target = strtolower(trim((string)($_GET['target'] ?? '')));
    $decision = strtolower(trim((string)($_GET['decision'] ?? '')));
    $token = trim((string)($_GET['token'] ?? ''));
    $statusField = cr_onboarding_status_field_by_target($target);
    $approvalDateFieldMap = [
        'hod' => 'hod_approval_date',
        'hrd' => 'hrd_approval_date',
        'ism' => 'ism_approval_date',
        'gm' => 'gm_approval_date',
        'fin' => 'fin_approval_date',
    ];
    $approvalDateField = (string)($approvalDateFieldMap[$target] ?? '');
    $decisionMap = ['approve' => 'Approved', 'decline' => 'Declined'];
    $statusValue = (string)($decisionMap[$decision] ?? '');

    if (
        $recordId <= 0
        || $statusField === ''
        || $approvalDateField === ''
        || $statusValue === ''
        || $token === ''
        || !isset($fieldColumnsByName[$statusField])
        || !isset($fieldColumnsByName[$approvalDateField])
    ) {
        http_response_code(400);
        echo 'Invalid approval link.';
        exit;
    }

    $stmt = mysqli_prepare($conn, 'SELECT company_id FROM `employee_onboarding_requests` WHERE id=? LIMIT 1');
    $recordCompanyId = 0;
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $recordId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
        $recordCompanyId = (int)($row['company_id'] ?? 0);
    }

    if ($recordCompanyId <= 0) {
        http_response_code(404);
        echo 'Request not found.';
        exit;
    }

    $expectedToken = cr_onboarding_sign_approval_action($recordId, $recordCompanyId, $target, $decision);
    if (!hash_equals($expectedToken, $token)) {
        http_response_code(403);
        echo 'Invalid or expired approval token.';
        exit;
    }

    $statusEsc = mysqli_real_escape_string($conn, $statusValue);
    $updateParts = [
        cr_escape_identifier($statusField) . "='" . $statusEsc . "'",
    ];
    if ($decision === 'approve') {
        $updateParts[] = cr_escape_identifier($approvalDateField) . "=CURDATE()";
    }
    $updateSql = 'UPDATE `employee_onboarding_requests` SET ' . implode(', ', $updateParts) . ' WHERE id=' . $recordId . ' LIMIT 1';
    $dbErrorCode = 0;
    $dbErrorMessage = '';
    if (!itm_run_query($conn, $updateSql, $dbErrorCode, $dbErrorMessage)) {
        http_response_code(500);
        echo 'Failed to save approval decision.';
        exit;
    }

    echo 'Approval status updated: ' . sanitize($statusValue) . '. You may close this tab.';
    exit;
}

// Handle deletion requests (bulk or single)
if ($crud_action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo 'Method not allowed.';
        exit;
    }

    cr_require_valid_csrf_token();

    $bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');
    $dbErrorCode = 0;
    $dbErrorMessage = '';

    if ($bulkAction === 'clear_table') {
        // Truncate the table within the company scope
        $where = '';
        if ($hasCompany && $company_id > 0) {
            $where = ' WHERE company_id=' . (int)$company_id;
        }
        $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where;
        if (!itm_run_query($conn, $deleteSql, $dbErrorCode, $dbErrorMessage)) {
            $_SESSION['crud_error'] = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
        }
        header('Location: ' . $listUrl);
        exit;
    }

    if ($bulkAction === 'bulk_delete') {
        // Delete selected multiple records
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $idList = [];
        foreach ($ids as $rawId) {
            $id = (int)$rawId;
            if ($id > 0) {
                $idList[$id] = $id;
            }
        }

        if (!empty($idList)) {
            $where = ' WHERE id IN (' . implode(',', array_values($idList)) . ')';
            if ($hasCompany && $company_id > 0) {
                $where .= ' AND company_id=' . (int)$company_id;
            }
            $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where;
            if (!itm_run_query($conn, $deleteSql, $dbErrorCode, $dbErrorMessage)) {
                $_SESSION['crud_error'] = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
            }
        } else {
            $_SESSION['crud_error'] = 'No records selected for deletion.';
        }
        header('Location: ' . $listUrl);
        exit;
    }

    // Delete a single record
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0) {
        $where = ' WHERE id=' . $id;
        if ($hasCompany && $company_id > 0) {
            $where .= ' AND company_id=' . (int)$company_id;
        }
        $deleteSql = 'DELETE FROM ' . cr_escape_identifier($crud_table) . $where . ' LIMIT 1';
        if (!itm_run_query($conn, $deleteSql, $dbErrorCode, $dbErrorMessage)) {
            $_SESSION['crud_error'] = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
        }
    }
    header('Location: ' . $listUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $crud_action === 'view' && isset($_POST['send_approval_email'])) {
    cr_require_valid_csrf_token();

    $approvalTarget = trim((string)($_POST['send_approval_email'] ?? ''));
    $approvalTypeMap = [
        'hod' => 'HOD Approval',
        'hrd' => 'HRD Approval',
        'ism' => 'ISM Approval',
        'gm' => 'GM Approval',
        'fin' => 'FIN Approval',
    ];
    $approvalType = (string)($approvalTypeMap[$approvalTarget] ?? '');
    $recordId = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($approvalType === '' || $recordId <= 0) {
        $invalidParts = [];
        if ($approvalType === '') {
            $invalidParts[] = 'approval target "' . $approvalTarget . '" is not valid';
        }
        if ($recordId <= 0) {
            $invalidParts[] = 'record id is missing or invalid';
        }
        $_SESSION['crud_error'] = 'Invalid approval request: ' . implode('; ', $invalidParts) . '.';
        header('Location: view.php?id=' . $recordId);
        exit;
    }

    if (!$hasCompany || (int)$company_id <= 0) {
        $_SESSION['crud_error'] = 'Approval email requires an active company.';
        header('Location: view.php?id=' . $recordId);
        exit;
    }

    $stmt = mysqli_prepare($conn, 'SELECT id, department_name, first_name, last_name, email_account FROM `employee_onboarding_requests` WHERE id=? AND company_id=? LIMIT 1');
    $record = null;
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $recordId, $company_id);
        mysqli_stmt_execute($stmt);
        $recordResult = mysqli_stmt_get_result($stmt);
        $record = $recordResult ? mysqli_fetch_assoc($recordResult) : null;
        mysqli_stmt_close($stmt);
    }

    if (!$record) {
        $_SESSION['crud_error'] = 'Record not found.';
        header('Location: index.php');
        exit;
    }

    if (in_array($approvalTarget, ['gm', 'fin'], true) && !cr_is_truthy_checkbox_value($record['email_account'] ?? '')) {
        $_SESSION['crud_error'] = 'GM/FIN approvals can only be sent when Email Account is enabled.';
        header('Location: view.php?id=' . $recordId);
        exit;
    }

    $departmentName = trim((string)($record['department_name'] ?? ''));
    $approverContact = cr_onboarding_find_active_approver_contact($conn, (int)$company_id, $departmentName, $approvalType);
    if ($approvalTarget !== 'hod' && trim((string)($approverContact['email'] ?? '')) === '') {
        $approverContact = cr_onboarding_find_active_approver_contact_by_type($conn, (int)$company_id, $approvalType);
    }

    $approverEmail = trim((string)($approverContact['email'] ?? ''));
    $approverName = trim((string)($approverContact['name'] ?? ''));

    if ($approverEmail === '') {
        $_SESSION['crud_error'] = $approvalType . ' email was not found in approvers configuration.';
        header('Location: view.php?id=' . $recordId);
        exit;
    }

    $employeeFullName = trim((string)($record['first_name'] ?? '') . ' ' . (string)($record['last_name'] ?? ''));
    if ($employeeFullName === '') {
        $employeeFullName = 'Employee Onboarding Request #' . $recordId;
    }

    $approvalTokenApprove = cr_onboarding_sign_approval_action($recordId, (int)$company_id, $approvalTarget, 'approve');
    $approvalTokenDecline = cr_onboarding_sign_approval_action($recordId, (int)$company_id, $approvalTarget, 'decline');
    $approvalApproveUrl = BASE_URL . 'modules/employee_onboarding_requests/index.php?approval_api=1&id=' . $recordId . '&target=' . urlencode($approvalTarget) . '&decision=approve&token=' . urlencode($approvalTokenApprove);
    $approvalDeclineUrl = BASE_URL . 'modules/employee_onboarding_requests/index.php?approval_api=1&id=' . $recordId . '&target=' . urlencode($approvalTarget) . '&decision=decline&token=' . urlencode($approvalTokenDecline);

    $subject = 'Email request for ' . $employeeFullName;
    $htmlBody = '<p>Hello ' . sanitize($approverName !== '' ? $approverName : 'Approver') . ',</p>'
        . '<p>Please review and approve onboarding request <strong>#' . (int)$recordId . '</strong> for <strong>' . sanitize($employeeFullName) . '</strong>.</p>'
        . '<p><a href="' . sanitize($approvalApproveUrl) . '">API Link: Approve</a></p>'
        . '<p><a href="' . sanitize($approvalDeclineUrl) . '">API Link: Decline</a></p>'
        . '<p><a href="' . sanitize(BASE_URL . 'modules/employee_onboarding_requests/view.php?id=' . $recordId) . '">Open request details</a></p>';

    $sendError = '';
    if (!cr_onboarding_send_approval_email_via_api($approverEmail, $approverName, $subject, $htmlBody, $sendError)) {
        $_SESSION['crud_error'] = $sendError;
    } else {
        $emailSentField = '';
        $emailSentAtField = '';
        if ($approvalTarget === 'hod') {
            $emailSentField = 'email_sent_hod';
            $emailSentAtField = 'email_sent_hod_at';
        } elseif ($approvalTarget === 'hrd') {
            $emailSentField = 'email_sent_hrd';
            $emailSentAtField = 'email_sent_hrd_at';
        } elseif ($approvalTarget === 'ism') {
            $emailSentField = 'email_sent_ism';
            $emailSentAtField = 'email_sent_ism_at';
        } elseif ($approvalTarget === 'gm') {
            $emailSentField = 'email_sent_gm';
            $emailSentAtField = 'email_sent_gm_at';
        } elseif ($approvalTarget === 'fin') {
            $emailSentField = 'email_sent_fin';
            $emailSentAtField = 'email_sent_fin_at';
        }
        if ($emailSentField !== '' && $emailSentAtField !== '' && isset($fieldColumnsByName[$emailSentField]) && isset($fieldColumnsByName[$emailSentAtField])) {
            $trackSql = 'UPDATE `employee_onboarding_requests` SET '
                . cr_escape_identifier($emailSentField) . "=1, "
                . cr_escape_identifier($emailSentAtField) . '=NOW() '
                . 'WHERE id=' . $recordId . ' AND company_id=' . (int)$company_id . ' LIMIT 1';
            $trackErrCode = 0;
            $trackErrMessage = '';
            itm_run_query($conn, $trackSql, $trackErrCode, $trackErrMessage);
        }
        $_SESSION['crud_success'] = $approvalType . ' email sent to ' . $approverEmail . '.';
    }

    header('Location: view.php?id=' . $recordId);
    exit;
}

$errors = [];
if (!empty($_SESSION['crud_error'])) {
    $errors[] = (string)$_SESSION['crud_error'];
    unset($_SESSION['crud_error']);
}
$successMessage = '';
if (!empty($_SESSION['crud_success'])) {
    $successMessage = (string)$_SESSION['crud_success'];
    unset($_SESSION['crud_success']);
}
$data = [];
foreach ($fieldColumns as $col) {
    $data[$col['Field']] = '';
}
$onboardingRequestedByDefault = cr_current_user_display_name($conn, (int)$company_id);

if ($crud_action === 'create' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (array_key_exists('starting_date', $data)) {
        $data['starting_date'] = '';
    }
    if (array_key_exists('active', $data)) {
        $data['active'] = 1;
    }
    if (array_key_exists('comments', $data)) {
        $data['comments'] = 'Email:';
    }
    if (array_key_exists('requested_by', $data) && $onboardingRequestedByDefault !== '') {
        $data['requested_by'] = $onboardingRequestedByDefault;
    }
    if (array_key_exists('requested_by_date', $data)) {
        $data['requested_by_date'] = date('Y-m-d');
    }
    if (array_key_exists('request_date', $data)) {
        $data['request_date'] = date('Y-m-d');
    }
    foreach (['status_hod', 'status_hrd', 'status_ism', 'status_gm', 'status_fin'] as $statusField) {
        if (array_key_exists($statusField, $data) && trim((string)$data[$statusField]) === '') {
            $data[$statusField] = 'Waiting';
        }
    }
    foreach (['email_sent_hod', 'email_sent_hrd', 'email_sent_ism', 'email_sent_gm', 'email_sent_fin'] as $emailSentField) {
        if (array_key_exists($emailSentField, $data)) {
            $data[$emailSentField] = 0;
        }
    }
    foreach (['email_sent_hod_at', 'email_sent_hrd_at', 'email_sent_ism_at', 'email_sent_gm_at', 'email_sent_fin_at'] as $emailSentAtField) {
        if (array_key_exists($emailSentAtField, $data)) {
            $data[$emailSentAtField] = '';
        }
    }
}

$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch record details for Edit or View pages
if (in_array($crud_action, ['edit', 'view'], true) && $editId > 0) {
    $where = ' WHERE id=' . $editId;
    if ($hasCompany && $company_id > 0) {
        $where .= ' AND company_id=' . (int)$company_id;
    }
    $q = mysqli_query($conn, 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' LIMIT 1');
    $data = ($q && mysqli_num_rows($q) === 1) ? mysqli_fetch_assoc($q) : [];
    if (!$data) {
        $errors[] = 'Record not found.';
    }
}

// Handle record submission (Create or Edit)

// Handle sample data seeding for empty companies in list view
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['index', 'list_all'], true) && isset($_POST['add_sample_data'])) {
    cr_require_valid_csrf_token();

    if (!$hasCompany || $company_id <= 0) {
        $_SESSION['crud_error'] = 'Sample data requires an active company.';
        header('Location: ' . $listUrl);
        exit;
    }

    $where = ' WHERE company_id=' . (int)$company_id;
    $countSql = 'SELECT COUNT(*) AS total_rows FROM ' . cr_escape_identifier($crud_table) . $where;
    $countResult = mysqli_query($conn, $countSql);
    $existingRows = 0;
    if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
        $existingRows = (int)($countRow['total_rows'] ?? 0);
    }

    if ($existingRows > 0) {
        $_SESSION['crud_error'] = 'Sample data can only be added when no records exist.';
        header('Location: ' . $listUrl);
        exit;
    }

    $seedError = '';
    $insertedRows = itm_seed_table_from_database_sql($conn, $crud_table, (int)$company_id, $seedError);
    if ($insertedRows <= 0 && $seedError !== '') {
        $_SESSION['crud_error'] = $seedError;
    }

    header('Location: ' . $listUrl);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true)) {
    cr_require_valid_csrf_token();

    foreach ($fieldColumns as $col) {
        $name = $col['Field'];
        $isTinyInt = (bool)preg_match('/^tinyint(\(\d+\))?/i', (string)$col['Type']);
        $isOnboardingSystemAccessField = cr_is_employee_onboarding_module() && isset($onboardingSystemAccessFields[$name]);

        if ($isOnboardingSystemAccessField) {
            $data[$name] = isset($_POST[$name]) ? "'1'" : "'0'";
            continue;
        }

        // Normalize boolean flags from checkboxes
        if ($isTinyInt) {
            $data[$name] = isset($_POST[$name]) ? 1 : 0;
            continue;
        }

        // Automatically assign company ID
        if ($name === 'company_id' && $company_id > 0) {
            $data[$name] = (int)$company_id;
            continue;
        }

        if ($name === 'requested_by' && $crud_action === 'create' && $onboardingRequestedByDefault !== '') {
            $data[$name] = "'" . mysqli_real_escape_string($conn, $onboardingRequestedByDefault) . "'";
            continue;
        }

        if ($name === 'request_date' && $crud_action === 'create') {
            $requestDate = trim((string)($_POST['request_date'] ?? ''));
            if ($requestDate === '') {
                $requestDate = date('Y-m-d');
            }
            $data[$name] = "'" . mysqli_real_escape_string($conn, $requestDate) . "'";
            continue;
        }

        if ($name === 'requested_by_date' && $crud_action === 'create') {
            $requestedByDate = trim((string)($_POST['requested_by_date'] ?? ''));
            if ($requestedByDate === '') {
                $requestedByDate = date('Y-m-d');
            }
            $data[$name] = "'" . mysqli_real_escape_string($conn, $requestedByDate) . "'";
            continue;
        }

        if (in_array($name, ['status_hod', 'status_hrd', 'status_ism', 'status_gm', 'status_fin'], true)) {
            $statusValue = trim((string)($_POST[$name] ?? ''));
            if ($statusValue === '') {
                $statusValue = 'Waiting';
            }
            $data[$name] = "'" . mysqli_real_escape_string($conn, $statusValue) . "'";
            continue;
        }

        // Special handling for foreign key inline creation
        if (isset($fkMap[$name])) {
            $value = $_POST[$name] ?? null;
            $newKey = $name . '__new_value';
            $newValueRaw = trim((string)($_POST[$newKey] ?? ''));

            if ($value === '__add_new__') {
                $errors[] = 'Please wait for the new value to be created before saving.';
                $data[$name] = 'NULL';
                continue;
            }

            if ($value === '__new__' && $newValueRaw !== '') {
                // If a new value was typed into the dropdown, create it first
                $fk = $fkMap[$name];
                $fkTable = $fk['REFERENCED_TABLE_NAME'];
                $fkCol = $fk['REFERENCED_COLUMN_NAME'];
                $meta = cr_fk_metadata($conn, $fkTable);
                $labelCol = $meta['label_col'];
                $available = $meta['available'];
                $newValueEsc = mysqli_real_escape_string($conn, $newValueRaw);

                $findSql = 'SELECT ' . cr_escape_identifier($fkCol) . ' AS id FROM ' . cr_escape_identifier($fkTable)
                    . ' WHERE ' . cr_escape_identifier($labelCol) . "='" . $newValueEsc . "'";
                if (in_array('company_id', $available, true) && $company_id > 0) {
                    $findSql .= ' AND company_id=' . (int)$company_id;
                }
                $findSql .= ' LIMIT 1';
                $existing = mysqli_query($conn, $findSql);
                if ($existing && mysqli_num_rows($existing) > 0) {
                    $row = mysqli_fetch_assoc($existing);
                    $data[$name] = (string)(int)$row['id'];
                } else {
                    $insertFields = [cr_escape_identifier($labelCol)];
                    $insertValues = ["'" . $newValueEsc . "'"];
                    if (in_array('company_id', $available, true) && $company_id > 0) {
                        $insertFields[] = '`company_id`';
                        $insertValues[] = (string)(int)$company_id;
                    }
                    $insertSql = 'INSERT INTO ' . cr_escape_identifier($fkTable)
                        . ' (' . implode(',', $insertFields) . ') VALUES (' . implode(',', $insertValues) . ')';
                    $dbErrorCode = 0;
                    $dbErrorMessage = '';
                    if (itm_run_query($conn, $insertSql, $dbErrorCode, $dbErrorMessage)) {
                        $data[$name] = (string)(int)mysqli_insert_id($conn);
                    } else {
                        $errors[] = 'Could not add related value for ' . $name . '. ' . itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
                        $data[$name] = 'NULL';
                    }
                }
                continue;
            }
        }

        if ($name === 'department_name') {
            $value = $_POST[$name] ?? null;
            $newKey = $name . '__new_value';
            $newValueRaw = trim((string)($_POST[$newKey] ?? ''));

            if ($value === '__add_new__') {
                $errors[] = 'Please wait for the new department to be created before saving.';
                $data[$name] = 'NULL';
                continue;
            }

            if ($value === '__new__' && $newValueRaw !== '') {
                $deptEsc = mysqli_real_escape_string($conn, $newValueRaw);
                $deptFindSql = "SELECT id FROM `departments` WHERE `name`='{$deptEsc}'";
                if ($company_id > 0) {
                    $deptFindSql .= ' AND company_id=' . (int)$company_id;
                }
                $deptFindSql .= ' LIMIT 1';
                $deptExisting = mysqli_query($conn, $deptFindSql);

                if (!($deptExisting && mysqli_num_rows($deptExisting) > 0)) {
                    $deptInsertSql = "INSERT INTO `departments` (`name`, `company_id`) VALUES ('{$deptEsc}', " . (int)$company_id . ")";
                    $deptErrorCode = 0;
                    $deptErrorMessage = '';
                    if (!itm_run_query($conn, $deptInsertSql, $deptErrorCode, $deptErrorMessage)) {
                        $errors[] = 'Could not add related value for department_name. ' . itm_format_db_constraint_error($deptErrorCode, $deptErrorMessage);
                        $data[$name] = 'NULL';
                        continue;
                    }
                }

                $data[$name] = "'" . $deptEsc . "'";
                continue;
            }
        }

        // Sanitize regular field values
        $value = $_POST[$name] ?? null;
        if ($value === '' || $value === null) {
            $data[$name] = 'NULL';
        } elseif (preg_match('/int|decimal|float|double/', $col['Type'])) {
            $normalizedNumeric = null;
            $numericError = '';
            if (!cr_validate_numeric_value($value, $col, $name, $normalizedNumeric, $numericError)) {
                $errors[] = $numericError;
                $data[$name] = 'NULL';
            } else {
                $data[$name] = $normalizedNumeric;
            }
        } else {
            $data[$name] = "'" . mysqli_real_escape_string($conn, $value) . "'";
        }
    }

    cr_onboarding_auto_fill_approvals(
        $conn,
        (int)$company_id,
        $data,
        $_POST['department_name'] ?? ($data['department_name'] ?? '')
    );

    if (cr_is_employee_onboarding_module() && array_key_exists('comments', $data) && array_key_exists('employee_id', $data)) {
        $employeeIdForComment = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;
        $commentInput = $_POST['comments'] ?? ($data['comments'] ?? '');
        $commentWithEmail = cr_onboarding_comment_with_employee_email($conn, (int)$company_id, $employeeIdForComment, $commentInput);
        if ($commentWithEmail === '') {
            $data['comments'] = 'NULL';
        } else {
            $data['comments'] = "'" . mysqli_real_escape_string($conn, $commentWithEmail) . "'";
        }
    }

    // Build and execute the dynamic query
    if (empty($errors)) {
        if ($crud_action === 'create') {
            $fields = [];
            $values = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $fields[] = cr_escape_identifier($name);
                $values[] = $data[$name];
            }
            $sql = 'INSERT INTO ' . cr_escape_identifier($crud_table) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')';
        } else {
            $sets = [];
            foreach ($fieldColumns as $col) {
                $name = $col['Field'];
                $sets[] = cr_escape_identifier($name) . '=' . $data[$name];
            }
            $where = ' WHERE id=' . $editId;
            if ($hasCompany && $company_id > 0) {
                $where .= ' AND company_id=' . (int)$company_id;
            }
            $sql = 'UPDATE ' . cr_escape_identifier($crud_table) . ' SET ' . implode(',', $sets) . $where . ' LIMIT 1';
        }

        $dbErrorCode = 0;
        $dbErrorMessage = '';
        if (itm_run_query($conn, $sql, $dbErrorCode, $dbErrorMessage)) {
            header('Location: ' . $listUrl);
            exit;
        }
        $errors[] = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
    }
}

// BUILD THE MAIN LIST DATA QUERY
$where = '';
if ($hasCompany && $company_id > 0) {
    $where = ' WHERE company_id=' . (int)$company_id;
}

// Handle global text search across all columns
$searchRaw = trim((string)($_GET['search'] ?? ''));
if ($searchRaw !== '') {
    $searchPattern = (str_contains($searchRaw, '%') || str_contains($searchRaw, '_')) ? $searchRaw : '%' . $searchRaw . '%';
    $searchEsc = mysqli_real_escape_string($conn, $searchPattern);
    $searchConditions = ["CAST(`id` AS CHAR) LIKE '{$searchEsc}'"];
    foreach ($fieldColumns as $col) {
        $fieldName = (string)($col['Field'] ?? '');
        if ($fieldName === '') {
            continue;
        }
        $searchConditions[] = 'CAST(' . cr_escape_identifier($fieldName) . " AS CHAR) LIKE '{$searchEsc}'";
    }

    if (!empty($searchConditions)) {
        $where .= ($where === '' ? ' WHERE ' : ' AND ') . '(' . implode(' OR ', $searchConditions) . ')';
    }
}

// Handle sorting
$sortableColumns = array_map(static function ($col) {
    return $col['Field'];
}, $uiColumns);

$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'id';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}
$sortSql = cr_escape_identifier($sort) . ' ' . $dir;

// Pagination logic
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$countResult = mysqli_query($conn, 'SELECT COUNT(*) AS total_rows FROM ' . cr_escape_identifier($crud_table) . $where);
$totalRows = 0;
if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
    $totalRows = (int)($countRow['total_rows'] ?? 0);
}
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Final data fetch
$rows = mysqli_query($conn, 'SELECT * FROM ' . cr_escape_identifier($crud_table) . $where . ' ORDER BY ' . $sortSql . ' LIMIT ' . $offset . ', ' . $perPage);
$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: ('🧩 ' . $crud_title);
$newButtonPosition = (string)(($ui_config ?? [])['new_button_position'] ?? 'left_right');
if (!in_array($newButtonPosition, ['left', 'right', 'left_right'], true)) {
    $newButtonPosition = 'left_right';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($crud_title); ?> Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error"><?php echo sanitize(implode(' ', $errors)); ?></div>
            <?php endif; ?>
            <?php if ($successMessage !== ''): ?>
                <div class="alert alert-success"><?php echo sanitize($successMessage); ?></div>
            <?php endif; ?>

            <?php if (in_array($crud_action, ['index', 'list_all'], true)): ?>
                <!-- DATA LIST VIEW -->
                <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
                    <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                        <a href="create.php" class="btn btn-primary">➕</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                    <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                        <a href="create.php" class="btn btn-primary">➕</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                </div>
            <div class="card" style="margin-bottom:16px;">
                <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                    <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                </form>
            </div>


                <div class="card" style="margin-bottom:16px;">
                    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                        <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                        <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                        <input type="hidden" name="page" value="1">
                        <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                            <label for="moduleSearch">Search (all fields)</label>
                            <input type="text" id="moduleSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Type to search records...">
                        </div>
                        <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="index.php" class="btn">🔙</a>
                        </div>
                    </form>
                </div>
                <div class="card" style="overflow:auto;">
                    <table>
                        <thead>
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th>
                            <?php foreach ($listColumns as $col): ?>
                                <?php $field = (string)$col['Field']; ?>
                                <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                                <th>
                                    <a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>&page=<?php echo (int)$page; ?>" style="text-decoration:none;color:inherit;">
                                        <?php echo sanitize(cr_is_employee_onboarding_module() ? cr_onboarding_field_label($field, $onboardingSystemAccessLabels) : cr_humanize_field($field)); ?>
                                        <?php if ($sort === $field): ?>
                                            <?php echo $dir === 'ASC' ? '▲' : '▼'; ?>
                                        <?php endif; ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($rows && mysqli_num_rows($rows) > 0): while ($row = mysqli_fetch_assoc($rows)): ?>
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" form="bulk-delete-form"></td>
                                <?php foreach ($listColumns as $col): $f = $col['Field']; ?>
                                    <td>
                                        <?php if ($f === 'comments' && trim((string)($row[$f] ?? '')) !== ''): ?>
                                            <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                        <?php elseif (isset($fkMap[$f]) && (int)($row[$f] ?? 0) > 0): ?>
                                            <?php
                                                $fkLabel = cr_fk_label_by_id($conn, $fkMap[$f], (int)$row[$f], (int)$company_id);
                                                echo sanitize($fkLabel !== '' ? $fkLabel : (string)$row[$f]);
                                            ?>
                                        <?php elseif (cr_is_employee_onboarding_module() && in_array($f, ['request_date', 'termination_date', 'starting_date', 'requested_by_date', 'hod_approval_date', 'hrd_approval_date', 'ism_approval_date', 'gm_approval_date', 'fin_approval_date'], true)): ?>
                                            <?php echo sanitize(cr_onboarding_display_value($row[$f] ?? '', true)); ?>
                                        <?php elseif (cr_is_employee_onboarding_module() && isset($onboardingSystemAccessFields[$f])): ?>
                                            <?php echo cr_is_truthy_checkbox_value($row[$f] ?? '') ? '✅' : '❌'; ?>
                                        <?php elseif (cr_is_employee_onboarding_module() && isset($fieldColumnsByName[$f]) && preg_match('/^tinyint(\(\d+\))?/i', (string)($fieldColumnsByName[$f]['Type'] ?? ''))): ?>
                                            <?php echo ((int)($row[$f] ?? 0) === 1) ? '✅' : '❌'; ?>
                                        <?php else: ?>
                                            <?php echo cr_render_cell_value($crud_table, $f, $row[$f] ?? ''); ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td>
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>">🔎</a>
                                    <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>">✏️</a>
                                    <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                        <input type="hidden" name="bulk_action" value="single_delete">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="<?php echo count($listColumns) + 2; ?>" style="text-align:center;">No records found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($hasCompany && $company_id > 0 && $totalRows === 0): ?>
                    <div class="card" style="margin-top:12px;">
                        <form method="POST" style="display:flex;justify-content:center;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
                        </form>
                    </div>
                <?php endif; ?>
                <?php if ($totalRows > $perPage): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                        <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?>">Previous</a>
                            <?php endif; ?>
                            <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page + 1; ?>">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif (in_array($crud_action, ['create', 'edit'], true) && cr_is_employee_onboarding_module()): ?>
                <h1><?php echo $crud_action === 'create' ? 'New ' : 'Edit '; ?><?php echo sanitize($crud_title); ?></h1>
                <form method="POST" style="max-width:980px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <div class="card" style="padding:14px;">
                        <table>
                            <tbody>
                            <?php
                            $onboardingRows = $onboardingRowsShared;
                            ?>
                            <?php $departmentNameOptions = cr_department_name_options($conn, (int)$company_id); ?>
                            <?php foreach ($onboardingRows as $pair): ?>
                                <tr>
                                    <?php foreach ($pair as $name): ?>
                                        <?php if ($name === null): ?>
                                            <th style="width:180px;"></th><td></td>
                                            <?php continue; ?>
                                        <?php endif; ?>
                                        <?php $col = null; foreach ($fieldColumns as $fieldCol) { if ($fieldCol['Field'] === $name) { $col = $fieldCol; break; } } ?>
                                        <?php if ($col === null): ?>
                                            <th style="width:180px;"></th><td></td>
                                            <?php continue; ?>
                                        <?php endif; ?>
                                        <?php
                                            $isTinyInt = (bool)preg_match('/^tinyint(\(\d+\))?/i', (string)$col['Type']);
                                            $isDate = str_starts_with($col['Type'], 'date');
                                            $isDateTime = str_starts_with($col['Type'], 'datetime');
                                            $isText = str_contains($col['Type'], 'text');
                                            $val = $data[$name] ?? '';
                                            $displayVal = ($val === 'NULL') ? '' : (string)$val;
                                        ?>
                                        <th style="width:180px;"><?php echo sanitize(cr_onboarding_field_label($name, $onboardingSystemAccessLabels)); ?></th>
                                        <td>
                                            <?php if ($name === 'company_id' && $company_id > 0): ?>
                                                <input type="hidden" name="company_id" value="<?php echo (int)$company_id; ?>">
                                        <?php elseif ($name === 'employee_position_id' && isset($fkMap[$name])): ?>
                                            <?php
                                                $opts = cr_fk_options($conn, $fkMap[$name], (int)$company_id);
                                                cr_ensure_fk_selected_option($conn, $opts, $fkMap[$name], (int)$displayVal, (int)$company_id);
                                                $fkMeta = cr_fk_metadata($conn, $fkMap[$name]['REFERENCED_TABLE_NAME']);
                                                $isCompanyScoped = in_array('company_id', $fkMeta['available'], true) ? 1 : 0;
                                            ?>
                                            <div style="display:flex;gap:8px;align-items:center;">
                                                <select
                                                    name="<?php echo sanitize($name); ?>"
                                                    data-addable-select="1"
                                                    data-add-table="<?php echo sanitize($fkMap[$name]['REFERENCED_TABLE_NAME']); ?>"
                                                    data-add-id-col="<?php echo sanitize($fkMap[$name]['REFERENCED_COLUMN_NAME']); ?>"
                                                    data-add-label-col="<?php echo sanitize($fkMeta['label_col']); ?>"
                                                    data-add-company-scoped="<?php echo $isCompanyScoped; ?>"
                                                    data-add-friendly="<?php echo sanitize(strtolower(cr_humanize_field($name))); ?>"
                                                >
                                                    <option value="">-- Select --</option>
                                                    <?php foreach ($opts as $opt): ?>
                                                        <option value="<?php echo (int)$opt['id']; ?>" <?php echo ((string)$displayVal === (string)$opt['id']) ? 'selected' : ''; ?>><?php echo sanitize($opt['label']); ?></option>
                                                    <?php endforeach; ?>
                                                    <option value="__add_new__">➕</option>
                                                </select>
                                            </div>
                                        <?php elseif ($name === 'department_name' && cr_is_employee_onboarding_module()): ?>
                                            <div style="display:flex;gap:8px;align-items:center;">
                                                <select
                                                    name="department_name"
                                                    data-addable-select="1"
                                                    data-add-table="departments"
                                                    data-add-id-col="id"
                                                    data-add-label-col="name"
                                                    data-add-company-scoped="1"
                                                    data-add-friendly="department"
                                                >
                                                    <option value="">-- Select --</option>
                                                    <?php foreach ($departmentNameOptions as $deptName): ?>
                                                        <option value="<?php echo sanitize($deptName); ?>" <?php echo ($displayVal === $deptName) ? 'selected' : ''; ?>><?php echo sanitize($deptName); ?></option>
                                                    <?php endforeach; ?>
                                                    <option value="__add_new__">➕</option>
                                                </select>
                                            </div>
                                            <?php elseif ($isTinyInt): ?>
                                                <label class="itm-checkbox-control">
                                                    <input type="checkbox" name="<?php echo sanitize($name); ?>" value="1" <?php echo ((int)$displayVal === 1) ? 'checked' : ''; ?>>
                                                    <span class="itm-check-indicator" aria-hidden="true"><?php echo ((int)$displayVal === 1) ? '✅' : '❌'; ?></span>
                                                </label>
                                            <?php elseif (isset($onboardingSystemAccessFields[$name])): ?>
                                                <?php $isChecked = cr_is_truthy_checkbox_value($displayVal); ?>
                                                <label class="itm-checkbox-control">
                                                    <input type="checkbox" name="<?php echo sanitize($name); ?>" value="1" <?php echo $isChecked ? 'checked' : ''; ?>>
                                                    <span class="itm-check-indicator" aria-hidden="true"><?php echo $isChecked ? '✅' : '❌'; ?></span>
                                                </label>
                                        <?php elseif ($name === 'requested_by' && $crud_action === 'create'): ?>
                                                <input type="text" name="requested_by" value="<?php echo sanitize($onboardingRequestedByDefault !== '' ? $onboardingRequestedByDefault : $displayVal); ?>" readonly>
                                            <?php elseif (in_array($name, ['hod_approval', 'hrd_approval', 'ism_approval', 'gm_approval', 'fin_approval'], true)): ?>
                                                <input
                                                    type="text"
                                                    name="<?php echo sanitize($name); ?>"
                                                    data-onboarding-approval-field="1"
                                                    value="<?php echo sanitize($displayVal); ?>"
                                                    readonly
                                                >
                                            <?php elseif (isset($fkMap[$name])): ?>
                                                <?php
                                                    $opts = cr_fk_options($conn, $fkMap[$name], (int)$company_id);
                                                    cr_ensure_fk_selected_option($conn, $opts, $fkMap[$name], (int)$displayVal, (int)$company_id);
                                                    $fkMeta = cr_fk_metadata($conn, $fkMap[$name]['REFERENCED_TABLE_NAME']);
                                                    $isCompanyScoped = in_array('company_id', $fkMeta['available'], true) ? 1 : 0;
                                                ?>
                                                <select
                                                    name="<?php echo sanitize($name); ?>"
                                                    data-addable-select="1"
                                                    data-add-table="<?php echo sanitize($fkMap[$name]['REFERENCED_TABLE_NAME']); ?>"
                                                    data-add-id-col="<?php echo sanitize($fkMap[$name]['REFERENCED_COLUMN_NAME']); ?>"
                                                    data-add-label-col="<?php echo sanitize($fkMeta['label_col']); ?>"
                                                    data-add-company-scoped="<?php echo $isCompanyScoped; ?>"
                                                    data-add-friendly="<?php echo sanitize(strtolower(cr_humanize_field($name))); ?>"
                                                >
                                                    <option value="">-- Select --</option>
                                                    <?php foreach ($opts as $opt): ?>
                                                        <option value="<?php echo (int)$opt['id']; ?>" <?php echo ((string)$displayVal === (string)$opt['id']) ? 'selected' : ''; ?>><?php echo sanitize($opt['label']); ?></option>
                                                    <?php endforeach; ?>
                                                    <option value="__add_new__">➕</option>
                                                </select>
                                            <?php elseif ($isDateTime): ?>
                                                <input type="datetime-local" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(str_replace(' ', 'T', substr($displayVal, 0, 16))); ?>">
                                            <?php elseif ($isDate): ?>
                                                <input type="date" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(substr($displayVal, 0, 10)); ?>">
                                            <?php elseif ($isText): ?>
                                                <textarea name="<?php echo sanitize($name); ?>" rows="3"><?php echo sanitize($displayVal); ?></textarea>
                                            <?php else: ?>
                                                <input type="text" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize($displayVal); ?>">
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <th><?php echo sanitize(cr_humanize_field('comments')); ?></th>
                                <td colspan="3">
                                    <textarea name="comments" rows="4"><?php echo sanitize((string)($data['comments'] ?? '')); ?></textarea>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">💾</button>
                        <a href="index.php" class="btn">🔙</a>
                    </div>
                </form>

            <?php elseif (in_array($crud_action, ['create', 'edit'], true)): ?>
                <!-- DATA ENTRY FORM VIEW -->
                <h1><?php echo $crud_action === 'create' ? 'New ' : 'Edit '; ?><?php echo sanitize($crud_title); ?></h1>
                <form method="POST" class="form-grid" style="max-width:980px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <?php foreach ($fieldColumns as $col): $name = $col['Field'];
                        $isTinyInt = str_starts_with($col['Type'], 'tinyint(1)');
                        $isDate = str_starts_with($col['Type'], 'date');
                        $isDateTime = str_starts_with($col['Type'], 'datetime');
                        $isText = str_contains($col['Type'], 'text');
                        $val = $data[$name] ?? '';
                        $displayVal = ($val === 'NULL') ? '' : (string)$val;
                    ?>
                        <div class="form-group">
                            <label><?php echo sanitize(cr_humanize_field($name)); ?></label>
                            <?php if ($name === 'company_id' && $company_id > 0): ?>
                                <input type="hidden" name="company_id" value="<?php echo (int)$company_id; ?>">
                            <?php elseif ($isTinyInt): ?>
                                <label class="itm-checkbox-control">
                                    <input type="checkbox" name="<?php echo sanitize($name); ?>" value="1" <?php echo ((int)$displayVal === 1) ? 'checked' : ''; ?>>
                                    <span><?php echo sanitize(cr_humanize_field($name)); ?> <span class="itm-check-indicator" aria-hidden="true"><?php echo ((int)$displayVal === 1) ? '✅' : '❌'; ?></span></span>
                                </label>
                            <?php elseif (isset($fkMap[$name])): ?>
                                    <?php
                                        $opts = cr_fk_options($conn, $fkMap[$name], (int)$company_id);
                                        cr_ensure_fk_selected_option($conn, $opts, $fkMap[$name], (int)$displayVal, (int)$company_id);
                                        $fkMeta = cr_fk_metadata($conn, $fkMap[$name]['REFERENCED_TABLE_NAME']);
                                        $isCompanyScoped = in_array('company_id', $fkMeta['available'], true) ? 1 : 0;
                                    ?>
                                <select
                                    name="<?php echo sanitize($name); ?>"
                                    data-addable-select="1"
                                    data-add-table="<?php echo sanitize($fkMap[$name]['REFERENCED_TABLE_NAME']); ?>"
                                    data-add-id-col="<?php echo sanitize($fkMap[$name]['REFERENCED_COLUMN_NAME']); ?>"
                                    data-add-label-col="<?php echo sanitize($fkMeta['label_col']); ?>"
                                    data-add-company-scoped="<?php echo $isCompanyScoped; ?>"
                                    data-add-friendly="<?php echo sanitize(strtolower(cr_humanize_field($name))); ?>"
                                >
                                    <option value="">-- Select --</option>
                                    <?php foreach ($opts as $opt): ?>
                                        <option value="<?php echo (int)$opt['id']; ?>" <?php echo ((string)$displayVal === (string)$opt['id']) ? 'selected' : ''; ?>><?php echo sanitize($opt['label']); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">➕</option>
                                </select>
                            <?php elseif ($isDateTime): ?>
                                <input type="datetime-local" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(str_replace(' ', 'T', substr($displayVal, 0, 16))); ?>">
                            <?php elseif ($isDate): ?>
                                <input type="date" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize(substr($displayVal, 0, 10)); ?>">
                            <?php elseif ($isText): ?>
                                <textarea name="<?php echo sanitize($name); ?>" rows="4"><?php echo sanitize($displayVal); ?></textarea>
                            <?php else: ?>
                                <input type="text" name="<?php echo sanitize($name); ?>" value="<?php echo sanitize($displayVal); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">💾</button>
                        <a href="index.php" class="btn">🔙</a>
                    </div>
                </form>

            <?php elseif ($crud_action === 'view' && cr_is_employee_onboarding_module()): ?>
                <h1>View <?php echo sanitize($crud_title); ?></h1>
                <div class="card">
                    <table>
                        <tbody>
                        <?php
                        $viewPairs = $onboardingRowsShared;
                        ?>
                        <?php foreach ($viewPairs as $pair): ?>
                            <tr>
                                <?php foreach ($pair as $f): ?>
                                    <?php if ($f === null): ?>
                                        <th style="width:180px;"></th><td></td>
                                        <?php continue; ?>
                                    <?php endif; ?>
                                    <?php
                                        $viewCol = null;
                                        foreach ($fieldColumns as $fieldCol) {
                                            if ($fieldCol['Field'] === $f) {
                                                $viewCol = $fieldCol;
                                                break;
                                            }
                                        }
                                        $viewType = (string)($viewCol['Type'] ?? '');
                                        $isViewTinyInt = (bool)preg_match('/^tinyint(\(\d+\))?/i', $viewType);
                                    ?>
                                    <th style="width:180px;"><?php echo sanitize(cr_onboarding_field_label($f, $onboardingSystemAccessLabels)); ?></th>
                                    <td>
                                        <?php if (isset($fkMap[$f]) && (int)($data[$f] ?? 0) > 0): ?>
                                            <?php
                                                $fkLabel = cr_fk_label_by_id($conn, $fkMap[$f], (int)$data[$f], (int)$company_id);
                                                echo sanitize($fkLabel !== '' ? $fkLabel : (string)$data[$f]);
                                            ?>
                                        <?php elseif ($isViewTinyInt): ?>
                                            <?php echo ((int)($data[$f] ?? 0) === 1) ? '✅' : '❌'; ?>
                                        <?php elseif (in_array($f, ['request_date', 'termination_date', 'starting_date', 'requested_by_date', 'hod_approval_date', 'hrd_approval_date', 'ism_approval_date', 'gm_approval_date', 'fin_approval_date'], true)): ?>
                                            <?php echo sanitize(cr_onboarding_display_value($data[$f] ?? '', true)); ?>
                                        <?php elseif (isset($onboardingSystemAccessFields[$f])): ?>
                                            <?php echo cr_is_truthy_checkbox_value($data[$f] ?? '') ? '✅' : '❌'; ?>
                                        <?php else: ?>
                                            <?php echo sanitize(cr_onboarding_display_value($data[$f] ?? '')); ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <th><?php echo sanitize(cr_humanize_field('comments')); ?></th>
                            <td colspan="3"><?php echo sanitize(cr_onboarding_display_value($data['comments'] ?? '')); ?></td>
                        </tr>
                        </tbody>
                    </table>
                    <p style="margin-top:16px;">
                        <a href="index.php" class="btn">🔙</a>
                        <a class="btn btn-primary" href="edit.php?id=<?php echo (int)($data['id'] ?? 0); ?>">✏️</a>
                        <form method="POST" action="view.php?id=<?php echo (int)($data['id'] ?? 0); ?>" style="display:inline-block;margin-left:8px;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)($data['id'] ?? 0); ?>">
                            <button type="submit" class="btn btn-primary" name="send_approval_email" value="hod">HOD Approval - Send for Approval</button>
                            <div style="margin-top:6px;">Status: <?php echo cr_onboarding_status_badge($data['status_hod'] ?? 'Waiting'); ?></div>
                            <div style="margin-top:4px;">Email: <?php echo sanitize(cr_onboarding_email_status_text($data['email_sent_hod'] ?? 0, $data['email_sent_hod_at'] ?? '')); ?></div>
                        </form>
                        <form method="POST" action="view.php?id=<?php echo (int)($data['id'] ?? 0); ?>" style="display:inline-block;margin-left:8px;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)($data['id'] ?? 0); ?>">
                            <button type="submit" class="btn btn-primary" name="send_approval_email" value="hrd">HRD Approval - Send for Approval</button>
                            <div style="margin-top:6px;">Status: <?php echo cr_onboarding_status_badge($data['status_hrd'] ?? 'Waiting'); ?></div>
                            <div style="margin-top:4px;">Email: <?php echo sanitize(cr_onboarding_email_status_text($data['email_sent_hrd'] ?? 0, $data['email_sent_hrd_at'] ?? '')); ?></div>
                        </form>
                        <form method="POST" action="view.php?id=<?php echo (int)($data['id'] ?? 0); ?>" style="display:inline-block;margin-left:8px;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)($data['id'] ?? 0); ?>">
                            <button type="submit" class="btn btn-primary" name="send_approval_email" value="ism">ISM Approval - Send for Approval</button>
                            <div style="margin-top:6px;">Status: <?php echo cr_onboarding_status_badge($data['status_ism'] ?? 'Waiting'); ?></div>
                            <div style="margin-top:4px;">Email: <?php echo sanitize(cr_onboarding_email_status_text($data['email_sent_ism'] ?? 0, $data['email_sent_ism_at'] ?? '')); ?></div>
                        </form>
                        <?php if (cr_is_truthy_checkbox_value($data['email_account'] ?? '')): ?>
                            <form method="POST" action="view.php?id=<?php echo (int)($data['id'] ?? 0); ?>" style="display:inline-block;margin-left:8px;">
                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                <input type="hidden" name="id" value="<?php echo (int)($data['id'] ?? 0); ?>">
                                <button type="submit" class="btn btn-primary" name="send_approval_email" value="gm">GM Approval - Send for Approval</button>
                                <div style="margin-top:6px;">Status: <?php echo cr_onboarding_status_badge($data['status_gm'] ?? 'Waiting'); ?></div>
                                <div style="margin-top:4px;">Email: <?php echo sanitize(cr_onboarding_email_status_text($data['email_sent_gm'] ?? 0, $data['email_sent_gm_at'] ?? '')); ?></div>
                            </form>
                            <form method="POST" action="view.php?id=<?php echo (int)($data['id'] ?? 0); ?>" style="display:inline-block;margin-left:8px;">
                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                <input type="hidden" name="id" value="<?php echo (int)($data['id'] ?? 0); ?>">
                                <button type="submit" class="btn btn-primary" name="send_approval_email" value="fin">FIN Approval - Send for Approval</button>
                                <div style="margin-top:6px;">Status: <?php echo cr_onboarding_status_badge($data['status_fin'] ?? 'Waiting'); ?></div>
                                <div style="margin-top:4px;">Email: <?php echo sanitize(cr_onboarding_email_status_text($data['email_sent_fin'] ?? 0, $data['email_sent_fin_at'] ?? '')); ?></div>
                            </form>
                        <?php endif; ?>
                    </p>
                </div>

            <?php elseif ($crud_action === 'view'): ?>
                <!-- DETAILED RECORD VIEW -->
                <h1>View <?php echo sanitize($crud_title); ?></h1>
                <div class="card">
                    <table>
                        <tbody>
                        <?php foreach ($uiColumns as $col): $f = $col['Field']; ?>
                            <tr>
                                <th style="width:240px;"><?php echo sanitize(cr_humanize_field($f)); ?></th>
                                <td>
                                    <?php if (isset($fkMap[$f]) && (int)($data[$f] ?? 0) > 0): ?>
                                        <?php
                                            $fkLabel = cr_fk_label_by_id($conn, $fkMap[$f], (int)$data[$f], (int)$company_id);
                                            echo sanitize($fkLabel !== '' ? $fkLabel : (string)$data[$f]);
                                        ?>
                                    <?php else: ?>
                                        <?php echo cr_render_cell_value($crud_table, $f, $data[$f] ?? ''); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:16px;"><a href="index.php" class="btn">🔙</a> <a class="btn btn-primary" href="edit.php?id=<?php echo (int)($data['id'] ?? 0); ?>">✏️</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
/**
 * Bulk Action and Row Selection Script
 */
(function () {
    const selectAllRows = document.getElementById('select-all-rows') || document.getElementById('select-all-departments');
    const bulkDeleteForm = document.querySelector('form[id="bulk-delete-form"], form[id="department-bulk-form"]');
    const toggleButton = bulkDeleteForm ? bulkDeleteForm.querySelector('button[name="bulk_action"][value="bulk_delete"]') : null;
    const rowCheckboxes = bulkDeleteForm ? document.querySelectorAll('input[name="ids[]"][form="' + bulkDeleteForm.id + '"]') : [];
    const deleteCells = Array.from(rowCheckboxes).map(function (checkbox) { return checkbox.closest('td'); }).filter(Boolean);
    const selectAllHeaderCell = selectAllRows ? selectAllRows.closest('th') : null;
    let selectionMode = false;

    function setSelectionVisibility(visible) {
        if (selectAllHeaderCell) {
            selectAllHeaderCell.style.display = visible ? '' : 'none';
        }
        deleteCells.forEach(function (cell) {
            cell.style.display = visible ? '' : 'none';
        });
    }

    if (selectAllRows) {
        selectAllRows.addEventListener('change', function () {
            rowCheckboxes.forEach(function (checkbox) {
                checkbox.checked = selectAllRows.checked;
            });
        });
    }

    if (bulkDeleteForm && toggleButton) {
        setSelectionVisibility(false);

        bulkDeleteForm.addEventListener('submit', function (event) {
            if (event.submitter !== toggleButton) {
                return;
            }

            if (!selectionMode) {
                event.preventDefault();
                selectionMode = true;
                setSelectionVisibility(true);
                toggleButton.textContent = 'Delete Selected';
                return;
            }

            const anySelected = Array.from(rowCheckboxes).some(function (checkbox) { return checkbox.checked; });
            if (!anySelected) {
                event.preventDefault();
                alert('Please select at least one record to delete.');
                return;
            }

            if (!confirm('Delete selected records?')) {
                event.preventDefault();
            }
        });
    }
})();
</script>
<script src="../../js/theme.js"></script>
<script>
window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
</script>
<script src="../../js/select-add-option.js"></script>

<script>
/**
 * Global UI Event Listeners
 */
document.addEventListener('click', function (event) {
    const link = event.target.closest('a[data-outlook-link="1"]');
    if (!link) return;
    const outlookHref = link.getAttribute('data-outlook-href');
    if (outlookHref) {
        window.location.href = outlookHref;
    }
});

document.addEventListener('change', function (event) {
    if (!event.target.matches('.itm-checkbox-control input[type="checkbox"]')) return;
    const indicator = event.target.closest('.itm-checkbox-control')?.querySelector('.itm-check-indicator');
    if (indicator) {
        indicator.textContent = event.target.checked ? '✅' : '❌';
    }
});

(function () {
    const departmentField = document.querySelector('select[name="department_name"]');
    if (!departmentField) {
        return;
    }

    const approvalFields = {
        hod_approval: document.querySelector('input[name="hod_approval"]'),
        hrd_approval: document.querySelector('input[name="hrd_approval"]'),
        ism_approval: document.querySelector('input[name="ism_approval"]'),
        gm_approval: document.querySelector('input[name="gm_approval"]'),
        fin_approval: document.querySelector('input[name="fin_approval"]')
    };

    function applyApprovalValues(values) {
        Object.keys(approvalFields).forEach(function (fieldName) {
            const input = approvalFields[fieldName];
            if (!input) {
                return;
            }

            const resolvedValue = values && typeof values[fieldName] === 'string' ? values[fieldName] : '';
            // Why: when a newly selected department has no active mapping, stale approval names must be cleared.
            input.value = resolvedValue;
        });
    }

    function resolveApprovalsByDepartment(departmentName) {
        if (!departmentName || !window.ITM_CSRF_TOKEN) {
            applyApprovalValues({});
            return;
        }

        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                resolve_onboarding_approvals: 1,
                csrf_token: window.ITM_CSRF_TOKEN,
                department_name: departmentName
            })
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (payload) {
            if (!payload || payload.ok !== true) {
                return;
            }
            applyApprovalValues(payload.approvals || {});
        })
        .catch(function () {
            // Why: keep form usable even if background approver resolution request fails.
        });
    }

    departmentField.addEventListener('change', function () {
        resolveApprovalsByDepartment(departmentField.value);
    });

    if (departmentField.value) {
        resolveApprovalsByDepartment(departmentField.value);
    }
})();

(function () {
    const employeeField = document.querySelector('select[name="employee_id"]');
    const commentsField = document.querySelector('textarea[name="comments"]');
    if (!employeeField || !commentsField) {
        return;
    }

    function setCommentsEmailPrefix(email) {
        const currentText = commentsField.value || '';
        const trimmedText = currentText.trim();
        const normalizedEmail = (email || '').trim();
        const placeholderValues = ['(Email:)', "'(Email:)'", 'Email:'];

        function removePlaceholderLines(text) {
            const lines = (text || '').split(/\r?\n/);
            return lines.filter(function (line) {
                const trimmed = (line || '').trim();
                if (!trimmed) {
                    return false;
                }
                return placeholderValues.indexOf(trimmed) === -1;
            });
        }

        if (normalizedEmail === '') {
            if (trimmedText === '' || placeholderValues.indexOf(trimmedText) !== -1) {
                commentsField.value = 'Email:';
            }
            return;
        }

        const emailLine = 'Email: ' + normalizedEmail;
        if (trimmedText === '' || placeholderValues.indexOf(trimmedText) !== -1) {
            commentsField.value = emailLine;
            return;
        }

        const lines = removePlaceholderLines(currentText);
        if (lines.length === 0) {
            commentsField.value = emailLine;
            return;
        }
        if (lines.length > 0 && /^email\s*:/i.test(lines[0].trim())) {
            lines[0] = emailLine;
            commentsField.value = lines.join('\n');
            return;
        }

        commentsField.value = emailLine + "\n" + lines.join('\n');
    }

    function resolveEmployeeEmail(employeeId) {
        if (!window.ITM_CSRF_TOKEN) {
            return;
        }

        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                resolve_onboarding_employee_email: 1,
                csrf_token: window.ITM_CSRF_TOKEN,
                employee_id: employeeId
            })
        })
        .then(function (response) {
            return response.json();
        })
        .then(function (payload) {
            if (!payload || payload.ok !== true) {
                return;
            }
            setCommentsEmailPrefix(payload.email || '');
        })
        .catch(function () {
            // Why: employee selection should not block form submission if async email lookup fails.
        });
    }

    employeeField.addEventListener('change', function () {
        resolveEmployeeEmail(employeeField.value || '');
    });

    if (employeeField.value) {
        resolveEmployeeEmail(employeeField.value);
    } else {
        setCommentsEmailPrefix('');
    }
})();
</script>

</body>
</html>
