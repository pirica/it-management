<?php
/**
 * Verifies registration_invitations Add sample data → soft-delete → re-seed cycle.
 *
 * CLI:
 *   php scripts/verify_registration_invitations_sample_cycle.php
 *   php scripts/verify_registration_invitations_sample_cycle.php --company=all --cycles=4
 *   php scripts/verify_registration_invitations_sample_cycle.php --company=4 --cycles=4
 *
 * Browser (Admin): scripts/verify_registration_invitations_sample_cycle.php?run=1&cycles=4
 *   (all seed companies 1–5) or ?run=1&company=4 for one tenant.
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<strong>Admin login required</strong> in browser.<br>
CLI: <code>php scripts/verify_registration_invitations_sample_cycle.php --cycles=4</code> (companies 1–5)<br>
CLI single tenant: <code>php scripts/verify_registration_invitations_sample_cycle.php --company=4 --cycles=4</code><br>
Browser (multi-company): <a href="verify_registration_invitations_sample_cycle.php?run=1&amp;cycles=4">verify_registration_invitations_sample_cycle.php?run=1&amp;cycles=4</a><br>
Browser (one company): <a href="verify_registration_invitations_sample_cycle.php?run=1&amp;company=4&amp;cycles=4">verify_registration_invitations_sample_cycle.php?run=1&amp;company=4&amp;cycles=4</a>
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

/**
 * @return list<int>
 */
function vri_resolve_seed_company_ids($conn, ?int $singleCompanyId): array
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

function vri_soft_delete_live_invitations($conn, int $companyId): bool
{
    $table = 'registration_invitations';
    $where = ' WHERE company_id=' . (int)$companyId;
    if (function_exists('itm_crud_append_not_deleted_predicate')) {
        $where = itm_crud_append_not_deleted_predicate($where);
    }
    $deleteSql = itm_crud_build_soft_delete_sql($table, $where, 1);
    $dbCode = 0;
    $dbMsg = '';
    return itm_run_query($conn, $deleteSql, $dbCode, $dbMsg) !== false;
}

function vri_run_cycles_for_company($conn, int $companyId, int $cycles): int
{
    global $nl;
    $localFailures = 0;
    $table = 'registration_invitations';

    if (!vri_soft_delete_live_invitations($conn, $companyId)) {
        vri_fail("Company {$companyId}: could not clear live rows before cycles.");
        return 1;
    }

    $liveStart = itm_seed_tenant_row_count($conn, $table, $companyId);
    if ($liveStart !== 0) {
        vri_fail("Company {$companyId}: expected live=0 before cycles; live={$liveStart}");
        return 1;
    }

    for ($cycle = 1; $cycle <= $cycles; $cycle++) {
        $liveBefore = itm_seed_tenant_row_count($conn, $table, $companyId);
        $seedError = '';
        $inserted = itm_seed_table_from_database_sql($conn, $table, $companyId, $seedError);
        $liveAfter = itm_seed_tenant_row_count($conn, $table, $companyId);

        if ($inserted <= 0 || $liveAfter < 1) {
            vri_fail("Company {$companyId} cycle {$cycle}: Add sample data expected >=1 live row; inserted={$inserted} live={$liveAfter} err={$seedError}");
            $localFailures++;
            continue;
        }
        vri_pass("Company {$companyId} cycle {$cycle}: Add sample data (live {$liveBefore} → {$liveAfter}, inserted={$inserted}).");

        if (!vri_soft_delete_live_invitations($conn, $companyId)) {
            vri_fail("Company {$companyId} cycle {$cycle}: soft-delete failed.");
            $localFailures++;
            continue;
        }

        $liveDeleted = itm_seed_tenant_row_count($conn, $table, $companyId);
        if ($liveDeleted !== 0) {
            vri_fail("Company {$companyId} cycle {$cycle}: expected 0 live rows after delete; live={$liveDeleted}");
            $localFailures++;
            continue;
        }
        vri_pass("Company {$companyId} cycle {$cycle}: soft-delete cleared live rows.");
    }

    if ($localFailures === 0) {
        vri_pass("Company {$companyId}: completed {$cycles} Add sample data → Delete cycles.");
    }

    return $localFailures;
}

if (!($conn instanceof mysqli)) {
    vri_fail('Database connection is required.');
    exit(1);
}

$cycles = 4;
$singleCompanyId = null;

if ($vriIsCli) {
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
    vri_fail('company must be a positive integer or all.');
    exit(1);
}

$companyIds = vri_resolve_seed_company_ids($conn, $singleCompanyId);
if ($companyIds === []) {
    vri_fail('No companies resolved for verification.');
    exit(1);
}

$_SESSION['employee_id'] = 1;

foreach ($companyIds as $companyId) {
    $_SESSION['company_id'] = $companyId;
    $failures += vri_run_cycles_for_company($conn, $companyId, $cycles);
}

if ($failures > 0) {
    itm_script_output_end(1);
    exit(1);
}

$companyLabel = $singleCompanyId !== null
    ? (string)$singleCompanyId
    : implode(',', $companyIds);
vri_pass("All checks passed for companies {$companyLabel} ({$cycles} cycles each).");
itm_script_output_end(0);
