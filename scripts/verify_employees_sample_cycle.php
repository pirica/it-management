<?php
/**
 * Verifies employees Add sample data → soft-delete → re-seed cycle.
 *
 * CLI:
 *   php scripts/verify_employees_sample_cycle.php
 *   php scripts/verify_employees_sample_cycle.php --company=all --cycles=4
 *   php scripts/verify_employees_sample_cycle.php --company=1 --cycles=4
 *
 * Browser (Admin): scripts/verify_employees_sample_cycle.php?run=1&cycles=4
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<strong>Admin login required</strong> in browser.<br>
CLI: <code>php scripts/verify_employees_sample_cycle.php --cycles=4</code> (companies 1–5)<br>
CLI single tenant: <code>php scripts/verify_employees_sample_cycle.php --company=1 --cycles=4</code><br>
Browser (multi-company): <a href="verify_employees_sample_cycle.php?run=1&amp;cycles=4">verify_employees_sample_cycle.php?run=1&amp;cycles=4</a><br>
Browser (one company): <a href="verify_employees_sample_cycle.php?run=1&amp;company=1&amp;cycles=4">verify_employees_sample_cycle.php?run=1&amp;company=1&amp;cycles=4</a>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$vesIsCli = PHP_SAPI === 'cli';

if ($vesIsCli && !defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_crud_audit_fields.php';
require_once ROOT_PATH . 'includes/itm_employees_hidden_accounts.php';
require_once ROOT_PATH . 'includes/itm_sample_data_seed.php';
require_once ROOT_PATH . 'modules/employees/delete_functions.php';
require_once ROOT_PATH . 'modules/employees/delete_clear_table.php';
require_once __DIR__ . '/lib/script_cli_output.php';

if (!$vesIsCli) {
    itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
}

itm_script_output_begin('Employees sample data cycle verification');

$nl = itm_script_output_nl();
$failures = 0;

function ves_fail(string $message): void
{
    global $failures, $nl;
    $failures++;
    itm_script_write_stderr('[FAIL] ' . $message . $nl);
}

function ves_pass(string $message): void
{
    global $nl;
    itm_script_write_stdout('[PASS] ' . $message . $nl);
}

/**
 * @return list<int>
 */
function ves_resolve_seed_company_ids($conn, ?int $singleCompanyId): array
{
    if ($singleCompanyId !== null && $singleCompanyId > 0) {
        return [$singleCompanyId];
    }

    $ids = [];
    if ($conn instanceof mysqli) {
        $res = mysqli_query($conn, 'SELECT id FROM companies WHERE id BETWEEN 1 AND 5 ORDER BY id ASC');
        if ($res instanceof mysqli_result) {
            while ($row = mysqli_fetch_assoc($res)) {
                $id = (int)($row['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
            mysqli_free_result($res);
        }
    }

    if ($ids === []) {
        return [1, 2, 3, 4, 5];
    }

    return $ids;
}

function ves_count_visible_live_employees($conn, int $companyId): int
{
    $sql = 'SELECT COUNT(*) AS total_count FROM employees WHERE company_id = ? AND is_hidden = 0 AND deleted_at IS NULL';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return -1;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return (int)($row['total_count'] ?? 0);
}

function ves_clear_visible_employees($conn, int $companyId): bool
{
    $error = employees_clear_table_for_company($conn, $companyId);

    return $error === null;
}

function ves_run_cycles_for_company($conn, int $companyId, int $cycles): int
{
    $localFailures = 0;
    $table = 'employees';

    if (!ves_clear_visible_employees($conn, $companyId)) {
        ves_fail("Company {$companyId}: could not clear visible live rows before cycles.");
        return 1;
    }

    $liveStart = ves_count_visible_live_employees($conn, $companyId);
    if ($liveStart !== 0) {
        ves_fail("Company {$companyId}: expected visible live=0 before cycles; live={$liveStart}");
        return 1;
    }

    for ($cycle = 1; $cycle <= $cycles; $cycle++) {
        $liveBefore = ves_count_visible_live_employees($conn, $companyId);
        $seedError = '';
        $inserted = itm_seed_table_from_database_sql($conn, $table, $companyId, $seedError);
        $liveAfter = ves_count_visible_live_employees($conn, $companyId);

        if ($inserted <= 0 || $liveAfter < 1) {
            ves_fail("Company {$companyId} cycle {$cycle}: Add sample data expected >=1 visible live row; inserted={$inserted} live={$liveAfter} err={$seedError}");
            $localFailures++;
            continue;
        }
        ves_pass("Company {$companyId} cycle {$cycle}: Add sample data (visible live {$liveBefore} → {$liveAfter}, inserted={$inserted}).");

        if (!ves_clear_visible_employees($conn, $companyId)) {
            ves_fail("Company {$companyId} cycle {$cycle}: soft-delete clear failed.");
            $localFailures++;
            continue;
        }

        $liveDeleted = ves_count_visible_live_employees($conn, $companyId);
        if ($liveDeleted !== 0) {
            ves_fail("Company {$companyId} cycle {$cycle}: expected 0 visible live rows after delete; live={$liveDeleted}");
            $localFailures++;
            continue;
        }
        ves_pass("Company {$companyId} cycle {$cycle}: soft-delete cleared visible live rows.");
    }

    if ($localFailures === 0) {
        ves_pass("Company {$companyId}: completed {$cycles} Add sample data → Delete cycles.");
    }

    return $localFailures;
}

if (!($conn instanceof mysqli)) {
    ves_fail('Database connection is required.');
    exit(1);
}

$cycles = 4;
$singleCompanyId = null;

if ($vesIsCli) {
    foreach ($GLOBALS['argv'] ?? [] as $arg) {
        if (preg_match('/^--company=(.+)$/', (string)$arg, $match)) {
            $companyArg = strtolower(trim((string)$match[1]));
            if ($companyArg !== '' && $companyArg !== 'all') {
                $singleCompanyId = (int)$companyArg;
            }
        } elseif (preg_match('/^--cycles=(\d+)$/', (string)$arg, $match)) {
            $cycles = max(1, (int)$match[1]);
        }
    }
} else {
    if (isset($_GET['company']) && (string)$_GET['company'] !== '') {
        $companyArg = strtolower(trim((string)$_GET['company']));
        if ($companyArg !== 'all') {
            $singleCompanyId = (int)$_GET['company'];
        }
    }
    if (isset($_GET['cycles']) && (string)$_GET['cycles'] !== '') {
        $cycles = max(1, (int)$_GET['cycles']);
    }
}

if ($singleCompanyId !== null && $singleCompanyId <= 0) {
    ves_fail('company must be a positive integer or all.');
    exit(1);
}

$companyIds = ves_resolve_seed_company_ids($conn, $singleCompanyId);
if ($companyIds === []) {
    ves_fail('No companies resolved for verification.');
    exit(1);
}

itm_script_write_stdout('Companies: ' . implode(', ', $companyIds) . $nl);
itm_script_write_stdout('Cycles per company: ' . $cycles . $nl . $nl);

foreach ($companyIds as $companyId) {
    $failures += ves_run_cycles_for_company($conn, (int)$companyId, $cycles);
}

itm_script_write_stdout($nl);
if ($failures === 0) {
    itm_script_write_stdout('[OK] All employees sample cycles passed.' . $nl);
    itm_script_output_end(0);
}

itm_script_write_stderr('[FAIL] ' . $failures . ' failure(s).' . $nl);
itm_script_output_end(1);
