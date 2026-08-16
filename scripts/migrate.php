<?php
/**
 * Database migration runner — apply db/migrations/*.sql in filename order.
 *
 * CLI: php scripts/migrate.php --status
 * CLI: php scripts/migrate.php --apply
 * Browser: scripts/migrate.php?run=1 (status) / ?run=1&apply=1 (Admin apply) / POST delete (Admin)
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/migrate.php --status</code> — probes the <strong>live database</strong> for every migration file (Applied vs Pending); <code>schema_migrations</code> is audit/history only.<br>
<code>php scripts/migrate.php --apply</code> — runs only migrations whose live schema probe failed; records satisfied migrations without re-executing destructive SQL. Browser apply requires Admin: <code>?run=1&amp;apply=1</code>. Browser delete (🗑️ column) removes the <code>db/migrations/*.sql</code> file from disk (Admin + confirm).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

/**
 * @param array<int, array<string, mixed>> $migrations
 */
function itm_migrate_render_browser_table(array $migrations, bool $allowDelete = false, string $csrfToken = ''): void
{
    echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin:12px 0;width:100%;max-width:1200px;">';
    echo '<thead><tr>';
    echo '<th>Status</th>';
    echo '<th>Migration file</th>';
    echo '<th>Message</th>';
    echo '<th>Verify Script run=1</th>';
    echo '<th>Fix Script run=1&amp;apply=1</th>';
    echo '<th>Open SQL (new window)</th>';
    echo '<th>Delete</th>';
    echo '</tr></thead><tbody>';

    foreach ($migrations as $row) {
        $state = (string)($row['state'] ?? '');
        $filename = (string)($row['filename'] ?? '');
        $detail = (string)($row['detail'] ?? '');
        $recorded = !empty($row['recorded']);

        if ($state === 'applied') {
            $color = '#1a7f37';
            $statusText = 'Applied';
        } elseif ($state === 'drift') {
            $color = '#cf222e';
            $statusText = 'Drift';
        } else {
            $color = '#9a6700';
            $statusText = 'Pending';
        }

        $verifyHref = 'migrate.php?run=1';
        $fixHref = 'migrate.php?run=1&amp;apply=1';
        $sqlHref = 'migrate.php?run=1&amp;sql=' . rawurlencode($filename);
        $verifyLink = '<a href="' . $verifyHref . '">migrate.php?run=1</a>';
        $sqlLink = '<a href="' . $sqlHref . '" target="_blank" rel="noopener noreferrer">Open SQL</a>';
        $fixLink = '—';
        if ($state === 'pending' || ($state === 'applied' && !$recorded)) {
            $fixLink = '<a href="' . $fixHref . '">migrate.php?run=1&amp;apply=1</a>';
        } elseif ($state === 'drift') {
            $fixLink = '<span title="Resolve checksum drift before apply">—</span>';
        }

        $deleteCell = '—';
        if ($allowDelete && $filename !== '' && $csrfToken !== '') {
            $confirmText = 'Delete migration file ' . $filename . ' from the filesystem? This cannot be undone.';
            $confirmJs = 'return confirm(' . json_encode($confirmText) . ');';
            $deleteCell = '<form method="POST" action="migrate.php?run=1" style="display:inline;margin:0;"'
                . ' onsubmit="' . htmlspecialchars($confirmJs, ENT_QUOTES, 'UTF-8') . '">'
                . '<input type="hidden" name="csrf_token" value="'
                . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">'
                . '<input type="hidden" name="delete_migration" value="1">'
                . '<input type="hidden" name="filename" value="'
                . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . '">'
                . '<button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>'
                . '</form>';
        }

        echo '<tr>';
        echo '<td style="color:' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . ';font-weight:600;">'
            . htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td><code>' . htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') . '</code></td>';
        echo '<td>' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . $verifyLink . '</td>';
        echo '<td>' . $fixLink . '</td>';
        echo '<td>' . $sqlLink . '</td>';
        echo '<td>' . $deleteCell . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}

// Why: Serve migration SQL as plain text in a new tab before the HTML <pre> wrapper starts.
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    $sqlView = isset($_GET['sql']) ? trim((string)$_GET['sql']) : '';
    if ($sqlView !== '' && isset($_GET['run']) && (string)$_GET['run'] === '1') {
        require_once __DIR__ . '/../config/config.php';
        require_once __DIR__ . '/../includes/itm_database_migrations.php';

        $fileRow = itm_database_migrations_resolve_discovered_file($sqlView);
        if ($fileRow === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Migration file not found or not allowed.\n";
            exit(1);
        }

        $contents = file_get_contents((string)$fileRow['path']);
        if ($contents === false) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Failed to read migration file.\n";
            exit(1);
        }

        if (strncmp($contents, "\xEF\xBB\xBF", 3) === 0) {
            $contents = substr($contents, 3);
        }

        header('Content-Type: text/plain; charset=utf-8');
        header(
            'Content-Disposition: inline; filename="'
            . str_replace('"', '', (string)$fileRow['filename'])
            . '"'
        );
        echo $contents;
        exit(0);
    }
}

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/../includes/itm_database_migrations.php';

$boot = itm_apply_script_bootstrap('Database migrations', [
    'usage_gate_title' => 'Database migrations',
    'skip_db_tests' => false,
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

$browserDeleteNotice = '';
if (
    !$isCli
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_POST['delete_migration'])
    && (string)$_POST['delete_migration'] === '1'
) {
    if (function_exists('itm_script_require_admin_script_or_exit')) {
        itm_script_require_admin_script_or_exit(
            $conn,
            'Forbidden: administrator login required.'
        );
    } elseif (!function_exists('itm_is_admin') || !itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
        http_response_code(403);
        echo colorText('[FAIL] Forbidden: administrator login required.', 'fail') . $nl;
        itm_script_output_end();
        exit(1);
    }

    itm_require_post_csrf();
    $deleteFilename = trim((string)($_POST['filename'] ?? ''));
    [$deleted, $deleteMessage] = itm_database_migrations_delete_discovered_file($conn, $deleteFilename);
    if ($deleted) {
        $browserDeleteNotice = $deleteMessage;
        echo colorText('[PASS] ' . $deleteMessage, 'pass') . $nl;
    } else {
        echo colorText('[FAIL] ' . $deleteMessage, 'fail') . $nl;
        itm_script_output_end();
        exit(1);
    }
}

$status = itm_database_migrations_build_status($conn);

if ($showStatus) {
    if (!$isCli) {
        $allowDelete = function_exists('itm_is_admin')
            && itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0));
        $csrfToken = function_exists('itm_get_csrf_token') ? itm_get_csrf_token() : '';

        itm_script_output_close_pre();
        echo '<h1>Database migration status</h1>';
        if ($browserDeleteNotice !== '') {
            echo '<p style="color:#1a7f37;font-weight:600;">'
                . htmlspecialchars($browserDeleteNotice, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        echo '<p>Database: <code>' . htmlspecialchars((string)$status['database'], ENT_QUOTES, 'UTF-8') . '</code>. ';
        echo 'Pending: <strong>' . (int)$status['pending_count'] . '</strong>. ';
        echo 'Drift: <strong>' . (int)$status['drift_count'] . '</strong>. ';
        echo 'Live DB probe is authoritative — <code>schema_migrations</code> is audit/history only.</p>';
        echo '<p>Full audit history: <a href="' . htmlspecialchars(BASE_URL . 'modules/schema_migrations/index.php', ENT_QUOTES, 'UTF-8') . '">modules/schema_migrations/index.php</a> (Admin session).</p>';
        itm_migrate_render_browser_table($status['migrations'], $allowDelete, $csrfToken);
    }

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

$run = itm_database_migrations_apply_pending($conn, !$apply);

if (!$isCli) {
    $status = itm_database_migrations_build_status($conn);
    $allowDelete = function_exists('itm_is_admin')
        && itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0));
    $csrfToken = function_exists('itm_get_csrf_token') ? itm_get_csrf_token() : '';

    itm_script_output_close_pre();
    echo '<h1>Database migration ' . htmlspecialchars($modeLabel, ENT_QUOTES, 'UTF-8') . '</h1>';
    if ($browserDeleteNotice !== '') {
        echo '<p style="color:#1a7f37;font-weight:600;">'
            . htmlspecialchars($browserDeleteNotice, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    echo '<p>Database: <code>' . htmlspecialchars((string)$status['database'], ENT_QUOTES, 'UTF-8') . '</code>. ';
    echo 'Pending: <strong>' . (int)$status['pending_count'] . '</strong>. ';
    echo 'Drift: <strong>' . (int)$status['drift_count'] . '</strong>.';
    if (!$apply) {
        echo ' Dry-run — no SQL executed.';
    }
    echo '</p>';
    itm_migrate_render_browser_table($status['migrations'], $allowDelete, $csrfToken);
}

echo colorText('Database migration ' . $modeLabel, 'info') . $nl;

if (!$apply) {
    echo '[INFO] Dry-run — no SQL executed.' . $nl;
}

foreach ($run['skipped'] ?? [] as $filename) {
    echo colorText('[SKIP] ' . $filename . ' — already applied.', 'info') . $nl;
}

foreach ($run['recorded'] ?? [] as $filename) {
    if ($apply) {
        echo colorText('[PASS] Recorded ' . $filename . ' (schema already matched)', 'pass') . $nl;
    } else {
        echo colorText('[PLAN] Would record ' . $filename . ' (schema already matched)', 'warn') . $nl;
    }
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
