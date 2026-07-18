<?php
/**
 * Backfill explicit company_module_access rows (enabled=1) for every active company × registry module.
 *
 * Idempotent: INSERT IGNORE only inserts missing pairs. A result of 0 new rows usually means
 * database.sql (or a prior run) already seeded full coverage.
 *
 * CLI: php scripts/seed_company_module_access.php
 * CLI: php scripts/seed_company_module_access.php 3  (single company)
 * Browser: scripts/seed_company_module_access.php (admin session)
 */

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Seed Company Module Access');
$nl = itm_script_output_nl();

if (!$itmIsCli) {
    itm_script_output_close_pre();
    echo '<h1>Seed Company Module Access</h1>';
}

if (!$conn instanceof mysqli) {
    echo itm_script_format_status_line('[FAIL] Database connection is required.') . $nl;
    itm_script_output_end();
    exit(1);
}

/**
 * @return array{companies:int,modules:int,rows:int,expected:int,missing:int}
 */
function seed_cma_count_coverage(mysqli $conn): array
{
    $stats = ['companies' => 0, 'modules' => 0, 'rows' => 0, 'expected' => 0, 'missing' => 0];

    $res = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM companies WHERE active = 1');
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $stats['companies'] = (int)($row['c'] ?? 0);
    }

    $res = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM modules_registry');
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $stats['modules'] = (int)($row['c'] ?? 0);
    }

    $res = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM company_module_access');
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $stats['rows'] = (int)($row['c'] ?? 0);
    }

    $stats['expected'] = $stats['companies'] * $stats['modules'];

    $res = mysqli_query(
        $conn,
        'SELECT COUNT(*) AS c
         FROM companies c
         CROSS JOIN modules_registry mr
         LEFT JOIN company_module_access cma
           ON cma.company_id = c.id AND cma.module_id = mr.id
         WHERE c.active = 1 AND cma.id IS NULL'
    );
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        $stats['missing'] = (int)($row['c'] ?? 0);
    }

    return $stats;
}

itm_sync_modules_registry_from_filesystem($conn);

$companyId = ($itmIsCli && isset($argv[1])) ? (int)$argv[1] : 0;
$before = seed_cma_count_coverage($conn);
$inserted = 0;

if ($companyId > 0) {
    $inserted = itm_seed_company_module_access_for_company($conn, $companyId);
    $after = seed_cma_count_coverage($conn);

    echo colorText('[INFO] Company ' . $companyId . ' backfill', 'info') . $nl;
    echo '[INFO] Registry modules: ' . $after['modules'] . $nl;
    echo '[INFO] Rows for this company before: ' . ($before['rows'] > 0 ? 'see total below' : '0') . $nl;
    echo '[INFO] New rows inserted (INSERT IGNORE): ' . $inserted . $nl;
    echo '[INFO] Total company_module_access rows now: ' . $after['rows'] . $nl;

    if ($inserted > 0) {
        echo itm_script_format_status_line('[PASS] Seeded ' . $inserted . ' access row(s) for company ' . $companyId . ' (enabled=1).') . $nl;
    } elseif ($after['missing'] === 0) {
        echo itm_script_format_status_line('[PASS] Company ' . $companyId . ' already has all registry modules enabled (0 new rows).') . $nl;
    } else {
        echo itm_script_format_status_line('[FAIL] Company ' . $companyId . ' still missing ' . $after['missing'] . ' pair(s) after seed.') . $nl;
        itm_script_output_end();
        exit(1);
    }

    itm_script_output_end();
    exit(0);
}

$inserted = itm_seed_company_module_access_all($conn);
$after = seed_cma_count_coverage($conn);

echo colorText('Seed Company Module Access', 'info') . $nl;
echo '[INFO] Active companies: ' . $after['companies'] . $nl;
echo '[INFO] Registry modules: ' . $after['modules'] . $nl;
echo '[INFO] Expected pairs (company × module): ' . $after['expected'] . $nl;
echo '[INFO] Rows before seed: ' . $before['rows'] . $nl;
echo '[INFO] New rows inserted (INSERT IGNORE): ' . $inserted . $nl;
echo '[INFO] Rows after seed: ' . $after['rows'] . $nl;
echo '[INFO] Missing pairs after seed: ' . $after['missing'] . $nl;

if ($after['missing'] > 0) {
    echo itm_script_format_status_line('[FAIL] Still missing ' . $after['missing'] . ' company×module pair(s) after seed.') . $nl;
    itm_script_output_end();
    exit(1);
}

if ($inserted > 0) {
    echo itm_script_format_status_line('[PASS] Seeded ' . $inserted . ' company_module_access row(s) (enabled=1).') . $nl;
} else {
    echo itm_script_format_status_line('[PASS] Already complete — all ' . $after['expected'] . ' pairs present (0 new rows; idempotent).') . $nl;
}

itm_script_output_end();
exit(0);
