<?php
/**
 * API v2 gateway regression checks.
 *
 * CLI: php scripts/verify_api_v2.php
 * Browser: scripts/verify_api_v2.php?run=1 (Administrator).
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/verify_api_v2.php</code> — exit <code>1</code> on failure. Browser: <a href="verify_api_v2.php?run=1">verify_api_v2.php?run=1</a> (Administrator).
<p>Regression for <code>api_key_scopes</code>, PATH_INFO route parsing, scoped auth, probe, and ticket list/create via in-process helpers.</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_api_tier_test_helpers.php';
require_once dirname(__DIR__) . '/includes/itm_api_v2_openapi.php';
require_once dirname(__DIR__) . '/includes/itm_api_v2_handlers/tickets.php';
require_once dirname(__DIR__) . '/includes/itm_api_v2_handlers/equipment.php';

itm_script_output_begin('API v2 verification');

$fail = 0;
function api_v2_fail($msg)
{
    global $fail;
    $fail++;
    echo "[FAIL] {$msg}\n";
}
function api_v2_pass($msg)
{
    echo "[PASS] {$msg}\n";
}

$res = mysqli_query($conn, "SHOW TABLES LIKE 'api_key_scopes'");
if ($res && mysqli_num_rows($res) > 0) {
    api_v2_pass('table api_key_scopes');
} else {
    api_v2_fail('missing table api_key_scopes — apply db/migrations/api_v2_scopes.sql');
}

$routerFile = dirname(__DIR__) . '/modules/api_v2/router.php';
if (is_file($routerFile) && strpos(file_get_contents($routerFile), 'ITM_API_V2') !== false) {
    api_v2_pass('modules/api_v2/router.php defines ITM_API_V2 bypass');
} else {
    api_v2_fail('modules/api_v2/router.php missing or bypass constant absent');
}

if (!function_exists('itm_api_v2_parse_request') || !function_exists('itm_api_v2_match_route')) {
    api_v2_fail('itm_api_v2 helpers missing');
} else {
    api_v2_pass('itm_api_v2 helpers loaded');
}

$parsed = itm_api_v2_match_route(['method' => 'GET', 'resource' => 'tickets', 'id' => 0]);
if (is_array($parsed) && ($parsed['scope'] ?? '') === 'tickets.read') {
    api_v2_pass('route match GET /tickets');
} else {
    api_v2_fail('route match GET /tickets');
}

$parsedId = itm_api_v2_match_route(['method' => 'PATCH', 'resource' => 'tickets', 'id' => 5]);
if (is_array($parsedId) && ($parsedId['scope'] ?? '') === 'tickets.write') {
    api_v2_pass('route match PATCH /tickets/{id}');
} else {
    api_v2_fail('route match PATCH /tickets/{id}');
}

$catalog = itm_api_v2_scope_catalog();
if (isset($catalog['tickets.read'], $catalog['equipment.write'])) {
    api_v2_pass('scope catalog');
} else {
    api_v2_fail('scope catalog incomplete');
}

$companyId = ITM_APITEST_COMPANY_ID;
$employeeId = itm_apitest_disposable_user_id(46);
$row = itm_apitest_seed_configuration($conn, $companyId, $employeeId, 'Basic', [
    'rate_limit_request_count' => 0,
]);

if ($row === null) {
    api_v2_fail('unable to seed disposable Basic-tier configuration');
    itm_apitest_report_seed_failure($conn, $companyId, $employeeId);
    itm_script_output_end();
    exit(1);
}

$uiConfigId = (int)($row['id'] ?? 0);
$apiKey = itm_apitest_plain_api_key_from_seed_row($row);
if ($uiConfigId <= 0 || $apiKey === '') {
    api_v2_fail('seed row missing id or api_key');
} else {
    api_v2_pass('disposable Basic ui_configuration row');
}

if (!itm_api_v2_seed_default_scopes_for_configuration($conn, $companyId, $uiConfigId, $employeeId)) {
    api_v2_fail('seed default read scopes');
} else {
    api_v2_pass('seed default read scopes');
}

$lookup = itm_api_lookup_configuration_by_key($conn, $apiKey);
if (!is_array($lookup) || (int)($lookup['employee_id'] ?? 0) !== $employeeId) {
    api_v2_fail('api key lookup');
} else {
    api_v2_pass('api key lookup');
}

if (!itm_api_v2_configuration_has_scope($conn, $companyId, $uiConfigId, 'tickets.read')) {
    api_v2_fail('tickets.read scope missing after seed');
} else {
    api_v2_pass('tickets.read scope granted');
}

if (itm_api_v2_configuration_has_scope($conn, $companyId, $uiConfigId, 'tickets.write')) {
    api_v2_fail('tickets.write should not be granted by default');
} else {
    api_v2_pass('tickets.write denied by default');
}

$scopesBeforeWrite = itm_api_v2_list_scopes_for_configuration($conn, $companyId, $uiConfigId);
itm_api_v2_bootstrap_session_from_row($lookup);
$scopeOk = itm_api_v2_configuration_has_scope($conn, $companyId, $uiConfigId, 'tickets.read');
if (!$scopeOk) {
    api_v2_fail('scope check after bootstrap');
} else {
    api_v2_pass('scope check after bootstrap');
}

$ticketList = itm_api_v2_tickets_list($conn, $companyId, ['limit' => 5]);
if (!is_array($ticketList) || !isset($ticketList['items'])) {
    api_v2_fail('tickets list handler');
} else {
    api_v2_pass('tickets list handler');
}

$createdTitle = 'API v2 verify ' . bin2hex(random_bytes(4));
if (itm_api_v2_configuration_has_scope($conn, $companyId, $uiConfigId, 'tickets.write')) {
    api_v2_fail('tickets.write should not be granted before explicit grant');
} else {
    api_v2_pass('tickets.write denied before grant');
}

if (!itm_api_v2_replace_scopes_for_configuration(
    $conn,
    $companyId,
    $uiConfigId,
    array_merge($scopesBeforeWrite, ['tickets.write']),
    $employeeId
)) {
    api_v2_fail('grant tickets.write scope');
} else {
    api_v2_pass('grant tickets.write scope');
}

$created = itm_api_v2_tickets_create($conn, $companyId, $employeeId, ['title' => $createdTitle]);
if (!is_array($created) || (string)($created['title'] ?? '') !== $createdTitle) {
    api_v2_fail('ticket create with write scope');
} else {
    api_v2_pass('ticket create with write scope');
    $newTicketId = (int)($created['id'] ?? 0);
    if ($newTicketId > 0) {
        mysqli_query(
            $conn,
            'UPDATE tickets SET deleted_at = NOW(), deleted_by = ' . (int)$employeeId . ', active = 0 WHERE id = ' . $newTicketId . ' AND company_id = ' . (int)$companyId
        );
    }
}

$openapiDoc = itm_api_v2_openapi_build_document();
if (is_array($openapiDoc) && isset($openapiDoc['paths']['/tickets']['get'])) {
    api_v2_pass('OpenAPI document includes GET /tickets');
} else {
    api_v2_fail('OpenAPI document missing GET /tickets');
}

itm_apitest_cleanup_configuration($conn, $companyId, $employeeId);

if ($fail > 0) {
    echo "\n[FAIL] {$fail} check(s) failed.\n";
    itm_script_output_end();
    exit(1);
}

echo "\n[PASS] API v2 verification complete.\n";
itm_script_output_end();
exit(0);
