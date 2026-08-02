<?php
/**
 * Database migration runner — apply db/migrations/*.sql in filename order.
 *
 * CLI: php scripts/migrate.php --status
 * CLI: php scripts/migrate.php --apply
 * Browser: scripts/migrate.php?run=1 (status) / ?run=1&apply=1 (Admin apply)
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/migrate.php --status</code> — lists pending <code>db/migrations/*.sql</code> files vs <code>schema_migrations</code>.<br>
<code>php scripts/migrate.php --apply</code> — runs pending migrations in one session (destructive DROP+CREATE files — back up first). Browser apply requires Admin: <code>?run=1&amp;apply=1</code>.<br>
Pair with <code>php scripts/verify_db_migrations.php</code> to probe live schema before/after apply.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/../includes/itm_database_migrations.php';

$boot = itm_apply_script_bootstrap('Database migrations', [
    'usage_gate_title' => 'Database migrations',
]);
$apply = $boot['apply'];
$nl = $boot['nl'];
$conn = $boot['conn'];
$isCli = $boot['is_cli'];
$argvLocal = $boot['argv'] ?? [];

$showStatus = !$apply;
if ($isCli) {
    if (in_array('--apply', $argvLocal, true)) {
        $showStatus = false;
    } elseif (in_array('--status', $argvLocal, true)) {
        $showStatus = true;
    }
}

if (!$conn instanceof mysqli) {
    echo colorText('[FAIL] No database connection.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$status = itm_database_migrations_build_status($conn);

if ($showStatus) {
    echo colorText('Database migration status', 'info') . $nl;
    echo '[INFO] Database: ' . (string)$status['database'] . $nl;
    echo '[INFO] Migration files: ' . (int)$status['file_count']
        . ' | Pending: ' . (int)$status['pending_count']
        . ' | Drift: ' . (int)$status['drift_count'] . $nl;
    echo str_repeat('-', 72) . $nl;

    foreach ($status['migrations'] as $row) {
        $prefix = '[PENDING]';
        $type = 'warn';
        if (($row['state'] ?? '') === 'applied') {
            $prefix = '[APPLIED]';
            $type = 'pass';
        } elseif (($row['state'] ?? '') === 'drift') {
            $prefix = '[DRIFT]';
            $type = 'fail';
        }
        $line = $prefix . ' ' . ($row['filename'] ?? '') . ' — ' . ($row['detail'] ?? '');
        echo colorText($line, $type) . $nl;
    }

    echo str_repeat('-', 72) . $nl;
    if ((int)$status['pending_count'] === 0 && (int)$status['drift_count'] === 0) {
        echo colorText('[PASS] No pending migrations.', 'pass') . $nl;
        itm_script_output_end();
        exit(0);
    }

    if ((int)$status['drift_count'] > 0) {
        echo colorText('[FAIL] Checksum drift on applied migration file(s).', 'fail') . $nl;
        itm_script_output_end();
        exit(1);
    }

    echo colorText('[INFO] Pending migrations listed above — run with --apply after backup.', 'info') . $nl;
    itm_script_output_end();
    exit(0);
}

$modeLabel = $apply ? 'apply' : 'dry-run';
echo colorText('Database migration ' . $modeLabel, 'info') . $nl;

$run = itm_database_migrations_apply_pending($conn, !$apply);
if (!$apply) {
    echo '[INFO] Dry-run — no SQL executed.' . $nl;
}

foreach ($run['skipped'] ?? [] as $filename) {
    echo colorText('[SKIP] ' . $filename . ' — already applied.', 'info') . $nl;
}

foreach ($run['applied'] ?? [] as $filename) {
    if ($apply) {
        echo colorText('[PASS] Applied ' . $filename, 'pass') . $nl;
    } else {
        echo colorText('[PLAN] Would apply ' . $filename, 'warn') . $nl;
    }
}

foreach ($run['errors'] ?? [] as $errorRow) {
    $file = (string)($errorRow['filename'] ?? '');
    $message = (string)($errorRow['message'] ?? '');
    $label = $file !== '' ? $file . ': ' . $message : $message;
    echo colorText('[FAIL] ' . $label, 'fail') . $nl;
}

if (!($run['ok'] ?? false)) {
    itm_script_output_end();
    exit(1);
}

if ($apply) {
    echo colorText('[PASS] Migration apply completed.', 'pass') . $nl;
} else {
    echo colorText('[PASS] Dry-run complete — re-run with --apply to execute.', 'pass') . $nl;
}

itm_script_output_end();
exit(0);
