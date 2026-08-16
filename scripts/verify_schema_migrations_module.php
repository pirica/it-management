<?php
/**
 * Schema Migrations module regression checks.
 *
 * CLI: php scripts/verify_schema_migrations_module.php
 * Browser: scripts/verify_schema_migrations_module.php?run=1
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="verify_schema_migrations_module.php?run=1">verify_schema_migrations_module.php?run=1</a>. CLI: <code>php scripts/verify_schema_migrations_module.php</code>. Run when changing <code>modules/schema_migrations/</code> or migration audit UI wiring.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/itm_database_migrations.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Schema Migrations Module Verification');

$nl = itm_script_output_nl();
$failures = 0;

function sm_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function sm_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$indexPath = ROOT_PATH . 'modules/schema_migrations/index.php';
$viewPath = ROOT_PATH . 'modules/schema_migrations/view.php';

if (!is_file($indexPath)) {
    sm_verify_fail('Missing modules/schema_migrations/index.php');
} else {
    sm_verify_pass('index.php exists.');
    $indexSource = file_get_contents($indexPath);
    if ($indexSource === false) {
        sm_verify_fail('Could not read index.php');
    } else {
        if (strpos($indexSource, 'itm_is_admin') === false) {
            sm_verify_fail('index.php missing admin gate (itm_is_admin).');
        } else {
            sm_verify_pass('index.php includes admin gate.');
        }
        foreach (['bulk_action', 'clear_table', 'add_sample_data'] as $forbidden) {
            if (strpos($indexSource, $forbidden) !== false) {
                sm_verify_fail('index.php contains forbidden token: ' . $forbidden);
            }
        }
        sm_verify_pass('index.php has no bulk/clear/sample-data markers.');
        if (strpos($indexSource, 'itm_database_migrations_build_status') === false) {
            sm_verify_fail('index.php missing migration status summary helper.');
        } else {
            sm_verify_pass('index.php uses itm_database_migrations_build_status().');
        }
    }
}

if (!is_file($viewPath)) {
    sm_verify_fail('Missing modules/schema_migrations/view.php');
} else {
    sm_verify_pass('view.php exists.');
    $viewSource = file_get_contents($viewPath);
    if ($viewSource !== false && strpos($viewSource, 'itm_is_admin') === false) {
        sm_verify_fail('view.php missing admin gate (itm_is_admin).');
    } elseif ($viewSource !== false) {
        sm_verify_pass('view.php includes admin gate.');
    }
}

foreach (['create.php', 'edit.php', 'delete.php'] as $wrapper) {
    $wrapperPath = ROOT_PATH . 'modules/schema_migrations/' . $wrapper;
    if (!is_file($wrapperPath)) {
        sm_verify_fail('Missing wrapper: ' . $wrapper);
        continue;
    }
    $wrapperSource = file_get_contents($wrapperPath);
    if ($wrapperSource === false || strpos($wrapperSource, 'index.php') === false) {
        sm_verify_fail($wrapper . ' does not redirect to index.php.');
    } else {
        sm_verify_pass($wrapper . ' redirects to index.php.');
    }
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    sm_verify_fail('No database connection.');
} else {
    if (itm_database_migrations_table_exists($conn)) {
        sm_verify_pass('schema_migrations table exists.');
        itm_database_migrations_ensure_table($conn);
        $map = itm_database_migrations_fetch_applied_map($conn);
        if (!is_array($map)) {
            sm_verify_fail('itm_database_migrations_fetch_applied_map() did not return an array.');
        } else {
            sm_verify_pass('itm_database_migrations_fetch_applied_map() returned array (' . count($map) . ' rows).');
            if (!isset($map['schema_migrations.sql'])) {
                sm_verify_fail('schema_migrations.sql bootstrap row missing after ensure_table.');
            } else {
                sm_verify_pass('schema_migrations.sql recorded in audit table.');
            }
        }
    } else {
        sm_verify_fail('schema_migrations table missing — run migrate.php or import db/.');
    }
}

if ($failures > 0) {
    echo colorText('[FAIL] ' . $failures . ' check(s) failed.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('[PASS] All schema_migrations module checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
