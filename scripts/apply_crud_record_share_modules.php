<?php
/**
 * CLI: php scripts/apply_crud_record_share_modules.php [--apply]
 * Wires QR share (join.php, AJAX, view buttons) for CRUD record share rollout modules.
 */

define('ITM_CLI_SCRIPT', true);
$repoRoot = dirname(__DIR__);
$apply = in_array('--apply', $argv ?? [], true);

$modules = [
    'employees' => ['share_label' => 'employee', 'view_file' => 'view.php', 'index_ajax' => 'index.php'],
    'departments' => ['share_label' => 'department', 'view_mode' => 'index'],
    'equipment' => ['share_label' => 'equipment', 'view_file' => 'view.php', 'index_ajax' => 'index.php', 'spm' => true],
    'catalogs' => ['share_label' => 'catalog', 'view_mode' => 'index'],
    'license_management' => ['share_label' => 'license', 'view_mode' => 'index'],
    'inventory_items' => ['share_label' => 'inventory item', 'view_mode' => 'index'],
    'suppliers' => ['share_label' => 'supplier', 'view_mode' => 'index'],
    'alerts' => ['share_label' => 'alert', 'view_mode' => 'index'],
    'tickets' => ['share_label' => 'ticket', 'view_file' => 'view.php', 'index_ajax' => 'index.php'],
    'patches_updates' => ['share_label' => 'patch', 'view_mode' => 'index'],
    'ops_report' => ['share_label' => 'ops report', 'view_mode' => 'index'],
    'annual_budgets' => ['share_label' => 'annual budget', 'view_mode' => 'index'],
    'approvals' => ['share_label' => 'approval', 'view_mode' => 'index'],
    'approvals_stage' => ['share_label' => 'approvals stage', 'view_mode' => 'index'],
    'approver_type' => ['share_label' => 'approver type', 'view_mode' => 'index'],
    'approvers' => ['share_label' => 'approver', 'view_mode' => 'index'],
    'budget_categories' => ['share_label' => 'budget category', 'view_mode' => 'index'],
    'cost_centers' => ['share_label' => 'cost center', 'view_mode' => 'index'],
    'expenses' => ['share_label' => 'expense', 'view_mode' => 'index'],
    'forecast_revisions' => ['share_label' => 'forecast revision', 'view_mode' => 'index'],
    'forecast_revisions_status' => ['share_label' => 'forecast status', 'view_mode' => 'index'],
    'gl_accounts' => ['share_label' => 'GL account', 'view_mode' => 'index'],
    'monthly_budgets' => ['share_label' => 'monthly budget', 'view_mode' => 'index'],
];

$joinTemplate = <<<'PHP'
<?php
/**
 * Public join page for temporary %s share sessions.
 */
define('ITM_QR_SHARE_PUBLIC', true);
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_crud_record_share.php';
itm_crud_record_share_render_join_page($conn, '%s');

PHP;

$ajaxBlock = <<<'PHP'

require_once ROOT_PATH . 'includes/itm_crud_record_share.php';
itm_crud_record_share_handle_ajax_request($conn, '%s');

PHP;

$changes = 0;

foreach ($modules as $slug => $meta) {
    $moduleDir = $repoRoot . '/modules/' . $slug;
    if (!is_dir($moduleDir)) {
        echo "[SKIP] missing module dir: {$slug}\n";
        continue;
    }

    $joinPath = $moduleDir . '/join.php';
    $joinBody = sprintf($joinTemplate, $slug, $slug);
    if (!is_file($joinPath) || sha1(file_get_contents($joinPath)) !== sha1($joinBody)) {
        echo ($apply ? '[WRITE]' : '[DRY]') . " {$joinPath}\n";
        if ($apply) {
            file_put_contents($joinPath, $joinBody);
        }
        $changes++;
    }

    $indexPath = $moduleDir . '/index.php';
    if (is_file($indexPath)) {
        $indexBody = file_get_contents($indexPath);
        $marker = "itm_crud_record_share_handle_ajax_request(\$conn, '{$slug}')";
        if (strpos($indexBody, $marker) === false) {
            $needle = "require_once '../../config/config.php';";
            if (strpos($indexBody, $needle) !== false) {
                $replacement = $needle . sprintf($ajaxBlock, $slug);
                $newBody = str_replace($needle, $replacement, $indexBody, $count);
                if ($count > 0) {
                    echo ($apply ? '[PATCH]' : '[DRY]') . " ajax {$indexPath}\n";
                    if ($apply) {
                        file_put_contents($indexPath, $newBody);
                    }
                    $changes++;
                }
            } else {
                $needle = "require '../../config/config.php';";
                if (strpos($indexBody, $needle) !== false) {
                    $replacement = $needle . sprintf($ajaxBlock, $slug);
                    $newBody = str_replace($needle, $replacement, $indexBody, $count);
                    if ($count > 0) {
                        echo ($apply ? '[PATCH]' : '[DRY]') . " ajax {$indexPath}\n";
                        if ($apply) {
                            file_put_contents($indexPath, $newBody);
                        }
                        $changes++;
                    }
                }
            }
        }

        if (($meta['view_mode'] ?? '') === 'index' && strpos($indexBody, 'itm_crud_record_share_render_action_buttons') === false) {
            $viewNeedle = '<p style="margin-top:16px;"><a href="index.php" class="btn">🔙</a>';
            $shareLabel = (string)($meta['share_label'] ?? $slug);
            $viewReplacement = '<p style="margin-top:16px;">' . "\n"
                . '                        <?php echo itm_crud_record_share_render_action_buttons(\'' . $slug . '\', (int)($data[\'id\'] ?? 0), \'' . $shareLabel . '\'); ?>' . "\n"
                . '                        <a href="index.php" class="btn" title="Back">🔙</a>';
            if (strpos($indexBody, $viewNeedle) !== false) {
                $newBody = str_replace($viewNeedle, $viewReplacement, $indexBody, $count);
                if ($count > 0) {
                    echo ($apply ? '[PATCH]' : '[DRY]') . " view buttons {$indexPath}\n";
                    if ($apply) {
                        file_put_contents($indexPath, $newBody);
                    }
                    $changes++;
                }
            }
        }

        if (strpos($indexBody, 'itm_crud_record_share_include_modal') === false && strpos($indexBody, 'itm_qr_share_modal.php') === false) {
            $footerNeedle = '</body>';
            $footerReplacement = "<?php itm_crud_record_share_include_modal(); ?>\n</body>";
            if (strpos($indexBody, $footerNeedle) !== false) {
                $newBody = str_replace($footerNeedle, $footerReplacement, $indexBody, $count);
                if ($count > 0) {
                    echo ($apply ? '[PATCH]' : '[DRY]') . " modal {$indexPath}\n";
                    if ($apply) {
                        file_put_contents($indexPath, $newBody);
                    }
                    $changes++;
                }
            }
        }
    }

    if (!empty($meta['view_file'])) {
        $viewPath = $moduleDir . '/' . $meta['view_file'];
        if (is_file($viewPath)) {
            $viewBody = file_get_contents($viewPath);
            $shareLabel = (string)($meta['share_label'] ?? $slug);
            if (strpos($viewBody, 'itm_crud_record_share_render_action_buttons') === false) {
                $patterns = [
                    '<a href="index.php" class="btn">🔙</a>',
                    '<a class="btn" href="index.php" title="Back">🔙</a>',
                ];
                foreach ($patterns as $pattern) {
                    if (strpos($viewBody, $pattern) !== false) {
                        $replacement = '<?php echo itm_crud_record_share_render_action_buttons(\'' . $slug . '\', (int)($employeeId ?? $id ?? $item[\'id\'] ?? 0), \'' . $shareLabel . '\'); ?>' . "\n                    " . $pattern;
                        $viewBody = str_replace($pattern, $replacement, $viewBody, $count);
                        if ($count > 0) {
                            echo ($apply ? '[PATCH]' : '[DRY]') . " view buttons {$viewPath}\n";
                            break;
                        }
                    }
                }
                if (strpos($viewBody, 'itm_crud_record_share_include_modal') === false && strpos($viewBody, 'itm_qr_share_modal.php') === false) {
                    $viewBody = str_replace('</body>', "<?php itm_crud_record_share_include_modal(); ?>\n</body>", $viewBody, $modalCount);
                }
                if ($apply && ($count > 0 || ($modalCount ?? 0) > 0)) {
                    file_put_contents($viewPath, $viewBody);
                    $changes++;
                } elseif ($count > 0 || ($modalCount ?? 0) > 0) {
                    $changes++;
                }
            }
        }
    }
}

echo $apply
    ? "Applied {$changes} change group(s).\n"
    : "Dry run — {$changes} change group(s). Re-run with --apply to write.\n";

exit(0);
