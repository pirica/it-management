<?php
/**
 * Visual report for database schema validation (errors, warnings, and skips).
 *
 * Why: Provides a high-level overview of schema integrity for administrators.
 *
 * Browser: open scripts/schema_report.php (login required).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();

$nl = itm_script_output_nl();

require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/itm_schema_validation.php';

if (PHP_SAPI === 'cli') {
    echo 'This script is intended for browser use.' . $nl;
    exit(0);
}

itm_script_require_admin_script_or_exit($conn, 'Administrator access required for the schema report.');

$validation = itm_schema_collect_validation_issues($conn);
$errors = $validation['errors'];
$warnings = $validation['warnings'];
$skips = $validation['skips'] ?? [];

$dbName = DB_NAME;
$dbHost = mysqli_get_host_info($conn);
$dbRow = mysqli_fetch_row(mysqli_query($conn, 'SELECT DATABASE()'));
if (is_array($dbRow) && isset($dbRow[0]) && (string)$dbRow[0] !== '') {
    $dbName = (string)$dbRow[0];
}

$errorCount = count($errors);
$warningCount = count($warnings);
$skipCount = count($skips);
$overallOk = $errorCount === 0 && $warningCount === 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Database Schema Report</title>
<link rel="stylesheet" href="../css/styles.css">
<style>
    body {
        padding: 32px 20px;
        background: var(--bg-secondary, #f6f8fa);
        display: block;
        min-height: 100vh;
        overflow-y: auto;
        color: var(--text-primary, #24292f);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }
    .schema-report-wrap {
        max-width: 960px;
        margin: 0 auto;
    }
    .schema-report-card {
        background: var(--bg-primary, #fff);
        border: 1px solid var(--border, #d0d7de);
        border-radius: 8px;
        padding: 28px 32px;
        box-shadow: var(--shadow, 0 1px 3px rgba(0, 0, 0, 0.08));
        margin-bottom: 20px;
    }
    .schema-report-header {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 8px;
    }
    .schema-report-header h1 {
        margin: 0;
        font-size: 1.5rem;
        line-height: 1.3;
        color: var(--text-primary, #24292f);
    }
    .schema-report-status {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        border-radius: 999px;
        font-size: 0.875rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .schema-report-status--ok {
        background: #dafbe1;
        color: #1a7f37;
        border: 1px solid #aff5b4;
    }
    .schema-report-status--issues {
        background: #fff8c5;
        color: #9a6700;
        border: 1px solid #d4a72c;
    }
    .schema-report-status--fail {
        background: #ffebe9;
        color: #cf222e;
        border: 1px solid #ffc1c0;
    }
    .schema-report-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px 24px;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .schema-report-meta li {
        margin: 0;
        font-size: 0.95rem;
        line-height: 1.5;
    }
    .schema-report-meta strong {
        display: block;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-secondary, #57606a);
        margin-bottom: 2px;
    }
    .schema-report-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }
    .schema-report-stat {
        flex: 1 1 140px;
        background: var(--bg-primary, #fff);
        border: 1px solid var(--border, #d0d7de);
        border-radius: 8px;
        padding: 16px 18px;
        text-align: center;
    }
    .schema-report-stat__value {
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .schema-report-stat__label {
        font-size: 0.8rem;
        color: var(--text-secondary, #57606a);
        margin-top: 4px;
    }
    .schema-report-stat--errors .schema-report-stat__value { color: #cf222e; }
    .schema-report-stat--warnings .schema-report-stat__value { color: #9a6700; }
    .schema-report-stat--skips .schema-report-stat__value { color: #57606a; }
    .schema-report-stat--ok .schema-report-stat__value { color: #1a7f37; }
    .schema-report-section h2 {
        margin: 0 0 12px;
        font-size: 1.1rem;
        color: var(--text-primary, #24292f);
        border-left: 4px solid #0969da;
        padding-left: 10px;
    }
    .schema-report-table {
        width: 100%;
        border-collapse: collapse;
        background: var(--bg-primary, #fff);
        border: 1px solid var(--border, #d0d7de);
        border-radius: 8px;
        overflow: hidden;
    }
    .schema-report-table th,
    .schema-report-table td {
        padding: 12px 14px;
        text-align: left;
        vertical-align: top;
        border-bottom: 1px solid var(--border, #d0d7de);
    }
    .schema-report-table th {
        background: #f6f8fa;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: var(--text-secondary, #57606a);
        width: 140px;
    }
    .schema-report-table tr:last-child td,
    .schema-report-table tr:last-child th {
        border-bottom: none;
    }
    .schema-report-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .schema-report-badge--ok { background: #dafbe1; color: #1a7f37; }
    .schema-report-badge--warn { background: #fff8c5; color: #9a6700; }
    .schema-report-badge--skip { background: #eaeef2; color: #57606a; }
    .schema-report-badge--error { background: #ffebe9; color: #cf222e; }
    .schema-report-desc { line-height: 1.5; word-break: break-word; }
    .itm-script-nav { margin-bottom: 20px !important; }
</style>
</head>
<body>

<div class="schema-report-wrap">
    <?php itm_script_browser_nav_echo(); ?>

    <div class="schema-report-card">
        <div class="schema-report-header">
            <h1 title="Database schema validation report">📊</h1>
            <?php if ($overallOk): ?>
                <span class="schema-report-status schema-report-status--ok" title="Schema validation passed">Schema consistent</span>
            <?php elseif ($errorCount > 0): ?>
                <span class="schema-report-status schema-report-status--fail" title="Schema has errors"><?= (int)$errorCount ?> error<?= $errorCount === 1 ? '' : 's' ?></span>
            <?php else: ?>
                <span class="schema-report-status schema-report-status--issues" title="Schema has warnings only"><?= (int)$warningCount ?> warning<?= $warningCount === 1 ? '' : 's' ?></span>
            <?php endif; ?>
        </div>

        <ul class="schema-report-meta">
            <li><strong>Database</strong><?= sanitize($dbName) ?></li>
            <li><strong>Host</strong><?= sanitize($dbHost) ?></li>
            <li><strong>Generated</strong><?= sanitize(date('d/m/Y H:i:s')) ?></li>
        </ul>
    </div>

    <div class="schema-report-stats">
        <div class="schema-report-stat schema-report-stat--errors">
            <div class="schema-report-stat__value"><?= (int)$errorCount ?></div>
            <div class="schema-report-stat__label">Errors</div>
        </div>
        <div class="schema-report-stat schema-report-stat--warnings">
            <div class="schema-report-stat__value"><?= (int)$warningCount ?></div>
            <div class="schema-report-stat__label">Warnings</div>
        </div>
        <div class="schema-report-stat schema-report-stat--skips">
            <div class="schema-report-stat__value"><?= (int)$skipCount ?></div>
            <div class="schema-report-stat__label">Skipped</div>
        </div>
        <div class="schema-report-stat schema-report-stat--ok">
            <div class="schema-report-stat__value"><?= $overallOk ? '✓' : '—' ?></div>
            <div class="schema-report-stat__label">Overall</div>
        </div>
    </div>

    <div class="schema-report-card schema-report-section">
        <h2 title="Schema errors">Errors</h2>
        <table class="schema-report-table">
            <thead>
                <tr>
                    <th scope="col">Status</th>
                    <th scope="col">Description</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($errors === []): ?>
                <tr>
                    <td><span class="schema-report-badge schema-report-badge--ok">OK</span></td>
                    <td class="schema-report-desc">No errors found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($errors as $error): ?>
                <tr>
                    <td><span class="schema-report-badge schema-report-badge--error">Error</span></td>
                    <td class="schema-report-desc"><?= sanitize($error) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="schema-report-card schema-report-section">
        <h2 title="Schema warnings">Warnings</h2>
        <table class="schema-report-table">
            <thead>
                <tr>
                    <th scope="col">Status</th>
                    <th scope="col">Description</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($warnings === []): ?>
                <tr>
                    <td><span class="schema-report-badge schema-report-badge--ok">OK</span></td>
                    <td class="schema-report-desc">No warnings found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($warnings as $warning): ?>
                <tr>
                    <td><span class="schema-report-badge schema-report-badge--warn">Warning</span></td>
                    <td class="schema-report-desc"><?= sanitize($warning) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="schema-report-card schema-report-section">
        <h2 title="SKIP DELETE CASCADE">Skipped</h2>
        <table class="schema-report-table">
            <thead>
                <tr>
                    <th scope="col">Status</th>
                    <th scope="col">Description</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($skips === []): ?>
                <tr>
                    <td><span class="schema-report-badge schema-report-badge--ok">OK</span></td>
                    <td class="schema-report-desc">No skipped checks.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($skips as $skip): ?>
                <tr>
                    <td><span class="schema-report-badge schema-report-badge--skip">Skip</span></td>
                    <td class="schema-report-desc"><?= sanitize($skip) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>

</body>
</html>

<?php
itm_script_output_end();
