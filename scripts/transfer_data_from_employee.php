<?php
/**
 * Clones an employee and transfers/copies their related data to the new record.
 *
 * Why: Useful for simulating user data or migrating responsibilities
 * while maintaining historical data in a new record.
 *
 * Default: dry-run (transaction rolled back — no lasting writes).
 * Apply: CLI --apply or browser apply=1 (Admin).
 *
 * Browser: scripts/transfer_data_from_employee.php?id=N (dry-run) or ?id=N&apply=1
 * CLI: php scripts/transfer_data_from_employee.php --id=N [--apply]
 */

declare(strict_types=1);

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');

if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

if (!$itmIsCli) {
    require_once __DIR__ . '/lib/script_browser_nav.php';
    itm_script_require_admin_script_or_exit($conn, 'Administrator access required for employee data transfer.');
}

/**
 * @return array{id:int,apply:bool}
 */
function itm_transfer_data_from_employee_parse_request(bool $isCli): array
{
    $id = 0;
    $apply = false;

    if ($isCli) {
        $options = getopt('', ['id:', 'apply']);
        $id = isset($options['id']) ? (int)$options['id'] : 0;
        $apply = isset($options['apply']);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        itm_require_post_csrf();
        $id = (int)($_POST['id'] ?? 0);
        $apply = !empty($_POST['apply']);
    } else {
        $id = (int)($_GET['id'] ?? 0);
        $apply = isset($_GET['apply']) && (string)$_GET['apply'] === '1';
    }

    return ['id' => $id, 'apply' => $apply];
}

/**
 * @return never
 */
function itm_transfer_data_from_employee_render_form(mysqli $conn): void
{
    $baseUrl = defined('BASE_URL') ? (string)BASE_URL : '../';
    $scriptSelf = $baseUrl . 'scripts/transfer_data_from_employee.php';
    $csrf = itm_get_csrf_token();

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Transfer Data from Employee</title>';
    echo '<style>';
    echo 'body{font-family:Segoe UI,sans-serif;background:#f6f8fa;color:#24292f;margin:0;padding:20px;}';
    echo '.tdfe-wrap{max-width:720px;margin:0 auto;}';
    echo '.tdfe-card{background:#fff;border:1px solid #d0d7de;border-radius:8px;padding:20px;margin-bottom:16px;}';
    echo 'label{display:block;font-weight:600;margin-bottom:6px;}';
    echo 'input[type=number]{width:100%;max-width:240px;padding:8px;border:1px solid #d0d7de;border-radius:6px;}';
    echo '.tdfe-muted{color:#57606a;line-height:1.5;}';
    echo '.tdfe-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px;}';
    echo '.btn{display:inline-block;padding:8px 14px;border-radius:6px;border:1px solid #d0d7de;background:#f6f8fa;color:#24292f;text-decoration:none;cursor:pointer;font-size:0.95rem;}';
    echo '.btn-primary{background:#0969da;border-color:#0969da;color:#fff;}';
    echo '.btn-danger{background:#cf222e;border-color:#cf222e;color:#fff;}';
    echo '</style></head><body><div class="tdfe-wrap">';
    itm_script_browser_nav_echo($baseUrl);
    echo '<div class="tdfe-card"><h1>Transfer data from employee</h1>';
    echo '<p class="tdfe-muted">Clones an employee and copies related rows to a new record. <strong>Default is dry-run</strong> (full transaction rolled back). Use <code>apply=1</code> only when you intend to keep the clone.</p>';
    echo '<form method="post" action="' . htmlspecialchars($scriptSelf, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') . '">';
    echo '<label for="id">Source employee id</label>';
    echo '<input type="number" name="id" id="id" min="1" required>';
    echo '<div class="tdfe-actions">';
    echo '<button type="submit" class="btn btn-primary" name="dry_run" value="1">Preview (dry-run)</button>';
    echo '<button type="submit" class="btn btn-danger" name="apply" value="1" onclick="return confirm(\'Create a real employee clone and copy related data?\');">Apply copy</button>';
    echo '</div>';
    echo '</form>';
    echo '<p class="tdfe-muted">CLI: <code>php scripts/transfer_data_from_employee.php --id=N</code> (dry-run) · <code>--apply</code> to write.</p>';
    echo '</div></div></body></html>';
    exit(0);
}

/**
 * Generate 3 random characters (A-Z + 0-9)
 */
function random3(): string
{
    return substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 3);
}

function generateUniqueUsername(mysqli $conn, int $company_id, string $baseUsername): string
{
    $suffix = random3();
    $newUsername = $baseUsername . '-new' . $suffix;
    $safe = mysqli_real_escape_string($conn, $newUsername);
    $check = mysqli_query($conn, "
        SELECT id FROM employees
        WHERE company_id = $company_id
          AND username = '$safe'
        LIMIT 1
    ");
    if ($check && mysqli_num_rows($check) === 0) {
        return $newUsername;
    }
    return generateUniqueUsername($conn, $company_id, $baseUsername);
}

function generateUniqueEmail(mysqli $conn, int $company_id, ?string $email): ?string
{
    if ($email === null || $email === '') {
        return null;
    }
    $suffix = random3();
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return null;
    }
    $newEmail = $parts[0] . '+new' . $suffix . '@' . $parts[1];
    $safe = mysqli_real_escape_string($conn, $newEmail);
    $check = mysqli_query($conn, "
        SELECT id FROM employees
        WHERE company_id = $company_id
          AND work_email = '$safe'
        LIMIT 1
    ");
    if ($check && mysqli_num_rows($check) === 0) {
        return $newEmail;
    }
    return generateUniqueEmail($conn, $company_id, $email);
}

/**
 * Create a new employee by cloning all columns except the PK.
 */
function createNewEmployeeClone(mysqli $conn, int $old_employee_id): int
{
    $res = mysqli_query($conn, "SELECT * FROM employees WHERE id = $old_employee_id");
    $old = ($res) ? mysqli_fetch_assoc($res) : null;
    if (!$old) {
        return 0;
    }

    $colsRes = mysqli_query($conn, 'SHOW COLUMNS FROM employees');
    $insertCols = [];
    $selectCols = [];

    while ($colsRes && ($c = mysqli_fetch_assoc($colsRes))) {
        $col = $c['Field'];
        if ($col === 'id') {
            continue;
        }

        $insertCols[] = "`$col`";

        if ($col === 'username' && !empty($old[$col])) {
            $uniqueUsername = generateUniqueUsername($conn, (int)$old['company_id'], (string)$old[$col]);
            $selectCols[] = "'" . mysqli_real_escape_string($conn, $uniqueUsername) . "' AS `$col`";
            continue;
        }

        if ($col === 'work_email' && !empty($old[$col])) {
            $uniqueEmail = generateUniqueEmail($conn, (int)$old['company_id'], (string)$old[$col]);
            $selectCols[] = "'" . mysqli_real_escape_string($conn, (string)$uniqueEmail) . "' AS `$col`";
            continue;
        }

        if ($col === 'personal_email' && !empty($old[$col])) {
            $uniqueEmail = generateUniqueEmail($conn, (int)$old['company_id'], (string)$old[$col]);
            $selectCols[] = "'" . mysqli_real_escape_string($conn, (string)$uniqueEmail) . "' AS `$col`";
            continue;
        }

        if (($col === 'external_id' || $col === 'employee_code') && !empty($old[$col])) {
            $newValue = $old[$col] . '-new' . random3();
            $selectCols[] = "'" . mysqli_real_escape_string($conn, (string)$newValue) . "' AS `$col`";
            continue;
        }

        if ($col === 'first_name') {
            $value = 'Clone of ' . $old['first_name'];
            $selectCols[] = "'" . mysqli_real_escape_string($conn, $value) . "' AS `$col`";
            continue;
        }

        $value = isset($old[$col]) ? mysqli_real_escape_string($conn, (string)$old[$col]) : null;
        if ($value === null) {
            $selectCols[] = "NULL AS `$col`";
        } else {
            $selectCols[] = "'$value' AS `$col`";
        }
    }

    $insertList = implode(', ', $insertCols);
    $selectList = implode(', ', $selectCols);
    $sql = "INSERT INTO employees ($insertList) SELECT $selectList";

    if (!mysqli_query($conn, $sql)) {
        return 0;
    }

    return (int)mysqli_insert_id($conn);
}

/**
 * @return array{insertCols:array<int,string>,selectCols:array<int,string>}|null
 */
function itm_transfer_data_from_employee_build_copy_sql_parts(mysqli $conn, string $table, int $newEmployeeId): ?array
{
    $columnsRes = mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
    if (!$columnsRes) {
        return null;
    }

    $insertCols = [];
    $selectCols = [];
    while ($c = mysqli_fetch_assoc($columnsRes)) {
        $field = $c['Field'];
        if ($field === 'id') {
            continue;
        }
        $insertCols[] = "`$field`";
        if ($field === 'employee_id') {
            $selectCols[] = "$newEmployeeId AS employee_id";
        } else {
            $selectCols[] = "`$field`";
        }
    }

    if ($insertCols === []) {
        return null;
    }

    return ['insertCols' => $insertCols, 'selectCols' => $selectCols];
}

/**
 * @return array{ok:bool,errors:array<int,array{table:string,error:string}>,copy_tables:array<int,string>,row_counts:array<string,int>}
 */
function itm_transfer_data_from_employee_probe_tables(mysqli $conn, int $oldEmployeeId, int $newEmployeeId, array $excludeTables, bool $leaveTestRows): array
{
    $errors = [];
    $copyTables = [];
    $rowCounts = [];

    $res = mysqli_query($conn, 'SHOW TABLES');
    while ($res && ($row = mysqli_fetch_row($res))) {
        $table = (string)$row[0];
        if (in_array($table, $excludeTables, true)) {
            continue;
        }

        $col = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE 'employee_id'");
        if (!$col || mysqli_num_rows($col) === 0) {
            continue;
        }

        $countRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM `$table` WHERE employee_id = $oldEmployeeId");
        $countRow = ($countRes) ? mysqli_fetch_assoc($countRes) : null;
        $rowCounts[$table] = (int)($countRow['c'] ?? 0);

        $parts = itm_transfer_data_from_employee_build_copy_sql_parts($conn, $table, $newEmployeeId);
        if ($parts === null) {
            continue;
        }

        $insertList = implode(', ', $parts['insertCols']);
        $selectList = implode(', ', $parts['selectCols']);
        $testSql = "
            INSERT INTO `$table` ($insertList)
            SELECT $selectList
            FROM `$table`
            WHERE employee_id = $oldEmployeeId
            LIMIT 1
        ";

        if ($leaveTestRows) {
            if (!mysqli_query($conn, $testSql)) {
                $errors[] = ['table' => $table, 'error' => mysqli_error($conn)];
            } else {
                $copyTables[] = $table;
            }
            continue;
        }

        mysqli_begin_transaction($conn);
        $ok = mysqli_query($conn, $testSql);
        $error = $ok ? '' : mysqli_error($conn);
        mysqli_rollback($conn);

        if (!$ok) {
            $errors[] = ['table' => $table, 'error' => $error];
        } else {
            $copyTables[] = $table;
        }
    }

    return [
        'ok' => $errors === [],
        'errors' => $errors,
        'copy_tables' => $copyTables,
        'row_counts' => $rowCounts,
    ];
}

/**
 * @return array{ok:bool,message:string,new_employee_id:int,copy_tables:array<int,string>,row_counts:array<string,int>,errors?:array<int,array{table:string,error:string}>,source?:array<string,mixed>}
 */
function itm_transfer_data_from_employee_run(mysqli $conn, int $oldEmployeeId, bool $dryRun): array
{
    $excludeTables = ['audit_logs', 'attempts'];
    $sourceRes = mysqli_query($conn, "SELECT id, company_id, username, first_name, last_name FROM employees WHERE id = $oldEmployeeId LIMIT 1");
    $source = ($sourceRes) ? mysqli_fetch_assoc($sourceRes) : null;
    if (!$source) {
        return ['ok' => false, 'message' => 'Source employee not found.', 'new_employee_id' => 0, 'copy_tables' => [], 'row_counts' => []];
    }

    if ($dryRun) {
        mysqli_begin_transaction($conn);

        $newEmployeeId = createNewEmployeeClone($conn, $oldEmployeeId);
        if ($newEmployeeId <= 0) {
            mysqli_rollback($conn);
            return ['ok' => false, 'message' => 'Could not create employee clone.', 'new_employee_id' => 0, 'copy_tables' => [], 'row_counts' => []];
        }

        $test = itm_transfer_data_from_employee_probe_tables($conn, $oldEmployeeId, $newEmployeeId, $excludeTables, true);
        mysqli_rollback($conn);

        if (!$test['ok']) {
            return [
                'ok' => false,
                'message' => 'COPY ABORTED — table test failures detected.',
                'new_employee_id' => 0,
                'copy_tables' => [],
                'row_counts' => $test['row_counts'],
                'errors' => $test['errors'],
            ];
        }

        return [
            'ok' => true,
            'message' => 'Dry-run complete — transaction rolled back (no data written).',
            'new_employee_id' => $newEmployeeId,
            'copy_tables' => $test['copy_tables'],
            'row_counts' => $test['row_counts'],
            'source' => $source,
        ];
    }

    $newEmployeeId = createNewEmployeeClone($conn, $oldEmployeeId);
    if ($newEmployeeId <= 0) {
        return ['ok' => false, 'message' => 'Could not create employee clone.', 'new_employee_id' => 0, 'copy_tables' => [], 'row_counts' => []];
    }

    $test = itm_transfer_data_from_employee_probe_tables($conn, $oldEmployeeId, $newEmployeeId, $excludeTables, false);
    if (!$test['ok']) {
        return [
            'ok' => false,
            'message' => 'COPY ABORTED — table test failures detected. Clone employee id ' . $newEmployeeId . ' was created; remove manually if not needed.',
            'new_employee_id' => $newEmployeeId,
            'copy_tables' => [],
            'row_counts' => $test['row_counts'],
            'errors' => $test['errors'],
            'source' => $source,
        ];
    }

    foreach ($test['copy_tables'] as $table) {
        $parts = itm_transfer_data_from_employee_build_copy_sql_parts($conn, $table, $newEmployeeId);
        if ($parts === null) {
            continue;
        }
        $insertList = implode(', ', $parts['insertCols']);
        $selectList = implode(', ', $parts['selectCols']);
        $sql = "
            INSERT INTO `$table` ($insertList)
            SELECT $selectList
            FROM `$table`
            WHERE employee_id = $oldEmployeeId
        ";
        if (!mysqli_query($conn, $sql)) {
            return [
                'ok' => false,
                'message' => "COPY FAILED on table $table: " . mysqli_error($conn) . ' (clone employee id ' . $newEmployeeId . ' may need manual cleanup).',
                'new_employee_id' => $newEmployeeId,
                'copy_tables' => $test['copy_tables'],
                'row_counts' => $test['row_counts'],
                'source' => $source,
            ];
        }
    }

    return [
        'ok' => true,
        'message' => 'COPY PROCESS COMPLETED.',
        'new_employee_id' => $newEmployeeId,
        'copy_tables' => $test['copy_tables'],
        'row_counts' => $test['row_counts'],
        'source' => $source,
    ];
}

$request = itm_transfer_data_from_employee_parse_request($itmIsCli);
$oldEmployeeId = (int)$request['id'];
$apply = (bool)$request['apply'];
$dryRun = !$apply;

if (!$itmIsCli && $oldEmployeeId <= 0) {
    itm_transfer_data_from_employee_render_form($conn);
}

$nl = itm_script_output_nl();
itm_script_output_begin('Employee data transfer (COPY MODE)');

if ($oldEmployeeId <= 0) {
    echo colorText('[FAIL] Please specify a source employee id (--id=N or browser form).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText(
    $dryRun
        ? 'Mode: DRY-RUN (default — transaction rolled back, no lasting writes)'
        : 'Mode: APPLY (writes clone and copied related rows)',
    $dryRun ? 'info' : 'warn'
) . $nl;

$result = itm_transfer_data_from_employee_run($conn, $oldEmployeeId, $dryRun);

if (!empty($result['source'])) {
    $source = $result['source'];
    echo 'Source employee: id=' . (int)$source['id']
        . ' company_id=' . (int)$source['company_id']
        . ' username=' . (string)($source['username'] ?? '')
        . ' name=' . trim((string)($source['first_name'] ?? '') . ' ' . (string)($source['last_name'] ?? ''))
        . $nl;
}

if (!$result['ok']) {
    echo colorText('[FAIL] ' . $result['message'], 'fail') . $nl;
    if (!empty($result['errors'])) {
        echo 'Fix these tables before retrying:' . $nl;
        foreach ($result['errors'] as $error) {
            echo ' - ' . $error['table'] . ': ' . $error['error'] . $nl;
        }
    }
    itm_script_output_end();
    exit(1);
}

if ($dryRun) {
    echo colorText('[PASS] Would create clone employee id ' . (int)$result['new_employee_id'] . ' (rolled back).', 'pass') . $nl;
} else {
    echo colorText('New employee created: ID ' . (int)$result['new_employee_id'], 'pass') . $nl;
}

echo ($dryRun ? 'Tables that would copy:' : 'Tables copied:') . $nl;
foreach ($result['copy_tables'] as $table) {
    $count = (int)($result['row_counts'][$table] ?? 0);
    $suffix = $count > 0 ? " ($count row(s))" : ' (0 rows)';
    echo ' - ' . $table . ': ' . colorText($dryRun ? 'WOULD COPY' : 'COPIED', 'pass') . $suffix . $nl;
}

echo $nl . colorText($result['message'], 'pass') . $nl;

if ($dryRun) {
    if ($itmIsCli) {
        echo 'Re-run with --apply to execute: php scripts/transfer_data_from_employee.php --id=' . $oldEmployeeId . ' --apply' . $nl;
    } else {
        echo 'Open with apply=1 to execute: transfer_data_from_employee.php?id=' . $oldEmployeeId . '&apply=1' . $nl;
    }
}

itm_script_output_end();
exit(0);
