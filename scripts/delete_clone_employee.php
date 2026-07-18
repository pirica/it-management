<?php
/**
 * Reverse an employee clone by deleting the employee and their related data.
 *
 * Why: Allows cleaning up after experimental data transfers.
 *
 * Default: dry-run (counts only — no deletes).
 * Apply: CLI --apply or browser apply=1 (Admin).
 *
 * Browser: scripts/delete_clone_employee.php?id=N (dry-run) or ?id=N&apply=1
 * CLI: php scripts/delete_clone_employee.php --id=N [--apply]
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
    itm_script_require_admin_script_or_exit($conn, 'Administrator access required to delete clone employees.');
}

/**
 * @return array{id:int,apply:bool}
 */
function itm_delete_clone_employee_parse_request(bool $isCli): array
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
function itm_delete_clone_employee_render_form(): void
{
    $baseUrl = defined('BASE_URL') ? (string)BASE_URL : '../';
    $scriptSelf = $baseUrl . 'scripts/delete_clone_employee.php';
    $csrf = itm_get_csrf_token();

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Delete Clone Employee</title>';
    echo '<style>';
    echo 'body{font-family:Segoe UI,sans-serif;background:#f6f8fa;color:#24292f;margin:0;padding:20px;}';
    echo '.dce-wrap{max-width:720px;margin:0 auto;}';
    echo '.dce-card{background:#fff;border:1px solid #d0d7de;border-radius:8px;padding:20px;margin-bottom:16px;}';
    echo 'label{display:block;font-weight:600;margin-bottom:6px;}';
    echo 'input[type=number]{width:100%;max-width:240px;padding:8px;border:1px solid #d0d7de;border-radius:6px;}';
    echo '.dce-muted{color:#57606a;line-height:1.5;}';
    echo '.dce-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px;}';
    echo '.btn{display:inline-block;padding:8px 14px;border-radius:6px;border:1px solid #d0d7de;background:#f6f8fa;color:#24292f;text-decoration:none;cursor:pointer;font-size:0.95rem;}';
    echo '.btn-primary{background:#0969da;border-color:#0969da;color:#fff;}';
    echo '.btn-danger{background:#cf222e;border-color:#cf222e;color:#fff;}';
    echo '</style></head><body><div class="dce-wrap">';
    itm_script_browser_nav_echo($baseUrl);
    echo '<div class="dce-card"><h1>Delete clone employee</h1>';
    echo '<p class="dce-muted">Removes a cloned employee and all related rows in tables with <code>employee_id</code> (excludes <code>audit_logs</code>, <code>attempts</code>). <strong>Default is dry-run</strong> (counts only). Use <code>apply=1</code> only when you intend to delete.</p>';
    echo '<form method="post" action="' . htmlspecialchars($scriptSelf, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') . '">';
    echo '<label for="id">Clone employee id</label>';
    echo '<input type="number" name="id" id="id" min="1" required>';
    echo '<div class="dce-actions">';
    echo '<button type="submit" class="btn btn-primary" name="dry_run" value="1">Preview (dry-run)</button>';
    echo '<button type="submit" class="btn btn-danger" name="apply" value="1" onclick="return confirm(\'Permanently delete this employee and all related data?\');">Apply delete</button>';
    echo '</div>';
    echo '</form>';
    echo '<p class="dce-muted">CLI: <code>php scripts/delete_clone_employee.php --id=N</code> (dry-run) · <code>--apply</code> to delete.</p>';
    echo '</div></div></body></html>';
    exit(0);
}

/**
 * @return array<int, string>
 */
function itm_delete_clone_employee_exclude_tables(): array
{
    return ['audit_logs', 'attempts'];
}

/**
 * @return array<int, string>
 */
function itm_delete_clone_employee_tables_with_employee_id(mysqli $conn, array $excludeTables): array
{
    $tables = [];
    $res = mysqli_query($conn, 'SHOW TABLES');
    while ($res && ($row = mysqli_fetch_row($res))) {
        $table = (string)$row[0];
        if (in_array($table, $excludeTables, true)) {
            continue;
        }
        $col = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE 'employee_id'");
        if ($col && mysqli_num_rows($col) > 0) {
            $tables[] = $table;
        }
    }

    sort($tables, SORT_STRING);
    return $tables;
}

/**
 * @return array{
 *   ok:bool,
 *   message:string,
 *   employee:?array<string,mixed>,
 *   table_counts:array<string,int>,
 *   errors:array<int,string>,
 *   deleted_tables:array<int,string>
 * }
 */
function itm_delete_clone_employee_run(mysqli $conn, int $employeeId, bool $dryRun): array
{
    $excludeTables = itm_delete_clone_employee_exclude_tables();
    $sourceRes = mysqli_query(
        $conn,
        "SELECT id, company_id, username, first_name, last_name FROM employees WHERE id = $employeeId LIMIT 1"
    );
    $employee = ($sourceRes) ? mysqli_fetch_assoc($sourceRes) : null;
    if (!$employee) {
        return [
            'ok' => false,
            'message' => 'Employee not found.',
            'employee' => null,
            'table_counts' => [],
            'errors' => [],
            'deleted_tables' => [],
        ];
    }

    $tables = itm_delete_clone_employee_tables_with_employee_id($conn, $excludeTables);
    $tableCounts = [];
    foreach ($tables as $table) {
        $countRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM `$table` WHERE employee_id = $employeeId");
        $countRow = ($countRes) ? mysqli_fetch_assoc($countRes) : null;
        $tableCounts[$table] = (int)($countRow['c'] ?? 0);
    }

    if ($dryRun) {
        return [
            'ok' => true,
            'message' => 'Dry-run complete — no rows deleted.',
            'employee' => $employee,
            'table_counts' => $tableCounts,
            'errors' => [],
            'deleted_tables' => $tables,
        ];
    }

    mysqli_begin_transaction($conn);

    $errors = [];
    $deletedTables = [];

    foreach ($tables as $table) {
        $sql = "DELETE FROM `$table` WHERE employee_id = $employeeId";
        if (!mysqli_query($conn, $sql)) {
            $errors[] = $table . ': ' . mysqli_error($conn);
            continue;
        }
        if (mysqli_affected_rows($conn) > 0) {
            $deletedTables[] = $table;
        }
    }

    $employeeSql = "DELETE FROM employees WHERE id = $employeeId";
    if (!mysqli_query($conn, $employeeSql)) {
        $errors[] = 'employees: ' . mysqli_error($conn);
    }

    if ($errors !== []) {
        mysqli_rollback($conn);
        return [
            'ok' => false,
            'message' => 'Delete aborted — transaction rolled back.',
            'employee' => $employee,
            'table_counts' => $tableCounts,
            'errors' => $errors,
            'deleted_tables' => [],
        ];
    }

    mysqli_commit($conn);

    return [
        'ok' => true,
        'message' => 'Clone reversed successfully — employee and related data removed.',
        'employee' => $employee,
        'table_counts' => $tableCounts,
        'errors' => [],
        'deleted_tables' => $deletedTables,
    ];
}

$request = itm_delete_clone_employee_parse_request($itmIsCli);
$employeeId = (int)$request['id'];
$apply = (bool)$request['apply'];
$dryRun = !$apply;

if (!$itmIsCli && $employeeId <= 0) {
    itm_delete_clone_employee_render_form();
}

$nl = itm_script_output_nl();
itm_script_output_begin('Delete Clone Employee');

if ($employeeId <= 0) {
    echo colorText('[FAIL] Please specify a valid employee id (--id=N or browser form).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText(
    $dryRun
        ? 'Mode: DRY-RUN (default — counts only, no deletes)'
        : 'Mode: APPLY (permanently deletes employee and related rows)',
    $dryRun ? 'info' : 'warn'
) . $nl;

$result = itm_delete_clone_employee_run($conn, $employeeId, $dryRun);

if ($result['employee'] !== null) {
    $employee = $result['employee'];
    echo 'Target employee: id=' . (int)$employee['id']
        . ' company_id=' . (int)$employee['company_id']
        . ' username=' . (string)($employee['username'] ?? '')
        . ' name=' . trim((string)($employee['first_name'] ?? '') . ' ' . (string)($employee['last_name'] ?? ''))
        . $nl;
}

if (!$result['ok']) {
    echo colorText('[FAIL] ' . $result['message'], 'fail') . $nl;
    if ($result['errors'] !== []) {
        echo 'Errors:' . $nl;
        foreach ($result['errors'] as $error) {
            echo ' - ' . $error . $nl;
        }
    }
    itm_script_output_end();
    exit(1);
}

if ($dryRun) {
    echo colorText('[PASS] Would delete employee id ' . $employeeId . ' and related rows listed below.', 'pass') . $nl;
} else {
    echo colorText('Deleted employee id ' . $employeeId . '.', 'pass') . $nl;
}

echo ($dryRun ? 'Tables that would be scanned:' : 'Tables with rows deleted:') . $nl;
foreach ($result['deleted_tables'] as $table) {
    $count = (int)($result['table_counts'][$table] ?? 0);
    if ($dryRun) {
        $label = $count > 0 ? 'WOULD DELETE' : 'no rows';
        $suffix = $count > 0 ? " ($count row(s))" : '';
        echo ' - ' . $table . ': ' . colorText($label, $count > 0 ? 'warn' : 'pass') . $suffix . $nl;
        continue;
    }
    if ($count > 0) {
        echo ' - ' . $table . ': ' . colorText("DELETED ($count rows)", 'pass') . $nl;
    }
}

if (!$dryRun) {
    echo ' - employees: ' . colorText('DELETED', 'pass') . $nl;
} else {
    echo ' - employees: ' . colorText('WOULD DELETE (1 row)', 'warn') . $nl;
}

echo $nl . colorText($result['message'], 'pass') . $nl;

if ($dryRun) {
    if ($itmIsCli) {
        echo 'Re-run with --apply to execute: php scripts/delete_clone_employee.php --id=' . $employeeId . ' --apply' . $nl;
    } else {
        echo 'Open with apply=1 to execute: delete_clone_employee.php?id=' . $employeeId . '&apply=1' . $nl;
    }
}

itm_script_output_end();
exit(0);
