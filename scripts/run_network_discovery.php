<?php
/**
 * Cron runner: scheduled network discovery profiles (chunked batches).
 *
 * CLI: php scripts/run_network_discovery.php [--company=ID] [--profile=ID] [--verbose]
 */
declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/run_network_discovery.php</code> — optional <code>--company=1</code>, <code>--profile=5</code>, <code>--verbose</code>. Schedule every 5–15 minutes; each run processes one batch per due profile. Admin browser: <code>?run=1</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    require_once __DIR__ . '/lib/script_browser_nav.php';
} else {
    define('ITM_CLI_SCRIPT', true);
    require_once dirname(__DIR__) . '/config/config.php';
}

require_once __DIR__ . '/lib/script_cli_output.php';
require_once ROOT_PATH . 'includes/itm_network_discovery.php';

itm_script_output_begin();

if (PHP_SAPI !== 'cli') {
    itm_script_require_admin_script_or_exit($conn);
    if (empty($_GET['run'])) {
        itm_script_browser_render_landing();
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Run Network Discovery</title></head><body><pre>';
}

$nl = itm_script_output_nl();
$companyFilter = 0;
$profileFilter = 0;
$verbose = false;

if (PHP_SAPI === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, '--company=') === 0) {
            $companyFilter = (int)substr($arg, 10);
        } elseif (strpos($arg, '--profile=') === 0) {
            $profileFilter = (int)substr($arg, 10);
        } elseif ($arg === '--verbose' || $arg === '-v') {
            $verbose = true;
        }
    }
} else {
    $companyFilter = (int)($_REQUEST['company'] ?? 0);
    $profileFilter = (int)($_REQUEST['profile'] ?? 0);
    $verbose = !empty($_REQUEST['verbose']);
}

if ($profileFilter > 0) {
    $batch = itm_network_discovery_profile_run_batch($conn, $profileFilter, (int)($_SESSION['employee_id'] ?? 0));
    echo 'Profile ' . $profileFilter . ': ' . ($batch['ok'] ? 'ok' : 'fail') . $nl;
    if ($verbose || empty($batch['ok'])) {
        echo json_encode($batch, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . $nl;
    }
} else {
    $summary = itm_network_discovery_run_scheduled($conn, $companyFilter);
    echo 'Profiles due: ' . (int)($summary['profiles'] ?? 0) . $nl;
    echo 'Jobs enqueued: ' . (int)($summary['enqueued'] ?? 0) . $nl;
    if ($verbose && !empty($summary['errors'])) {
        foreach ($summary['errors'] as $err) {
            echo 'Error: ' . $err . $nl;
        }
    }
}

if (PHP_SAPI !== 'cli') {
    echo '</pre></body></html>';
}

itm_script_output_end();
