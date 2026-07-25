<?php
/**
 * Repair scaffold active checkbox markup (itm-checkbox-control + itm-check-indicator).
 *
 * Browser: module select + dry-run / apply (Admin for apply).
 * CLI: php scripts/fix_scaffold_active_checkbox.php [--module=idfs|--all] [--apply]
 */
declare(strict_types=1);

$itmIsCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/itm_list_active_and_checkboxes_report.php';
require_once __DIR__ . '/lib/itm_active_checkbox_fix.php';
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

$argvLocal = $GLOBALS['argv'] ?? [];
$moduleFilter = '';
$runRequested = false;

if ($itmIsCli) {
    foreach ($argvLocal as $arg) {
        if (strpos((string)$arg, '--module=') === 0) {
            $moduleFilter = trim(substr((string)$arg, 9));
            $runRequested = true;
        }
        if ((string)$arg === '--all') {
            $moduleFilter = '';
            $runRequested = true;
        }
    }
} else {
    $runRequested = isset($_GET['module']) || isset($_GET['all']) || isset($_GET['apply']);
    if (isset($_GET['module'])) {
        $moduleFilter = trim((string)$_GET['module']);
    }
}

$boot = itm_apply_script_bootstrap('Fix scaffold active checkbox', ['skip_db_tests' => false]);
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/\\');

$report = itm_collect_active_and_checkboxes_report($conn);
$allViolations = itm_active_checkbox_fix_filter_violations($report, null);
$moduleSlugs = itm_active_checkbox_fix_module_slugs($allViolations);
$violations = itm_active_checkbox_fix_filter_violations(
    $report,
    $moduleFilter !== '' ? $moduleFilter : null
);

if (!$itmIsCli) {
    itm_script_output_close_pre();
    echo '<h1>Fix scaffold active checkbox</h1>';
    echo '<p>Repairs <code>scaffold_active_checkbox_not_compliant</code> findings from '
        . '<a href="list_active_and_checkboxes.php">list_active_and_checkboxes.php</a>.</p>';
    echo '<form method="get" style="margin:16px 0;padding:12px;border:1px solid #d0d7de;border-radius:8px;max-width:720px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;">';
    echo '<label for="module" style="display:block;margin-bottom:8px;font-weight:600;">Module</label>';
    echo '<select name="module" id="module" style="width:100%;padding:8px;margin-bottom:12px;">';
    echo '<option value="">All modules with scaffold violations</option>';
    foreach ($moduleSlugs as $slug) {
        $selected = $moduleFilter === $slug ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>'
            . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select>';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap;">';
    echo '<button type="submit" name="all" value="1" style="padding:8px 12px;">Preview all</button>';
    echo '<button type="submit" style="padding:8px 12px;">Preview selected</button>';
    echo '<button type="submit" name="apply" value="1" style="padding:8px 12px;font-weight:600;">Apply (Admin)</button>';
    echo '</div></form>';

    if (!$runRequested) {
        echo '<p>Select a module (for example <strong>idfs</strong>) and click <strong>Preview selected</strong>.</p>';
        itm_script_output_end();
        exit(0);
    }

    echo '<pre>';
}

if ($itmIsCli && !$runRequested) {
    echo 'Usage:' . $nl;
    echo '  php scripts/fix_scaffold_active_checkbox.php --all' . $nl;
    echo '  php scripts/fix_scaffold_active_checkbox.php --module=idfs' . $nl;
    echo '  php scripts/fix_scaffold_active_checkbox.php --module=idfs --apply' . $nl . $nl;
    echo 'Modules with scaffold violations: ' . ($moduleSlugs === [] ? '(none)' : implode(', ', $moduleSlugs)) . $nl;
    itm_script_output_end();
    exit(0);
}

if ($moduleFilter !== '') {
    echo '[INFO] Module filter: ' . $moduleFilter . $nl;
} else {
    echo '[INFO] All modules with scaffold violations.' . $nl;
}

if ($violations === []) {
    echo colorText('[PASS] No scaffold_active_checkbox_not_compliant violations for this selection.', 'pass') . $nl;
    itm_script_output_end();
    exit(0);
}

$changedFiles = [];
$totalReplacements = 0;

foreach ($violations as $row) {
    $relativePath = (string)($row['file'] ?? '');
    if ($relativePath === '') {
        continue;
    }
    $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $result = itm_active_checkbox_fix_apply_file($absolutePath, $apply);

    if (!empty($result['skipped'])) {
        echo '[skip] ' . $relativePath . ' (' . (string)$result['reason'] . ')' . $nl;
        continue;
    }

    $hits = (int)$result['replacement_count'];
    $totalReplacements += $hits;
    $changedFiles[] = $relativePath . ' (' . $hits . ' replacement(s))';
    echo ($apply ? '[apply] ' : '[dry-run] ') . $relativePath . ' (' . $hits . ' replacement(s))' . $nl;
}

echo $nl;
if ($changedFiles === []) {
    echo 'No automatic replacements matched (manual edit may be required).' . $nl;
} else {
    $modeLabel = $apply ? 'Applied' : 'Would change';
    echo $modeLabel . ': ' . count($changedFiles) . ' file(s), ' . $totalReplacements . ' replacement(s).' . $nl . $nl;
    itm_apply_script_echo_list($modeLabel . ' files', $changedFiles);
}

if ($apply && $changedFiles !== []) {
    $post = itm_collect_active_and_checkboxes_report($conn);
    $remaining = itm_active_checkbox_fix_filter_violations(
        $post,
        $moduleFilter !== '' ? $moduleFilter : null
    );
    if ($remaining === []) {
        echo colorText('[PASS] No remaining scaffold violations for this selection.', 'pass') . $nl;
    } else {
        echo colorText('[WARN] ' . count($remaining) . ' file(s) still violate — re-run list_active_and_checkboxes.php.', 'fail') . $nl;
    }
}

itm_apply_script_finish_hint(
    $apply,
    $itmIsCli,
    count($changedFiles),
    $nl,
    'fix_scaffold_active_checkbox.php',
    'Re-check: php scripts/list_active_and_checkboxes.php'
);

itm_script_output_end();
exit(0);
