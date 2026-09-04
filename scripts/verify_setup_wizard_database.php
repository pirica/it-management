<?php
/**
 * Setup wizard step 3 database probe / create / replace regression.
 *
 * CLI: php scripts/verify_setup_wizard_database.php
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

require_once ROOT_PATH . 'includes/bootstrap_helpers.php';
require_once ROOT_PATH . 'setup/includes/itm_setup_wizard.php';

$fail = 0;

function setup_db_fail(string $message): void
{
    global $fail;
    $fail++;
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
}

function setup_db_pass(string $message): void
{
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
}

if (!itm_setup_wizard_is_safe_database_name('itmanagement2')) {
    setup_db_fail('itmanagement2 must be a safe database name');
} else {
    setup_db_pass('Safe database name accepts alphanumeric + underscore');
}

if (itm_setup_wizard_is_safe_database_name('bad-name')) {
    setup_db_fail('Hyphenated database names must be rejected');
} else {
    setup_db_pass('Unsafe database names are rejected');
}

$rewritten = itm_setup_wizard_rewrite_sql_for_database("USE `itmanagement`;\n", 'itmanagement3');
if (strpos($rewritten, 'USE `itmanagement3`;') === false) {
    setup_db_fail('SQL bundle rewrite must map canonical database name to target schema');
} else {
    setup_db_pass('SQL bundle rewrite maps itmanagement to custom DB name');
}

if (strpos(itm_setup_wizard_rewrite_sql_for_database('USE `itmanagement`;', 'itmanagement'), 'itmanagement3') !== false) {
    setup_db_fail('SQL rewrite must be a no-op when target matches canonical name');
} else {
    setup_db_pass('SQL rewrite is a no-op for canonical database name');
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('DB_PORT') ?: '3306');
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'itmanagement';
$testDb = 'itm_setup_wizard_probe_' . substr(sha1((string)getmypid()), 0, 8);

$serverConn = itm_setup_wizard_connect_mysql_server($host, $port, $user, $pass);
if (!$serverConn) {
    fwrite(STDOUT, '[SKIP] MySQL server not reachable at ' . $host . ':' . $port . ' — live probe tests skipped.' . PHP_EOL);
    exit($fail > 0 ? 1 : 0);
}
mysqli_close($serverConn);

$probeMissing = itm_setup_wizard_probe_database($host, $port, $user, $pass, $testDb);
if (empty($probeMissing['server_ok'])) {
    setup_db_fail('Probe must report server_ok when credentials work');
} elseif (empty($probeMissing['needs_create']) || !empty($probeMissing['database_exists'])) {
    setup_db_fail('Missing database must set needs_create and not database_exists');
} elseif (stripos($probeMissing['message'], 'Unknown database') !== false) {
    setup_db_fail('Missing database message must not be raw mysqli Unknown database error');
} else {
    setup_db_pass('Probe missing database returns needs_create with friendly message');
}

$create = itm_setup_wizard_create_database($host, $port, $user, $pass, $testDb);
if (!$create['ok']) {
    if (stripos($create['message'], 'schema directory') !== false) {
        fwrite(STDOUT, '[SKIP] CREATE DATABASE unavailable in this MySQL environment — create/reset live tests skipped.' . PHP_EOL);
        exit($fail > 0 ? 1 : 0);
    }
    setup_db_fail('Create database failed: ' . $create['message']);
} else {
    setup_db_pass('Create database succeeds for new schema name');
}

$duplicate = itm_setup_wizard_create_database($host, $port, $user, $pass, $testDb);
if ($duplicate['ok']) {
    setup_db_fail('Create database must fail when schema already exists');
} else {
    setup_db_pass('Create database rejects duplicate schema');
}

$probeEmpty = itm_setup_wizard_probe_database($host, $port, $user, $pass, $testDb);
if (!$probeEmpty['ok'] || !empty($probeEmpty['needs_create']) || !empty($probeEmpty['needs_replace_confirm'])) {
    setup_db_fail('Empty created database must connect without create/replace flags');
} else {
    setup_db_pass('Probe empty database connects successfully');
}

$dbConn = itm_mysqli_connect($host, $user, $pass, $testDb, $port);
if (!$dbConn) {
    setup_db_fail('Could not connect to test database for table seed');
} else {
    mysqli_query($dbConn, 'CREATE TABLE setup_wizard_probe_dummy (id INT PRIMARY KEY)');
    mysqli_close($dbConn);

    $probeTables = itm_setup_wizard_probe_database($host, $port, $user, $pass, $testDb);
    if (!$probeTables['ok'] || empty($probeTables['needs_replace_confirm']) || (int)$probeTables['table_count'] < 1) {
        setup_db_fail('Database with tables must set needs_replace_confirm');
    } else {
        setup_db_pass('Probe existing tables sets needs_replace_confirm');
    }
}

$reset = itm_setup_wizard_reset_database($host, $port, $user, $pass, $testDb);
if (!$reset['ok']) {
    setup_db_fail('Reset database failed: ' . $reset['message']);
} else {
    setup_db_pass('Reset database drops and recreates schema');
}

$probeAfterReset = itm_setup_wizard_probe_database($host, $port, $user, $pass, $testDb);
if (!$probeAfterReset['ok'] || !empty($probeAfterReset['needs_replace_confirm'])) {
    setup_db_fail('After reset, database must be empty and not need replace confirm');
} else {
    setup_db_pass('After reset, probe reports empty database');
}

$cleanupConn = itm_setup_wizard_connect_mysql_server($host, $port, $user, $pass);
if ($cleanupConn) {
    mysqli_query($cleanupConn, 'DROP DATABASE IF EXISTS `' . $testDb . '`');
    mysqli_close($cleanupConn);
}

exit($fail > 0 ? 1 : 0);
