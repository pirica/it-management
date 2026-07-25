<?php
/**
 * Apply company_module_share capable seed (same as db/migrations/company_module_share_capable_seed.sql).
 *
 * Why: After adding a new company or new share-capable slugs, backfill enabled=1 rows and remove
 * non-capable matrix rows. Slugs always match itm_qr_share_capable_module_slugs().
 *
 * Browser + CLI. Default dry-run; writes with CLI --apply or browser ?apply=1 (Admin).
 * Optional single company: CLI --company=N or argv[1]; browser ?company=N.
 *
 * CLI: php scripts/apply_new_company_module_share_capable_seed.php
 * CLI: php scripts/apply_new_company_module_share_capable_seed.php --apply
 * CLI: php scripts/apply_new_company_module_share_capable_seed.php --apply --company=3
 * Browser: scripts/apply_new_company_module_share_capable_seed.php?company=3&apply=1
 */

declare(strict_types=1);

$isCliBootstrap = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($isCliBootstrap && !defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}
require_once dirname(__DIR__) . '/config/config.php';

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_apply_new_company_module_share_seed.php';

$boot = itm_apply_script_bootstrap('Apply new company module share capable seed', ['skip_db_tests' => false]);
$apply = $boot['apply'];
$nl = $boot['nl'];
$isCli = $boot['is_cli'];
$argv = $boot['argv'];

$companyId = 0;
if ($isCli) {
    foreach ($argv as $arg) {
        if (preg_match('/^--company=(\d+)$/', (string)$arg, $m)) {
            $companyId = (int)$m[1];
            break;
        }
    }
    if ($companyId <= 0 && isset($argv[1]) && preg_match('/^\d+$/', (string)$argv[1])) {
        $companyId = (int)$argv[1];
    }
} else {
    $companyId = (int)($_GET['company'] ?? 0);
}

if (!($conn instanceof mysqli)) {
    echo colorText('[FAIL] Database connection required.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

itm_sync_modules_registry_from_filesystem($conn);

$report = itm_apply_new_company_module_share_seed_report($conn, $companyId);
if (empty($report['ok'])) {
    echo colorText('[FAIL] ' . (string)($report['error'] ?? 'Report failed.'), 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

if (!$isCli) {
    itm_script_output_close_pre();
    echo '<h1>Apply company_module_share capable seed</h1>';
    echo '<p>Equivalent to <code>db/migrations/company_module_share_capable_seed.sql</code>. ';
    echo 'Dry-run by default; add <code>?apply=1</code> (Admin) to write.</p>';
    if ($companyId > 0) {
        echo '<p>Company filter: <strong>' . (int)$companyId . '</strong></p>';
    }
}

echo colorText('Apply new company module share capable seed', 'info') . $nl;
if ($companyId > 0) {
    echo '[INFO] Company filter: ' . $companyId . $nl;
} else {
    echo '[INFO] Scope: all active companies' . $nl;
}
echo '[INFO] Capable slugs (code): ' . (int)$report['capable_slug_count'] . $nl;
echo '[INFO] Capable registry rows: ' . (int)($report['capable_registry_rows'] ?? 0) . $nl;
echo '[INFO] Active companies in scope: ' . (int)$report['active_companies'] . $nl;
echo '[INFO] Expected capable pairs: ' . (int)$report['expected_pairs'] . $nl;
echo '[INFO] Existing capable rows: ' . (int)$report['existing_capable_rows'] . $nl;
echo '[INFO] Non-capable rows to DELETE: ' . (int)$report['non_capable_rows'] . $nl;
echo '[INFO] Missing capable pairs to INSERT: ' . (int)$report['missing_pairs'] . $nl;

if (!$apply) {
    echo colorText('[INFO] Dry-run only — re-run with --apply or ?apply=1 to execute.', 'info') . $nl;
    if (!$isCli) {
        $qs = 'apply=1';
        if ($companyId > 0) {
            $qs .= '&company=' . (int)$companyId;
        }
        echo '<p><a href="?' . htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') . '">Apply now</a></p>';
    }
    itm_script_output_end();
    exit(0);
}

$result = itm_apply_new_company_module_share_seed_run($conn, $companyId);
if (empty($result['ok'])) {
    echo colorText('[FAIL] ' . (string)($result['error'] ?? 'Apply failed.'), 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo '[INFO] Rows deleted (non-capable): ' . (int)$result['deleted'] . $nl;
echo '[INFO] Rows inserted (INSERT IGNORE): ' . (int)$result['inserted'] . $nl;

$after = $result['report'];
if ((int)($after['non_capable_rows'] ?? -1) !== 0 || (int)($after['missing_pairs'] ?? -1) !== 0) {
    echo colorText(
        '[FAIL] After apply: non_capable=' . (int)($after['non_capable_rows'] ?? -1)
        . ' missing_pairs=' . (int)($after['missing_pairs'] ?? -1),
        'fail'
    ) . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('[PASS] company_module_share capable seed is complete for scope.', 'pass') . $nl;
if (!$isCli) {
    echo '<p><a href="verify_module_share.php">Verify module share</a></p>';
}
itm_script_output_end();
exit(0);
