<?php
/**
 * CLI-only: replace legacy user_id entries in CRUD $hidden column arrays with employee_id.
 *
 * Why: Flattened module scaffolds copied a dead user_id hide key after the employees merge.
 */
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body><p>CLI only: <code>php scripts/apply_crud_hidden_employee_id_alias.php</code></p>';
    echo '<p><a href="scripts.php">← Scripts index</a></p></body></html>';
    exit(0);
}

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();

$root = dirname(__DIR__);
$modulesDir = $root . '/modules';
$changed = [];

if (!is_dir($modulesDir)) {
    echo "No modules directory found.\n";
    exit(0);
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
        file_put_contents($path, implode('', $lines));
        $changed[] = $relative;
    }
}

echo 'Updated ' . count($changed) . " module PHP file(s).\n";
foreach ($changed as $rel) {
    echo "  - {$rel}\n";
}
