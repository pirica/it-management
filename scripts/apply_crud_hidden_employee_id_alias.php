<?php
/**
 * Replace legacy user_id entries in CRUD $hidden column arrays with employee_id.
 *
 * Browser + CLI. Default run is always dry-run; writes only with CLI --apply or browser ?apply=1 (Admin).
 */
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Apply CRUD Hidden employee_id Alias');
$apply = $boot['apply'];
$nl = $boot['nl'];
$root = rtrim($boot['root'], '/');
$modulesDir = $root . '/modules';
$changed = [];
$unchanged = [];

if (!is_dir($modulesDir)) {
    echo colorText('No modules directory found.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($modulesDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
        continue;
    }

    $path = $fileInfo->getPathname();
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    $lines = file($path);
    if ($lines === false) {
        continue;
    }

    $updated = false;
    foreach ($lines as $lineNum => $line) {
        if (!preg_match('/\$hidden\s*=\s*\[/', $line) || strpos($line, "'user_id'") === false) {
            continue;
        }

        $newLine = str_replace("'user_id'", "'employee_id'", $line);
        if ($newLine !== $line) {
            $lines[$lineNum] = $newLine;
            $updated = true;
        }
    }

    if ($updated) {
        if ($apply) {
            file_put_contents($path, implode('', $lines));
        }
        $changed[] = $relative;
    }
}

$modeLabel = $apply ? 'Updated' : 'Would update';
echo $nl . $modeLabel . ' ' . count($changed) . ' module PHP file(s).' . $nl . $nl;
itm_apply_script_echo_list($modeLabel . ' files', $changed);
itm_apply_script_finish_hint($apply, $boot['is_cli'], count($changed), $nl, 'apply_crud_hidden_employee_id_alias.php');

itm_script_output_end();
exit(0);
