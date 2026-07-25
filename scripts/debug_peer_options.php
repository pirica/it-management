<?php
/**
 * Debug Live Chat peer picker options vs chat_same_tenant and tenant access.
 *
 * Prints it_settings.chat_same_tenant, accessible companies for the session employee,
 * and merged peer options (same helpers as modules/live_chat/api.php list_employees).
 *
 * Browser: scripts/debug_peer_options.php?company_id=4&employee_id=4
 * CLI: php scripts/debug_peer_options.php --company_id=4 --employee_id=4
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/debug_peer_options.php --company_id=4 --employee_id=4</code> — browser <code>?company_id=4&amp;employee_id=4</code>. Run when peer picker looks wrong vs Settings same-tenant toggle.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once dirname(__DIR__) . '/includes/itm_company_session.php';

itm_script_output_begin('Live Chat peer options debug');

$nl = itm_script_output_nl();
$isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';

$companyId = 1;
$employeeId = 1;
if ($isCli) {
    foreach ($GLOBALS['argv'] ?? [] as $arg) {
        $arg = (string)$arg;
        if (strpos($arg, '--company_id=') === 0) {
            $companyId = (int)substr($arg, strlen('--company_id='));
        }
        if (strpos($arg, '--employee_id=') === 0) {
            $employeeId = (int)substr($arg, strlen('--employee_id='));
        }
    }
} else {
    $companyId = (int)($_GET['company_id'] ?? 1);
    $employeeId = (int)($_GET['employee_id'] ?? (int)($_SESSION['employee_id'] ?? 1));
}

if (!($conn instanceof mysqli)) {
    echo colorText('[FAIL] Database connection unavailable.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

if ($companyId <= 0 || $employeeId <= 0) {
    echo colorText('[FAIL] company_id and employee_id must be positive integers.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$sameTenant = itm_it_settings_chat_same_tenant_enabled($conn, $companyId) ? 1 : 0;
$isAdmin = function_exists('itm_is_admin') && itm_is_admin($conn, $employeeId);
$accessible = itm_list_employee_accessible_companies($conn, $employeeId);
$accessibleIds = array_map(static function ($row) {
    return (int)($row['id'] ?? 0);
}, $accessible);

echo 'company_id=' . $companyId . $nl;
echo 'employee_id=' . $employeeId . ' admin=' . ($isAdmin ? '1' : '0') . $nl;
echo 'chat_same_tenant=' . $sameTenant . $nl;
echo 'accessible_company_ids=' . implode(',', array_filter($accessibleIds)) . $nl;

$options = itm_live_chat_peer_options_for_company($conn, $companyId, $employeeId);
echo 'peer_options_count=' . count($options) . $nl;
foreach ($options as $opt) {
    $id = (int)($opt['id'] ?? 0);
    $label = (string)($opt['label'] ?? '');
    $peer = $id === $employeeId ? ' (self)' : '';
    echo '  id=' . $id . ' label=' . $label . $peer . $nl;
}

$outForApi = [];
foreach ($options as $opt) {
    if ((int)($opt['id'] ?? 0) === $employeeId) {
        continue;
    }
    $outForApi[] = $opt;
}
echo 'list_employees_count_after_exclude_self=' . count($outForApi) . $nl;

itm_script_output_end();
exit(0);
