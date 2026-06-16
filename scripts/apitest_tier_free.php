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
$userId = itm_apitest_disposable_user_id(1);
$allPassed = true;

itm_apitest_output_line('[INFO] Seeding disposable Free-tier ui_configuration row…', 'info');

$row = itm_apitest_seed_configuration($conn, $companyId, $userId, 'Free', [
    'rate_limit_enabled' => 1,
    'rate_limit_window_start' => time(),
    'rate_limit_request_count' => 50000,
]);

if ($row === null) {
    itm_apitest_output_line('[FAIL] Unable to seed disposable Free-tier configuration row.', 'fail');
    exit(1);
}

register_shutdown_function(static function () use ($conn, $companyId, $userId) {
    itm_apitest_cleanup_configuration($conn, $companyId, $userId);
});

$status = itm_api_rate_limit_status_from_row($row);
$allPassed = itm_apitest_assert('Free status marks tier unlimited', !empty($status['unlimited'])) && $allPassed;
$allPassed = itm_apitest_assert('Free status limit is zero (no cap)', (int)($status['limit'] ?? -1) === 0) && $allPassed;
$allPassed = itm_apitest_assert('Free status remaining is null', $status['remaining'] === null) && $allPassed;

for ($attempt = 1; $attempt <= 3; $attempt++) {
    $freshRow = itm_apitest_reload_configuration($conn, (int)$row['id'], $companyId, $userId);
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

$lookup = itm_api_lookup_configuration_by_key($conn, (string)$row['api_key']);
$allPassed = itm_apitest_assert('Lookup resolves seeded Free API key', is_array($lookup) && (int)($lookup['id'] ?? 0) === (int)$row['id']) && $allPassed;

$probe = itm_apitest_probe_rate_limit_http((string)$row['api_key']);
if (is_array($probe)) {
    $allPassed = itm_apitest_assert('HTTP probe returns ok=1', !empty($probe['ok'])) && $allPassed;
    $allPassed = itm_apitest_assert('HTTP probe reports unlimited tier', !empty($probe['unlimited'])) && $allPassed;
} else {
    itm_apitest_output_line('[INFO] HTTP probe skipped (Apache/curl unavailable or non-JSON response).', 'info');
}

itm_apitest_cleanup_configuration($conn, $companyId, $userId);

if ($allPassed) {
    itm_apitest_output_line('[PASS] Free tier API rate-limit regression complete.', 'pass');
    exit(0);
}

itm_apitest_output_line('[FAIL] Free tier API rate-limit regression failed.', 'fail');
exit(1);
