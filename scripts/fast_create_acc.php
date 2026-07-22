<?php
/**
 * Fast account creator for demo employees with module-scoped RBAC.
 *
 * CLI: php scripts/fast_create_acc.php --seed-demo-bundle [--company=1]
 * Browser: redirects to modules/employees/fast_create_acc.php (active company UI).
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_demo_module_users_seed.php';

$conn = $GLOBALS['conn'] ?? null;
$nl = itm_script_output_nl();
$isCli = PHP_SAPI === 'cli';

if (!$conn instanceof mysqli) {
    if ($isCli) {
        echo '[FAIL] Database connection required.' . $nl;
        exit(1);
    }
    http_response_code(500);
    exit('Database connection required.');
}

if ($isCli) {
    itm_script_output_begin('Fast Create Account');
    $options = getopt('', ['seed-demo-bundle', 'company:']);
    if (isset($options['seed-demo-bundle'])) {
        $companyId = isset($options['company']) ? (int)$options['company'] : 1;
        $summary = itm_demo_module_users_seed_bundle($conn, $companyId, 0);
        foreach ($summary['messages'] as $line) {
            echo colorText('[INFO] ' . $line, 'pass') . $nl;
        }
        foreach ($summary['errors'] as $line) {
            echo colorText('[FAIL] ' . $line, 'fail') . $nl;
        }
        exit($summary['ok'] ? 0 : 1);
    }

    echo 'Usage:' . $nl;
    echo '  php scripts/fast_create_acc.php --seed-demo-bundle [--company=1]' . $nl;
    echo 'Browser UI: modules/employees/fast_create_acc.php' . $nl;
    exit(0);
}

header('Location: ' . rtrim(BASE_URL, '/') . '/modules/employees/fast_create_acc.php');
exit;
