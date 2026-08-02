<?php
/**
 * HTTP regression for hotel booking distribution API (probe + auth contract).
 *
 * CLI: php scripts/verify_hotel_booking_distribution_http.php
 * Browser: scripts/verify_hotel_booking_distribution_http.php?run=1 (Administrator).
 *
 * Browser mode delegates to a CLI subprocess so curl against localhost does not deadlock Apache workers.
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/verify_hotel_booking_distribution_http.php</code> — optional <code>--base-url=http://localhost/it-management</code>. Browser: <a href="verify_hotel_booking_distribution_http.php?run=1">verify_hotel_booking_distribution_http.php?run=1</a> (Administrator). Browser runs the same checks in a detached CLI subprocess (avoids gateway timeout when curling localhost).
<p>Creates a disposable distribution channel, exercises <code>probe</code> and missing-key auth over HTTP, then cleans up.</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

if (!$itmIsCli) {
    require_once dirname(__DIR__) . '/includes/itm_cli_binary.php';
    itm_script_require_admin_script_or_exit($conn);
    itm_script_output_begin('Hotel booking distribution HTTP verification');

    $phpBin = itm_resolve_cli_php_binary();
    $scriptPath = __DIR__ . '/verify_hotel_booking_distribution_http.php';
    $cmdArgs = '';
    $baseUrlParam = trim((string) ($_GET['base_url'] ?? $_GET['base-url'] ?? ''));
    if ($baseUrlParam !== '') {
        $cmdArgs = ' --base-url=' . escapeshellarg($baseUrlParam);
    }
    $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($scriptPath) . $cmdArgs . ' 2>&1';
    exec($cmd, $lines, $exitCode);

    echo colorText('[INFO] Browser mode delegates HTTP curl to CLI subprocess (avoids Apache gateway timeout).', 'info') . "\n";
    if ($lines === [] && $exitCode !== 0) {
        echo colorText('[FAIL] CLI subprocess produced no output. Set PHP_EXE in .env to CLI php (not php-cgi).', 'fail') . "\n";
        echo 'PHP binary: ' . itm_script_escape_browser_pre_text($phpBin) . "\n";
    } else {
        foreach ($lines as $line) {
            echo itm_script_format_status_line((string) $line) . "\n";
        }
    }

    itm_script_output_end($exitCode);
    exit($exitCode);
}

itm_script_output_begin('Hotel booking distribution HTTP verification');

$fail = 0;
function hbd_http_fail($msg) {
    global $fail;
    $fail++;
    echo "[FAIL] {$msg}\n";
}
function hbd_http_pass($msg) {
    echo "[PASS] {$msg}\n";
}

$baseUrl = 'http://localhost/it-management';
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--base-url=(.+)$/', (string) $arg, $m)) {
        $baseUrl = rtrim((string) $m[1], '/');
    }
}

if (!function_exists('curl_init')) {
    hbd_http_fail('curl extension required for HTTP regression');
    itm_script_output_end(1);
}

$apiPath = $baseUrl . '/modules/hotel_booking_api/api.php';
$plainKey = itm_hotel_booking_distribution_generate_api_key();
$prefix = itm_hotel_booking_distribution_api_key_prefix($plainKey);
$hash = itm_hotel_booking_distribution_hash_api_key($plainKey);
$companyId = 1;
$channelCode = 'http_' . substr(sha1((string) microtime(true)), 0, 8);
$channelId = 0;

$ins = mysqli_prepare(
    $conn,
    'INSERT INTO hotel_booking_distribution_channels (company_id, channel_code, name, standard, api_key_prefix, api_key_hash, hourly_rate_limit, active, created_at)
     VALUES (?, ?, ?, \'itm_native\', ?, ?, 1000, 1, NOW())'
);
if ($ins) {
    $name = 'HTTP verify channel';
    mysqli_stmt_bind_param($ins, 'issss', $companyId, $channelCode, $name, $prefix, $hash);
    if (mysqli_stmt_execute($ins)) {
        $channelId = (int) mysqli_insert_id($conn);
        hbd_http_pass('disposable channel insert');
    } else {
        hbd_http_fail('disposable channel insert');
    }
    mysqli_stmt_close($ins);
} else {
    hbd_http_fail('disposable channel prepare');
}

$hbd_http_request = static function ($url, array $headers = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $body = is_string($raw) ? substr($raw, $headerSize) : '';
    return [$httpCode, $body];
};

list($noKeyCode,) = $hbd_http_request($apiPath . '?action=probe');
if ($noKeyCode === 401) {
    hbd_http_pass('probe without API key returns 401');
} else {
    hbd_http_fail('probe without API key expected 401 got ' . $noKeyCode);
}

if ($channelId > 0) {
    list($probeCode, $probeBody) = $hbd_http_request(
        $apiPath . '?action=probe',
        ['X-API-Key: ' . $plainKey, 'Accept: application/json']
    );
    $probeJson = json_decode((string) $probeBody, true);
    if ($probeCode === 200 && is_array($probeJson) && !empty($probeJson['success']) && ($probeJson['channel_code'] ?? '') === $channelCode) {
        hbd_http_pass('probe with API key returns channel metadata');
    } else {
        hbd_http_fail('probe with API key: HTTP ' . $probeCode . ' body=' . substr((string) $probeBody, 0, 200));
    }

    $hotelRes = mysqli_query($conn, 'SELECT id FROM hotel_booking_hotels WHERE company_id = 1 AND deleted_at IS NULL AND active = 1 LIMIT 1');
    $hotelRow = $hotelRes ? mysqli_fetch_assoc($hotelRes) : null;
    $hotelId = (int) ($hotelRow['id'] ?? 0);
    if ($hotelId > 0) {
        $availUrl = $apiPath . '?action=availability&hotel_id=' . $hotelId . '&check_in=2026-12-01&check_out=2026-12-03&adults=2';
        list($availCode, $availBody) = $hbd_http_request($availUrl, ['X-API-Key: ' . $plainKey]);
        $availJson = json_decode((string) $availBody, true);
        if ($availCode === 200 && is_array($availJson) && !empty($availJson['success'])) {
            hbd_http_pass('availability GET over HTTP');
        } else {
            hbd_http_fail('availability GET: HTTP ' . $availCode);
        }
    } else {
        hbd_http_fail('no seed hotel for company 1');
    }

    mysqli_query($conn, 'DELETE FROM hotel_booking_distribution_channels WHERE id = ' . (int) $channelId);
    hbd_http_pass('disposable channel cleanup');
}

itm_script_output_end($fail === 0 ? 0 : 1);
