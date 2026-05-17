<?php
/**
 * Audit database.sql for tenant unique-key policy.
 *
 * Scope: `name` when present, else 3rd column after `id` + `company_id`.
 * Pass: PRIMARY + UNIQUE starting with (`company_id`, scope_column); exactly 2 uniques.
 *
 * Browser: open while logged in (read-only audit; results on load).
 * CLI: php scripts/check_database_sql_company_name_uniques.php
 */

declare(strict_types=1);

$itmIsCli = PHP_SAPI === 'cli';

if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

/**
 * @param mixed $value
 */
function itm_db_sql_unique_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$sqlPath = dirname(__DIR__) . '/database.sql';

if ($itmIsCli) {
    require_once dirname(__DIR__) . '/config/config.php';
    require_once dirname(__DIR__) . '/includes/database_sql_unique_audit.php';

    $result = itm_database_sql_unique_audit_run($sqlPath);
    fwrite(STDOUT, 'File: ' . $result['sql_path'] . PHP_EOL);
    fwrite(STDOUT, 'Required unique count: ' . $result['required_unique_count'] . PHP_EOL);
    fwrite(STDOUT, 'Tables parsed: ' . $result['summary']['tables'] . PHP_EOL);
    fwrite(STDOUT, 'Pass: ' . $result['summary']['pass'] . PHP_EOL);
    fwrite(STDOUT, 'Fail: ' . $result['summary']['fail'] . PHP_EOL);
    fwrite(STDOUT, 'Skip: ' . $result['summary']['skip'] . PHP_EOL . PHP_EOL);

    foreach ($result['lines'] as $line) {
        $flag = strtoupper((string) $line['status']);
        $scope = (string) ($line['scope_column'] ?? '');
        $scopeSuffix = $scope !== '' ? ' [scope=' . $scope . ']' : '';
        fwrite(STDOUT, '[' . $flag . '] ' . $line['table'] . $scopeSuffix . ' — ' . $line['message'] . PHP_EOL);
        if ($line['alter_sql'] !== '') {
            fwrite(STDOUT, '       Suggested: ' . $line['alter_sql'] . PHP_EOL);
        }
    }

    exit($result['summary']['fail'] > 0 ? 1 : 0);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/database_sql_unique_audit.php';

if ($company_id <= 0) {
    http_response_code(401);
    exit('Login required. Sign in to the app, then open this script again.');
}

$baseUrl = defined('BASE_URL') ? (string) BASE_URL : '../';
$scriptSelf = $baseUrl . 'scripts/check_database_sql_company_name_uniques.php';
$result = itm_database_sql_unique_audit_run($sqlPath);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $result = itm_database_sql_unique_audit_run($sqlPath);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>database.sql company+name unique audit</title>
    <link rel="stylesheet" href="<?= itm_db_sql_unique_escape($baseUrl . 'css/styles.css'); ?>">
    <style>
        .itm-dsu-wrap { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .itm-dsu-card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d0d7de); border-radius: 8px; margin-bottom: 16px; padding: 16px; }
        .itm-dsu-muted { color: var(--text-muted, #57606a); line-height: 1.5; }
        .itm-dsu-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .itm-dsu-table th, .itm-dsu-table td { border: 1px solid var(--border-color, #d0d7de); padding: 8px; text-align: left; vertical-align: top; }
        .itm-dsu-table th { background: var(--table-header-bg, #f6f8fa); }
        .itm-dsu-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.78rem; font-weight: 600; }
        .itm-dsu-badge-pass { background: #dafbe1; color: #116329; }
        .itm-dsu-badge-fail { background: #ffebe9; color: #cf222e; }
        .itm-dsu-badge-skip { background: #f6f8fa; color: #57606a; }
        .itm-dsu-summary span { display: inline-block; margin: 0 8px 8px 0; padding: 6px 10px; border-radius: 6px; background: var(--table-header-bg, #f6f8fa); border: 1px solid var(--border-color, #d0d7de); }
        .itm-dsu-table code { font-size: 0.85rem; word-break: break-word; }
        .itm-dsu-row-skip td { color: var(--text-muted, #57606a); }
        .itm-dsu-dash { color: var(--text-muted, #57606a); }
    </style>
</head>
<body>
<div class="itm-dsu-wrap">
    <div class="itm-dsu-card">
        <h1>database.sql — company + name unique audit</h1>
        <p class="itm-dsu-muted">
            Parses <code>database.sql</code> for every table with <code>company_id</code>.
            Scope column: <code>name</code> when present, otherwise the <strong>3rd column</strong> after <code>id</code> and <code>company_id</code>
            (e.g. <code>annual_budget_id</code> on <code>monthly_budgets</code>).
            <strong>Pass (true):</strong> exactly <strong>2</strong> uniques — <code>PRIMARY KEY</code> + a <code>UNIQUE</code> that starts with
            <code>(company_id, scope_column)</code> (wider composites are OK).
            <strong>Fail (false):</strong> only one unique, missing scope unique, or extra uniques.
            Only tables <em>without</em> <code>company_id</code> are skipped.
        </p>
        <p class="itm-dsu-muted">
            Example: <code>ALTER TABLE `location_types` ADD UNIQUE KEY `uq_location_types_company_name` (`company_id`, `name`);</code>
            · <a href="<?= itm_db_sql_unique_escape($baseUrl . 'scripts/index.html'); ?>">Scripts index</a>
        </p>
    </div>

    <div class="itm-dsu-card">
        <h2>Run audit</h2>
        <form method="post" action="<?= itm_db_sql_unique_escape($scriptSelf); ?>">
            <input type="hidden" name="csrf_token" value="<?= itm_db_sql_unique_escape(itm_get_csrf_token()); ?>">
            <button type="submit" class="btn-primary">Scan database.sql</button>
        </form>
        <p class="itm-dsu-muted" style="margin-top:12px;">
            CLI: <code>php scripts/check_database_sql_company_name_uniques.php</code>
        </p>
    </div>

    <div class="itm-dsu-card">
        <h2>Summary</h2>
        <div class="itm-dsu-summary">
            <span>File: <strong><?= itm_db_sql_unique_escape(basename($result['sql_path'])); ?></strong></span>
            <span>Tables: <strong><?= (int) $result['summary']['tables']; ?></strong></span>
            <span>Pass: <strong><?= (int) $result['summary']['pass']; ?></strong></span>
            <span>Fail: <strong><?= (int) $result['summary']['fail']; ?></strong></span>
            <span>Skipped: <strong><?= (int) $result['summary']['skip']; ?></strong></span>
        </div>
        <?php if ($result['summary']['fail'] === 0): ?>
            <p class="itm-dsu-muted">All tenant-scoped tables have the required two uniques.</p>
        <?php else: ?>
            <p class="itm-dsu-muted">Some tables need a scope <code>UNIQUE</code> or have extra unique keys. Skipped rows have no <code>company_id</code>.</p>
        <?php endif; ?>
    </div>

    <div class="itm-dsu-card">
        <h2>Results (all <?= (int) $result['summary']['tables']; ?> tables)</h2>
        <table class="itm-dsu-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Table</th>
                    <th>Uniques</th>
                    <th>Scope column</th>
                    <th>Scope UNIQUE</th>
                    <th>Notes</th>
                    <th>Suggested ALTER</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['lines'] as $line): ?>
                    <?php
                    $status = (string) $line['status'];
                    if ($status === 'pass') {
                        $badgeClass = 'itm-dsu-badge-pass';
                        $statusLabel = 'pass';
                    } elseif ($status === 'skip') {
                        $badgeClass = 'itm-dsu-badge-skip';
                        $statusLabel = 'skip';
                    } else {
                        $badgeClass = 'itm-dsu-badge-fail';
                        $statusLabel = 'fail';
                    }
                    $rowClass = $status === 'skip' ? 'itm-dsu-row-skip' : '';
                    $scopeColumn = (string) ($line['scope_column'] ?? '');
                    $scopeUnique = (string) ($line['scope_unique'] ?? '');
                    ?>
                <tr class="<?= itm_db_sql_unique_escape($rowClass); ?>">
                    <td><span class="itm-dsu-badge <?= $badgeClass; ?>"><?= itm_db_sql_unique_escape($statusLabel); ?></span></td>
                    <td><code><?= itm_db_sql_unique_escape($line['table']); ?></code></td>
                    <td><?= (int) $line['unique_count']; ?></td>
                    <td><?php if ($scopeColumn !== ''): ?><code><?= itm_db_sql_unique_escape($scopeColumn); ?></code><?php else: ?><span class="itm-dsu-dash">—</span><?php endif; ?></td>
                    <td><?php if ($scopeUnique !== ''): ?><code><?= itm_db_sql_unique_escape($scopeUnique); ?></code><?php else: ?><span class="itm-dsu-dash">—</span><?php endif; ?></td>
                    <td><?= itm_db_sql_unique_escape($line['message']); ?></td>
                    <td><?php if ($line['alter_sql'] !== ''): ?><code><?= itm_db_sql_unique_escape($line['alter_sql']); ?></code><?php else: ?><span class="itm-dsu-dash">—</span><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
