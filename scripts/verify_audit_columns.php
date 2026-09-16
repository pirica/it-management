<?php
/**
 * Verify mandatory tenant/audit meta columns on live tables.
 *
 * Soft-delete + stamp columns are required on CRUD tables (including live_chat_typing).
 * Private chat tables (messages/typing) still must not get audit_logs triggers — see AGENTS.md.
 */


/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_audit_columns.php</code> — exit <code>1</code> when a table lacks mandatory audit/soft-delete columns. <code>live_chat_messages</code> / <code>live_chat_typing</code> include those columns but remain private-data exempt from <code>audit_logs</code> triggers.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli && !defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

itm_script_output_begin('Audit Column Verification');

$nl = itm_script_output_nl();

$mandatoryCols = [
    'company_id' => ['type' => 'int', 'null' => 'NO'],
    'active' => ['type' => 'tinyint', 'default' => '1'],
    'deleted_by' => ['type' => 'int', 'null' => 'YES', 'default' => NULL],
    'deleted_at' => ['type' => 'timestamp', 'null' => 'YES', 'default' => NULL],
    'created_by' => ['type' => 'int', 'null' => 'YES', 'default' => NULL],
    'created_at' => ['type' => 'timestamp', 'null' => 'YES', 'default' => 'CURRENT_TIMESTAMP'],
    'updated_by' => ['type' => 'int', 'null' => 'YES', 'default' => NULL],
    'updated_at' => ['type' => 'timestamp', 'null' => 'YES', 'default' => NULL, 'extra' => 'ON UPDATE CURRENT_TIMESTAMP'],
];

$res = mysqli_query($conn, 'SHOW TABLES');
$allPassed = true;

while ($row = mysqli_fetch_row($res)) {
    $table = $row[0];
    $colRes = mysqli_query($conn, 'SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
    $columns = [];
    while ($colRow = mysqli_fetch_assoc($colRes)) {
        $columns[$colRow['Field']] = $colRow;
    }

    $missing = [];
    foreach ($mandatoryCols as $mCol => $specs) {
        if ($mCol === 'company_id' && in_array($table, ['companies', 'audit_logs', 'modules_registry'], true)) {
            continue;
        }
        if ($mCol === 'active' && in_array($table, ['tickets', 'patches_updates', 'employees', 'equipment'], true)) {
            continue;
        }
        if (!isset($columns[$mCol])) {
            $missing[] = $mCol . ' (Missing)';
        }
    }

    if ($missing === []) {
        echo itm_script_format_status_line('[PASS] ' . $table) . $nl;
    } else {
        echo itm_script_format_status_line('[FAIL] ' . $table . ' - ' . implode(', ', $missing)) . $nl;
        $allPassed = false;
    }
}

if ($allPassed) {
    echo $nl . colorText('Verification successful! All tables are compliant.', 'pass') . $nl;
    itm_script_output_end();
    exit(0);
}

echo $nl . colorText('Verification failed. Some tables are missing mandatory columns.', 'fail') . $nl;
itm_script_output_end();
exit(1);
