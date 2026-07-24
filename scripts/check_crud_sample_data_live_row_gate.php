<?php
/**
 * Fail when finance modules still use raw company_id COUNT for Add sample data gate.
 *
 * CLI: php scripts/check_crud_sample_data_live_row_gate.php
 */
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Check CRUD sample data live row gate (finance)');

$nl = itm_script_output_nl();
$root = rtrim(ROOT_PATH, '/\\');

$financeSlugs = [
    'tax_rates',
    'paid_statuses',
    'payment_modes',
    'expense_recurrence',
    'expenses',
    'bills',
    'invoices',
    'customers',
    'customer_statuses',
    'bank_accounts',
    'integration_accounts',
    'gl_accounts',
    'cost_centers',
    'budget_categories',
    'annual_budgets',
    'monthly_budgets',
    'forecast_revisions',
    'forecast_revisions_status',
    'approvals',
    'approvals_stage',
    'approvers',
    'approver_type',
];

$badPattern = "\$countSql = 'SELECT COUNT(*) AS total_rows FROM ' . cr_escape_identifier(\$crud_table)";
$failures = [];

foreach ($financeSlugs as $slug) {
    $indexPath = $root . '/modules/' . $slug . '/index.php';
    if (!is_file($indexPath)) {
        continue;
    }
    $content = file_get_contents($indexPath);
    if ($content === false) {
        $failures[] = $slug . ': unreadable index.php';
        continue;
    }
    if (strpos($content, 'add_sample_data') === false) {
        continue;
    }
    if (strpos($content, $badPattern) !== false) {
        $failures[] = $slug . ': Add sample data still uses raw COUNT (expected itm_seed_tenant_row_count)';
    }
    if (strpos($content, 'itm_seed_tenant_row_count') === false) {
        $failures[] = $slug . ': missing itm_seed_tenant_row_count in sample data handler';
    }
}

if ($failures !== []) {
    foreach ($failures as $line) {
        echo colorText('[FAIL] ' . $line, 'fail') . $nl;
    }
    itm_script_output_end();
    exit(1);
}

echo colorText('[PASS] All finance modules use live-row sample data gate.', 'pass') . $nl;
itm_script_output_end();
exit(0);
