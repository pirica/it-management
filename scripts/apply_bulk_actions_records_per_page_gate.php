<?php
/**
 * Add records_per_page visibility gate for bulk delete / clear table on module index.php files.
 *
 * Why: check_ui_configuration_coverage.php requires $showBulkActions = $totalRows >= $perPage
 * before showing Select to Delete / Clear Table (AGENTS.md standard feature set).
 *
 * CLI: php scripts/apply_bulk_actions_records_per_page_gate.php
 *      php scripts/apply_bulk_actions_records_per_page_gate.php --dry-run
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Apply Bulk Actions records_per_page Gate');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/');
$modulesDir = $root . '/modules';
$excludeModulesFile = __DIR__ . '/data/ui_configuration_excluded_modules.txt';
$excludePrefixesFile = __DIR__ . '/data/ui_configuration_excluded_prefixes.txt';

/**
 * @return array<int, string>
 */
function itm_bulk_gate_load_list(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $entries = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim((string)$line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        $entries[] = $line;
    }
    return $entries;
}

/**
 * @return array{status:string,detail:string}
 */
function itm_bulk_gate_patch_index(string $path, bool $apply): array
{
    $content = file_get_contents($path);
    if (!is_string($content) || $content === '') {
        return ['status' => 'skip', 'detail' => 'unreadable'];
    }

    if (stripos($content, 'bulk-delete-form') === false || stripos($content, 'bulk_delete') === false) {
        return ['status' => 'skip', 'detail' => 'no bulk-delete-form'];
    }

    if (stripos($content, 'itm_resolve_records_per_page') === false) {
        return ['status' => 'skip', 'detail' => 'no records_per_page'];
    }

    if (preg_match('/\$showBulkActions\s*=.*\$totalRows\s*(>=|>)\s*\$perPage/is', $content) === 1) {
        return ['status' => 'skip', 'detail' => 'gate already present'];
    }

    $original = $content;

    if (preg_match(
        '/(\$totalPages\s*=\s*max\s*\([^;]+\)\s*;)/',
        $content,
        $totalPagesMatch,
        PREG_OFFSET_CAPTURE
    ) !== 1) {
        return ['status' => 'fail', 'detail' => 'totalPages assignment not found'];
    }

    $insertPos = $totalPagesMatch[0][1] + strlen($totalPagesMatch[0][0]);
    $content = substr($content, 0, $insertPos)
        . "\n\$showBulkActions = (\$totalRows >= \$perPage);"
        . substr($content, $insertPos);

    if (preg_match(
        '#(?<block>(?:[ \t]*<!-- TABLE MAINTENANCE -->\s*\r?\n)?[ \t]*<div class="card" style="margin-bottom:16px;">\s*\r?\n[ \t]*<form id="bulk-delete-form"[\s\S]*?</form>\s*\r?\n[ \t]*</div>)#',
        $content,
        $bulkCardMatch
    ) === 1) {
        $block = $bulkCardMatch['block'];
        if (strpos($block, '<?php if ($showBulkActions)') === false) {
            $indent = '';
            if (preg_match('/^([ \t]*)/', $block, $indentMatch) === 1) {
                $indent = $indentMatch[1];
            }
            $replacement = $indent . '<?php if ($showBulkActions): ?>' . "\n"
                . $block . "\n"
                . $indent . '<?php endif; ?>';
            $content = str_replace($block, $replacement, $content);
        }
    } else {
        return ['status' => 'fail', 'detail' => 'bulk-delete-form card block not found'];
    }

    $selectAllTh = '<th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th>';
    $selectAllWrapped = '<?php if ($showBulkActions): ?>' . $selectAllTh . '<?php endif; ?>';
    if (strpos($content, $selectAllTh) !== false && strpos($content, $selectAllWrapped) === false) {
        $content = str_replace($selectAllTh, $selectAllWrapped, $content);
    }

    // Why: gate tbody checkbox cells so columns stay aligned when bulk actions are hidden (< records_per_page).
    $rowCheckboxPatterns = [
        '#(?<!\$showBulkActions\): \?>)<td[^>]*>\s*<input type="checkbox" name="ids\[\]" value="<\?php echo \(int\)\$[a-zA-Z_][a-zA-Z0-9_]*\[\'id\'\]; \?>"\s+form="bulk-delete-form"\s*/>\s*</td>#s',
        '#(?<!\$showBulkActions\): \?>)<td[^>]*>\s*<input type="checkbox" name="ids\[\]" value="<\?php echo \(int\)\$[a-zA-Z_][a-zA-Z0-9_]*\[\'id\'\]; \?>" form="bulk-delete-form"></td>#s',
    ];
    foreach ($rowCheckboxPatterns as $pattern) {
        $content = (string)preg_replace_callback(
            $pattern,
            static function (array $matches): string {
                return '<?php if ($showBulkActions): ?>' . $matches[0] . '<?php endif; ?>';
            },
            $content
        );
    }

    $content = preg_replace(
        '/count\(\$fieldColumns\)\s*\+\s*2/',
        'count($fieldColumns) + ($showBulkActions ? 2 : 1)',
        $content
    );
    $content = preg_replace(
        '/count\(\$uiColumns\)\s*\+\s*2/',
        'count($uiColumns) + ($showBulkActions ? 2 : 1)',
        $content
    );

    if ($content === $original) {
        return ['status' => 'fail', 'detail' => 'no changes produced'];
    }

    if ($apply) {
        if (file_put_contents($path, $content) === false) {
            return ['status' => 'fail', 'detail' => 'write failed'];
        }
    }

    return ['status' => 'ok', 'detail' => $apply ? 'patched' : 'would patch'];
}

$excludeModules = itm_bulk_gate_load_list($excludeModulesFile);
$excludePrefixes = itm_bulk_gate_load_list($excludePrefixesFile);

$stats = ['ok' => 0, 'skip' => 0, 'fail' => 0];
$okModules = [];
$skipModules = [];
$failModules = [];

foreach (scandir($modulesDir) ?: [] as $module) {
    if ($module === '.' || $module === '..') {
        continue;
    }
    if (in_array($module, $excludeModules, true)) {
        continue;
    }
    $skipPrefix = false;
    foreach ($excludePrefixes as $prefix) {
        if ($prefix !== '' && strpos($module, $prefix) === 0) {
            $skipPrefix = true;
            break;
        }
    }
    if ($skipPrefix) {
        continue;
    }

    $indexPath = $modulesDir . '/' . $module . '/index.php';
    if (!is_file($indexPath)) {
        continue;
    }

    $result = itm_bulk_gate_patch_index($indexPath, $apply);
    $stats[$result['status']] = ($stats[$result['status']] ?? 0) + 1;
    $line = '[' . strtoupper($result['status']) . '] ' . $module . ' — ' . $result['detail'];
    echo $line . $nl;
    if ($result['status'] === 'ok') {
        $okModules[] = $module;
    } elseif ($result['status'] === 'skip') {
        $skipModules[] = $module . ' — ' . $result['detail'];
    } elseif ($result['status'] === 'fail') {
        $failModules[] = $module . ' — ' . $result['detail'];
    }
}

$modeLabel = $apply ? 'Patched' : 'Would patch';
echo $nl . 'Summary: ok=' . (int)($stats['ok'] ?? 0)
    . ' skip=' . (int)($stats['skip'] ?? 0)
    . ' fail=' . (int)($stats['fail'] ?? 0) . $nl . $nl;
itm_apply_script_echo_list($modeLabel . ' modules', $okModules);
itm_apply_script_echo_list('Skipped', $skipModules);
itm_apply_script_echo_list('Failed', $failModules);
itm_apply_script_finish_hint($apply, $boot['is_cli'], (int)($stats['ok'] ?? 0), $nl, 'apply_bulk_actions_records_per_page_gate.php');

itm_script_output_end();
exit($failModules !== [] ? 1 : 0);
