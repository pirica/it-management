<?php
/**
 * Audits module active-field UI against database schema and AGENTS.md rules.
 *
 * Why: Scaffold modules must render active as an itm-checkbox-control checkbox on create/edit;
 * status-driven modules (employees, equipment, patches_updates, tickets) must use hidden active=1.
 * Forbidden: <input type="text" name="active">.
 *
 * Browser: Admin session (read-only report on load).
 * CLI: php scripts/list_active_and_checkboxes.php [--json] [--all]
 */

declare(strict_types=1);

$itmIsCli = PHP_SAPI === 'cli';

if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/itm_list_active_and_checkboxes_report.php';
require_once __DIR__ . '/lib/script_cli_output.php';

/**
 * @param array{file:string,module?:string,table?:string} $row
 */
function itm_active_audit_format_file_line(array $row): string
{
    $file = (string) ($row['file'] ?? '');
    if ($file === '') {
        return '';
    }
    if (itm_script_is_cli_sapi()) {
        return $file;
    }

    return itm_script_external_link_html('../' . $file, $file);
}

/**
 * @param array{file:string,module:string,table:string,issues?:array} $row
 */
function itm_active_audit_format_violation_header(array $row): string
{
    $line = itm_active_audit_format_file_line($row);
    $table = (string) ($row['table'] ?? '');
    $module = (string) ($row['module'] ?? '');

    if (itm_script_is_cli_sapi()) {
        return $line . ' (table: ' . $table . ')';
    }

    $moduleLink = $module !== '' ? itm_script_format_module_link($module) : '';
    $tableLink = $table !== '' ? itm_script_format_table_link($table) : '';

    return $line . ' (table: ' . ($tableLink !== '' ? $tableLink : htmlspecialchars($table, ENT_QUOTES, 'UTF-8'))
        . ($moduleLink !== '' ? '; module: ' . $moduleLink : '') . ')';
}

itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

$report = itm_collect_active_and_checkboxes_report($conn);
$asJson = $itmIsCli
    ? in_array('--json', $argv ?? [], true)
    : isset($_GET['format']) && strtolower((string) $_GET['format']) === 'json';
$showAll = $itmIsCli && in_array('--all', $argv ?? [], true);

if ($itmIsCli) {
    itm_script_output_begin('Active field and checkbox audit');

    $nl = itm_script_output_nl();
    if ($asJson) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . $nl;
        exit(((int) ($report['summary']['violations'] ?? 0)) > 0 ? 1 : 0);
    }

    echo 'Active field / checkbox audit' . $nl;
    echo str_repeat('=', 72) . $nl;
    itm_active_audit_echo_summary($report, $nl);

    if ($report['violations'] !== []) {
        echo 'VIOLATIONS' . $nl;
        echo str_repeat('-', 72) . $nl;
        foreach ($report['violations'] as $row) {
            echo itm_active_audit_format_violation_header($row) . $nl;
            foreach ($row['issues'] as $issue) {
                echo '  [' . $issue['code'] . '] ' . $issue['message'] . $nl;
            }
        }
        echo $nl;
    } else {
        echo 'No active-field violations.' . $nl . $nl;
    }

    if ($showAll && $report['compliant_checkbox'] !== []) {
        echo 'COMPLIANT ACTIVE CHECKBOX FILES' . $nl;
        echo str_repeat('-', 72) . $nl;
        foreach ($report['compliant_checkbox'] as $row) {
            echo itm_active_audit_format_file_line($row) . $nl;
        }
        echo $nl;
    }

    if ($showAll && $report['hidden_active'] !== []) {
        echo 'HIDDEN ACTIVE FILES' . $nl;
        echo str_repeat('-', 72) . $nl;
        foreach ($report['hidden_active'] as $row) {
            echo itm_active_audit_format_file_line($row) . $nl;
        }
        echo $nl;
    }

    exit(((int) ($report['summary']['violations'] ?? 0)) > 0 ? 1 : 0);
}

if ($asJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

itm_script_output_begin('Active field and checkbox audit');
$nl = itm_script_output_nl();
itm_active_audit_echo_summary($report, $nl);

if ($report['violations'] !== []) {
    echo 'VIOLATIONS' . $nl;
    foreach ($report['violations'] as $row) {
        echo itm_active_audit_format_violation_header($row) . $nl;
        foreach ($row['issues'] as $issue) {
            echo '  [' . htmlspecialchars($issue['code'], ENT_QUOTES, 'UTF-8') . '] '
                . htmlspecialchars($issue['message'], ENT_QUOTES, 'UTF-8') . $nl;
        }
    }
} else {
    echo 'No active-field violations.' . $nl;
}

itm_script_output_end();
