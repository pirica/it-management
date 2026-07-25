<?php
/**
 * CLI: php scripts/patch_crud_share_agent_notes.php [--apply]
 * Appends Share section to CRUD record share rollout module AGENT_NOTES.md files.
 */

define('ITM_CLI_SCRIPT', true);
$apply = in_array('--apply', $argv ?? [], true);
$repoRoot = dirname(__DIR__);

$modules = [
    'employees' => 'Share buttons on view.php (admin employee profile).',
    'departments' => 'Share buttons on index.php inline view block.',
    'equipment' => 'Share on view.php; Switch Port Manager share on index.php?switch_id=&spm=1 (share_kind=switch_ports).',
    'catalogs' => 'Share buttons on index.php inline view block.',
    'license_management' => 'Share buttons on index.php inline view block.',
    'inventory_items' => 'Share buttons on view.php.',
    'suppliers' => 'Share buttons on index.php inline view block.',
    'alerts' => 'Share buttons on index.php inline view block.',
    'tickets' => 'Share buttons on view.php.',
    'patches_updates' => 'Share buttons on index.php inline view block.',
    'ops_report' => 'Share buttons on index.php report toolbar (loaded report_id).',
    'annual_budgets' => 'Share buttons on index.php inline view block.',
    'approvals' => 'Share buttons on index.php inline view block.',
    'approvals_stage' => 'Share buttons on index.php inline view block.',
    'approver_type' => 'Share buttons on index.php inline view block.',
    'approvers' => 'Share buttons on index.php inline view block.',
    'budget_categories' => 'Share buttons on index.php inline view block.',
    'cost_centers' => 'Share buttons on index.php inline view block.',
    'expenses' => 'Share buttons on index.php inline view block.',
    'forecast_revisions' => 'Share buttons on index.php inline view block.',
    'forecast_revisions_status' => 'Share buttons on index.php inline view block.',
    'gl_accounts' => 'Share buttons on index.php inline view block.',
    'monthly_budgets' => 'Share buttons on index.php inline view block.',
];

$marker = '## Share (temporary QR / code)';
$changes = 0;

foreach ($modules as $slug => $ui) {
    $path = $repoRoot . '/modules/' . $slug . '/AGENT_NOTES.md';
    if (!is_file($path)) {
        echo "[MISSING] {$path}\n";
        continue;
    }
    $body = file_get_contents($path);
    if (strpos($body, $marker) !== false) {
        echo "[SKIP] {$slug}\n";
        continue;
    }
    $block = "\n{$marker}\n"
        . "- **Capable:** `itm_qr_share_capable_module_slugs()`.\n"
        . "- **UI:** {$ui}\n"
        . "- **Wiring:** `includes/itm_crud_record_share.php`; public `join.php`; AJAX `index.php?ajax_action=create_share_session`. Company gate: `modules/share_modules/`.\n"
        . "- **Doc:** `docs/CRUD_RECORD_SHARE.md`.\n";
    echo ($apply ? '[WRITE]' : '[DRY]') . " {$slug}\n";
    if ($apply) {
        file_put_contents($path, rtrim($body) . $block);
    }
    $changes++;
}

echo $apply
    ? "Applied {$changes} module note patch(es).\n"
    : "Dry run — {$changes} patch(es). Re-run with --apply.\n";

exit(0);
