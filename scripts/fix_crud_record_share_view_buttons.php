<?php
/**
 * Repair CRUD record share buttons on scaffold index.php inline view blocks.
 *
 * Browser: dry-run by default; ?apply=1 (Admin) writes module files.
 * CLI: php scripts/fix_crud_record_share_view_buttons.php [--apply]
 */
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_fix_script_report.php';

$boot = itm_apply_script_bootstrap('Fix CRUD record share view buttons');
$apply = $boot['apply'];
$nl = $boot['nl'];
$repoRoot = rtrim($boot['root'], '/\\');

$modules = [
    'departments' => 'department',
    'catalogs' => 'catalog',
    'license_management' => 'license',
    'inventory_items' => 'inventory item',
    'suppliers' => 'supplier',
    'alerts' => 'alert',
    'patches_updates' => 'patch',
    'annual_budgets' => 'annual budget',
    'approvals' => 'approval',
    'approvals_stage' => 'approvals stage',
    'approver_type' => 'approver type',
    'approvers' => 'approver',
    'budget_categories' => 'budget category',
    'cost_centers' => 'cost center',
    'expenses' => 'expense',
    'forecast_revisions' => 'forecast revision',
    'forecast_revisions_status' => 'forecast status',
    'gl_accounts' => 'GL account',
    'monthly_budgets' => 'monthly budget',
];

$sqlBundleItems = [itm_fix_script_report_sql_na_item()];
$liveDbItems = [itm_fix_script_report_na_item()];
$fixItems = [];

foreach ($modules as $slug => $label) {
    $path = $repoRoot . '/modules/' . $slug . '/index.php';
    $relativePath = 'modules/' . $slug . '/index.php';
    if (!is_file($path)) {
        continue;
    }
    $body = file_get_contents($path);
    if (strpos($body, 'itm_crud_record_share_render_action_buttons') !== false) {
        continue;
    }
    $insert = "<?php echo itm_crud_record_share_render_action_buttons('{$slug}', (int)(\$data['id'] ?? 0), '{$label}'); ?>\n                        ";
    $patterns = [
        '<p style="margin-top:16px;"><a href="index.php" class="btn">🔙</a> <a class="btn btn-primary" href="edit.php?id=<?php echo (int)($data[\'id\'] ?? 0); ?>">✏️</a></p>' =>
            '<p style="margin-top:16px;">' . "\n                        " . $insert . '<a href="index.php" class="btn" title="Back">🔙</a> <a class="btn btn-primary" href="edit.php?id=<?php echo (int)($data[\'id\'] ?? 0); ?>" title="Edit">✏️</a></p>',
        '<p style="margin-top:16px;">' . "\n                        " . '<a href="index.php" class="btn">🔙</a>' =>
            '<p style="margin-top:16px;">' . "\n                        " . $insert . '<a href="index.php" class="btn" title="Back">🔙</a>',
    ];
    $newBody = $body;
    foreach ($patterns as $from => $to) {
        if (strpos($newBody, $from) !== false) {
            $newBody = str_replace($from, $to, $newBody);
            break;
        }
    }
    if ($newBody !== $body) {
        $fixItems[] = $relativePath . ': insert itm_crud_record_share_render_action_buttons() on inline view block';
        if ($apply) {
            file_put_contents($path, $newBody);
        }
    }
}

itm_fix_script_report_finish(
    $apply,
    $boot['is_cli'],
    $fixItems !== [],
    $nl,
    'fix_crud_record_share_view_buttons.php',
    $liveDbItems,
    $sqlBundleItems,
    $fixItems
);

itm_script_output_end();
