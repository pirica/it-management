<?php
/**
 * CLI: php scripts/fix_crud_record_share_view_buttons.php [--apply]
 * Browser: scripts/fix_crud_record_share_view_buttons.php?apply=1 (Admin)
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/fix_crud_record_share_view_buttons.php --apply</code><br>
Browser: repairs record share action button markup in index.php of CRUD modules.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$boot = itm_apply_script_bootstrap('Fix CRUD record share view buttons');
$apply = $boot['apply'];
$nl = $boot['nl'];
$repoRoot = rtrim($boot['root'], '/\\');
$conn = $boot['conn'];

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

$changes = 0;
foreach ($modules as $slug => $label) {
    $path = $repoRoot . '/modules/' . $slug . '/index.php';
    if (!is_file($path)) {
        continue;
    }
    $body = file_get_contents($path);
    if ($body === false) {
        continue;
    }
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
        echo ($apply ? '[PATCH]' : '[DRY]') . " {$path}" . $nl;
        if ($apply) {
            file_put_contents($path, $newBody);
        }
        $changes++;
    }
}

echo ($apply ? "Patched {$changes} file(s)." : "Dry run: {$changes} file(s).") . $nl;

itm_apply_script_finish_hint($apply, $boot['is_cli'], $changes, $nl, 'fix_crud_record_share_view_buttons.php');

itm_script_output_end();
exit(0);
