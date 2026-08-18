<?php
/**
 * Static audit: detect modules where $hasCompany is derived from UI-filtered
 * $fieldColumns after company_id was hidden (breaks tenant scope + Add sample data).
 *
 * Browser: scripts/check_crud_has_company_from_field_columns.php (Administrator session).
 * CLI: php scripts/check_crud_has_company_from_field_columns.php [--strict-warn]
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/itm_script_access_helpers.php';
require_once __DIR__ . '/lib/itm_crud_has_company_field_columns_audit.php';

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: plain-text report with module links. CLI: <code>php scripts/check_crud_has_company_from_field_columns.php</code> — exit <code>1</code> when any module hides <code>company_id</code> from <code>$fieldColumns</code> but still sets <code>$hasCompany</code> from that array (same bug as employee_notifications sample data). Optional <code>--strict-warn</code> also fails modules that still use the legacy <code>foreach ($fieldColumns …)</code> pattern without hiding <code>company_id</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$nl = itm_check_script_begin_browser_admin('CRUD hasCompany fieldColumns audit');

$argvList = $GLOBALS['argv'] ?? [];
$strictWarn = in_array('--strict-warn', $argvList, true)
    || (isset($_GET['strict_warn']) && (string) $_GET['strict_warn'] === '1');

$report = itm_crud_has_company_audit_collect_report(dirname(__DIR__));
$linkModules = !itm_script_access_is_cli();

echo itm_crud_has_company_audit_format_report($report, $nl, $linkModules);

if ($report['failures'] !== []) {
    echo colorText('FAIL: resolve hasCompany from schema $columns before UI field filters.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

if ($strictWarn && $report['warnings'] !== []) {
    echo colorText('FAIL (--strict-warn): migrate hasCompany to foreach ($columns as $c).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('PASS: no modules hide company_id from $fieldColumns while deriving $hasCompany there.', 'pass') . $nl;
itm_script_output_end();
exit(0);
