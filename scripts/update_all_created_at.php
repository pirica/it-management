<?php
/**
 * Set created_at on every row in every table that has a created_at column.
 *
 * Why: After importing database.sql, DEFAULT CURRENT_TIMESTAMP and replication gaps
 * can leave live rows with import-time created_at values. Run from the browser after
 * import (or via CLI for automation).
 *
 * Browser: open scripts/update_all_created_at.php (login required).
 * CLI: php scripts/update_all_created_at.php [--dry-run] [--at="2026-01-01 00:00:01"]
 */

declare(strict_types=1);

$itmIsCli = PHP_SAPI === 'cli';

if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

/**
 * @param mixed $value
 */
function itm_update_all_created_at_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * @return array{
 *     lines: array<int, array{level: string, table: string, message: string, rows: int}>,
 *     total_affected: int,
 *     error_count: int,
 *     table_count: int,
 *     target: string,
 *     dry_run: bool
 * }
 */
function itm_update_all_created_at_run(mysqli $conn, string $targetCreatedAt, bool $dryRun): array
{
    $lines = [];
    $totalAffected = 0;
    $errorCount = 0;

    // Why: database.sql once shipped sidebar audit triggers with username/user_email columns removed from audit_logs.
    if (function_exists('itm_ensure_employee_sidebar_preferences_audit_triggers')) {
        if (!itm_ensure_employee_sidebar_preferences_audit_triggers($conn)) {
            $lines[] = [
                'level' => 'error',
                'table' => 'employee_sidebar_preferences',
                'message' => 'Could not rebuild audit triggers (username/user_email column mismatch).',
                'rows' => 0,
            ];
            $errorCount++;
        }
    }

    $schemaName = mysqli_real_escape_string($conn, (string) DB_NAME);
    $listSql = "
        SELECT `TABLE_NAME` AS `table_name`
        FROM `information_schema`.`COLUMNS`
        WHERE `TABLE_SCHEMA` = '{$schemaName}'
          AND `COLUMN_NAME` = 'created_at'
        ORDER BY `TABLE_NAME` ASC
    ";

    $columnResult = itm_run_query($conn, $listSql);
    if (!$columnResult) {
        return [
            'lines' => [['level' => 'error', 'table' => '', 'message' => 'Unable to enumerate tables with created_at.', 'rows' => 0]],
            'total_affected' => 0,
            'error_count' => 1,
            'table_count' => 0,
            'target' => $targetCreatedAt,
            'dry_run' => $dryRun,
        ];
    }

    $tables = [];
    while ($row = mysqli_fetch_assoc($columnResult)) {
        $tableName = isset($row['table_name']) ? (string) $row['table_name'] : '';
        if ($tableName !== '' && itm_is_safe_identifier($tableName)) {
            $tables[] = $tableName;
        }
    }
    mysqli_free_result($columnResult);

    if (!$tables) {
        return [
            'lines' => [['level' => 'info', 'table' => '', 'message' => "No tables with created_at in schema '" . DB_NAME . "'.", 'rows' => 0]],
            'total_affected' => 0,
            'error_count' => 0,
            'table_count' => 0,
            'target' => $targetCreatedAt,
            'dry_run' => $dryRun,
        ];
    }

    foreach ($tables as $tableName) {
        $countSql = "SELECT COUNT(*) AS `row_count` FROM `{$tableName}` WHERE `created_at` IS NULL OR `created_at` <> ?";
        $countStmt = mysqli_prepare($conn, $countSql);
        if (!$countStmt) {
            $errorCount++;
            $lines[] = ['level' => 'error', 'table' => $tableName, 'message' => 'Unable to prepare count query.', 'rows' => 0];
            continue;
        }
        mysqli_stmt_bind_param($countStmt, 's', $targetCreatedAt);
        if (!mysqli_stmt_execute($countStmt)) {
            $errorCount++;
            $lines[] = ['level' => 'error', 'table' => $tableName, 'message' => 'Count query failed.', 'rows' => 0];
            mysqli_stmt_close($countStmt);
            continue;
        }
        $countResult = mysqli_stmt_get_result($countStmt);
        $pendingRows = 0;
        if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
            $pendingRows = (int) ($countRow['row_count'] ?? 0);
        }
        if ($countResult) {
            mysqli_free_result($countResult);
        }
        mysqli_stmt_close($countStmt);

        if ($pendingRows === 0) {
            $lines[] = ['level' => 'skip', 'table' => $tableName, 'message' => 'Already normalized.', 'rows' => 0];
            continue;
        }

        if ($dryRun) {
            $totalAffected += $pendingRows;
            $lines[] = ['level' => 'plan', 'table' => $tableName, 'message' => 'Would update row(s).', 'rows' => $pendingRows];
            continue;
        }

        $updateSql = "UPDATE `{$tableName}` SET `created_at` = ? WHERE `created_at` IS NULL OR `created_at` <> ?";
        $updateStmt = mysqli_prepare($conn, $updateSql);
        if (!$updateStmt) {
            $errorCount++;
            $lines[] = ['level' => 'error', 'table' => $tableName, 'message' => 'Unable to prepare update query.', 'rows' => 0];
            continue;
        }
        mysqli_stmt_bind_param($updateStmt, 'ss', $targetCreatedAt, $targetCreatedAt);
        if (!mysqli_stmt_execute($updateStmt)) {
            $errorCount++;
            $lines[] = [
                'level' => 'error',
                'table' => $tableName,
                'message' => 'Update failed — ' . mysqli_stmt_error($updateStmt),
                'rows' => 0,
            ];
            mysqli_stmt_close($updateStmt);
            continue;
        }
        $affected = (int) mysqli_stmt_affected_rows($updateStmt);
        mysqli_stmt_close($updateStmt);
        $totalAffected += $affected;
        $lines[] = ['level' => 'ok', 'table' => $tableName, 'message' => 'Updated row(s).', 'rows' => $affected];
    }

    return [
        'lines' => $lines,
        'total_affected' => $totalAffected,
        'error_count' => $errorCount,
        'table_count' => count($tables),
        'target' => $targetCreatedAt,
        'dry_run' => $dryRun,
    ];
}

/**
 * @param array{
 *     lines: array<int, array{level: string, table: string, message: string, rows: int}>,
 *     total_affected: int,
 *     error_count: int,
 *     table_count: int,
 *     target: string,
 *     dry_run: bool
 * } $result
 */
function itm_update_all_created_at_print_cli(array $result): void
{
    $modeLabel = $result['dry_run'] ? 'DRY RUN' : 'UPDATE';
    fwrite(STDOUT, "[{$modeLabel}] Setting created_at to {$result['target']} on {$result['table_count']} table(s) in '" . DB_NAME . "'...\n\n");

    foreach ($result['lines'] as $line) {
        $tag = strtoupper($line['level']);
        if ($tag === 'PLAN') {
            $tag = 'PLAN ';
        } elseif (strlen($tag) < 5) {
            $tag = str_pad($tag, 5, ' ', STR_PAD_RIGHT);
        }
        $tablePart = $line['table'] !== '' ? $line['table'] . ': ' : '';
        $rowsPart = $line['rows'] > 0 ? " {$line['rows']} row(s)" : '';
        if ($line['level'] === 'skip') {
            fwrite(STDOUT, "[SKIP ] {$line['table']}: {$line['message']}\n");
        } elseif ($line['level'] === 'plan') {
            fwrite(STDOUT, "[PLAN ] {$line['table']}: would update{$rowsPart}.\n");
        } elseif ($line['level'] === 'ok') {
            fwrite(STDOUT, "[OK   ] {$line['table']}: updated{$rowsPart}.\n");
        } elseif ($line['level'] === 'error') {
            fwrite(STDOUT, "[ERROR] {$tablePart}{$line['message']}\n");
        } else {
            fwrite(STDOUT, "[{$tag}] {$tablePart}{$line['message']}\n");
        }
    }

    fwrite(STDOUT, "\n");
    if ($result['dry_run']) {
        fwrite(STDOUT, "Dry run complete. {$result['total_affected']} row(s) would be updated.\n");
    } else {
        fwrite(STDOUT, "Done. {$result['total_affected']} row(s) updated across {$result['table_count']} table(s).");
        if ($result['error_count'] > 0) {
            fwrite(STDOUT, " {$result['error_count']} table(s) had errors.");
        }
        fwrite(STDOUT, "\n");
    }
}

$defaultTarget = '2026-01-01 00:00:01';

if ($itmIsCli) {
    $targetCreatedAt = $defaultTarget;
    $dryRun = false;

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run' || $arg === '-n') {
            $dryRun = true;
            continue;
        }
        if (strpos($arg, '--at=') === 0) {
            $targetCreatedAt = substr($arg, 5);
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            fwrite(STDOUT, "Usage: php scripts/update_all_created_at.php [--dry-run] [--at=\"2026-01-01 00:00:01\"]\n");
            fwrite(STDOUT, "Browser: open scripts/update_all_created_at.php in the app (login required).\n");
            exit(0);
        }
        fwrite(STDERR, "Unknown argument: {$arg}\n");
        exit(1);
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $targetCreatedAt)) {
        fwrite(STDERR, "Invalid --at value; expected YYYY-MM-DD HH:MM:SS.\n");
        exit(1);
    }

    try {
        require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();

$nl = itm_script_output_nl();

    } catch (Throwable $e) {
        fwrite(STDERR, 'Unable to bootstrap application config/db connection: ' . $e->getMessage() . "\n");
        exit(1);
    }

    if (!isset($conn) || !($conn instanceof mysqli) || mysqli_connect_errno()) {
        fwrite(STDERR, "Database connection failed.\n");
        exit(1);
    }

    $result = itm_update_all_created_at_run($conn, $targetCreatedAt, $dryRun);
    itm_update_all_created_at_print_cli($result);
    exit($result['error_count'] > 0 ? 1 : 0);
}

require_once dirname(__DIR__) . '/config/config.php';

if (!isset($conn) || !($conn instanceof mysqli) || mysqli_connect_errno()) {
    http_response_code(500);
    exit('Database connection failed.');
}

$baseUrl = defined('BASE_URL') ? (string) BASE_URL : '../';
$scriptSelf = $baseUrl . 'scripts/update_all_created_at.php';
$targetCreatedAt = $defaultTarget;
$dryRun = false;
$result = null;
$formError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $targetCreatedAt = isset($_POST['target_created_at']) ? trim((string) $_POST['target_created_at']) : $defaultTarget;
    $dryRun = !empty($_POST['dry_run']);
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $targetCreatedAt)) {
        $formError = 'Invalid timestamp. Use format YYYY-MM-DD HH:MM:SS.';
    } else {
        $result = itm_update_all_created_at_run($conn, $targetCreatedAt, $dryRun);
    }
}

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/lib/script_browser_nav.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Update all created_at</title>
    <link rel="stylesheet" href="<?= itm_update_all_created_at_escape($baseUrl . 'css/styles.css'); ?>">
    <style>
        .itm-created-at-wrap { max-width: 1100px; margin: 0 auto; padding: 20px; }
        .itm-created-at-card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d0d7de); border-radius: 8px; margin-bottom: 16px; padding: 16px; }
        .itm-created-at-muted { color: var(--text-muted, #57606a); line-height: 1.5; }
        .itm-created-at-form label { display: block; font-weight: 600; margin-bottom: 4px; }
        .itm-created-at-form input[type="text"] { width: 100%; max-width: 280px; padding: 8px; border-radius: 6px; border: 1px solid var(--border-color, #d0d7de); }
        .itm-created-at-form .form-row { margin-bottom: 12px; }
        .itm-created-at-table { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
        .itm-created-at-table th, .itm-created-at-table td { border: 1px solid var(--border-color, #d0d7de); padding: 8px; text-align: left; }
        .itm-created-at-table th { background: var(--table-header-bg, #f6f8fa); }
        .itm-created-at-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.78rem; font-weight: 600; }
        .itm-created-at-badge-ok { background: #dafbe1; color: #116329; }
        .itm-created-at-badge-plan { background: #ddf4ff; color: #0969da; }
        .itm-created-at-badge-skip { background: #f6f8fa; color: #57606a; }
        .itm-created-at-badge-error { background: #ffebe9; color: #cf222e; }
        .itm-created-at-alert { padding: 10px 12px; border-radius: 6px; margin-bottom: 12px; }
        .itm-created-at-alert-danger { background: #ffebe9; border: 1px solid #ff8182; color: #82071e; }
        .itm-created-at-alert-success { background: #dafbe1; border: 1px solid #4ac26b; color: #116329; }
    </style>
</head>
<body>
<div class="itm-created-at-wrap">
<?php itm_script_browser_nav_echo($baseUrl); ?>
    <div class="itm-created-at-card">
        <h1>Update all <code>created_at</code></h1>
        <p class="itm-created-at-muted">
            Run this <strong>after</strong> importing <code>database.sql</code> in phpMyAdmin.
            Every table that has a <code>created_at</code> column is updated to one timestamp
            (default <code>2026-01-01 00:00:01</code>). <code>updated_at</code> and other date columns are not changed.
        </p>
        <p class="itm-created-at-muted">
            CLI optional: <code>php scripts/update_all_created_at.php --dry-run</code>
        </p>
    </div>

    <div class="itm-created-at-card">
        <h2>Options</h2>
        <?php if ($formError !== ''): ?>
            <div class="itm-created-at-alert itm-created-at-alert-danger"><?= itm_update_all_created_at_escape($formError); ?></div>
        <?php endif; ?>
        <form class="itm-created-at-form" method="post" action="<?= itm_update_all_created_at_escape($scriptSelf); ?>">
            <input type="hidden" name="csrf_token" value="<?= itm_update_all_created_at_escape(itm_get_csrf_token()); ?>">
            <div class="form-row">
                <label for="target_created_at">Target <code>created_at</code></label>
                <input type="text" name="target_created_at" id="target_created_at" value="<?= itm_update_all_created_at_escape($targetCreatedAt); ?>" required pattern="\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}" placeholder="2026-01-01 00:00:01">
            </div>
            <div class="form-row">
                <label>
                    <input type="checkbox" name="dry_run" value="1"<?= $dryRun ? ' checked' : ''; ?>>
                    Dry run only (preview row counts, no changes)
                </label>
            </div>
            <div class="form-row">
                <button type="submit" class="btn-primary"><?= $dryRun ? 'Preview changes' : 'Update all created_at'; ?></button>
            </div>
        </form>
    </div>

    <?php if ($result !== null): ?>
    <div class="itm-created-at-card">
        <h2><?= $result['dry_run'] ? 'Preview results' : 'Update results'; ?></h2>
        <?php if ($result['error_count'] === 0): ?>
            <div class="itm-created-at-alert itm-created-at-alert-success">
                <?= $result['dry_run']
                    ? itm_update_all_created_at_escape((string) $result['total_affected'] . ' row(s) would be updated across ' . $result['table_count'] . ' table(s).')
                    : itm_update_all_created_at_escape((string) $result['total_affected'] . ' row(s) updated across ' . $result['table_count'] . ' table(s).'); ?>
            </div>
        <?php else: ?>
            <div class="itm-created-at-alert itm-created-at-alert-danger">
                <?= itm_update_all_created_at_escape($result['error_count'] . ' table(s) reported errors.'); ?>
            </div>
        <?php endif; ?>
        <table class="itm-created-at-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Table</th>
                    <th>Rows</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['lines'] as $line): ?>
                    <?php
                    $badgeClass = 'itm-created-at-badge-skip';
                    $statusLabel = strtoupper($line['level']);
                    if ($line['level'] === 'ok') {
                        $badgeClass = 'itm-created-at-badge-ok';
                    } elseif ($line['level'] === 'plan') {
                        $badgeClass = 'itm-created-at-badge-plan';
                    } elseif ($line['level'] === 'error') {
                        $badgeClass = 'itm-created-at-badge-error';
                    }
                    ?>
                <tr>
                    <td><span class="itm-created-at-badge <?= $badgeClass; ?>"><?= itm_update_all_created_at_escape($statusLabel); ?></span></td>
                    <td><?= itm_script_format_table_link((string)$line['table']); ?></td>
                    <td><?= $line['rows'] > 0 ? (int) $line['rows'] : '—'; ?></td>
                    <td><?= itm_update_all_created_at_escape($line['message']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>

itm_script_output_end();
