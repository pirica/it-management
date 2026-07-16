<?php
/**
 * Replace duplicated alert-error blocks with itm_render_alert_errors().
 *
 * Browser + CLI. Default run is always dry-run; writes only with CLI --apply or browser ?apply=1 (Admin).
 * Optional filter: --module=name (CLI) or ?module=name (browser).
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Apply Human-Friendly Error Display');
$apply = $boot['apply'];
$nl = $boot['nl'];
$argv = $boot['argv'];
$dryRun = !$apply;

$onlyModules = [];
if ($boot['is_cli']) {
    foreach ($argv as $arg) {
        if (strpos($arg, '--module=') === 0) {
            $onlyModules[] = substr($arg, 9);
        }
    }
} else {
    $moduleFilter = itm_apply_script_arg_value($argv, false, 'module', '');
    if ($moduleFilter !== '') {
        $onlyModules[] = $moduleFilter;
    }
}

function itm_apply_human_friendly_error_display_to_file($path, $dryRun) {
    $content = file_get_contents($path);
    if ($content === false) {
        return ['changed' => false, 'notes' => ['unreadable']];
    }

    $original = $content;
    $notes = [];

    $replacements = [
        '/<\?php\s+if\s*\(\s*!\s*empty\s*\(\s*\$errors\s*\)\s*\)\s*:\s*\?>\s*<div\s+class="alert\s+alert-error">\s*<\?php\s+echo\s+sanitize\s*\(\s*implode\s*\(\s*[\'"]\s*[\'"]\s*,\s*\$errors\s*\)\s*\)\s*;\s*\?>\s*<\/div>\s*<\?php\s+endif;\s*\?>/s'
            => '<?php echo itm_render_alert_errors($errors); ?>',
        '/<\?php\s+foreach\s*\(\s*\$errors\s+as\s+\$error\s*\)\s*:\s*\?>\s*<div\s+class="alert\s+alert-error">\s*<\?php\s+echo\s+sanitize\s*\(\s*\$error\s*\)\s*;\s*\?>\s*<\/div>\s*<\?php\s+endforeach;\s*\?>/s'
            => '<?php echo itm_render_alert_errors($errors); ?>',
        '/<\?php\s+if\s*\(\s*!\s*empty\s*\(\s*\$fpGalleryAccessError\s*\)\s*\)\s*:\s*\?>\s*<div\s+class="alert\s+alert-error">\s*<\?php\s+echo\s+sanitize\s*\(\s*\$fpGalleryAccessError\s*\)\s*;\s*\?>\s*<\/div>\s*<\?php\s+endif;\s*\?>/s'
            => '<?php echo itm_render_alert_errors($fpGalleryAccessError ?? \'\'); ?>',
        '/<div\s+class="alert\s+alert-error">\s*<\?php\s+echo\s+sanitize\s*\(\s*\$reportError\s*\)\s*;\s*\?>\s*<\/div>/s'
            => '<?php echo itm_render_alert_errors($reportError ?? \'\'); ?>',
        '/<p\s+class="alert\s+alert-error">\s*<\?php\s+echo\s+sanitize\s*\(\s*\$fetchError\s*\)\s*;\s*\?>\s*<\/p>/s'
            => '<?php echo itm_render_alert_errors($fetchError ?? \'\'); ?>',
        '/<\?php\s+foreach\s*\(\s*\$errors\s+as\s+\$error\s*\)\s*:\s*\?>\s*<div\s+class="alert\s+alert-danger"[^>]*>\s*<\?php\s+echo\s+sanitize\s*\(\s*\$error\s*\)\s*;\s*\?>\s*<\/div>\s*<\?php\s+endforeach;\s*\?>/s'
            => '<?php echo itm_render_alert_errors($errors); ?>',
        '/<\?php\s+if\s*\(\s*\$error\s*\)\s*:\s*\?>\s*<div\s+class="alert\s+alert-danger">\s*<\?php\s+echo\s+sanitize\s*\(\s*\$error\s*\)\s*;\s*\?>\s*<\/div>\s*<\?php\s+endif;\s*\?>/s'
            => '<?php echo itm_render_alert_errors($error ?? \'\'); ?>',
        '/<\?php\s+if\s*\(\s*\$employeeSystemAccessError\s*!==\s*[\'"]{2}\s*\)\s*:\s*\?>\s*<div\s+class="alert\s+alert-danger">\s*<\?php\s+echo\s+sanitize\s*\(\s*\$employeeSystemAccessError\s*\)\s*;\s*\?>\s*<\/div>\s*<\?php\s+endif;\s*\?>/s'
            => '<?php echo itm_render_alert_errors($employeeSystemAccessError ?? \'\'); ?>',
        '/<div\s+class="alert\s+alert-danger">\s*<\?php\s+echo\s+sanitize\s*\(\s*\$employeeSystemAccessError\s*\)\s*;\s*\?>\s*<\/div>/s'
            => '<?php echo itm_render_alert_errors($employeeSystemAccessError ?? \'\'); ?>',
        '/<div\s+class="alert\s+alert-danger">\s*<\?php\s+echo\s+sanitize\s*\(\s*\$error\s*\)\s*;\s*\?>\s*<\/div>/s'
            => '<?php echo itm_render_alert_errors($error ?? \'\'); ?>',
        '/<\?php\s+if\s*\(\s*\$inventoryError\s*!==\s*[\'"]{2}\s*\)\s*:\s*\?>\s*<div\s+class="alert\s+alert-danger">\s*<\?php\s+echo\s+sanitize\s*\(\s*\$inventoryError\s*\)\s*;\s*\?>\s*<\/div>\s*<\?php\s+endif;\s*\?>/s'
            => '<?php echo itm_render_alert_errors($inventoryError ?? \'\'); ?>',
    ];

    foreach ($replacements as $pattern => $replace) {
        $updated = preg_replace($pattern, $replace, $content, -1, $count);
        if ($count > 0 && is_string($updated)) {
            $content = $updated;
            $notes[] = 'replaced alert block';
        }
    }

    $changed = $content !== $original;
    if ($changed && !$dryRun) {
        file_put_contents($path, $content);
    }

    return ['changed' => $changed, 'notes' => $notes];
}

function itm_collect_module_php_files($moduleDir) {
    $paths = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($moduleDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        if (strpos($path, DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR) !== false) {
            continue;
        }
        $paths[] = $path;
    }
    return $paths;
}

$changedFiles = 0;
$changedList = [];

foreach (glob(ROOT_PATH . 'modules/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
    $module = basename($moduleDir);
    if ($onlyModules && !in_array($module, $onlyModules, true)) {
        continue;
    }

    foreach (itm_collect_module_php_files($moduleDir) as $path) {
        $scan = file_get_contents($path);
        if ($scan === false || (strpos($scan, 'alert alert-error') === false && strpos($scan, 'alert alert-danger') === false)) {
            continue;
        }
        if (strpos($scan, 'implode') === false
            && strpos($scan, 'foreach ($errors') === false
            && strpos($scan, '$fpGalleryAccessError') === false
            && strpos($scan, '$reportError') === false
            && strpos($scan, '$fetchError') === false
            && strpos($scan, '$employeeSystemAccessError') === false
            && strpos($scan, '$inventoryError') === false
            && !preg_match('/sanitize\s*\(\s*\$error\s*\)/', $scan)) {
            continue;
        }
        if (strpos($scan, 'itm_render_alert_errors') !== false && strpos($scan, "implode(' ', \$errors)") === false) {
            continue;
        }

        $result = itm_apply_human_friendly_error_display_to_file($path, $dryRun);
        if ($result['changed']) {
            $changedFiles++;
            $rel = str_replace(ROOT_PATH, '', $path);
            $rel = str_replace('\\', '/', $rel);
            $changedList[] = $rel;
            echo ($dryRun ? '[dry-run] ' : '[apply] ') . $rel . ': ' . implode(', ', $result['notes']) . $nl;
        }
    }
}

$includePaths = [
    ROOT_PATH . 'includes/itm_it_location_linked_floor_plans.php',
    ROOT_PATH . 'modules/ip_subnets/includes/partials/render.php',
    ROOT_PATH . 'modules/ip_addresses/includes/partials/render.php',
    ROOT_PATH . 'modules/rack_planner/includes/partials/render.php',
];
foreach ($includePaths as $path) {
    if (!is_file($path)) {
        continue;
    }
    $scan = file_get_contents($path);
    if ($scan === false || strpos($scan, 'alert alert-error') === false) {
        continue;
    }
    if (strpos($scan, 'implode') === false && strpos($scan, 'foreach ($errors') === false) {
        continue;
    }
    $result = itm_apply_human_friendly_error_display_to_file($path, $dryRun);
    if ($result['changed']) {
        $changedFiles++;
        $rel = str_replace(ROOT_PATH, '', $path);
        $rel = str_replace('\\', '/', $rel);
        $changedList[] = $rel;
        echo ($dryRun ? '[dry-run] ' : '[apply] ') . $rel . ': ' . implode(', ', $result['notes']) . $nl;
    }
}

$modeLabel = $apply ? 'Updated' : 'Would update';
echo $nl . $modeLabel . ' ' . $changedFiles . ' file(s).' . $nl . $nl;
itm_apply_script_echo_list($modeLabel . ' files', $changedList);
itm_apply_script_finish_hint($apply, $boot['is_cli'], $changedFiles, $nl, 'apply_human_friendly_error_display.php');

itm_script_output_end();
exit(0);
