<?php
/**
 * Validation Script: Audit Log Redaction
 *
 * Verifies that the fixed audit_functions.php correctly redacts sensitive fields.
 */

require_once __DIR__ . '/../fixed_files/includes/audit_functions.php';

echo "Audit Log Redaction Validation\n";
echo "==============================\n";

$testData = [
    'employee_id' => 5,
    'username' => 'testuser',
    'password' => 'secret123',
    'reset_token' => 'token_abcd_123',
    'api_key' => 'ak_test_key',
    'email' => 'test@example.com'
];

echo "Original Data: " . json_encode($testData) . "\n";

$redactedData = itm_audit_redact_sensitive_fields($testData);

echo "Redacted Data: " . json_encode($redactedData) . "\n";

$sensitiveFields = ['password', 'reset_token', 'api_key'];
$allRedacted = true;

foreach ($sensitiveFields as $field) {
    if (isset($redactedData[$field]) && $redactedData[$field] === '[REDACTED]') {
        echo "[PASS] Field '$field' was successfully redacted.\n";
    } else {
        echo "[FAIL] Field '$field' was NOT redacted correctly. Value: " . ($redactedData[$field] ?? 'MISSING') . "\n";
        $allRedacted = false;
    }
}

if ($allRedacted && $redactedData['username'] === 'testuser') {
    echo "[PASS] Non-sensitive fields (e.g., 'username') were preserved.\n";
} else {
    echo "[FAIL] Non-sensitive fields were corrupted.\n";
    $allRedacted = false;
}

if ($allRedacted) {
    echo "\nSUMMARY: Audit log redaction logic verified successfully.\n";
} else {
    echo "\nSUMMARY: Audit log redaction logic failed validation.\n";
    exit(1);
}
