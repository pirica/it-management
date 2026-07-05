<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();


$isCli = (php_sapi_name() === 'cli');
$nl = itm_script_output_nl();

if (!$isCli) {
    itm_script_browser_nav_echo();
    echo '<h1>Sync Modules Registry</h1>';
}

if (!$conn instanceof mysqli) {
    $message = 'Database connection is required.';
    echo $message . $nl;
    exit(1);
}

$result = itm_sync_modules_registry_from_filesystem($conn);

$summary = sprintf(
    'Registry sync complete. Discovered: %d, inserted: %d, updated: %d. Access rows seeded: %d.',
    (int)($result['total'] ?? 0),
    (int)($result['inserted'] ?? 0),
    (int)($result['updated'] ?? 0),
    (int)($result['access_seeded'] ?? 0)
);

echo $summary . $nl;
exit(0);
