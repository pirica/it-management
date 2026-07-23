<?php
/**
 * Browser UI for FK dropdown risk detection (no CLI required).
 *
 * Why: Operators need the same scans as detect_fk_dropdown_ui_risk.php with dropdowns,
 * not terminal commands.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'itm_script_regression_entry.php';
require_once ROOT_PATH . 'includes' . DIRECTORY_SEPARATOR . 'detect_fk_dropdown_ui_risk_lib.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'script_browser_nav.php';

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
$filteredSummary = [
    'data_issue_count' => 0,
    'code_issue_count' => 0,
    'duplicate_dropdown_data' => 0,
];

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
    $filteredSummary = [
        'data_issue_count' => count($dataIssues),
        'code_issue_count' => count($codeIssues),
        'duplicate_dropdown_data' => count(array_filter($dataIssues, static function ($row) {
            return (string)($row['risk'] ?? '') === 'duplicate_dropdown_risk';
        })),
    ];
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
    <?php itm_script_browser_nav_echo($baseUrl); ?>
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
    $filteredReport = $report;
    $filteredReport['data_issues'] = $dataIssues;
    $filteredReport['code_issues'] = $codeIssues;
    $filteredReport['summary'] = $filteredSummary;
    $filteredReport['risk_filter'] = $riskFilter;
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
                <span>duplicate_dropdown_risk: <strong><?= (int)$filteredSummary['duplicate_dropdown_data']; ?></strong></span>
            </div>
            <?php if ($dataIssues === [] && $codeIssues === []): ?>
                <p><strong>OK</strong> — No matching FK dropdown UI risks for the selected filters.</p>
            <?php else: ?>
                <p class="fk-risk-muted"><strong>Action needed</strong> — Review the findings below. <em>Duplicate dropdown option</em> means the edit screen can show two select choices for the same logical value (for example two “Switch” equipment types).</p>
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
                    <th>What is wrong</th>
                    <th>Record</th>
                    <th>Reference data</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($dataIssues as $issue): ?>
                <?php
                $risk = (string)($issue['risk'] ?? '');
                $badgeClass = $risk === 'duplicate_dropdown_risk' ? 'fk-risk-badge-danger' : 'fk-risk-badge-warn';
                $childTable = (string)($issue['child_table'] ?? '');
                $refTable = (string)($issue['ref_table'] ?? '');
                $modulePath = (string)($issue['module'] ?? itm_script_module_path_from_table($childTable));
                $editUrl = itm_script_module_relative_href_from_path($modulePath, 'edit.php?id=' . (int)($issue['child_id'] ?? 0));
                ?>
                <tr>
                    <td><span class="fk-risk-badge <?= $badgeClass; ?>"><?= itm_fk_risk_ui_escape(itm_detect_fk_risk_label($risk)); ?></span></td>
                    <td><?= itm_fk_risk_ui_escape(itm_detect_fk_data_issue_summary($issue)); ?></td>
                    <td>
                        <?php if ($childTable !== ''): ?>
                            Table <?= itm_script_format_table_link($childTable); ?><br>
                        <?php endif; ?>
                        Row #<?= (int)($issue['child_id'] ?? 0); ?> · Company <?= (int)($issue['child_company_id'] ?? 0); ?>
                    </td>
                    <td>
                        <?php if ($refTable !== ''): ?>
                            <?= itm_script_format_table_link($refTable); ?><br>
                        <?php endif; ?>
                        <?= itm_fk_risk_ui_escape(itm_detect_fk_column_label((string)($issue['fk_column'] ?? ''))); ?>:
                        stored id <?= (int)($issue['stored_fk_id'] ?? 0); ?> (company <?= (int)($issue['stored_ref_company_id'] ?? 0); ?>)
                        <?php if ((int)($issue['tenant_equivalent_id'] ?? 0) > 0): ?>
                            <br>Should use id <?= (int)$issue['tenant_equivalent_id']; ?>
                        <?php endif; ?>
                        <?php if ((string)($issue['business_key'] ?? '') !== ''): ?>
                            <br><span class="fk-risk-muted"><?= itm_fk_risk_ui_escape((string)$issue['business_key']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($editUrl !== ''): ?>
                            <?= itm_script_external_link_html($editUrl, 'Edit row'); ?>
                        <?php endif; ?>
                        <?php if ($modulePath !== ''): ?>
                            <br><?= itm_script_format_module_path_link($modulePath, 'Open module'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($scanScope !== 'data_only' && $codeIssues !== []): ?>
    <div class="fk-risk-card">
        <h2>Module code risks</h2>
        <table class="fk-risk-table">
            <thead>
                <tr>
                    <th>Risk</th>
                    <th>What is wrong</th>
                    <th>Module</th>
                    <th>File</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($codeIssues as $issue): ?>
                <?php
                $modulePath = (string)($issue['module'] ?? '');
                $moduleLabel = trim($modulePath, '/');
                ?>
                <tr>
                    <td><span class="fk-risk-badge fk-risk-badge-warn"><?= itm_fk_risk_ui_escape(itm_detect_fk_risk_label((string)($issue['risk'] ?? ''))); ?></span></td>
                    <td><?= itm_fk_risk_ui_escape(itm_detect_fk_code_issue_summary($issue)); ?></td>
                    <td>
                        <?php if ($modulePath !== ''): ?>
                            <?= itm_script_format_module_path_link($modulePath, $moduleLabel); ?>
                        <?php else: ?>
                            <?= itm_fk_risk_ui_escape($moduleLabel); ?>
                        <?php endif; ?>
                    </td>
                    <td><code><?= itm_fk_risk_ui_escape((string)($issue['file'] ?? '')); ?></code></td>
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
