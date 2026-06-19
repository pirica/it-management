<?php
/**
 * Regression: Basic tier API keys enforce the hourly request cap.
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_api_tier_test_helpers.php';

itm_script_output_begin('API Tier Basic Test');

$companyId = ITM_APITEST_COMPANY_ID;
$employeeId = itm_apitest_disposable_user_id(2);
$basicLimit = itm_api_tier_hourly_limit('Basic');
$allPassed = true;

itm_apitest_output_line('[INFO] Seeding disposable Basic-tier ui_configuration row at limit-1…', 'info');

$row = itm_apitest_seed_configuration($conn, $companyId, $employeeId, 'Basic', [
    'rate_limit_enabled' => 1,
    'rate_limit_window_start' => time(),
    'rate_limit_request_count' => max(0, $basicLimit - 1),
]);

if ($row === null) {
    itm_apitest_output_line('[FAIL] Unable to seed disposable Basic-tier configuration row.', 'fail');
    exit(1);
}

itm_apitest_print_probe_links((string)$row['api_key'], 'Basic-tier');

$status = itm_api_rate_limit_status_from_row($row);
$allPassed = itm_apitest_assert('Basic status is not unlimited', empty($status['unlimited'])) && $allPassed;
$allPassed = itm_apitest_assert('Basic status limit matches tier cap', (int)($status['limit'] ?? 0) === $basicLimit) && $allPassed;
$allPassed = itm_apitest_assert('Basic status remaining is one before cap', (int)($status['remaining'] ?? -1) === 1) && $allPassed;

$freshRow = itm_apitest_reload_configuration($conn, (int)$row['id'], $companyId, $employeeId);
$consumeAllowed = itm_api_consume_rate_limit($conn, $freshRow ?: $row);
$allPassed = itm_apitest_assert(
    'Basic consume at limit-1 is allowed',
    !empty($consumeAllowed['allowed']),
    json_encode($consumeAllowed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
) && $allPassed;
$allPassed = itm_apitest_assert(
    'Basic consume at limit-1 leaves zero remaining',
    (int)($consumeAllowed['remaining'] ?? -1) === 0,
    json_encode($consumeAllowed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
) && $allPassed;

$freshRow = itm_apitest_reload_configuration($conn, (int)$row['id'], $companyId, $employeeId);
$consumeBlocked = itm_api_consume_rate_limit($conn, $freshRow ?: $row);
$allPassed = itm_apitest_assert(
    'Basic consume at cap is blocked',
    empty($consumeBlocked['allowed']),
    json_encode($consumeBlocked, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
) && $allPassed;
$allPassed = itm_apitest_assert(
    'Basic blocked response mentions rate limit',
    stripos((string)($consumeBlocked['error'] ?? ''), 'rate limit') !== false,
    (string)($consumeBlocked['error'] ?? '')
) && $allPassed;

$atCapRow = itm_apitest_reload_configuration($conn, (int)$row['id'], $companyId, $employeeId);
if (is_array($atCapRow)) {
    $atCapStatus = itm_api_rate_limit_status_from_row($atCapRow);
    $allPassed = itm_apitest_assert('Basic status at cap shows zero remaining', (int)($atCapStatus['remaining'] ?? -1) === 0) && $allPassed;
}

$probe = itm_apitest_probe_rate_limit_http((string)$row['api_key']);
if (is_array($probe)) {
    if (!empty($probe['ok'])) {
        $allPassed = itm_apitest_assert('HTTP probe returns ok=1', true) && $allPassed;
        $allPassed = itm_apitest_assert('HTTP probe reports Basic tier', (string)($probe['tier'] ?? '') === 'Basic') && $allPassed;
        $allPassed = itm_apitest_assert('HTTP probe is not unlimited', empty($probe['unlimited'])) && $allPassed;
    } else {
        itm_apitest_output_line(
            '[INFO] HTTP probe returned error: ' . (string)($probe['error'] ?? 'unknown')
            . (isset($probe['_raw_body']) ? ' — ' . $probe['_raw_body'] : ''),
            'info'
        );
    }
} else {
    itm_apitest_output_line('[INFO] HTTP probe skipped (no HTTP response). Use the Browser probe URL above.', 'info');
}

if ($allPassed) {
    itm_apitest_output_line('[PASS] Basic tier API rate-limit regression complete.', 'pass');
    exit(0);
}

itm_apitest_output_line('[FAIL] Basic tier API rate-limit regression failed.', 'fail');
exit(1);
