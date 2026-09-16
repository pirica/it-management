<?php
/**
 * HTTP regression for hotel booking distribution API (probe + auth contract).
 *
 * CLI: php scripts/verify_hotel_booking_distribution_http.php
 * Browser: scripts/verify_hotel_booking_distribution_http.php?run=1 (Administrator).
 *
 * Default HTTP target is a local PHP built-in server (not Apache) so browser runs do not deadlock workers.
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/verify_hotel_booking_distribution_http.php</code> — optional <code>--base-url=http://localhost/it-management</code> to curl an existing web server instead of the built-in PHP server. Browser: <a href="verify_hotel_booking_distribution_http.php?run=1">verify_hotel_booking_distribution_http.php?run=1</a> (Administrator). Browser always uses a built-in PHP server for curl (avoids Apache gateway timeout).
<p>Creates a disposable distribution channel, exercises <code>probe</code> and missing-key auth over HTTP, then cleans up.</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once dirname(__DIR__) . '/includes/itm_cli_binary.php';

if (!$itmIsCli) {
    itm_script_require_admin_script_or_exit($conn);
}

/**
 * @return int
 */
function hbd_http_pick_free_port()
{
    $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        return 0;
    }
    if (!@socket_bind($socket, '127.0.0.1', 0)) {
        socket_close($socket);
        return 0;
    }
    $port = 0;
    if (!@socket_getsockname($socket, $bindAddress, $port)) {
        socket_close($socket);
        return 0;
    }
    socket_close($socket);

    return (int) $port;
}

/**
 * @return array{proc:resource,base_url:string,port:int}|null
 */
function hbd_http_start_builtin_server()
{
    $port = hbd_http_pick_free_port();
    if ($port < 1) {
        return null;
    }

    $repoRoot = dirname(__DIR__);
    $phpBin = itm_resolve_cli_php_binary();
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $cmd = [
        $phpBin,
        '-S',
        '127.0.0.1:' . $port,
        '-t',
        $repoRoot,
    ];
    $proc = @proc_open($cmd, $descriptors, $pipes, $repoRoot);
    if (!is_resource($proc)) {
        return null;
    }

    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }

    $ready = false;
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
        if (is_resource($sock)) {
            fclose($sock);
            $ready = true;
            break;
        }
        usleep(100000);
    }

    if (!$ready) {
        proc_terminate($proc);
        proc_close($proc);
        return null;
    }

    return [
        'proc' => $proc,
        'base_url' => 'http://127.0.0.1:' . $port,
        'port' => $port,
    ];
}

/**
 * @param resource $proc
 */
function hbd_http_stop_builtin_server($proc)
{
    if (!is_resource($proc)) {
        return;
    }
    @proc_terminate($proc);
    @proc_close($proc);
}

/**
 * @return array{0:int,1:string}
 */
function hbd_http_request($url, array $headers = [])
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $body = is_string($raw) ? substr($raw, $headerSize) : '';

    return [$httpCode, $body];
}

/**
 * @return int Exit-style failure count (0 = all pass).
 */
function hbd_http_run_verification($conn, $baseUrl)
{
    $fail = 0;
    $failFn = static function ($msg) use (&$fail) {
        $fail++;
        echo "[FAIL] {$msg}\n";
    };
    $passFn = static function ($msg) {
        echo "[PASS] {$msg}\n";
    };

    if (!function_exists('curl_init')) {
        $failFn('curl extension required for HTTP regression');
        return $fail;
    }

    $baseUrl = rtrim((string) $baseUrl, '/');
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
            $passFn('disposable channel insert');
        } else {
            $failFn('disposable channel insert');
        }
        mysqli_stmt_close($ins);
    } else {
        $failFn('disposable channel prepare');
    }

    list($noKeyCode,) = hbd_http_request($apiPath . '?action=probe');
    if ($noKeyCode === 401) {
        $passFn('probe without API key returns 401');
    } else {
        $failFn('probe without API key expected 401 got ' . $noKeyCode);
    }

    if ($channelId > 0) {
        list($probeCode, $probeBody) = hbd_http_request(
            $apiPath . '?action=probe',
            ['X-API-Key: ' . $plainKey, 'Accept: application/json']
        );
        $probeJson = json_decode((string) $probeBody, true);
        if ($probeCode === 200 && is_array($probeJson) && !empty($probeJson['success']) && ($probeJson['channel_code'] ?? '') === $channelCode) {
            $passFn('probe with API key returns channel metadata');
        } else {
            $failFn('probe with API key: HTTP ' . $probeCode . ' body=' . substr((string) $probeBody, 0, 200));
        }

        $hotelRes = mysqli_query($conn, 'SELECT id FROM hotel_booking_hotels WHERE company_id = 1 AND deleted_at IS NULL AND active = 1 LIMIT 1');
        $hotelRow = $hotelRes ? mysqli_fetch_assoc($hotelRes) : null;
        $hotelId = (int) ($hotelRow['id'] ?? 0);
        if ($hotelId > 0) {
            $availUrl = $apiPath . '?action=availability&hotel_id=' . $hotelId . '&check_in=2026-12-01&check_out=2026-12-03&adults=2';
            list($availCode, $availBody) = hbd_http_request($availUrl, ['X-API-Key: ' . $plainKey]);
            $availJson = json_decode((string) $availBody, true);
            if ($availCode === 200 && is_array($availJson) && !empty($availJson['success'])) {
                $passFn('availability GET over HTTP');
            } else {
                $failFn('availability GET: HTTP ' . $availCode);
            }
        } else {
            $failFn('no seed hotel for company 1');
        }

        mysqli_query($conn, 'DELETE FROM hotel_booking_distribution_channels WHERE id = ' . (int) $channelId);
        $passFn('disposable channel cleanup');
    }

    return $fail;
}

itm_script_output_begin('Hotel booking distribution HTTP verification');

$explicitBaseUrl = '';
if ($itmIsCli) {
    foreach ($argv ?? [] as $arg) {
        if (preg_match('/^--base-url=(.+)$/', (string) $arg, $m)) {
            $explicitBaseUrl = rtrim((string) $m[1], '/');
        }
    }
}

$builtinProc = null;
$baseUrl = $explicitBaseUrl;
if ($baseUrl === '') {
    $started = hbd_http_start_builtin_server();
    if ($started === null) {
        echo "[FAIL] Unable to start built-in PHP server for HTTP regression\n";
        itm_script_output_end(1);
        exit(1);
    }
    $builtinProc = $started['proc'];
    $baseUrl = (string) $started['base_url'];
    echo "[PASS] built-in PHP server on 127.0.0.1:" . (int) $started['port'] . " (curl avoids Apache worker deadlock)\n";
}

$failures = hbd_http_run_verification($conn, $baseUrl);

if ($builtinProc !== null) {
    hbd_http_stop_builtin_server($builtinProc);
}

itm_script_output_end($failures === 0 ? 0 : 1);
