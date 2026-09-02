<?php

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="sync_modules_registry.php">sync_modules_registry.php</a>. CLI: <code>php scripts/sync_modules_registry.php</code>. Run after adding module folders; optional when only a new MySQL table was created (sidebar auto-scaffold + register).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();


$isCli = (php_sapi_name() === 'cli');
$nl = itm_script_output_nl();

if (!$isCli) {
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

itm_script_output_end();
