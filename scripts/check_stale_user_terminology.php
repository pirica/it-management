<?php
/**
 * Static audit: fail on stale Users-module prose, employee_companies user_id helpers,
 * session role_name admin checks, cr_username_for_user_id, and user_id in $hidden arrays.
 *
 * Why: After the employees merge, docs/scripts must say Employees module and employee_id.
 */
require_once __DIR__ . '/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli') {
    itm_script_output_begin('Stale User Terminology Check');
    echo '<p>CLI only: <code>php scripts/check_stale_user_terminology.php</code></p>';
    exit(0);
}

itm_script_output_begin('Stale User Terminology Check');
$nl = itm_script_output_nl();

$root = dirname(__DIR__);
$scanDirs = [
    $root . '/scripts',
    $root . '/docs',
    $root . '/phpunit/tests',
];

$excludeRelative = [
    'scripts/check_stale_user_terminology.php',
    'scripts/check_stale_user_id_sql.php',
];

$patterns = [
    'users_module_prose' => '/\bUsers module\b/i',
    'users_management_title' => '/Users Management/i',
    'employee_companies_user_id' => '/employee_companies[^\n]{0,120}\buser_id\b/i',
];

$modulePatterns = [
    'session_role_name_admin' => '/strtolower\s*\(\s*\$_SESSION\s*\[\s*[\'"]role_name[\'"]\s*\]/',
    'cr_username_for_user_id' => '/\bcr_username_for_user_id\b/',
    'hidden_user_id_scaffold' => '/\$hidden\s*=\s*\[[^\]]*\'user_id\'/',
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
        if (!$fileInfo->isFile()) {
            continue;
        }
        $ext = strtolower($fileInfo->getExtension());
        if (!in_array($ext, ['php', 'md'], true)) {
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
            // Why: Guard script and catalog rows document forbidden phrases literally.
            if (preg_match('/<code>Users module<\/code>/', $line)) {
                continue;
            }
            if (strpos($line, 'Users module') !== false && strpos($line, 'check_stale_user_terminology') !== false) {
                continue;
            }
            if (strpos($line, 'Users Management') !== false && strpos($line, 'check_stale_user_terminology') !== false) {
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

$modulesDir = $root . '/modules';
if (is_dir($modulesDir)) {
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
        foreach ($lines as $lineNum => $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || $trimmed[0] === '*' || strpos($trimmed, '//') === 0) {
                continue;
            }
            foreach ($modulePatterns as $label => $pattern) {
                if (preg_match($pattern, $line)) {
                    $failures[] = $relative . ':' . ($lineNum + 1) . ' [' . $label . '] ' . trim($line);
                    break;
                }
            }
        }
    }
}

$auditPath = $root . '/includes/database_sql_unique_audit.php';
if (is_file($auditPath)) {
    $auditLines = file($auditPath);
    if ($auditLines !== false) {
        foreach ($auditLines as $lineNum => $line) {
            if (preg_match("/employee_companies'.*'user_id'/", $line) || preg_match('/employee_companies.*\buser_id\b/', $line)) {
                $failures[] = 'includes/database_sql_unique_audit.php:' . ($lineNum + 1) . ' [employee_companies_user_id] ' . trim($line);
            }
        }
    }
}

if (empty($failures)) {
    echo "PASS: No stale Users-module prose, employee_companies user_id helpers, session role_name admin checks, cr_username_for_user_id, or user_id in \$hidden scaffold arrays." . $nl;
    exit(0);
}

echo 'FAIL: ' . count($failures) . " stale reference(s):" . $nl;
foreach ($failures as $msg) {
    echo '  - ' . $msg . $nl;
}
exit(1);
