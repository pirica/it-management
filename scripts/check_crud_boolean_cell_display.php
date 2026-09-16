<?php
/**
 * Static audit: visible tinyint(1) list/view columns must not render raw 0/1.
 *
 * Browser: scripts/check_crud_boolean_cell_display.php (Administrator session).
 * CLI: php scripts/check_crud_boolean_cell_display.php
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/itm_script_access_helpers.php';
require_once __DIR__ . '/lib/itm_crud_boolean_cell_display_audit.php';

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: plain-text report with module links. CLI: <code>php scripts/check_crud_boolean_cell_display.php</code> — exit <code>1</code> when any visible <code>tinyint(1)</code> column in <code>cr_render_cell_value()</code> lacks badge/emoji handling (<code>active</code> → badges; other checkbox columns → <code>itm_crud_render_checkbox_boolean_cell_value()</code> or bespoke ✅/❌).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$nl = itm_check_script_begin_browser_admin('CRUD boolean cell display audit');

$report = itm_crud_boolean_cell_audit_collect_report(dirname(__DIR__));
$linkModules = !itm_script_access_is_cli();

echo itm_crud_boolean_cell_audit_format_report($report, $nl, $linkModules);

if ($report['failures'] !== []) {
    echo colorText(
        'FAIL: add active badges or itm_crud_render_checkbox_boolean_cell_value() / bespoke ✅/❌ in cr_render_cell_value().',
        'fail'
    ) . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('PASS: all visible tinyint(1) list/view columns have boolean display handling.', 'pass') . $nl;
itm_script_output_end();
exit(0);
