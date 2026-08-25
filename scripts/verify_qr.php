<?php
/**
 * CLI: php scripts/verify_qr.php
 * Regression for modules/qr QR Generator.
 */

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_qr.php</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_qr_generator.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('QR Generator Verification');
$nl = itm_script_output_nl();
$failures = 0;

function qr_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function qr_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

if (!($conn instanceof mysqli)) {
    qr_verify_fail('Database connection unavailable.');
    exit(1);
}

$res = mysqli_query($conn, "SHOW TABLES LIKE 'qr_codes'");
if (!$res || mysqli_num_rows($res) === 0) {
    qr_verify_fail('qr_codes table missing — re-import db/01_schema.sql.');
    exit(1);
}
qr_verify_pass('qr_codes table exists.');

$res2 = mysqli_query($conn, "SHOW TABLES LIKE 'qr_code_scans'");
if (!$res2 || mysqli_num_rows($res2) === 0) {
    qr_verify_fail('qr_code_scans table missing.');
    exit(1);
}
qr_verify_pass('qr_code_scans table exists.');

$catalog = itm_qr_generator_type_catalog();
if (count($catalog) < 16) {
    qr_verify_fail('Type catalog should include at least 16 types.');
} else {
    qr_verify_pass('Type catalog has ' . count($catalog) . ' types.');
}

$wifi = itm_qr_generator_build_static_payload('wifi', [
    'ssid' => 'TestNet',
    'password' => 'secret',
    'encryption' => 'WPA',
    'hidden' => 0,
]);
if (strpos($wifi, 'WIFI:T:WPA;S:TestNet;') === false) {
    qr_verify_fail('WiFi static payload builder failed.');
} else {
    qr_verify_pass('WiFi static payload builder OK.');
}

$url = itm_qr_generator_build_static_payload('website', ['url' => 'example.com']);
if ($url !== 'https://example.com') {
    qr_verify_fail('Website static payload should normalize https.');
} else {
    qr_verify_pass('Website static payload normalization OK.');
}

$token = itm_qr_generator_generate_access_token();
if (strlen($token) !== 64) {
    qr_verify_fail('Access token should be 64 hex chars.');
} else {
    qr_verify_pass('Access token generation OK.');
}

$publicUrl = itm_qr_generator_build_public_url($token);
if (strpos($publicUrl, '/modules/qr/r.php?t=') === false) {
    qr_verify_fail('Public URL builder failed.');
} else {
    qr_verify_pass('Public URL builder OK.');
}

$empId = 1;
$companyId = 1;
$title = 'Verify QR ' . date('Y-m-d H:i:s');
$typeSlug = 'text';
$encodingMode = 'static';
$payload = ['text' => 'ITM verify QR'];
$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
$encoded = itm_qr_generator_build_static_payload($typeSlug, $payload);
$designJson = json_encode(itm_qr_generator_default_design(), JSON_UNESCAPED_UNICODE);
$accessToken = itm_qr_generator_generate_access_token();

$sql = 'INSERT INTO qr_codes (company_id, employee_id, title, type_slug, encoding_mode, payload_json, encoded_payload, access_token, design_json, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    qr_verify_fail('INSERT prepare failed.');
    exit(1);
}
mysqli_stmt_bind_param($stmt, 'iisssssssii', $companyId, $empId, $title, $typeSlug, $encodingMode, $payloadJson, $encoded, $accessToken, $designJson, $empId, $empId);
if (!mysqli_stmt_execute($stmt)) {
    qr_verify_fail('INSERT qr_codes failed.');
    mysqli_stmt_close($stmt);
    exit(1);
}
$newId = (int) mysqli_insert_id($conn);
mysqli_stmt_close($stmt);
qr_verify_pass('Inserted test qr_codes row id=' . $newId);

$row = itm_qr_generator_fetch_by_id($conn, $companyId, $empId, $newId);
if (!$row || (string) $row['encoded_payload'] !== $encoded) {
    qr_verify_fail('fetch_by_id did not return encoded payload.');
} else {
    qr_verify_pass('fetch_by_id OK.');
}

itm_qr_generator_record_scan($conn, $row);
$rowAfter = itm_qr_generator_fetch_by_id($conn, $companyId, $empId, $newId);
if ((int) ($rowAfter['scan_count'] ?? 0) < 1) {
    qr_verify_fail('scan_count should increment after record_scan.');
} else {
    qr_verify_pass('Scan recording increments scan_count.');
}

$resShort = mysqli_query($conn, "SHOW TABLES LIKE 'short_urls'");
if ($resShort && mysqli_num_rows($resShort) > 0) {
    require_once ROOT_PATH . 'includes/itm_short_url.php';
    $shortCreate = itm_short_url_create_from_destination($conn, $companyId, $empId, 'https://example.com/qr-verify-short', ['title' => 'QR verify short']);
    if (empty($shortCreate['ok'])) {
        qr_verify_fail('Short URL create_from_destination for QR integration failed.');
    } else {
        qr_verify_pass('Short URL create_from_destination OK for QR integration.');
        $shortUrlId = (int) $shortCreate['id'];
        $dynToken = itm_qr_generator_generate_access_token();
        $dynPayload = json_encode(['url' => (string) $shortCreate['public_url']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $dynDesign = json_encode(itm_qr_generator_default_design(), JSON_UNESCAPED_UNICODE);
        $dynTitle = 'Verify QR website short ' . date('His');
        $dynSql = 'INSERT INTO qr_codes (company_id, employee_id, title, type_slug, encoding_mode, payload_json, access_token, design_json, short_url_id, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $dynStmt = mysqli_prepare($conn, $dynSql);
        if ($dynStmt) {
            $typeSlugDyn = 'website';
            $modeDyn = 'dynamic';
            mysqli_stmt_bind_param($dynStmt, 'iissssssiii', $companyId, $empId, $dynTitle, $typeSlugDyn, $modeDyn, $dynPayload, $dynToken, $dynDesign, $shortUrlId, $empId, $empId);
            if (mysqli_stmt_execute($dynStmt)) {
                $dynQrId = (int) mysqli_insert_id($conn);
                $dynRow = itm_qr_generator_fetch_by_id($conn, $companyId, $empId, $dynQrId);
                if ($dynRow && (int) ($dynRow['short_url_id'] ?? 0) === $shortUrlId) {
                    qr_verify_pass('Dynamic website QR with short_url_id link OK.');
                } else {
                    qr_verify_fail('Dynamic website QR short_url_id mismatch.');
                }
                mysqli_query($conn, 'DELETE FROM qr_codes WHERE id = ' . $dynQrId);
            } else {
                qr_verify_fail('Dynamic website QR insert with short_url_id failed.');
            }
            mysqli_stmt_close($dynStmt);
        }
        mysqli_query($conn, 'DELETE FROM short_url_clicks WHERE short_url_id = ' . $shortUrlId);
        mysqli_query($conn, 'DELETE FROM short_urls WHERE id = ' . $shortUrlId);
    }
} else {
    qr_verify_pass('short_urls table absent — skip QR/short-url integration probe.');
}

$del = mysqli_prepare($conn, 'UPDATE qr_codes SET active = 0, deleted_by = ?, deleted_at = NOW() WHERE id = ? AND company_id = ?');
mysqli_stmt_bind_param($del, 'iii', $empId, $newId, $companyId);
mysqli_stmt_execute($del);
mysqli_stmt_close($del);
qr_verify_pass('Soft-deleted test row.');

if ($failures > 0) {
    echo colorText('FAILED (' . $failures . ')', 'fail') . $nl;
    exit(1);
}
echo colorText('ALL PASSED', 'pass') . $nl;
exit(0);
