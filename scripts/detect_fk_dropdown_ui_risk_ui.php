<?php
/**
 * Browser UI for FK dropdown risk detection (no CLI required).
 *
 * Why: Operators need the same scans as detect_fk_dropdown_ui_risk.php with dropdowns,
 * not terminal commands.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'detect_fk_dropdown_ui_risk_lib.php';

/**
 * @param mixed $value
 */
function itm_fk_risk_ui_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$scanScope = isset($_GET['scan_scope']) ? (string)$_GET['scan_scope'] : 'full';
if (!in_array($scanScope, ['full', 'data_only', 'code_only'], true)) {
    $scanScope = 'full';
}

$companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
$riskFilter = isset($_GET['risk_filter']) ? (string)$_GET['risk_filter'] : 'all';
$outputFormat = isset($_GET['output_format']) ? (string)$_GET['output_format'] : 'table';
if (!in_array($outputFormat, ['table', 'json'], true)) {
    $outputFormat = 'table';
}

$runScan = isset($_GET['run']) && (string)$_GET['run'] === '1';

$companies = [];
$companyRes = mysqli_query($conn, 'SELECT id, name FROM companies ORDER BY id ASC');
if ($companyRes) {
    while ($row = mysqli_fetch_assoc($companyRes)) {
        $companies[] = [
            'id' => (int)($row['id'] ?? 0),
            'name' => (string)($row['name'] ?? ''),
        ];
    }
}

$report = null;
$dataIssues = [];
$codeIssues = [];

if ($runScan) {
    $runOptions = [
        'scan_scope' => $scanScope,
        'company' => $companyId,
        'code_only' => $scanScope === 'code_only',
        'data_only' => $scanScope === 'data_only',
    ];
    $report = itm_detect_fk_dropdown_ui_risk_run(ROOT_PATH, $conn, $runOptions);
    $dataIssues = itm_detect_fk_filter_issues_by_risk($report['data_issues'] ?? [], $riskFilter);
    $codeIssues = itm_detect_fk_filter_issues_by_risk($report['code_issues'] ?? [], $riskFilter);
}

$baseUrl = defined('BASE_URL') ? (string)BASE_URL : '../';
$scriptSelf = $baseUrl . 'scripts/detect_fk_dropdown_ui_risk_ui.php';

$riskFilterOptions = [
    'all' => 'All risk types',
    'duplicate_dropdown_risk' => 'duplicate_dropdown_risk (data)',
    'cross_tenant_fk' => 'cross_tenant_fk (data)',
    'wrong_or_missing_tenant_row' => 'wrong_or_missing_tenant_row (data)',
    'duplicate_dropdown_code_risk' => 'duplicate_dropdown_code_risk (code)',
    'append_without_tenant_resolve' => 'append_without_tenant_resolve (code)',
];

$scanScopeOptions = [
    'full' => 'Full scan (database + modules)',
    'data_only' => 'Database only',
    'code_only' => 'Module code only',
];

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FK Dropdown UI Risk Scanner</title>
    <link rel="stylesheet" href="<?= itm_fk_risk_ui_escape($baseUrl . 'css/styles.css'); ?>">
    <style>
        .fk-risk-wrap { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .fk-risk-card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d0d7de); border-radius: 8px; margin-bottom: 16px; padding: 16px; }
        .fk-risk-muted { color: var(--text-muted, #57606a); }
        .fk-risk-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px 16px; align-items: end; }
        .fk-risk-form label { display: block; font-weight: 600; margin-bottom: 4px; }
        .fk-risk-form select { width: 100%; padding: 8px; border-radius: 6px; border: 1px solid var(--border-color, #d0d7de); }
        .fk-risk-table { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
        .fk-risk-table th, .fk-risk-table td { border: 1px solid var(--border-color, #d0d7de); padding: 8px; text-align: left; vertical-align: top; }
        .fk-risk-table th { background: var(--table-header-bg, #f6f8fa); }
        .fk-risk-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
        .fk-risk-badge-danger { background: #ffebe9; color: #cf222e; }
        .fk-risk-badge-warn { background: #fff8c5; color: #9a6700; }
        .fk-risk-json { width: 100%; min-height: 320px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .fk-risk-summary { display: flex; flex-wrap: wrap; gap: 12px; }
        .fk-risk-summary span { padding: 6px 10px; border-radius: 6px; background: var(--table-header-bg, #f6f8fa); border: 1px solid var(--border-color, #d0d7de); }
    </style>
</head>
<body>
<div class="fk-risk-wrap">
    <div class="fk-risk-card">
        <h1>FK Dropdown UI Risk Scanner</h1>
        <p class="fk-risk-muted">Find cross-tenant FK rows and modules that append persisted FK ids without tenant resolution (duplicate select options).</p>
        <p class="fk-risk-muted">CLI equivalent: <code>php scripts/detect_fk_dropdown_ui_risk.php</code></p>
    </div>

    <div class="fk-risk-card">
        <h2>Scan options</h2>
        <form class="fk-risk-form" method="get" action="<?= itm_fk_risk_ui_escape($scriptSelf); ?>">
            <input type="hidden" name="run" value="1">
            <div>
                <label for="scan_scope">Scan mode</label>
                <select name="scan_scope" id="scan_scope">
                    <?php foreach ($scanScopeOptions as $value => $label): ?>
                        <option value="<?= itm_fk_risk_ui_escape($value); ?>"<?= $scanScope === $value ? ' selected' : ''; ?>><?= itm_fk_risk_ui_escape($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="company_id">Company (tenant)</label>
                <select name="company_id" id="company_id">
                    <option value="0"<?= $companyId === 0 ? ' selected' : ''; ?>>All companies</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= (int)$company['id']; ?>"<?= $companyId === (int)$company['id'] ? ' selected' : ''; ?>>
                            <?= itm_fk_risk_ui_escape('#' . $company['id'] . ' — ' . $company['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="risk_filter">Risk type filter</label>
                <select name="risk_filter" id="risk_filter">
                    <?php foreach ($riskFilterOptions as $value => $label): ?>
                        <option value="<?= itm_fk_risk_ui_escape($value); ?>"<?= $riskFilter === $value ? ' selected' : ''; ?>><?= itm_fk_risk_ui_escape($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="output_format">Output</label>
                <select name="output_format" id="output_format">
                    <option value="table"<?= $outputFormat === 'table' ? ' selected' : ''; ?>>HTML table</option>
                    <option value="json"<?= $outputFormat === 'json' ? ' selected' : ''; ?>>JSON (for CI / copy)</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn-primary">Run scan</button>
            </div>
        </form>
    </div>

<?php if ($runScan && $report !== null): ?>
    <?php
    $dbError = (string)($report['db_error'] ?? '');
    $summary = $report['summary'] ?? [];
    $filteredReport = $report;
    $filteredReport['data_issues'] = $dataIssues;
    $filteredReport['code_issues'] = $codeIssues;
    ?>
    <div class="fk-risk-card">
        <h2>Results</h2>
        <?php if ($dbError !== ''): ?>
            <p class="fk-risk-badge fk-risk-badge-danger"><?= itm_fk_risk_ui_escape($dbError); ?></p>
        <?php else: ?>
            <p class="fk-risk-muted">Generated: <?= itm_fk_risk_ui_escape((string)($report['generated_at'] ?? '')); ?></p>
            <div class="fk-risk-summary">
                <span>Data issues: <strong><?= count($dataIssues); ?></strong></span>
                <span>Code issues: <strong><?= count($codeIssues); ?></strong></span>
                <span>duplicate_dropdown_risk: <strong><?= (int)($summary['duplicate_dropdown_data'] ?? 0); ?></strong></span>
            </div>
            <?php if ($dataIssues === [] && $codeIssues === []): ?>
                <p><strong>[OK]</strong> No matching FK dropdown UI risks for the selected filters.</p>
            <?php else: ?>
                <p class="fk-risk-muted"><strong>[FAIL]</strong> Review findings below. <code>duplicate_dropdown_risk</code> usually means two dropdown options for the same logical FK value.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($dbError === '' && $outputFormat === 'json'): ?>
    <div class="fk-risk-card">
        <h2>JSON</h2>
        <textarea class="fk-risk-json" readonly><?= itm_fk_risk_ui_escape(json_encode($filteredReport, JSON_PRETTY_PRINT)); ?></textarea>
    </div>
    <?php elseif ($dbError === ''): ?>

    <?php if ($scanScope !== 'code_only' && $dataIssues !== []): ?>
    <div class="fk-risk-card">
        <h2>Database cross-tenant FK rows</h2>
        <table class="fk-risk-table">
            <thead>
                <tr>
                    <th>Risk</th>
                    <th>Table / row</th>
                    <th>FK</th>
                    <th>Tenant match</th>
                    <th>Business key</th>
                    <th>Open</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($dataIssues as $issue): ?>
                <?php
                $risk = (string)($issue['risk'] ?? '');
                $badgeClass = $risk === 'duplicate_dropdown_risk' ? 'fk-risk-badge-danger' : 'fk-risk-badge-warn';
                $editUrl = $baseUrl . (string)($issue['module'] ?? '') . 'edit.php?id=' . (int)($issue['child_id'] ?? 0);
                ?>
                <tr>
                    <td><span class="fk-risk-badge <?= $badgeClass; ?>"><?= itm_fk_risk_ui_escape($risk); ?></span></td>
                    <td>
                        <?= itm_fk_risk_ui_escape((string)($issue['child_table'] ?? '')); ?>
                        #<?= (int)($issue['child_id'] ?? 0); ?><br>
                        company <?= (int)($issue['child_company_id'] ?? 0); ?>
                    </td>
                    <td>
                        <?= itm_fk_risk_ui_escape((string)($issue['fk_column'] ?? '')); ?>=<?= (int)($issue['stored_fk_id'] ?? 0); ?><br>
                        ref company <?= (int)($issue['stored_ref_company_id'] ?? 0); ?>
                    </td>
                    <td>
                        <?php if ((int)($issue['tenant_equivalent_id'] ?? 0) > 0): ?>
                            tenant id <?= (int)$issue['tenant_equivalent_id']; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= itm_fk_risk_ui_escape((string)($issue['business_key'] ?? '')); ?></td>
                    <td><a href="<?= itm_fk_risk_ui_escape($editUrl); ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($scanScope !== 'data_only' && $codeIssues !== []): ?>
    <div class="fk-risk-card">
        <h2>Module code (append without tenant resolve)</h2>
        <table class="fk-risk-table">
            <thead>
                <tr>
                    <th>Risk</th>
                    <th>Module</th>
                    <th>File</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($codeIssues as $issue): ?>
                <tr>
                    <td><span class="fk-risk-badge fk-risk-badge-warn"><?= itm_fk_risk_ui_escape((string)($issue['risk'] ?? '')); ?></span></td>
                    <td><?= itm_fk_risk_ui_escape((string)($issue['module'] ?? '')); ?></td>
                    <td><code><?= itm_fk_risk_ui_escape((string)($issue['file'] ?? '')); ?></code></td>
                    <td><?= itm_fk_risk_ui_escape((string)($issue['note'] ?? '')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php endif; ?>
<?php elseif (!$runScan): ?>
    <div class="fk-risk-card">
        <p class="fk-risk-muted">Choose scan mode and company, then click <strong>Run scan</strong>.</p>
    </div>
<?php endif; ?>
</div>
</body>
</html>
