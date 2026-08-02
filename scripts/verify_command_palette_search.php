<?php
/**
 * Global command-palette search regression checks.
 *
 * CLI: php scripts/verify_command_palette_search.php
 * Browser: scripts/verify_command_palette_search.php?run=1
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_command_palette_search.php</code> — exit <code>1</code> on failure. Run when changing <code>includes/itm_command_palette_search.php</code>, <code>modules/search/api.php</code>, <code>js/command-palette.js</code>, or <code>includes/header.php</code> palette wiring.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/../includes/itm_command_palette_search.php';

itm_script_output_begin('Command Palette Search Verification');

$nl = itm_script_output_nl();
$failures = 0;

function cps_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function cps_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    cps_verify_fail('No database connection.');
    exit(1);
}

$requiredFiles = [
    'includes/itm_command_palette_search.php',
    'modules/search/api.php',
    'modules/search/index.php',
    'js/command-palette.js',
];
foreach ($requiredFiles as $relativePath) {
    $fullPath = ROOT_PATH . $relativePath;
    if (!is_file($fullPath)) {
        cps_verify_fail('Missing file ' . $relativePath . '.');
    } else {
        cps_verify_pass('File exists: ' . $relativePath);
    }
}

$headerCode = (string)@file_get_contents(ROOT_PATH . 'includes/header.php');
if (strpos($headerCode, 'data-itm-command-palette-open="1"') === false) {
    cps_verify_fail('includes/header.php missing command-palette trigger button.');
} else {
    cps_verify_pass('Header exposes command-palette open button.');
}
if (strpos($headerCode, 'command-palette.js') === false) {
    cps_verify_fail('includes/header.php missing command-palette.js include.');
} else {
    cps_verify_pass('Header loads command-palette.js.');
}

$apiCode = (string)@file_get_contents(ROOT_PATH . 'modules/search/api.php');
if (strpos($apiCode, 'itm_api_enforce_rate_limit_or_exit') === false) {
    cps_verify_fail('modules/search/api.php missing rate-limit enforcement.');
} else {
    cps_verify_pass('Search API enforces rate limits.');
}
if (strpos($apiCode, 'itm_command_palette_search') === false) {
    cps_verify_fail('modules/search/api.php missing unified search helper.');
} else {
    cps_verify_pass('Search API delegates to itm_command_palette_search().');
}

$schemaSql = (string)@file_get_contents(ROOT_PATH . 'db/01_schema.sql');
if (strpos($schemaSql, 'CREATE TABLE `search_index`') === false) {
    cps_verify_fail('db/01_schema.sql missing search_index table (phase 2).');
} else {
    cps_verify_pass('search_index schema present in db/01_schema.sql.');
}

$slugs = itm_command_palette_searchable_module_slugs();
$expected = ['employees', 'equipment', 'tickets', 'ip_addresses', 'catalogs'];
if ($slugs !== $expected) {
    cps_verify_fail('Searchable module slug list drifted from phase-1 contract.');
} else {
    cps_verify_pass('Phase-1 searchable modules: ' . implode(', ', $slugs) . '.');
}

$companyId = 1;
$adminStmt = mysqli_prepare(
    $conn,
    "SELECT id FROM employees WHERE company_id = ? AND username = 'Admin' AND deleted_at IS NULL LIMIT 1"
);
$adminId = 0;
if ($adminStmt) {
    mysqli_stmt_bind_param($adminStmt, 'i', $companyId);
    mysqli_stmt_execute($adminStmt);
    $adminRow = mysqli_fetch_assoc(mysqli_stmt_get_result($adminStmt));
    mysqli_stmt_close($adminStmt);
    $adminId = (int)($adminRow['id'] ?? 0);
}

if ($adminId <= 0) {
    cps_verify_fail('Seed Admin employee for company 1 not found.');
} else {
    cps_verify_pass('Resolved seed Admin employee id ' . $adminId . '.');

    if (!itm_command_palette_user_can_search_module($conn, $companyId, $adminId, 'employees')) {
        cps_verify_fail('Admin should be allowed to search employees.');
    } else {
        cps_verify_pass('Admin can search employees module.');
    }

    $payload = itm_command_palette_search($conn, $companyId, $adminId, 'Admin', 3);
    if (!is_array($payload) || !isset($payload['groups']) || !is_array($payload['groups'])) {
        cps_verify_fail('itm_command_palette_search() returned invalid payload.');
    } else {
        cps_verify_pass('Unified search returns groups payload.');
        $hasEmployeeHit = false;
        foreach ($payload['groups'] as $group) {
            if (($group['module_slug'] ?? '') === 'employees' && !empty($group['results'])) {
                $hasEmployeeHit = true;
                $first = $group['results'][0];
                if (empty($first['url']) || strpos((string)$first['url'], 'modules/employees/view.php?id=') === false) {
                    cps_verify_fail('Employee result missing view.php URL.');
                } else {
                    cps_verify_pass('Employee search result includes view URL.');
                }
            }
        }
        if (!$hasEmployeeHit) {
            cps_verify_fail('Expected at least one employees group result for query Admin.');
        }
    }
}

$demoStmt = mysqli_prepare(
    $conn,
    "SELECT e.id FROM employees e
     INNER JOIN employee_roles er ON er.id = e.role_id AND er.company_id = e.company_id
     WHERE e.company_id = ? AND e.deleted_at IS NULL AND LOWER(er.name) = 'demo' LIMIT 1"
);
$demoId = 0;
if ($demoStmt) {
    mysqli_stmt_bind_param($demoStmt, 'i', $companyId);
    mysqli_stmt_execute($demoStmt);
    $demoRow = mysqli_fetch_assoc(mysqli_stmt_get_result($demoStmt));
    mysqli_stmt_close($demoStmt);
    $demoId = (int)($demoRow['id'] ?? 0);
}

if ($demoId > 0) {
    $_SESSION['employee_id'] = $demoId;
    $_SESSION['company_id'] = $companyId;
    if (itm_command_palette_user_can_search_module($conn, $companyId, $demoId, 'employees')) {
        cps_verify_fail('Non-admin Demo role should not search employees.');
    } else {
        cps_verify_pass('Non-admin Demo role blocked from employees search.');
    }
} else {
    cps_verify_pass('Demo employee not seeded — skipped non-admin employees RBAC probe.');
}

if ($failures > 0) {
    echo $nl . colorText('FAILED: ' . $failures . ' check(s).', 'fail') . $nl;
    exit(1);
}

echo $nl . colorText('All command palette checks passed.', 'pass') . $nl;
exit(0);
