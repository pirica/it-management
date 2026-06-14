<?php
/**
 * Run ANALYZE TABLE on every base table in the active schema.
 *
 * Why: phpMyAdmin "Analyze table" can stop on the first failing table,
 * which hides which table caused the failure and why.
 *
 * Browser: open scripts/analyze_database_health.php (login required).
 * CLI: php scripts/analyze_database_health.php
 */

declare(strict_types=1);

$itmIsCli = PHP_SAPI === 'cli';

if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

/**
 * @param mixed $value
 */
function itm_analyze_database_health_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * @param array<string, mixed> $row
 * @return mixed
 */
function itm_analyze_database_health_row_value(array $row, string $key)
{
    if (array_key_exists($key, $row)) {
        return $row[$key];
    }
    $upper = strtoupper($key);
    if (array_key_exists($upper, $row)) {
        return $row[$upper];
    }
    $lower = strtolower($key);
    if (array_key_exists($lower, $row)) {
        return $row[$lower];
    }

    return null;
}

/**
 * @return array{
 *     lines: array<int, array{level: string, table: string, message: string, hint: string}>,
 *     ok_count: int,
 *     warning_count: int,
 *     error_count: int,
 *     table_count: int,
 *     schema: string
 * }
 */
function itm_analyze_database_health_run(mysqli $conn): array
{
    $lines = [];
    $okCount = 0;
    $warningCount = 0;
    $errorCount = 0;

    $schemaName = mysqli_real_escape_string($conn, (string) DB_NAME);
    $listSql = "
        SELECT `TABLE_NAME` AS `table_name`
        FROM `information_schema`.`tables`
        WHERE `table_schema` = '{$schemaName}'
          AND `table_type` = 'BASE TABLE'
        ORDER BY `TABLE_NAME` ASC
    ";

    $tableResult = itm_run_query($conn, $listSql);
    if (!$tableResult) {
        return [
            'lines' => [['level' => 'error', 'table' => '', 'message' => 'Unable to enumerate database tables.', 'hint' => '']],
            'ok_count' => 0,
            'warning_count' => 0,
            'error_count' => 1,
            'table_count' => 0,
            'schema' => (string) DB_NAME,
        ];
    }

    $tables = [];
    while ($tableRow = mysqli_fetch_assoc($tableResult)) {
        $tableName = (string) (itm_analyze_database_health_row_value($tableRow, 'table_name') ?? '');
        if ($tableName !== '' && itm_is_safe_identifier($tableName)) {
            $tables[] = $tableName;
        }
    }
    mysqli_free_result($tableResult);

    if (!$tables) {
        return [
            'lines' => [['level' => 'info', 'table' => '', 'message' => 'No base tables were found in this schema.', 'hint' => '']],
            'ok_count' => 0,
            'warning_count' => 0,
            'error_count' => 0,
            'table_count' => 0,
            'schema' => (string) DB_NAME,
        ];
    }

    foreach ($tables as $tableName) {
        $sql = "ANALYZE TABLE `{$tableName}`";
        $analyzeResult = itm_run_query($conn, $sql);

        if (!$analyzeResult) {
            $errorCount++;
            $lines[] = [
                'level' => 'error',
                'table' => $tableName,
                'message' => 'Query execution failed.',
                'hint' => '',
            ];
            continue;
        }

        $tableHadIssue = false;
        while ($analyzeRow = mysqli_fetch_assoc($analyzeResult)) {
            $msgType = strtolower((string) (itm_analyze_database_health_row_value($analyzeRow, 'Msg_type') ?? 'status'));
            $msgText = (string) (itm_analyze_database_health_row_value($analyzeRow, 'Msg_text') ?? '');

            if ($msgType === 'error') {
                $errorCount++;
                $tableHadIssue = true;
                $hint = '';
                if (stripos($msgText, "doesn't exist in engine") !== false) {
                    $hint = 'php scripts/repair_table_from_schema.php --table=' . $tableName;
                }
                $lines[] = [
                    'level' => 'error',
                    'table' => $tableName,
                    'message' => $msgText,
                    'hint' => $hint,
                ];
            } elseif ($msgType === 'warning') {
                $warningCount++;
                $tableHadIssue = true;
                $lines[] = [
                    'level' => 'warning',
                    'table' => $tableName,
                    'message' => $msgText,
                    'hint' => '',
                ];
            }
        }

        mysqli_free_result($analyzeResult);

        if (!$tableHadIssue) {
            $okCount++;
            $lines[] = [
                'level' => 'ok',
                'table' => $tableName,
                'message' => 'Table analyzed successfully.',
                'hint' => '',
            ];
        }
    }

    return [
        'lines' => $lines,
        'ok_count' => $okCount,
        'warning_count' => $warningCount,
        'error_count' => $errorCount,
        'table_count' => count($tables),
        'schema' => (string) DB_NAME,
    ];
}

/**
 * @param array{
 *     lines: array<int, array{level: string, table: string, message: string, hint: string}>,
 *     ok_count: int,
 *     warning_count: int,
 *     error_count: int,
 *     table_count: int,
 *     schema: string
 * } $result
 */
function itm_analyze_database_health_print_cli(array $result): void
{
    if ($result['table_count'] === 0 && $result['error_count'] === 0) {
        fwrite(STDOUT, "No base tables were found in schema '" . $result['schema'] . "'.\n");
        return;
    }

    fwrite(
        STDOUT,
        'Running ANALYZE TABLE on ' . $result['table_count'] . " base table(s) in schema '" . $result['schema'] . "'...\n\n"
    );

    foreach ($result['lines'] as $line) {
        if ($line['level'] === 'ok') {
            fwrite(STDOUT, "[OK   ] {$line['table']}\n");
            continue;
        }
        if ($line['level'] === 'warning') {
            fwrite(STDOUT, "[WARN ] {$line['table']}: {$line['message']}\n");
            continue;
        }
        if ($line['level'] === 'error') {
            fwrite(STDOUT, "[ERROR] {$line['table']}: {$line['message']}\n");
            if ($line['hint'] !== '') {
                fwrite(STDOUT, "        Hint: {$line['hint']}\n");
            }
            continue;
        }
        fwrite(STDOUT, '[' . strtoupper($line['level']) . "] {$line['message']}\n");
    }

    fwrite(
        STDOUT,
        "\nSummary: OK={$result['ok_count']}, WARN={$result['warning_count']}, ERROR={$result['error_count']}\n"
    );
}

if ($itmIsCli) {
    try {
        require_once dirname(__DIR__) . '/config/config.php';
    } catch (Throwable $e) {
        fwrite(STDERR, 'Unable to bootstrap application config/db connection: ' . $e->getMessage() . "\n");
        fwrite(STDERR, "Hint: PATH php may be PHP 7.0 without mysqli. Use Laragon PHP 7.4:\n");
        fwrite(STDERR, "  C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\bin\\php\\php-7.4.33-nts-Win32-vc15-x64\\php.exe scripts\\analyze_database_health.php\n");
        exit(1);
    }

    if (!isset($conn) || !($conn instanceof mysqli) || mysqli_connect_errno()) {
        fwrite(STDERR, "Database connection failed.\n");
        exit(1);
    }

    $result = itm_analyze_database_health_run($conn);
    itm_analyze_database_health_print_cli($result);
    exit($result['error_count'] > 0 ? 1 : 0);
}

require_once dirname(__DIR__) . '/config/config.php';

if (!isset($conn) || !($conn instanceof mysqli) || mysqli_connect_errno()) {
    http_response_code(500);
    exit('Database connection failed.');
}

$baseUrl = defined('BASE_URL') ? (string) BASE_URL : '../';
$scriptSelf = $baseUrl . 'scripts/analyze_database_health.php';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $result = itm_analyze_database_health_run($conn);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analyze database tables</title>
    <link rel="stylesheet" href="<?= itm_analyze_database_health_escape($baseUrl . 'css/styles.css'); ?>">
    <style>
        .itm-analyze-wrap { max-width: 1100px; margin: 0 auto; padding: 20px; }
        .itm-analyze-card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d0d7de); border-radius: 8px; margin-bottom: 16px; padding: 16px; }
        .itm-analyze-muted { color: var(--text-muted, #57606a); line-height: 1.5; }
        .itm-analyze-table { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
        .itm-analyze-table th, .itm-analyze-table td { border: 1px solid var(--border-color, #d0d7de); padding: 8px; text-align: left; vertical-align: top; }
        .itm-analyze-table th { background: var(--table-header-bg, #f6f8fa); }
        .itm-analyze-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.78rem; font-weight: 600; }
        .itm-analyze-badge-ok { background: #dafbe1; color: #116329; }
        .itm-analyze-badge-warn { background: #fff8c5; color: #9a6700; }
        .itm-analyze-badge-error { background: #ffebe9; color: #cf222e; }
        .itm-analyze-badge-info { background: #f6f8fa; color: #57606a; }
        .itm-analyze-alert { padding: 10px 12px; border-radius: 6px; margin-bottom: 12px; }
        .itm-analyze-alert-success { background: #dafbe1; border: 1px solid #4ac26b; color: #116329; }
        .itm-analyze-alert-danger { background: #ffebe9; border: 1px solid #ff8182; color: #82071e; }
        .itm-analyze-summary { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
        .itm-analyze-summary span { padding: 6px 10px; border-radius: 6px; background: var(--table-header-bg, #f6f8fa); border: 1px solid var(--border-color, #d0d7de); }
    </style>
</head>
<body>
<?php
require_once __DIR__ . '/lib/script_browser_nav.php';
?>
<div class="itm-analyze-wrap">
<?php itm_script_browser_nav_echo($baseUrl); ?>
    <div class="itm-analyze-card">
        <h1>Analyze database tables</h1>
        <p class="itm-analyze-muted">
            Runs <code>ANALYZE TABLE</code> on every base table in <code><?= itm_analyze_database_health_escape(DB_NAME); ?></code>
            and lists per-table results (unlike phpMyAdmin, which can stop on the first error).
        </p>
        <p class="itm-analyze-muted">
            CLI optional: <code>php scripts/analyze_database_health.php</code>
        </p>
    </div>

    <div class="itm-analyze-card">
        <h2>Run analyze</h2>
        <form method="post" action="<?= itm_analyze_database_health_escape($scriptSelf); ?>">
            <input type="hidden" name="csrf_token" value="<?= itm_analyze_database_health_escape(itm_get_csrf_token()); ?>">
            <button type="submit" class="btn-primary">Run ANALYZE TABLE on all tables</button>
        </form>
    </div>

    <?php if ($result !== null): ?>
    <div class="itm-analyze-card">
        <h2>Results</h2>
        <div class="itm-analyze-summary">
            <span>Schema: <strong><?= itm_analyze_database_health_escape($result['schema']); ?></strong></span>
            <span>Tables: <strong><?= (int) $result['table_count']; ?></strong></span>
            <span>OK: <strong><?= (int) $result['ok_count']; ?></strong></span>
            <span>Warnings: <strong><?= (int) $result['warning_count']; ?></strong></span>
            <span>Errors: <strong><?= (int) $result['error_count']; ?></strong></span>
        </div>
        <?php if ($result['error_count'] === 0 && $result['table_count'] > 0): ?>
            <div class="itm-analyze-alert itm-analyze-alert-success">All analyzed tables completed without errors.</div>
        <?php elseif ($result['error_count'] > 0): ?>
            <div class="itm-analyze-alert itm-analyze-alert-danger">Some tables reported errors. See repair hints below.</div>
        <?php endif; ?>
        <table class="itm-analyze-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Table</th>
                    <th>Message</th>
                    <th>Hint</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['lines'] as $line): ?>
                    <?php
                    $badgeClass = 'itm-analyze-badge-info';
                    if ($line['level'] === 'ok') {
                        $badgeClass = 'itm-analyze-badge-ok';
                    } elseif ($line['level'] === 'warning') {
                        $badgeClass = 'itm-analyze-badge-warn';
                    } elseif ($line['level'] === 'error') {
                        $badgeClass = 'itm-analyze-badge-error';
                    }
                    ?>
                <tr>
                    <td><span class="itm-analyze-badge <?= $badgeClass; ?>"><?= itm_analyze_database_health_escape(strtoupper($line['level'])); ?></span></td>
                    <td><?= itm_script_format_table_link((string)$line['table']); ?></td>
                    <td><?= itm_analyze_database_health_escape($line['message']); ?></td>
                    <td><?php if ($line['hint'] !== ''): ?><code><?= itm_analyze_database_health_escape($line['hint']); ?></code><?php else: ?>—<?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
