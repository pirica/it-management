<?php
/**
 * Production go-live hardening audit (todo #5).
 *
 * CLI: php scripts/check_prod_hardening.php [--enforce] [--skip-http]
 * Browser: scripts/check_prod_hardening.php?run=1 (Administrator).
 *
 * Enforces failures when APP_ENV=production (default when unset). Development
 * profile prints [WARN] only unless --enforce / ?enforce=1.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/script_cli_output.php';

if (!function_exists('itm_script_browser_how_to_use')) {
    function itm_script_browser_how_to_use(): string
    {
        return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/check_prod_hardening.php</code> — pre-deploy gate for production posture. Fails (exit <code>1</code>) when <code>APP_ENV=production</code> and seed passwords, web-root <code>error_log.txt</code>, browser error reporting, dev env flags, no-auth allowlists, or web-reachable <code>bypass_login.php</code> are unsafe. Optional: <code>ITM_PROD_HARDENING_BASE_URL</code> for HTTP probe; <code>--enforce</code> / <code>?enforce=1</code> to fail in development too.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
    }
}

$isBrowser = PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg' && !defined('PHPUNIT_RUNNING');
$forceEnforce = false;
$skipHttp = false;

if ($isBrowser) {
    if (empty($_GET['run'])) {
        itm_script_output_begin('Production hardening check');
        itm_script_output_close_pre();
        echo '<p>' . itm_script_browser_how_to_use() . '</p>';
        echo '<p><a href="?run=1">Run check</a> · <a href="?run=1&amp;enforce=1">Run (enforce in development)</a></p>';
        itm_script_output_end();
        exit(0);
    }
    $forceEnforce = !empty($_GET['enforce']);
    $skipHttp = !empty($_GET['skip_http']);
    require_once dirname(__DIR__) . '/config/config.php';
    if (!itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
        http_response_code(403);
        echo 'Administrator session required.';
        exit(1);
    }
} else {
    if (!defined('ITM_CLI_SCRIPT')) {
        define('ITM_CLI_SCRIPT', true);
    }
    foreach (array_slice($argv ?? [], 1) as $arg) {
        if ($arg === '--enforce') {
            $forceEnforce = true;
        } elseif ($arg === '--skip-http') {
            $skipHttp = true;
        }
    }
    require_once dirname(__DIR__) . '/config/config.php';
}

require_once __DIR__ . '/lib/itm_prod_hardening_check.php';

itm_script_output_begin('Production hardening check');

$nl = itm_script_output_nl();
$enforce = itm_prod_hardening_enforce_failures($forceEnforce);
$failures = 0;

$record = static function (string $level, string $message) use ($nl, $enforce, &$failures): void {
    if ($level === 'fail' && $enforce) {
        $failures++;
        echo colorText('[FAIL] ' . $message, 'fail') . $nl;
        return;
    }
    if ($level === 'fail' && !$enforce) {
        echo colorText('[WARN] ' . $message, 'warn') . $nl;
        return;
    }
    if ($level === 'pass') {
        echo colorText('[PASS] ' . $message, 'pass') . $nl;
        return;
    }
    echo colorText('[INFO] ' . $message, 'info') . $nl;
};

$profile = defined('APP_ENV') ? (string)APP_ENV : 'unknown';
if ($enforce) {
    $record('info', 'Production hardening enforcement is active (APP_ENV=' . $profile . ($forceEnforce ? ', --enforce' : '') . ')');
} else {
    $record('info', 'Development profile — unsafe findings print as [WARN] only (APP_ENV=' . $profile . '). Use --enforce to fail.');
}

foreach (itm_prod_hardening_check_env_dev_flags() as $message) {
    $record('fail', $message);
}
if (itm_prod_hardening_check_env_dev_flags() === []) {
    $record('pass', 'No dev-only env bypass flags (ITM_SKIP_FORCE_PASSWORD_CHANGE / ITM_DEV)');
}

foreach (itm_prod_hardening_check_no_auth_env_overrides() as $message) {
    $record('fail', $message);
}
if (itm_prod_hardening_check_no_auth_env_overrides() === []) {
    $record('pass', 'No broad ITM_SCRIPT_NO_AUTH env overrides');
}

$rootPath = defined('ROOT_PATH') ? (string)ROOT_PATH : dirname(__DIR__) . '/';
foreach (itm_prod_hardening_check_error_log_web_root($rootPath) as $message) {
    $record('fail', $message);
}
if (itm_prod_hardening_check_error_log_web_root($rootPath) === []) {
    $record('pass', 'No error_log.txt under application root');
}

if ($conn instanceof mysqli) {
    $displayFailures = itm_prod_hardening_check_display_errors_setting($conn);
    foreach ($displayFailures as $message) {
        $record('fail', $message);
    }
    if ($displayFailures === []) {
        $record('pass', 'Browser error reporting disabled (ui_configuration + display_errors)');
    }

    $seedFailures = itm_prod_hardening_check_seed_passwords($conn);
    foreach ($seedFailures as $message) {
        $record('fail', $message);
    }
    if ($seedFailures === []) {
        $record('pass', 'Seed admin/demo accounts no longer accept canonical import passwords');
    }
} else {
    $record('fail', 'Database connection unavailable — cannot verify seed passwords or ui_configuration');
}

if (!$skipHttp) {
    $probe = itm_prod_hardening_check_bypass_login_http(itm_prod_hardening_resolve_probe_base_url());
    if ($probe['skipped']) {
        $record('info', $probe['skip_reason']);
    } else {
        foreach ($probe['failures'] as $message) {
            $record('fail', $message);
        }
        if ($probe['failures'] === []) {
            $record('pass', 'scripts/bypass_login.php is not web-reachable or is blocked');
        }
    }
} else {
    $record('info', 'HTTP probe skipped (--skip-http)');
}

if ($failures > 0) {
    echo $nl . colorText('[FAIL] Production hardening check failed (' . $failures . ' issue(s)).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . colorText('[PASS] Production hardening check passed for the active profile.', 'pass') . $nl;
itm_script_output_end();
exit(0);
