<?php
/**
 * Verifies employees audit triggers do not disclose auth secrets in audit_logs.
 *
 * Checks:
 *  1) db/ trg_employees_audit_* omit sensitive JSON keys
 *  2) Live UPDATE on disposable employee — audit row must not contain secrets
 *  3) Retro scan of recent employees audit_logs rows (informational)
 *
 * Browser: scripts/verify_audit_logs_disclosure.php (admin login required).
 * CLI: php scripts/verify_audit_logs_disclosure.php
 */
if (PHP_SAPI === 'cli') {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_employees_auth_sensitive_fields.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once dirname(__DIR__) . '/includes/itm_database_sql_source.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

itm_script_output_begin('Audit Logs Disclosure Verification');

$nl = itm_script_output_nl();
$companyId = (int)(getenv('ITM_TEST_COMPANY_ID') ?: 1);
$sensitiveFields = itm_employees_auth_sensitive_field_names();
$failed = false;

echo 'Verifying sensitive information disclosure in audit_logs...' . $nl;
echo $nl;
echo 'This script checks:' . $nl;
echo '  1) db/03_triggers.sql — trg_employees_audit_insert|update|delete omit auth secrets from JSON_OBJECT payloads' . $nl;
echo '  2) Live probe — disposable employee UPDATE (reset_token + password) must not leak secrets into audit_logs' . $nl;
echo '  3) Retro scan — last 25 employees audit rows for company ' . $companyId . ' (no password/reset_token JSON keys or plaintext)' . $nl;
echo $nl;

/**
 * @return string[]
 */
function vald_scan_employees_audit_triggers_in_database_sql(array $sensitiveFields): array
{
    $path = itm_database_sql_schema_path();
    if (!is_readable($path)) {
        return ['db/ split bundle is not readable'];
    }

    $sql = (string)file_get_contents($path);
    $issues = [];
    $triggerNames = [
        'trg_employees_audit_insert',
        'trg_employees_audit_update',
        'trg_employees_audit_delete',
    ];

    foreach ($triggerNames as $triggerName) {
        $pattern = '/CREATE TRIGGER `' . preg_quote($triggerName, '/') . '`.+?END\$\$/s';
        if (!preg_match($pattern, $sql, $match)) {
            $issues[] = $triggerName . ' not found in db/03_triggers.sql01_schema.sql';
            continue;
        }

        $body = (string)$match[0];
        foreach ($sensitiveFields as $field) {
            $fieldPattern = '/[\'"]' . preg_quote($field, '/') . '[\'"]|`' . preg_quote($field, '/') . '`/';
            if (preg_match($fieldPattern, $body)) {
                $issues[] = $triggerName . ' references sensitive field "' . $field . '"';
            }
        }
    }

    return $issues;
}

/**
 * @param string $json
 * @param string $plaintextNeedle
 * @param string[] $forbiddenKeys
 * @return string[]
 */
function vald_audit_json_payload_issues($json, $plaintextNeedle, array $forbiddenKeys): array
{
    $issues = [];
    $text = (string)$json;
    if ($text === '') {
        return $issues;
    }

    if ($plaintextNeedle !== '' && strpos($text, $plaintextNeedle) !== false) {
        $issues[] = 'contains plaintext probe value';
    }

    $decoded = json_decode($text, true);
    if (!is_array($decoded)) {
        return $issues;
    }

    foreach ($forbiddenKeys as $key) {
        if (array_key_exists($key, $decoded)) {
            $issues[] = 'JSON key "' . $key . '" present';
        }
    }

    return $issues;
}

/**
 * @param mysqli $conn
 * @param int $companyId
 * @param string $plaintextNeedle
 * @param string[] $sensitiveFields
 * @param int $limit
 * @return array{rows:int,issues:string[]}
 */
function vald_scan_recent_employees_audit_logs(mysqli $conn, int $companyId, $plaintextNeedle, array $sensitiveFields, int $limit = 25): array
{
    $issues = [];
    $rows = 0;
    $stmt = $conn->prepare(
        'SELECT old_values, new_values FROM audit_logs
         WHERE table_name = ? AND company_id = ?
         ORDER BY id DESC
         LIMIT ' . (int)$limit
    );
    if (!$stmt) {
        return ['rows' => 0, 'issues' => ['audit_logs prepare failed: ' . $conn->error]];
    }

    $table = 'employees';
    $stmt->bind_param('si', $table, $companyId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows++;
        $combinedIssues = array_merge(
            vald_audit_json_payload_issues((string)($row['old_values'] ?? ''), $plaintextNeedle, $sensitiveFields),
            vald_audit_json_payload_issues((string)($row['new_values'] ?? ''), $plaintextNeedle, $sensitiveFields)
        );
        if ($combinedIssues !== []) {
            $issues[] = 'row #' . $rows . ': ' . implode('; ', $combinedIssues);
        }
    }
    $stmt->close();

    return ['rows' => $rows, 'issues' => $issues];
}

// --- Step 1: static trigger contract In db/01_schema.sql ---
echo 'Step 1 — db/ employees audit triggers' . $nl;
$triggerIssues = vald_scan_employees_audit_triggers_in_database_sql($sensitiveFields);
if ($triggerIssues === []) {
    echo colorText('[PASS] trg_employees_audit_insert|update|delete omit ' . implode(', ', $sensitiveFields) . '.', 'pass') . $nl;
} else {
    $failed = true;
    foreach ($triggerIssues as $issue) {
        echo colorText('[FAIL] ' . $issue, 'fail') . $nl;
    }
}
echo $nl;

// --- Step 2: live disposable employee UPDATE probe ---
echo 'Step 2 — live audit row after employees UPDATE' . $nl;
$probeEmployeeId = 0;
$probeToken = 'AUDIT_DISCLOSURE_' . bin2hex(random_bytes(8));
$probeTokenHash = hash('sha256', $probeToken);
$probePasswordHash = password_hash('AuditDisclosureProbe1', PASSWORD_BCRYPT);
$probeExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));

$testUser = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-audit-logs-disclosure']);
if (!is_array($testUser)) {
    $failed = true;
    echo colorText('[FAIL] Unable to create disposable test employee.', 'fail') . $nl;
} else {
    $probeEmployeeId = (int)$testUser['id'];
    $probeUsername = (string)$testUser['username'];
    $snapshot = itm_script_test_employee_snapshot($conn, $probeEmployeeId, $sensitiveFields);
    itm_script_test_employee_register_teardown($conn, $probeEmployeeId, $snapshot);

    itm_script_test_employee_set_audit_context($conn, $probeEmployeeId, $probeUsername, $companyId);

    $stmtUpdate = $conn->prepare(
        'UPDATE employees SET reset_token = ?, reset_token_hash = ?, reset_token_expires_at = ?, password = ? WHERE id = ? AND company_id = ?'
    );
    if (!$stmtUpdate) {
        $failed = true;
        echo colorText('[FAIL] employees UPDATE prepare failed: ' . $conn->error, 'fail') . $nl;
    } else {
        $stmtUpdate->bind_param('ssssii', $probeToken, $probeTokenHash, $probeExpires, $probePasswordHash, $probeEmployeeId, $companyId);
        if (!$stmtUpdate->execute()) {
            $failed = true;
            echo colorText('[FAIL] employees UPDATE failed: ' . $stmtUpdate->error, 'fail') . $nl;
        }
        $stmtUpdate->close();

        $stmtLog = $conn->prepare(
            "SELECT old_values, new_values FROM audit_logs
             WHERE table_name = 'employees' AND record_id = ? AND company_id = ?
             ORDER BY id DESC LIMIT 1"
        );
        if (!$stmtLog) {
            $failed = true;
            echo colorText('[FAIL] audit_logs SELECT prepare failed: ' . $conn->error, 'fail') . $nl;
        } else {
            $stmtLog->bind_param('ii', $probeEmployeeId, $companyId);
            $stmtLog->execute();
            $logRow = itm_mysqli_stmt_fetch_assoc($stmtLog);
            $stmtLog->close();

            if (!is_array($logRow)) {
                $failed = true;
                echo colorText('[FAIL] No audit_logs row found for disposable employee UPDATE.', 'fail') . $nl;
            } else {
                $liveIssues = array_merge(
                    vald_audit_json_payload_issues((string)($logRow['old_values'] ?? ''), $probeToken, $sensitiveFields),
                    vald_audit_json_payload_issues((string)($logRow['new_values'] ?? ''), $probeToken, $sensitiveFields)
                );
                if (strpos((string)($logRow['new_values'] ?? ''), $probePasswordHash) !== false
                    || strpos((string)($logRow['old_values'] ?? ''), $probePasswordHash) !== false) {
                    $liveIssues[] = 'contains password hash value';
                }
                if (strpos((string)($logRow['new_values'] ?? ''), $probeTokenHash) !== false
                    || strpos((string)($logRow['old_values'] ?? ''), $probeTokenHash) !== false) {
                    $liveIssues[] = 'contains reset_token_hash value';
                }

                if ($liveIssues === []) {
                    echo colorText('[PASS] Latest audit row for employee id=' . $probeEmployeeId . ' omits auth secrets.', 'pass') . $nl;
                } else {
                    $failed = true;
                    foreach ($liveIssues as $issue) {
                        echo colorText('[FAIL] Live audit payload ' . $issue . '.', 'fail') . $nl;
                    }
                }
            }
        }
    }
}
echo $nl;

// --- Step 3: retro scan of recent rows ---
echo 'Step 3 — retro scan of recent employees audit_logs' . $nl;
$retro = vald_scan_recent_employees_audit_logs($conn, $companyId, $probeToken, $sensitiveFields, 25);
if ($retro['rows'] === 0) {
    echo colorText('[INFO] No existing employees audit rows for company ' . $companyId . ' (live probe above is authoritative).', 'info') . $nl;
} elseif ($retro['issues'] === []) {
    echo colorText('[PASS] Scanned ' . $retro['rows'] . ' recent row(s); no sensitive JSON keys or probe plaintext found.', 'pass') . $nl;
} else {
    $failed = true;
    echo colorText('[FAIL] Sensitive patterns in retro scan (' . $retro['rows'] . ' row(s)):', 'fail') . $nl;
    foreach ($retro['issues'] as $issue) {
        echo '  - ' . $issue . $nl;
    }
}
echo $nl;

if ($failed) {
    echo colorText('[FAIL] Audit log disclosure checks failed.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('[PASS] Audit log disclosure checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
