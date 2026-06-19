<?php
/**
 * CLI audit: fail when scripts hardcode seed user id 1 for users-table mutations.
 */
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><body><p>CLI only: <code>php scripts/check_script_disposable_employees.php</code></p>';
    echo '<p><a href="scripts.php">← Scripts index</a></p></body></html>';
    exit(0);
}

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();

$root = dirname(__DIR__);
$scriptsRoot = __DIR__;
$failures = [];

$excludeFiles = [
    'lib/itm_script_test_employee.php',
    'bypass_login.php',
    'check_script_disposable_employees.php',
];

$sensitiveNeedles = [
    'UPDATE employees',
    'reset_token',
    'INSERT INTO notes',
    'DELETE FROM employees WHERE id = 1',
    'DELETE FROM employees WHERE id=1',
];

$employeeIdOnePatterns = [
    '/\$user_id\s*=\s*1\b/',
    "/['\"]user_id['\"]\s*=>\s*1\b/",
    '/SET\s+@app_employee_id\s*=\s*1\b/',
    '/\$_SESSION\s*\[\s*[\'"]user_id[\'"]\s*\]\s*=\s*1\b/',
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($scriptsRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
        continue;
    }

    $relative = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($scriptsRoot) + 1));
    foreach ($excludeFiles as $exclude) {
        if ($relative === $exclude) {
            continue 2;
        }
    }

    $content = file_get_contents($fileInfo->getPathname());
    if ($content === false) {
        continue;
    }

    if (strpos($content, 'itm_script_test_employee_') !== false) {
        continue;
    }

    $usesUserIdOne = false;
    foreach ($employeeIdOnePatterns as $pattern) {
        if (preg_match($pattern, $content)) {
            $usesUserIdOne = true;
            break;
        }
    }

    if (!$usesUserIdOne) {
        continue;
    }

    $hasSensitive = false;
    foreach ($sensitiveNeedles as $needle) {
        if (stripos($content, $needle) !== false) {
            $hasSensitive = true;
            break;
        }
    }

    if ($hasSensitive) {
        $failures[] = 'scripts/' . $relative . ' hardcodes user id 1 with users/reset_token/notes mutation — use scripts/lib/itm_script_test_employee.php';
    }
}

if (empty($failures)) {
    echo "PASS: No scripts hardcode user id 1 for disposable-user mutations.\n";
    exit(0);
}

echo "FAIL: " . count($failures) . " issue(s):\n";
foreach ($failures as $msg) {
    echo "  - {$msg}\n";
}
exit(1);
