<?php
/**
 * One-shot CLI probe: render a single hospitality module index.php with Admin session.
 *
 * Usage: php scripts/lib/itm_hospitality_index_probe.php <module_slug>
 */
define('ITM_CLI_SCRIPT', true);

$slug = isset($argv[1]) ? trim((string) $argv[1]) : '';
if ($slug === '' || !preg_match('/^[a-z0-9_]+$/', $slug)) {
    fwrite(STDERR, "Usage: php itm_hospitality_index_probe.php <module_slug>\n");
    exit(2);
}

$repoRoot = dirname(__DIR__, 2);
chdir($repoRoot);
require $repoRoot . '/config/config.php';

$_SESSION['employee_id'] = 1;
$_SESSION['login_employee_id'] = 1;
$_SESSION['company_id'] = 1;
$_SESSION['company_name'] = 'TechCorp Global';
$_SESSION['username'] = 'Admin';
$_SESSION['role_name'] = 'admin';

$indexPath = $repoRoot . '/modules/' . $slug . '/index.php';
if (!is_file($indexPath)) {
    fwrite(STDERR, "Missing index: modules/{$slug}/index.php\n");
    exit(1);
}

$issues = [];
set_error_handler(static function ($errno, $errstr, $file, $line) use (&$issues) {
    if ($errno & (E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE | E_ERROR | E_USER_ERROR)) {
        $issues[] = trim((string) $errstr) . ' in ' . $file . ':' . $line;
    }
    return true;
});

$_GET = [];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/it-management/modules/' . $slug . '/index.php';
chdir(dirname($indexPath));
ob_start();
try {
    include $indexPath;
} catch (Throwable $e) {
    $issues[] = 'Throwable: ' . $e->getMessage();
}
ob_end_clean();
restore_error_handler();

if (!empty($issues)) {
    foreach (array_slice($issues, 0, 5) as $issue) {
        fwrite(STDERR, $issue . "\n");
    }
    exit(1);
}

exit(0);
