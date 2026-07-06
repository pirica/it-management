<?php
/**
 * Regression: Free tier API keys remain unlimited even with high counters.
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_api_tier_test_helpers.php';

itm_script_output_begin('API Tier Free Test');

$companyId = ITM_APITEST_COMPANY_ID;
$employeeId = itm_apitest_disposable_user_id(1);
$allPassed = true;

itm_apitest_output_line('[INFO] Seeding disposable Free-tier ui_configuration row…', 'info');

$row = itm_apitest_seed_configuration($conn, $companyId, $employeeId, 'Free', [
    'api_key' => '',
    'rate_limit_enabled' => 1,
    'rate_limit_window_start' => time(),
    'rate_limit_request_count' => 50000,
]);

if ($row === null) {
    itm_apitest_output_line('[FAIL] Unable to seed disposable Free-tier configuration row.', 'fail');
    exit(1);
}

itm_apitest_print_probe_links('', 'Free-tier', true);

$_SESSION['company_id'] = $companyId;
$_SESSION['employee_id'] = $employeeId;
$resolvedWithoutKey = itm_api_resolve_rate_limit_row($conn);
$allPassed = itm_apitest_assert(
    'Free resolve without API key via session',
    is_array($resolvedWithoutKey) && (string)($resolvedWithoutKey['tier'] ?? '') === 'Free'
) && $allPassed;
$allPassed = itm_apitest_assert(
    'Free resolve reports api_key not required',
    is_array($resolvedWithoutKey) && function_exists('itm_api_tier_requires_api_key')
        && !itm_api_tier_requires_api_key($resolvedWithoutKey['tier'] ?? 'Free')
) && $allPassed;

$status = itm_api_rate_limit_status_from_row($row);
$allPassed = itm_apitest_assert('Free status marks tier unlimited', !empty($status['unlimited'])) && $allPassed;
$allPassed = itm_apitest_assert('Free status limit is zero (no cap)', (int)($status['limit'] ?? -1) === 0) && $allPassed;
$allPassed = itm_apitest_assert('Free status remaining is null', $status['remaining'] === null) && $allPassed;

for ($attempt = 1; $attempt <= 3; $attempt++) {
    $freshRow = itm_apitest_reload_configuration($conn, (int)$row['id'], $companyId, $employeeId);
    if ($freshRow === null) {
        $allPassed = itm_apitest_assert('Reload configuration before consume #' . $attempt, false) && $allPassed;
        break;
    }

    $consume = itm_api_consume_rate_limit($conn, $freshRow);
    $allPassed = itm_apitest_assert(
        'Free consume attempt #' . $attempt . ' stays allowed',
        !empty($consume['allowed']),
        json_encode($consume, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ) && $allPassed;
    $allPassed = itm_apitest_assert(
        'Free consume attempt #' . $attempt . ' reports unlimited',
        !empty($consume['unlimited']),
        json_encode($consume, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ) && $allPassed;
}

$emptyLookup = itm_api_lookup_configuration_by_key($conn, '');
$allPassed = itm_apitest_assert('Empty API key lookup returns null', $emptyLookup === null) && $allPassed;

$probePayload = function_exists('itm_api_build_rate_limit_probe_payload')
    ? itm_api_build_rate_limit_probe_payload($row)
    : null;
$allPassed = itm_apitest_assert(
    'Probe payload marks api_key_required false on Free',
    is_array($probePayload) && empty($probePayload['api_key_required'])
) && $allPassed;
$allPassed = itm_apitest_assert(
    'Probe payload includes employee_id',
    is_array($probePayload) && (int)($probePayload['employee_id'] ?? 0) === $employeeId
) && $allPassed;
$allPassed = itm_apitest_assert(
    'Probe payload omits legacy user_id key',
    is_array($probePayload) && !array_key_exists('user_id', $probePayload)
) && $allPassed;

$httpSessionId = itm_apitest_publish_http_session($companyId, $employeeId);
$probe = itm_apitest_probe_rate_limit_http('', '', $httpSessionId);
if (is_array($probe)) {
    if (!empty($probe['ok'])) {
        $allPassed = itm_apitest_assert('HTTP probe returns ok=1', true) && $allPassed;
        $allPassed = itm_apitest_assert('HTTP probe reports unlimited tier', !empty($probe['unlimited'])) && $allPassed;
        $allPassed = itm_apitest_assert(
            'HTTP probe marks api_key_required false',
            array_key_exists('api_key_required', $probe) && empty($probe['api_key_required'])
        ) && $allPassed;
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
    itm_apitest_output_line('[PASS] Free tier API rate-limit regression complete.', 'pass');
    exit(0);
}

itm_apitest_output_line('[FAIL] Free tier API rate-limit regression failed.', 'fail');
exit(1);

itm_script_output_end();
