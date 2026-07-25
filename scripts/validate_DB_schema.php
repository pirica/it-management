<?php
/**
 * Static validation of database schema consistency.
 *
 * Why: Checks for missing FKs on employee_id columns, duplicate indexes,
 * and orphaned indexes to ensure long-term database health.
 *
 * Browser: open scripts/validate_DB_schema.php (login required).
 * CLI: php scripts/validate_DB_schema.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
} else {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_schema_validation.php';

$nl = itm_script_output_nl();
itm_script_output_begin('Validating Database Schema');

$validation = itm_schema_collect_validation_issues($conn);
$errors = $validation['errors'];
$warnings = $validation['warnings'];
$skips = $validation['skips'] ?? [];

echo '### Schema validation results...' . $nl;

foreach ($errors as $error) {
    echo colorText('[ERROR]', 'fail') . ' ' . $error . $nl;
}

foreach ($warnings as $warning) {
    echo colorText('[WARN]', 'warn') . ' ' . $warning . $nl;
}

foreach ($skips as $skip) {
    echo colorText('[SKIP]', 'pass') . ' ' . $skip . $nl;
}

echo $nl . '## Schema Validation Completed' . $nl;

if ($errors === [] && $warnings === [] && $skips === []) {
    echo colorText('No issues found. Schema is consistent.', 'pass') . $nl;
} else {
    if ($errors !== []) {
        echo colorText('Errors:', 'fail') . $nl;
        foreach ($errors as $e) {
            echo ' - ' . $e . $nl;
        }
    }

    if ($warnings !== []) {
        echo colorText('Warnings:', 'warn') . $nl;
        foreach ($warnings as $w) {
            echo ' - ' . $w . $nl;
        }
    }

    if ($skips !== []) {
        echo colorText('Skipped:', 'pass') . $nl;
        foreach ($skips as $s) {
            echo ' - ' . $s . $nl;
        }
    }
}

itm_script_output_end();

if ($errors !== []) {
    exit(1);
}
