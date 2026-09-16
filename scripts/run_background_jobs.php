<?php
/**
 * Cron worker: process pending background_jobs rows (chunked batches).
 *
 * CLI: php scripts/run_background_jobs.php [--company=ID] [--type=network_discovery_scan] [--limit=20] [--verbose]
 */
declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/run_background_jobs.php</code> — optional <code>--company=1</code>, <code>--type=network_discovery_scan</code>, <code>--limit=20</code>, <code>--verbose</code>. Schedule every 1–5 minutes alongside <code>run_network_discovery.php</code> (enqueue). Admin browser: <code>?run=1</code>.
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
require_once ROOT_PATH . 'includes/itm_background_jobs.php';
require_once ROOT_PATH . 'includes/itm_network_discovery.php';

itm_script_output_begin();

if (PHP_SAPI !== 'cli') {
    itm_script_require_admin_script_or_exit($conn);
    if (empty($_GET['run'])) {
        itm_script_browser_render_landing();
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Run Background Jobs</title></head><body><pre>';
}

$nl = itm_script_output_nl();
$companyFilter = 0;
$jobType = '';
$limit = 20;
$verbose = false;

if (PHP_SAPI === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, '--company=') === 0) {
            $companyFilter = (int)substr($arg, 10);
        } elseif (strpos($arg, '--type=') === 0) {
            $jobType = trim(substr($arg, 7));
        } elseif (strpos($arg, '--limit=') === 0) {
            $limit = max(1, (int)substr($arg, 8));
        } elseif ($arg === '--verbose' || $arg === '-v') {
            $verbose = true;
        }
    }
} else {
    $companyFilter = (int)($_REQUEST['company'] ?? 0);
    $jobType = trim((string)($_REQUEST['type'] ?? ''));
    $limit = max(1, (int)($_REQUEST['limit'] ?? 20));
    $verbose = !empty($_REQUEST['verbose']);
}

$summary = itm_background_jobs_process($conn, $limit, $companyFilter, $jobType);
echo 'Processed: ' . (int)($summary['processed'] ?? 0) . $nl;
echo 'Completed: ' . (int)($summary['completed'] ?? 0) . $nl;
echo 'Failed: ' . (int)($summary['failed'] ?? 0) . $nl;
if ($verbose && !empty($summary['errors'])) {
    foreach ($summary['errors'] as $err) {
        echo 'Error: ' . $err . $nl;
    }
}

if (PHP_SAPI !== 'cli') {
    echo '</pre></body></html>';
}

itm_script_output_end();
