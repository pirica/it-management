<?php
/**
 * Align Add sample data empty gate with list queries (live rows: deleted_at IS NULL).
 *
 * Replaces company_id COUNT(*) blocks that ignore soft-delete with itm_seed_tenant_row_count().
 *
 * Usage:
 *   php scripts/apply_crud_sample_data_live_row_gate.php
 *   php scripts/apply_crud_sample_data_live_row_gate.php --apply
 *   php scripts/apply_crud_sample_data_live_row_gate.php --apply --finance-only
 */
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Apply CRUD sample data live row gate');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/');

$financeOnly = false;
if ($boot['is_cli']) {
    $financeOnly = in_array('--finance-only', $argv ?? [], true);
} elseif (isset($_GET['finance_only']) && (string) $_GET['finance_only'] === '1') {
    $financeOnly = true;
}

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

$oldPattern = '/    \$where = \' WHERE company_id=\' \. \(int\)\$company_id;\R'
    . '    \$countSql = \'SELECT COUNT\(\*\) AS total_rows FROM \' \. cr_escape_identifier\(\$crud_table\) \. \$where;\R'
    . '    \$countResult = mysqli_query\(\$conn, \$countSql\);\R'
    . '    \$existingRows = 0;\R'
    . '    if \(\$countResult && \(\$countRow = mysqli_fetch_assoc\(\$countResult\)\)\) \{\R'
    . '        \$existingRows = \(int\)\(\$countRow\[\'total_rows\'\] \?\? 0\);\R'
    . '    \}\R/s';

$new = "    // Why: List uses deleted_at IS NULL; sample gate must match live tenant rows (itm_seed_tenant_row_count).\n"
    . "    \$existingRows = function_exists('itm_seed_tenant_row_count')\n"
    . "        ? itm_seed_tenant_row_count(\$conn, \$crud_table, (int)\$company_id)\n"
    . "        : 0;\n";

$paths = glob($root . '/modules/*/index.php') ?: [];
$paths = array_merge($paths, glob($root . '/modules/*/list_all.php') ?: []);
$paths = array_merge($paths, [
    $root . '/modules/attempts/list_all.php',
    $root . '/modules/ip_subnets/includes/handlers_post.php',
    $root . '/modules/ip_addresses/includes/handlers_post.php',
]);

$changed = [];
$unchanged = [];
$skipped = [];

foreach ($paths as $path) {
    if (!is_file($path)) {
        continue;
    }
    $rel = itm_apply_script_rel_path($root, $path);
    if ($financeOnly && !preg_match('#modules[/\\\\]([^/\\\\]+)[/\\\\]#', $rel, $m)) {
        continue;
    }
    if ($financeOnly && !in_array($m[1] ?? '', $financeSlugs, true)) {
        $skipped[] = $rel;
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }
    if (!preg_match($oldPattern, $content)) {
        $unchanged[] = $rel;
        continue;
    }
    $patched = preg_replace($oldPattern, $new, $content, 1);
    if ($patched === $content) {
        $unchanged[] = $rel;
        continue;
    }
    if ($apply) {
        file_put_contents($path, $patched);
    }
    $changed[] = $rel;
}

$modeLabel = $apply ? 'Updated' : 'Would update';
echo $nl . $modeLabel . ' ' . count($changed) . ' file(s).' . $nl . $nl;
itm_apply_script_echo_list($modeLabel . ' files', $changed);
if ($financeOnly) {
    itm_apply_script_echo_list('Skipped (not finance slug)', array_slice($skipped, 0, 5));
}
itm_apply_script_echo_list('Unchanged (pattern not found)', $unchanged);
itm_apply_script_finish_hint($apply, $boot['is_cli'], count($changed), $nl, 'apply_crud_sample_data_live_row_gate.php');

itm_script_output_end();
exit(0);
