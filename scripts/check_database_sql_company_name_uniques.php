<?php
/**
 * Audit database.sql for tenant unique-key policy.
 *
 * Scope column (per table): `name` when present, else the 3rd column after `id` + `company_id`
 * (e.g. `display_name` on `floor_plans`). `employee_companies` uses `employee_id`.
 *
 * Pass: exactly 2 uniques — PRIMARY KEY (`id`) plus one business UNIQUE led by `company_id`
 * and the scope column (wider composites allowed, e.g. monthly_budgets adds `month`).
 *
 * Floor plan exceptions (middle column must be the intended FK, not any IFNULL):
 * - floor_plan_folders: (`company_id`, IFNULL(`parent_folder_id`, 0), `name`)
 * - floor_plans & explorer: (`company_id`, IFNULL(`folder_id`, 0), `display_name`)
 *   Do not use UNIQUE (`company_id`, `folder_id`) alone — that allows only one file per folder.
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
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();

$nl = itm_script_output_nl();

    require_once dirname(__DIR__) . '/includes/database_sql_unique_audit.php';

    $result = itm_database_sql_unique_audit_run($sqlPath);
    fwrite(STDOUT, 'File: ' . $result['sql_path'] . PHP_EOL);
    fwrite(STDOUT, 'Required unique count: ' . $result['required_unique_count'] . PHP_EOL);
    fwrite(STDOUT, 'Tables parsed: ' . $result['summary']['tables'] . PHP_EOL);
    fwrite(STDOUT, 'Pass: ' . $result['summary']['pass'] . PHP_EOL);
    fwrite(STDOUT, 'Fail: ' . $result['summary']['fail'] . PHP_EOL);
    fwrite(STDOUT, 'Skip: ' . $result['summary']['skip'] . PHP_EOL . PHP_EOL);
    fwrite(STDOUT, 'Scope rules: `name` when present, else 3rd column after id+company_id.' . PHP_EOL);
    fwrite(STDOUT, 'Floor plans: folders = company + IFNULL(parent_folder_id,0) + name;' . PHP_EOL);
    fwrite(STDOUT, '  files = company + IFNULL(folder_id,0) + display_name (not company+folder_id only).' . PHP_EOL . PHP_EOL);

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
require_once __DIR__ . '/lib/script_browser_nav.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>database.sql tenant unique-key audit</title>
    <link rel="stylesheet" href="<?= itm_db_sql_unique_escape($baseUrl . 'css/styles.css'); ?>">
    <style>
        .itm-dsu-wrap { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .itm-dsu-card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d0d7de); border-radius: 8px; margin-bottom: 16px; padding: 16px; }
        .itm-dsu-muted { color: var(--text-muted, #57606a); line-height: 1.5; }
        .itm-dsu-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .itm-dsu-table { width: max-content; min-width: 100%; border-collapse: collapse; font-size: 0.9rem; table-layout: auto; }
        .itm-dsu-table th, .itm-dsu-table td { border: 1px solid var(--border-color, #d0d7de); padding: 8px; text-align: left; vertical-align: top; }
        .itm-dsu-table thead th { background: var(--table-header-bg, #f6f8fa); white-space: nowrap !important; }
        .itm-dsu-table thead th.itm-dsu-col-compact,
        .itm-dsu-table tbody td.itm-dsu-col-compact { white-space: nowrap !important; width: 1%; }
        .itm-dsu-table tbody td.itm-dsu-col-compact code { white-space: nowrap; }
        .itm-dsu-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.78rem; font-weight: 600; }
        .itm-dsu-badge-pass { background: #dafbe1; color: #116329; }
        .itm-dsu-badge-fail { background: #ffebe9; color: #cf222e; }
        .itm-dsu-badge-skip { background: #f6f8fa; color: #57606a; }
        .itm-dsu-summary span { display: inline-block; margin: 0 8px 8px 0; padding: 6px 10px; border-radius: 6px; background: var(--table-header-bg, #f6f8fa); border: 1px solid var(--border-color, #d0d7de); }
        .itm-dsu-table code { font-size: 0.85rem; word-break: break-word; }
        .itm-dsu-row-skip td { color: var(--text-muted, #57606a); }
        .itm-dsu-dash { color: var(--text-muted, #57606a); }
        .itm-dsu-th-sort { cursor: pointer; user-select: none; white-space: nowrap; }
        .itm-dsu-th-sort:hover { background: var(--table-row-hover-bg, #eef1f4); }
        .itm-dsu-th-inner { display: inline-flex; flex-wrap: nowrap; align-items: center; gap: 4px; white-space: nowrap !important; }
        .itm-dsu-th-label { white-space: nowrap; }
        .itm-dsu-sort-indicator { flex: 0 0 auto; min-width: 1.1em; color: var(--text-muted, #57606a); font-size: 0.75rem; line-height: 1; }
    </style>
</head>
<body>
<div class="itm-dsu-wrap">
<?php itm_script_browser_nav_echo($baseUrl); ?>
    <div class="itm-dsu-card">
        <h1>database.sql — tenant unique-key audit</h1>
        <p class="itm-dsu-muted">
            Parses <code>database.sql</code> for every table with <code>company_id</code>.
            <strong>Scope column:</strong> <code>name</code> when present, otherwise the <strong>3rd column</strong> after
            <code>id</code> and <code>company_id</code> (e.g. <code>annual_budget_id</code> on <code>monthly_budgets</code>,
            <code>display_name</code> on <code>floor_plans</code>). <code>employee_companies</code> uses <code>employee_id</code>.
        </p>
        <p class="itm-dsu-muted">
            <strong>Pass:</strong> exactly <strong>2</strong> uniques — <code>PRIMARY KEY</code> (<code>id</code>) plus one business
            <code>UNIQUE</code> led by <code>(company_id, scope_column)</code> (wider composites are OK).<br>
            <strong>Fail:</strong> missing scope unique, only one unique, or extra uniques.<br>
            Tables without <code>company_id</code> (and exempt tables such as <code>audit_logs</code>) are skipped.
        </p>
        <p class="itm-dsu-muted">
            <strong>Explorer & Floor plan gallery (required shape):</strong>
            <code>floor_plan_folders</code> —
            <code>(company_id, IFNULL(parent_folder_id, 0), name)</code>;
            <code>floor_plans & explorer</code> —
            <code>(company_id, IFNULL(folder_id, 0), display_name)</code>.<br>
            The audit accepts only <code>parent_folder_id</code> / <code>folder_id</code> (plain or that exact
            <code>IFNULL(..., 0)</code>), not other expressions such as <code>IFNULL(created_by_employee_id, 0)</code>.<br>
            Do <strong>not</strong> add <code>UNIQUE (company_id, folder_id)</code> on <code>floor_plans</code> — that allows only one file per folder.
        </p>
        <p class="itm-dsu-muted">
            Example: <code>ALTER TABLE `location_types` ADD UNIQUE KEY `uq_location_types_company_name` (`company_id`, `name`);</code>
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
        <div class="itm-dsu-table-wrap">
        <table class="itm-dsu-table" id="itm-dsu-results-table">
            <thead>
                <tr>
                    <th class="itm-dsu-th-sort itm-dsu-col-compact" data-sort-type="status" scope="col" aria-sort="none"><span class="itm-dsu-th-inner"><span class="itm-dsu-th-label">Status</span><span class="itm-dsu-sort-indicator" aria-hidden="true"></span></span></th>
                    <th class="itm-dsu-th-sort itm-dsu-col-compact" data-sort-type="text" scope="col" aria-sort="none"><span class="itm-dsu-th-inner"><span class="itm-dsu-th-label">Table</span><span class="itm-dsu-sort-indicator" aria-hidden="true"></span></span></th>
                    <th class="itm-dsu-th-sort itm-dsu-col-compact" data-sort-type="number" scope="col" aria-sort="none"><span class="itm-dsu-th-inner"><span class="itm-dsu-th-label">Uniques</span><span class="itm-dsu-sort-indicator" aria-hidden="true"></span></span></th>
                    <th class="itm-dsu-th-sort itm-dsu-col-compact" data-sort-type="text" scope="col" aria-sort="none"><span class="itm-dsu-th-inner"><span class="itm-dsu-th-label">Scope&nbsp;column</span><span class="itm-dsu-sort-indicator" aria-hidden="true"></span></span></th>
                    <th class="itm-dsu-th-sort" data-sort-type="text" scope="col" aria-sort="none"><span class="itm-dsu-th-inner"><span class="itm-dsu-th-label">Scope&nbsp;UNIQUE</span><span class="itm-dsu-sort-indicator" aria-hidden="true"></span></span></th>
                    <th class="itm-dsu-th-sort" data-sort-type="text" scope="col" aria-sort="none"><span class="itm-dsu-th-inner"><span class="itm-dsu-th-label">Notes</span><span class="itm-dsu-sort-indicator" aria-hidden="true"></span></span></th>
                    <th class="itm-dsu-th-sort" data-sort-type="text" scope="col" aria-sort="none"><span class="itm-dsu-th-inner"><span class="itm-dsu-th-label">Suggested&nbsp;ALTER</span><span class="itm-dsu-sort-indicator" aria-hidden="true"></span></span></th>
                </tr>
            </thead>
            <tbody id="itm-dsu-results-body">
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
                <tr class="<?= itm_db_sql_unique_escape($rowClass); ?>" data-status="<?= itm_db_sql_unique_escape($statusLabel); ?>">
                    <td class="itm-dsu-col-compact" data-sort-value="<?= itm_db_sql_unique_escape($statusLabel); ?>"><span class="itm-dsu-badge <?= $badgeClass; ?>"><?= itm_db_sql_unique_escape($statusLabel); ?></span></td>
                    <td class="itm-dsu-col-compact" data-sort-value="<?= itm_db_sql_unique_escape($line['table']); ?>"><?= itm_script_format_table_link((string)$line['table']); ?></td>
                    <td class="itm-dsu-col-compact" data-sort-value="<?= (int) $line['unique_count']; ?>"><?= (int) $line['unique_count']; ?></td>
                    <td class="itm-dsu-col-compact" data-sort-value="<?= itm_db_sql_unique_escape($scopeColumn !== '' ? $scopeColumn : ''); ?>"><?php if ($scopeColumn !== ''): ?><code><?= itm_db_sql_unique_escape($scopeColumn); ?></code><?php else: ?><span class="itm-dsu-dash">—</span><?php endif; ?></td>
                    <td data-sort-value="<?= itm_db_sql_unique_escape($scopeUnique !== '' ? $scopeUnique : ''); ?>"><?php if ($scopeUnique !== ''): ?><code><?= itm_db_sql_unique_escape($scopeUnique); ?></code><?php else: ?><span class="itm-dsu-dash">—</span><?php endif; ?></td>
                    <td data-sort-value="<?= itm_db_sql_unique_escape($line['message']); ?>"><?= itm_db_sql_unique_escape($line['message']); ?></td>
                    <td data-sort-value="<?= itm_db_sql_unique_escape($line['alter_sql'] !== '' ? $line['alter_sql'] : ''); ?>"><?php if ($line['alter_sql'] !== ''): ?><code><?= itm_db_sql_unique_escape($line['alter_sql']); ?></code><?php else: ?><span class="itm-dsu-dash">—</span><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<script>
(function () {
    var table = document.getElementById('itm-dsu-results-table');
    var tbody = document.getElementById('itm-dsu-results-body');
    if (!table || !tbody) {
        return;
    }

    var headers = table.querySelectorAll('thead .itm-dsu-th-sort');
    var statusRank = { fail: 0, skip: 1, pass: 2 };
    var activeHeader = null;
    var activeDir = 'asc';

    function cellSortValue(row, colIndex) {
        var cell = row.cells[colIndex];
        if (!cell) {
            return '';
        }
        if (cell.hasAttribute('data-sort-value')) {
            return cell.getAttribute('data-sort-value') || '';
        }
        return (cell.textContent || '').trim();
    }

    function compareRows(rowA, rowB, colIndex, sortType, dir) {
        var a = cellSortValue(rowA, colIndex);
        var b = cellSortValue(rowB, colIndex);
        var cmp = 0;

        if (sortType === 'number') {
            cmp = (parseFloat(a) || 0) - (parseFloat(b) || 0);
        } else if (sortType === 'status') {
            cmp = (statusRank[a] !== undefined ? statusRank[a] : 99) - (statusRank[b] !== undefined ? statusRank[b] : 99);
            if (cmp === 0) {
                cmp = String(a).localeCompare(String(b), undefined, { sensitivity: 'base' });
            }
        } else {
            cmp = String(a).localeCompare(String(b), undefined, { sensitivity: 'base', numeric: true });
        }

        return dir === 'desc' ? -cmp : cmp;
    }

    function updateIndicators() {
        headers.forEach(function (th) {
            var indicator = th.querySelector('.itm-dsu-sort-indicator');
            if (!indicator) {
                return;
            }
            if (th === activeHeader) {
                indicator.textContent = activeDir === 'asc' ? '\u25B2' : '\u25BC';
                th.setAttribute('aria-sort', activeDir === 'asc' ? 'ascending' : 'descending');
            } else {
                indicator.textContent = '';
                th.setAttribute('aria-sort', 'none');
            }
        });
    }

    function sortByHeader(th) {
        var colIndex = Array.prototype.indexOf.call(th.parentNode.children, th);
        var sortType = th.getAttribute('data-sort-type') || 'text';

        if (activeHeader === th) {
            activeDir = activeDir === 'asc' ? 'desc' : 'asc';
        } else {
            activeHeader = th;
            activeDir = sortType === 'number' ? 'desc' : 'asc';
        }

        var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
        rows.sort(function (rowA, rowB) {
            return compareRows(rowA, rowB, colIndex, sortType, activeDir);
        });
        rows.forEach(function (row) {
            tbody.appendChild(row);
        });
        updateIndicators();
    }

    headers.forEach(function (th) {
        th.addEventListener('click', function () {
            sortByHeader(th);
        });
        th.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                sortByHeader(th);
            }
        });
        th.setAttribute('tabindex', '0');
        th.setAttribute('role', 'button');
    });

    var defaultHeader = headers[0];
    if (defaultHeader) {
        sortByHeader(defaultHeader);
    }
})();
</script>
</body>
</html>
