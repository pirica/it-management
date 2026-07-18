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
            echo $row['file'] . ' (table: ' . $row['table'] . ')' . $nl;
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
            echo $row['file'] . $nl;
        }
        echo $nl;
    }

    if ($showAll && $report['hidden_active'] !== []) {
        echo 'HIDDEN ACTIVE FILES' . $nl;
        echo str_repeat('-', 72) . $nl;
        foreach ($report['hidden_active'] as $row) {
            echo $row['file'] . $nl;
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
        echo $row['file'] . ' (table: ' . htmlspecialchars($row['table'], ENT_QUOTES, 'UTF-8') . ')' . $nl;
        foreach ($row['issues'] as $issue) {
            echo '  [' . htmlspecialchars($issue['code'], ENT_QUOTES, 'UTF-8') . '] '
                . htmlspecialchars($issue['message'], ENT_QUOTES, 'UTF-8') . $nl;
        }
    }
} else {
    echo 'No active-field violations.' . $nl;
}

itm_script_output_end();
