<?php
/**
 * Validation Script: Audit Log Redaction
 *
 * Verifies that the fixed audit_functions.php correctly redacts sensitive fields.
 */

require_once __DIR__ . '/../includes/audit_functions.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Audit Log Redaction Validation');

$nl = itm_script_output_nl();

echo "Audit Log Redaction Validation" . $nl;
echo "==============================" . $nl;

$testData = [
    'employee_id' => 5,
    'username' => 'testuser',
    'password' => 'secret123',
    'reset_token' => 'token_abcd_123',
    'api_key' => 'ak_test_key',
    'email' => 'test@example.com'
];

echo "Original Data: " . json_encode($testData) . $nl;

$redactedData = itm_audit_redact_sensitive_fields($testData);

echo "Redacted Data: " . json_encode($redactedData) . $nl;

$sensitiveFields = ['password', 'reset_token', 'api_key'];
$allRedacted = true;

foreach ($sensitiveFields as $field) {
    if (isset($redactedData[$field]) && $redactedData[$field] === '[REDACTED]') {
        echo itm_script_format_status_line("[PASS] Field '$field' was successfully redacted.") . $nl;
    } else {
        echo itm_script_format_status_line("[FAIL] Field '$field' was NOT redacted correctly. Value: " . ($redactedData[$field] ?? 'MISSING')) . $nl;
        $allRedacted = false;
    }
}

if ($allRedacted && $redactedData['username'] === 'testuser') {
    echo itm_script_format_status_line("[PASS] Non-sensitive fields (e.g., 'username') were preserved.") . $nl;
} else {
    echo itm_script_format_status_line("[FAIL] Non-sensitive fields were corrupted.") . $nl;
    $allRedacted = false;
}

if ($allRedacted) {
    echo $nl . itm_script_format_status_line("SUMMARY: Audit log redaction logic verified successfully.") . $nl;
} else {
    echo $nl . itm_script_format_status_line("SUMMARY: Audit log redaction logic failed validation.") . $nl;
    exit(1);
}

itm_script_output_end();
