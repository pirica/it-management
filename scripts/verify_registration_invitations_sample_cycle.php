<?php
/**
 * Verifies registration_invitations Add sample data → soft-delete → re-seed cycle.
 *
 * CLI:
 *   php scripts/verify_registration_invitations_sample_cycle.php
 *   php scripts/verify_registration_invitations_sample_cycle.php --company=4 --cycles=4
 *
 * Browser (Admin): scripts/verify_registration_invitations_sample_cycle.php?run=1&company=4
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<strong>Admin login required</strong> in browser.<br>
CLI: <code>php scripts/verify_registration_invitations_sample_cycle.php --company=4 --cycles=4</code><br>
Browser: <a href="verify_registration_invitations_sample_cycle.php?run=1&amp;company=4">verify_registration_invitations_sample_cycle.php?run=1&amp;company=4</a>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$vriIsCli = PHP_SAPI === 'cli';

if ($vriIsCli && !defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_crud_audit_fields.php';
require_once ROOT_PATH . 'includes/itm_sample_data_seed.php';
require_once __DIR__ . '/lib/script_cli_output.php';

if (!$vriIsCli) {
    itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
}

itm_script_output_begin('Registration invitations sample cycle verification');

$nl = itm_script_output_nl();
$failures = 0;

function vri_fail(string $message): void
{
    global $failures, $nl;
    $failures++;
    itm_script_write_stderr('[FAIL] ' . $message . $nl);
}

function vri_pass(string $message): void
{
    global $nl;
    itm_script_write_stdout('[PASS] ' . $message . $nl);
}

if (!($conn instanceof mysqli)) {
    vri_fail('Database connection is required.');
    exit(1);
}

$companyId = 4;
$cycles = 4;

if ($vriIsCli) {
    foreach ($GLOBALS['argv'] ?? [] as $arg) {
        if (preg_match('/^--company=(\d+)$/', (string)$arg, $match)) {
            $companyId = (int)$match[1];
        } elseif (preg_match('/^--cycles=(\d+)$/', (string)$arg, $match)) {
            $cycles = max(1, (int)$match[1]);
        }
    }
} else {
    if (isset($_GET['company']) && (string)$_GET['company'] !== '') {
        $companyId = (int)$_GET['company'];
    }
    if (isset($_GET['cycles']) && (string)$_GET['cycles'] !== '') {
        $cycles = max(1, (int)$_GET['cycles']);
    }
}

if ($companyId <= 0) {
    vri_fail('company must be a positive integer.');
    exit(1);
}

$_SESSION['employee_id'] = 1;
$_SESSION['company_id'] = $companyId;

$table = 'registration_invitations';

for ($cycle = 1; $cycle <= $cycles; $cycle++) {
    $liveBefore = itm_seed_tenant_row_count($conn, $table, $companyId);
    $seedError = '';
    $inserted = itm_seed_table_from_database_sql($conn, $table, $companyId, $seedError);
    $liveAfter = itm_seed_tenant_row_count($conn, $table, $companyId);

    if ($inserted <= 0 || $liveAfter < 1) {
        vri_fail("Cycle {$cycle}: Add sample data expected >=1 live row; inserted={$inserted} live={$liveAfter} err={$seedError}");
        continue;
    }
    vri_pass("Cycle {$cycle}: Add sample data (live {$liveBefore} → {$liveAfter}, inserted={$inserted}).");

    $where = ' WHERE company_id=' . (int)$companyId;
    if (function_exists('itm_crud_append_not_deleted_predicate')) {
        $where = itm_crud_append_not_deleted_predicate($where);
    }
    $deleteSql = itm_crud_build_soft_delete_sql($table, $where, 1);
    $dbCode = 0;
    $dbMsg = '';
    if (itm_run_query($conn, $deleteSql, $dbCode, $dbMsg) === false) {
        vri_fail("Cycle {$cycle}: soft-delete failed code={$dbCode} msg={$dbMsg}");
        continue;
    }

    $liveDeleted = itm_seed_tenant_row_count($conn, $table, $companyId);
    if ($liveDeleted !== 0) {
        vri_fail("Cycle {$cycle}: expected 0 live rows after delete; live={$liveDeleted}");
        continue;
    }
    vri_pass("Cycle {$cycle}: soft-delete cleared live rows.");
}

if ($failures > 0) {
    itm_script_output_end(1);
    exit(1);
}

vri_pass("Completed {$cycles} Add sample data → Delete cycles for company {$companyId}.");
itm_script_output_end(0);
