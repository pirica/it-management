<?php
/**
 * Static audit: view-visible CRUD fields must appear on create/edit forms.
 *
 * Why: List grids often hide detailed columns via $*ListHiddenFields on $uiColumns while
 * view.php/detail still shows them — create/edit must expose the same business fields
 * (minus company_id and audit meta) via extra form cards or helpers.
 *
 * Browser: scripts/check_crud_view_edit_field_parity.php (Admin). Optional ?module=slug&json=1
 * CLI: php scripts/check_crud_view_edit_field_parity.php [--module=slug] [--json]
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: optional <code>?module=&lt;slug&gt;</code> · <code>?json=1</code>. CLI: <code>php scripts/check_crud_view_edit_field_parity.php</code> · <code>--module=&lt;slug&gt;</code> · <code>--json</code>. Exit <code>1</code> on <code>[FAIL]</code> view/edit parity gaps. <code>[INFO]</code> lists schema columns not referenced in module entry PHP.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_script_access_helpers.php';
require_once __DIR__ . '/lib/itm_crud_view_edit_field_parity_audit.php';

$nl = itm_check_script_begin_browser_admin('CRUD view/edit field parity');

$moduleFilter = '';
$jsonOutput = false;
if (itm_script_access_is_cli()) {
    foreach ($GLOBALS['argv'] ?? [] as $arg) {
        if (strpos((string) $arg, '--module=') === 0) {
            $moduleFilter = trim(substr((string) $arg, 9));
        }
        if ((string) $arg === '--json') {
            $jsonOutput = true;
        }
    }
} else {
    if (isset($_GET['module'])) {
        $moduleFilter = trim((string) $_GET['module']);
    }
    if (isset($_GET['json'])) {
        $jsonOutput = true;
    }
}

$report = itm_crud_view_edit_field_parity_collect_report($moduleFilter !== '' ? $moduleFilter : null);

if ($jsonOutput) {
    if (!itm_script_access_is_cli()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    itm_script_output_end();
    exit(($report['failure_count'] ?? 0) > 0 ? 1 : 0);
}

echo 'CRUD view/edit field parity audit' . $nl;
echo 'Scope: dynamic scaffold modules with $viewColumns detail loops (index/create/edit/view/list_all).' . $nl;
if ($moduleFilter !== '') {
    echo 'Filter: module=' . $moduleFilter . $nl;
}
echo $nl;

foreach ($report['modules'] as $moduleReport) {
    $slug = (string) ($moduleReport['module'] ?? '');
    if (!empty($moduleReport['skipped'])) {
        echo '[SKIP] ' . $slug . ': ' . (string) ($moduleReport['skip_reason'] ?? 'skipped') . $nl;
        continue;
    }

    foreach ((array) ($moduleReport['infos'] ?? []) as $infoLine) {
        echo '[INFO] ' . $infoLine . $nl;
    }
    foreach ((array) ($moduleReport['passes'] ?? []) as $passLine) {
        echo '[PASS] ' . $passLine . $nl;
    }
    foreach ((array) ($moduleReport['failures'] ?? []) as $failure) {
        echo '[FAIL] ' . (string) ($failure['message'] ?? '') . $nl;
    }
}

echo $nl;
echo 'Summary: ' . (int) ($report['failure_count'] ?? 0) . ' failure(s), '
    . (int) ($report['pass_count'] ?? 0) . ' pass line(s), '
    . (int) ($report['info_count'] ?? 0) . ' info line(s), '
    . (int) ($report['skipped_count'] ?? 0) . ' skipped module(s).' . $nl;

itm_script_output_end();
exit(($report['failure_count'] ?? 0) > 0 ? 1 : 0);
