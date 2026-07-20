<?php
/**
 * Static audit: fail on stale user_id column / users table SQL after employees merge.
 *
 * Why: db/ uses employee_id; leftover user_id SQL breaks at runtime.
 */
require_once __DIR__ . '/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli') {
    itm_script_output_begin('Stale user_id SQL Check');
    echo '<p>CLI only: <code>php scripts/check_stale_user_id_sql.php</code></p>';
    exit(0);
}

itm_script_output_begin('Stale user_id SQL Check');
$nl = itm_script_output_nl();

$root = dirname(__DIR__);
$scanDirs = [
    $root . '/modules',
    $root . '/includes',
    $root . '/config',
];

$excludeRelative = [
    'includes/ui_config.php', // legacy migration + audit JSON keys
    'includes/employee_profile_photo.php', // legacy disk path helper
];

$patterns = [
    'sql_user_id_column' => '/\b(?:al|e|b|n|pc|pe|u)\.user_id\b|\bWHERE\b[^;\n]{0,120}\buser_id\s*=\s*\?|\bAND\b[^;\n]{0,80}\buser_id\s*=\s*\?|\bINSERT\b[^;\n]{0,200}\buser_id\b|\bUPDATE\b[^;\n]{0,200}\buser_id\b|\bemployees\.user_id\b|\bemployees WHERE employee_id\b/i',
    'users_table' => '/\b(?:FROM|JOIN|INTO|UPDATE|DELETE FROM)\s+`?users`?\b/i',
    'show_users_table' => '/SHOW TABLES LIKE [\'"]users[\'"]/i',
];

$failures = [];

foreach ($scanDirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
            continue;
        }
        $path = $fileInfo->getPathname();
        $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
        if (in_array($relative, $excludeRelative, true)) {
            continue;
        }

        $lines = file($path);
        if ($lines === false) {
            continue;
        }

        foreach ($lines as $lineNum => $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || $trimmed[0] === '*' || strpos($trimmed, '//') === 0 || strpos($trimmed, '#') === 0) {
                continue;
            }
            // Why: FK shim blocks intentionally reference user_id as legacy alias until CRUD templates are aligned.
            if (strpos($line, "'COLUMN_NAME' => 'user_id'") !== false) {
                continue;
            }
            if (strpos($line, "'user_id' =>") !== false && strpos($line, 'employee_id') !== false) {
                continue;
            }
            if (preg_match('/\$hidden\s*=\s*\[[^\]]*[\'"]user_id[\'"]/', $line)) {
                continue;
            }
            if (preg_match('/\$_SESSION\s*\[\s*[\'"]employee_id[\'"]\s*\]/', $line) && preg_match('/\$user_id\s*=/', $line)) {
                continue;
            }
            if (preg_match('/function\s+\w+\([^)]*\$user_id/', $line)) {
                continue;
            }
            if (preg_match('/\$user_id\s*=\s*\(int\)\(\$_SESSION\[[\'"]employee_id[\'"]\]/', $line)) {
                continue;
            }
            if (preg_match('/\$logged_user_id\s*=/', $line)) {
                continue;
            }
            if (preg_match('/Private\/\{?\$?_?(?:username|safe_username).*\$user_id/', $line)) {
                continue;
            }
            if (preg_match('/itm_notes_resolve_image_path\s*\([^)]*\$logged_user_id/', $line)) {
                continue;
            }
            // Why: Session var $user_id often holds $_SESSION['employee_id'] while SQL uses employee_id column.
            if (preg_match('/\bemployee_id\s*=\s*\$user_id\b/', $line)) {
                continue;
            }

            foreach ($patterns as $label => $pattern) {
                if (preg_match($pattern, $line)) {
                    $failures[] = $relative . ':' . ($lineNum + 1) . ' [' . $label . '] ' . trim($line);
                    break;
                }
            }
        }
    }
}

if (empty($failures)) {
    echo "PASS: No stale user_id SQL or users table references in modules/includes/config." . $nl;
    exit(0);
}

echo 'FAIL: ' . count($failures) . " stale reference(s):" . $nl;
foreach ($failures as $msg) {
    echo '  - ' . $msg . $nl;
}
exit(1);

itm_script_output_end();
