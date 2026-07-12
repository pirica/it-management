<?php
/**
 * Human-style end-to-end sync test for IDF rack/device workflows.
 *
 * Why: validates real HTTP flows used by modules/idfs/view.php and modules/idfs/device.php
 * and asserts synchronization across idf_ports, switch_ports, equipment, and idf_links.
 *
 * Usage (recommended — CLI):
 *   php scripts/idfs_sync_human_test.php
 *
 * Can also be opened in a browser for debugging; output is plain text.
 *
 * Optional env vars:
 *   ITM_BASE_URL   (default: http://localhost/it-management)
 *   ITM_USER       (default: Admin)
 *   ITM_PASS       (default: Admin)
 *   ITM_DB_HOST    (default: 127.0.0.1)
 *   ITM_DB_USER    (default: root)
 *   ITM_DB_PASS    (default: itmanagement)
 *   ITM_DB_NAME    (default: itmanagement)
 *   ITM_COMPANY_ID (default: 4; auto-resolved with ITM_IDF_ID when pair missing)
 *   ITM_IDF_ID     (default: 4; auto-resolved with ITM_COMPANY_ID when pair missing)
 *
 * After login, POSTs to index.php to set session company_id (Admin login otherwise picks the first active company).
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();


function itm_test_is_cli()
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

function itm_test_eol()
{
    return itm_script_output_nl();
}

function itm_test_esc_line($message)
{
    $text = rtrim((string)$message);
    return itm_test_is_cli() ? $text : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function itm_test_browser_init()
{
    static $initialized = false;
    if ($initialized || itm_test_is_cli()) {
        return;
    }
    $initialized = true;
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    require_once __DIR__ . '/lib/script_browser_nav.php';
    $itmIdfSyncTableLabels = ['idf_ports', 'switch_ports', 'equipment', 'idf_links'];
    $itmIdfSyncTableLinks = [];
    foreach ($itmIdfSyncTableLabels as $itmIdfSyncTableName) {
        $itmIdfSyncTableLinks[] = itm_script_format_table_link($itmIdfSyncTableName);
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>IDF sync human test</title></head>'
        . '<body style="font-family:Segoe UI,system-ui,sans-serif;line-height:1.45;margin:16px;max-width:960px;">';
    itm_script_browser_nav_echo();
    echo '<p style="color:#57606a;margin:0 0 14px;">End-to-end sync across '
        . itm_script_format_module_link('idfs', '', 'IDF module')
        . ' · tables '
        . implode(', ', $itmIdfSyncTableLinks)
        . '. CLI is recommended for CI.</p>';
}

function itm_test_browser_close()
{
    if (!itm_test_is_cli()) {
        echo '</body></html>';
    }
}

function itm_test_ensure_plain_text_response_headers()
{
    if (itm_test_is_cli() || headers_sent()) {
        return;
    }
    itm_test_browser_init();
}

function itm_test_write_line($message, $isError = false)
{
    $line = itm_script_format_status_line(itm_test_esc_line($message)) . itm_test_eol();

    if (itm_test_is_cli()) {
        $stream = null;
        if ($isError && defined('STDERR') && is_resource(STDERR)) {
            $stream = STDERR;
        } elseif (!$isError && defined('STDOUT') && is_resource(STDOUT)) {
            $stream = STDOUT;
        }
        if ($stream !== null && @fwrite($stream, $line) !== false) {
            return;
        }
        echo $line;
        return;
    }

    itm_test_ensure_plain_text_response_headers();
    if ($isError) {
        @error_log(rtrim($line));
    }
    echo $line;
    if (function_exists('flush')) {
        @flush();
    }
}

function itm_test_out($message)
{
    itm_test_write_line($message, false);
}

function itm_test_err($message)
{
    itm_test_write_line($message, true);
}

function itm_test_fail($message)
{
    throw new RuntimeException($message);
}

function itm_test_assert($condition, $message)
{
    if (!$condition) {
        itm_test_fail($message);
    }
    itm_test_out('[PASS] ' . $message);
}

function itm_test_http_request(
    $method,
    $url,
    $cookieFile,
    $body,
    array $headers,
    &$statusCode
) {
    $ch = curl_init($url);
    if ($ch === false) {
        itm_test_fail('Failed to initialize cURL.');
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        itm_test_fail('HTTP request failed: ' . $error);
    }

    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return (string)$response;
}

function itm_test_http_parse_location_header($headerBlock)
{
    if (preg_match('/^Location:\s*(.+)$/mi', (string)$headerBlock, $matches) === 1) {
        return trim($matches[1]);
    }

    return '';
}

function itm_test_http_resolve_url($baseUrl, $location)
{
    $location = trim((string)$location);
    if ($location === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $location) === 1) {
        return $location;
    }

    $baseParts = parse_url(rtrim((string)$baseUrl, '/'));
    $scheme = isset($baseParts['scheme']) ? (string)$baseParts['scheme'] : 'http';
    $host = isset($baseParts['host']) ? (string)$baseParts['host'] : 'localhost';
    $port = isset($baseParts['port']) ? ':' . (int)$baseParts['port'] : '';

    if (strpos($location, '/') === 0) {
        return $scheme . '://' . $host . $port . $location;
    }

    $path = isset($baseParts['path']) ? rtrim((string)$baseParts['path'], '/') : '';
    return $scheme . '://' . $host . $port . $path . '/' . ltrim($location, '/');
}

/**
 * Why: CURLOPT_FOLLOWLOCATION is ignored or warns when open_basedir is set; follow Location manually.
 */
function itm_test_http_get_following_redirects($baseUrl, $url, $cookieFile, &$statusCode, $maxHops = 5)
{
    $currentUrl = (string)$url;
    $body = '';

    for ($hop = 0; $hop < $maxHops; $hop++) {
        $ch = curl_init($currentUrl);
        if ($ch === false) {
            itm_test_fail('Failed to initialize cURL.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            itm_test_fail('HTTP request failed: ' . $error);
        }

        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headers = substr((string)$raw, 0, $headerSize);
        $body = substr((string)$raw, $headerSize);

        if (!in_array($statusCode, [301, 302, 303, 307, 308], true)) {
            return (string)$body;
        }

        $location = itm_test_http_parse_location_header($headers);
        if ($location === '') {
            return (string)$body;
        }

        $nextUrl = itm_test_http_resolve_url($baseUrl, $location);
        if ($nextUrl === '') {
            return (string)$body;
        }
        $currentUrl = $nextUrl;
    }

    return (string)$body;
}

function itm_test_extract_csrf($html)
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $html, $matches) === 1) {
        return trim((string)$matches[1]);
    }
    return '';
}

/**
 * Why: Admin login pre-selects the first active company alphabetically; HTTP tests must match ITM_COMPANY_ID.
 */
function itm_test_select_company_in_session($baseUrl, $cookieFile, $companyId)
{
    $companyId = (int)$companyId;
    if ($companyId <= 0) {
        return;
    }

    $indexStatus = 0;
    $indexHtml = itm_test_http_get_following_redirects(
        $baseUrl,
        rtrim($baseUrl, '/') . '/index.php',
        $cookieFile,
        $indexStatus
    );
    itm_test_assert($indexStatus === 200, 'Company selection page is reachable for tenant switch');
    $indexCsrf = itm_test_extract_csrf($indexHtml);
    itm_test_assert($indexCsrf !== '', 'CSRF token extracted from company selection page');

    $selectStatus = 0;
    itm_test_http_request(
        'POST',
        rtrim($baseUrl, '/') . '/index.php',
        $cookieFile,
        http_build_query([
            'csrf_token' => $indexCsrf,
            'company_id' => $companyId,
        ]),
        ['Content-Type: application/x-www-form-urlencoded'],
        $selectStatus
    );
    itm_test_assert($selectStatus === 302 || $selectStatus === 200, 'Company selection POST completed for company_id=' . $companyId);
}

/**
 * @return array{company_id:int,idf_id:int}
 */
function itm_test_resolve_company_and_idf($db, $companyId, $idfId)
{
    $companyId = (int)$companyId;
    $idfId = (int)$idfId;

    if ($companyId > 0 && $idfId > 0) {
        $row = itm_test_db_one(
            $db,
            'SELECT id, company_id FROM idfs WHERE id = ? AND company_id = ? AND active = 1 LIMIT 1',
            'ii',
            [$idfId, $companyId]
        );
        if (is_array($row)) {
            return ['company_id' => (int)$row['company_id'], 'idf_id' => (int)$row['id']];
        }
    }

    $fallback = itm_test_db_one(
        $db,
        'SELECT id, company_id FROM idfs WHERE active = 1 ORDER BY company_id ASC, id ASC LIMIT 1',
        '',
        []
    );
    if (!is_array($fallback)) {
        itm_test_fail('No active IDF rows available for human sync test.');
    }

    return [
        'company_id' => (int)$fallback['company_id'],
        'idf_id' => (int)$fallback['id'],
    ];
}

function itm_test_fetch_idf_view($baseUrl, $idfId, $cookieFile)
{
    $statusCode = 0;
    $html = itm_test_http_request(
        'GET',
        rtrim($baseUrl, '/') . '/modules/idfs/view.php?id=' . (int)$idfId . '&_itm_test=' . time(),
        $cookieFile,
        null,
        [],
        $statusCode
    );
    itm_test_assert($statusCode === 200, 'IDF rack view rendered for UI assertion');
    return $html;
}

function itm_test_extract_position_slot_html($html, $positionNo)
{
    $pattern = '/<div\s+class="idf-slot"[^>]*data-position="' . preg_quote((string)$positionNo, '/') . '"[^>]*>/i';
    if (preg_match($pattern, $html, $matches, PREG_OFFSET_CAPTURE) !== 1) {
        itm_test_fail('Rendered IDF slot not found for position ' . (int)$positionNo);
    }

    $start = (int)$matches[0][1];
    $next = strpos($html, '<div class="idf-slot"', $start + 1);
    if ($next === false) {
        $next = strlen($html);
    }
    return substr($html, $start, $next - $start);
}

function itm_test_assert_slot_contains($slotHtml, $needle, $message, $debug)
{
    if (strpos($slotHtml, $needle) === false) {
        itm_test_fail($message . $debug);
    }
    itm_test_out('[PASS] ' . $message);
}

function itm_test_assert_idf_slot_rendered($html, $positionNo, $positionId, $rj45Count, $layoutName, $layoutSlug, $gridCols, $gridRows, $message, $sfpCount = 0, $visualizerPortTotal = null)
{
    $sfpCount = (int)$sfpCount;
    $rj45Count = (int)$rj45Count;
    $visualizerPortTotal = $visualizerPortTotal !== null ? (int)$visualizerPortTotal : ($rj45Count + $sfpCount);
    $slotHtml = itm_test_extract_position_slot_html($html, $positionNo);
    $slotDebug = '';
    if (preg_match_all('/data-(?:layout|port-total|grid-cols|grid-rows)="[^"]*"/i', $slotHtml, $slotMatches)) {
        $slotDebug = ' Rendered visualizer attrs: ' . implode(' ', array_unique($slotMatches[0]));
    }
    itm_test_assert_slot_contains($slotHtml, 'data-has-device="1"', $message . ' has a device in rendered rack slot', $slotDebug);
    itm_test_assert_slot_contains($slotHtml, 'data-position-id="' . (int)$positionId . '"', $message . ' exposes rendered position id', $slotDebug);
    itm_test_assert_slot_contains($slotHtml, 'data-rj45-count="' . $rj45Count . '"', $message . ' exposes rendered RJ45 count', $slotDebug);
    if ($sfpCount > 0) {
        itm_test_assert_slot_contains($slotHtml, 'data-sfp-count="' . $sfpCount . '"', $message . ' exposes rendered SFP count', $slotDebug);
    }
    itm_test_assert_slot_contains($slotHtml, 'data-layout-name="' . $layoutName . '"', $message . ' exposes rendered numbering layout', $slotDebug);
    itm_test_assert_slot_contains($slotHtml, 'data-layout="' . $layoutSlug . '"', $message . ' renders visualizer with selected layout', $slotDebug);
    itm_test_assert_slot_contains($slotHtml, 'data-port-total="' . $visualizerPortTotal . '"', $message . ' renders visualizer with selected port total', $slotDebug);
    if ($sfpCount <= 0) {
        itm_test_assert_slot_contains($slotHtml, 'data-grid-cols="' . (int)$gridCols . '"', $message . ' renders expected visualizer columns', $slotDebug);
        itm_test_assert_slot_contains($slotHtml, 'data-grid-rows="' . (int)$gridRows . '"', $message . ' renders expected visualizer rows', $slotDebug);
    } elseif (strpos($slotHtml, 'itm-port-grid') === false && strpos($slotHtml, 'itm-device-icon') === false) {
        itm_test_fail($message . ' did not render a port visualizer for mixed RJ45/SFP device');
    } else {
        itm_test_out('[PASS] ' . $message . ' renders mixed RJ45/SFP visualizer blocks');
    }
}

function itm_test_assert_idf_slot_sfp_count($html, $positionNo, $expectedCount, $message)
{
    $slotHtml = itm_test_extract_position_slot_html($html, $positionNo);
    preg_match_all('/data-port-type="sfp(?:_plus)?"/i', $slotHtml, $matches);
    $actualCount = count($matches[0]);
    itm_test_assert(
        $actualCount === (int)$expectedCount,
        $message . ' renders expected Fiber Ports Number dots (' . (int)$expectedCount . ')'
    );
}

function itm_test_api_post_json($baseUrl, $path, $cookieFile, array $payload)
{
    $statusCode = 0;
    $decoded = itm_test_api_post_json_raw($baseUrl, $path, $cookieFile, $payload, $statusCode);
    if ($statusCode >= 400 || empty($decoded['ok'])) {
        $error = (string)($decoded['error'] ?? 'Unknown API error');
        itm_test_fail("API {$path} failed (HTTP {$statusCode}): {$error}");
    }
    return $decoded;
}

function itm_test_api_post_json_raw($baseUrl, $path, $cookieFile, array $payload, &$statusCode)
{
    $statusCode = 0;
    $response = itm_test_http_request(
        'POST',
        rtrim($baseUrl, '/') . $path,
        $cookieFile,
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ['Content-Type: application/json'],
        $statusCode
    );

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        itm_test_fail("Invalid JSON response from {$path} (HTTP {$statusCode}): {$response}");
    }
    return $decoded;
}

function itm_test_api_post_json_expect_fail($baseUrl, $path, $cookieFile, array $payload)
{
    $statusCode = 0;
    $decoded = itm_test_api_post_json_raw($baseUrl, $path, $cookieFile, $payload, $statusCode);
    if (!empty($decoded['ok'])) {
        itm_test_fail("API {$path} unexpectedly succeeded");
    }
    return (string)($decoded['error'] ?? '');
}

function itm_test_db_connect()
{
    $host = getenv('ITM_DB_HOST') ?: '127.0.0.1';
    $user = getenv('ITM_DB_USER') ?: 'root';
    $pass = getenv('ITM_DB_PASS') ?: 'itmanagement';
    $name = getenv('ITM_DB_NAME') ?: 'itmanagement';

    $db = mysqli_connect($host, $user, $pass, $name);
    if (!$db) {
        itm_test_fail('MySQL connection failed: ' . mysqli_connect_error());
    }
    mysqli_set_charset($db, 'utf8mb4');
    return $db;
}

function itm_test_db_one($db, $sql, $types = '', array $params = array())
{
    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        itm_test_fail('Prepare failed: ' . mysqli_error($db) . ' | SQL: ' . $sql);
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        itm_test_fail('Execute failed: ' . $err . ' | SQL: ' . $sql);
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function itm_test_db_exec($db, $sql, $types = '', array $params = array())
{
    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        itm_test_fail('Prepare failed: ' . mysqli_error($db) . ' | SQL: ' . $sql);
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        itm_test_fail('Execute failed: ' . $err . ' | SQL: ' . $sql);
    }
    mysqli_stmt_close($stmt);
}

function itm_test_lookup_status_id($db, $companyId, $status)
{
    $row = itm_test_db_one(
        $db,
        "SELECT id FROM switch_status WHERE company_id = ? AND LOWER(status) = LOWER(?) ORDER BY id ASC LIMIT 1",
        'is',
        [$companyId, $status]
    );
    return (int)($row['id'] ?? 0);
}

function itm_test_lookup_color_id($db, $companyId, $colorName)
{
    $row = itm_test_db_one(
        $db,
        "SELECT id FROM cable_colors WHERE company_id = ? AND LOWER(color_name) = LOWER(?) ORDER BY id ASC LIMIT 1",
        'is',
        [$companyId, $colorName]
    );
    return (int)($row['id'] ?? 0);
}

function itm_test_lookup_rj45_id_by_count($db, $companyId, $portCount)
{
    $row = itm_test_db_one(
        $db,
        "SELECT id
         FROM equipment_rj45
         WHERE company_id = ?
           AND name REGEXP CONCAT('(^|[^0-9])', ?, '([^0-9]|$)')
         ORDER BY id ASC
         LIMIT 1",
        'ii',
        [$companyId, $portCount]
    );
    return (int)($row['id'] ?? 0);
}

function itm_test_lookup_layout_id($db, $companyId, $layoutName)
{
    $row = itm_test_db_one(
        $db,
        "SELECT id
         FROM switch_port_numbering_layout
         WHERE company_id = ?
           AND LOWER(name) = LOWER(?)
         ORDER BY id ASC
         LIMIT 1",
        'is',
        [$companyId, $layoutName]
    );
    return (int)($row['id'] ?? 0);
}

function itm_test_switch_label_column($db)
{
    foreach (['to_patch_port', 'label', 'patch_port'] as $candidate) {
        $row = itm_test_db_one(
            $db,
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'switch_ports'
               AND COLUMN_NAME = ?
             LIMIT 1",
            's',
            [$candidate]
        );
        if ($row) {
            return $candidate;
        }
    }
    return 'to_patch_port';
}

function itm_test_port_type_family($typeLabel)
{
    $raw = strtolower(trim((string)$typeLabel));
    if (strpos($raw, 'sfp') !== false) {
        return 'fiber';
    }
    return 'rj45';
}

function itm_test_lookup_device_type_id($db, $companyId, $typeName)
{
    $row = itm_test_db_one(
        $db,
        "SELECT id
         FROM idf_device_type
         WHERE company_id = ?
           AND LOWER(idfdevicetype_name) = LOWER(?)
         ORDER BY id ASC
         LIMIT 1",
        'is',
        [$companyId, $typeName]
    );
    return (int)($row['id'] ?? 0);
}

function itm_test_lookup_color_hex($db, $companyId, $colorId)
{
    $row = itm_test_db_one(
        $db,
        "SELECT UPPER(hex_color) AS hex_color
         FROM cable_colors
         WHERE company_id = ? AND id = ?
         LIMIT 1",
        'ii',
        [$companyId, $colorId]
    );
    return strtoupper(trim((string)($row['hex_color'] ?? '')));
}

function itm_test_fetch_unlinked_ports($db, $companyId, $idfId)
{
    $ports = [];
    $sql = "SELECT pr.id AS port_id,
                   pr.port_no,
                   pr.port_type,
                   p.id AS position_id,
                   p.position_no,
                   CAST(p.equipment_id AS UNSIGNED) AS equipment_id,
                   COALESCE(spt.type, 'RJ45') AS port_type_label,
                   CASE
                     WHEN sp.id IS NOT NULL THEN 1
                     ELSE 0
                   END AS has_switch_mirror
              FROM idf_ports pr
              JOIN idf_positions p
                ON p.id = pr.position_id
               AND p.company_id = pr.company_id
              LEFT JOIN idf_links l
                ON l.company_id = pr.company_id
               AND (l.port_id_a = pr.id OR l.port_id_b = pr.id)
              LEFT JOIN switch_port_types spt
                ON spt.id = pr.port_type
               AND spt.company_id = pr.company_id
              LEFT JOIN switch_ports sp
                ON sp.company_id = p.company_id
               AND sp.equipment_id = CAST(p.equipment_id AS UNSIGNED)
               AND sp.port_number = pr.port_no
             WHERE pr.company_id = ?
               AND p.idf_id = ?
               AND l.id IS NULL
             ORDER BY p.position_no ASC, pr.port_no ASC";
    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        itm_test_fail('Prepare failed: ' . mysqli_error($db));
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $idfId);
    if (!mysqli_stmt_execute($stmt)) {
        itm_test_fail('Execute failed: ' . mysqli_stmt_error($stmt));
    }
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $ports[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $ports;
}

function itm_test_find_compatible_unlinked_pair(array $ports, $family, $sourceRequiresSwitchMirror = false, array $excludePortIds = array())
{
    $excludePortIds = array_fill_keys(array_map('intval', $excludePortIds), true);
    $filtered = [];
    foreach ($ports as $port) {
        if (isset($excludePortIds[(int)($port['port_id'] ?? 0)])) {
            continue;
        }
        if (itm_test_port_type_family((string)($port['port_type_label'] ?? '')) !== $family) {
            continue;
        }
        $filtered[] = $port;
    }

    $count = count($filtered);
    for ($i = 0; $i < $count; $i++) {
        if ($sourceRequiresSwitchMirror && (int)($filtered[$i]['has_switch_mirror'] ?? 0) !== 1) {
            continue;
        }
        for ($j = 0; $j < $count; $j++) {
            if ($i === $j) {
                continue;
            }
            if (isset($excludePortIds[(int)($filtered[$j]['port_id'] ?? 0)])) {
                continue;
            }
            if ((int)$filtered[$i]['position_id'] === (int)$filtered[$j]['position_id']) {
                continue;
            }
            return [
                'port_a' => $filtered[$i],
                'port_b' => $filtered[$j],
            ];
        }
    }
    return null;
}

function itm_test_find_mixed_family_unlinked_pair(array $ports)
{
    $rj45Ports = [];
    $fiberPorts = [];
    foreach ($ports as $port) {
        $family = itm_test_port_type_family((string)($port['port_type_label'] ?? ''));
        if ($family === 'rj45') {
            $rj45Ports[] = $port;
        } elseif ($family === 'fiber') {
            $fiberPorts[] = $port;
        }
    }

    foreach ($rj45Ports as $rj45Port) {
        foreach ($fiberPorts as $fiberPort) {
            if ((int)$rj45Port['position_id'] === (int)$fiberPort['position_id']) {
                continue;
            }
            return [
                'port_a' => $rj45Port,
                'port_b' => $fiberPort,
            ];
        }
    }
    return null;
}

function itm_test_next_idf_position_no($db, $companyId, $idfId)
{
    $row = itm_test_db_one(
        $db,
        "SELECT COALESCE(MAX(position_no), 0) AS max_position
         FROM idf_positions
         WHERE company_id = ? AND idf_id = ?",
        'ii',
        [$companyId, $idfId]
    );
    return ((int)($row['max_position'] ?? 0)) + 1;
}

function itm_test_unlinked_ports_have_rj45_switch_mirror(array $ports)
{
    foreach ($ports as $port) {
        if (itm_test_port_type_family((string)($port['port_type_label'] ?? '')) !== 'rj45') {
            continue;
        }
        if ((int)($port['has_switch_mirror'] ?? 0) === 1) {
            return true;
        }
    }
    return false;
}

function itm_test_create_temp_switch_rack_position(
    $db,
    $baseUrl,
    $cookieFile,
    $csrf,
    $companyId,
    $idfId,
    $positionNo,
    $switchDeviceTypeId,
    $rj45EightId,
    $layoutVerticalId,
    $statusUnknownId,
    $colorGrayId,
    $nameSuffix,
    array &$createdTempEquipmentIds,
    array &$createdTempPositionIds
) {
    itm_test_assert($positionNo <= 100, 'Temporary link seed position number is within allowed range');

    $equipmentName = 'ITM Human Link Seed ' . $nameSuffix . ' ' . date('YmdHis') . ' ' . mt_rand(1000, 9999);
    itm_test_db_exec(
        $db,
        "INSERT INTO equipment (
            company_id, equipment_type_id, status_id, idf_id, name,
            switch_rj45_id, switch_port_numbering_layout_id, switch_environment_id, active
         ) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, 1)",
        'iiisiii',
        [$companyId, 37, 25, $equipmentName, $rj45EightId, $layoutVerticalId, 8]
    );
    $equipmentId = (int)mysqli_insert_id($db);
    itm_test_assert($equipmentId > 0, 'Temporary link seed equipment created (' . $nameSuffix . ')');
    $createdTempEquipmentIds[] = $equipmentId;

    itm_test_db_exec(
        $db,
        "INSERT INTO switch_ports (
            company_id, equipment_id, hostname, port_type, port_number,
            to_patch_port, status_id, color_id, idf_id, management_id, comments
         ) VALUES (?, ?, ?, 'RJ45', 1, '0', ?, ?, NULL, 8, '')",
        'iisii',
        [$companyId, $equipmentId, $equipmentName, $statusUnknownId, $colorGrayId]
    );

    itm_test_api_post_json(
        $baseUrl,
        '/modules/idfs/api/position_save.php',
        $cookieFile,
        [
            'csrf_token' => $csrf,
            'idf_id' => $idfId,
            'position_no' => $positionNo,
            'device_type' => $switchDeviceTypeId,
            'device_name' => $equipmentName,
            'equipment_id' => $equipmentId,
            'switch_rj45_id' => $rj45EightId,
            'switch_port_numbering_layout_id' => $layoutVerticalId,
            'rj45_count' => 8,
            'notes' => 'ITM HUMAN TEMP LINK SEED ' . strtoupper((string)$nameSuffix),
        ]
    );

    $positionRow = itm_test_db_one(
        $db,
        "SELECT id
         FROM idf_positions
         WHERE company_id = ? AND idf_id = ? AND position_no = ? AND equipment_id = ?
         LIMIT 1",
        'iiii',
        [$companyId, $idfId, $positionNo, $equipmentId]
    );
    $positionId = (int)($positionRow['id'] ?? 0);
    itm_test_assert($positionId > 0, 'Temporary link seed position created (' . $nameSuffix . ')');
    $createdTempPositionIds[] = $positionId;
    itm_test_out('[PASS] Seeded temporary switch position ' . (int)$positionNo . ' for link tests (' . $nameSuffix . ')');
}

function itm_test_create_temp_fiber_only_patch_position(
    $db,
    $baseUrl,
    $cookieFile,
    $csrf,
    $companyId,
    $idfId,
    $positionNo,
    $patchPanelTypeId,
    $layoutVerticalId,
    $fiberPortCount,
    $nameSuffix,
    array &$createdTempPositionIds
) {
    itm_test_assert($positionNo <= 100, 'Temporary fiber seed position number is within allowed range');
    $fiberPortCount = max(1, (int)$fiberPortCount);

    $deviceName = 'ITM Human Test SFP Seed ' . $nameSuffix . ' ' . date('YmdHis') . ' ' . mt_rand(1000, 9999);
    itm_test_api_post_json(
        $baseUrl,
        '/modules/idfs/api/position_save.php',
        $cookieFile,
        [
            'csrf_token' => $csrf,
            'idf_id' => $idfId,
            'position_no' => $positionNo,
            'device_type' => $patchPanelTypeId,
            'device_name' => $deviceName,
            'equipment_id' => '',
            'switch_port_numbering_layout_id' => $layoutVerticalId,
            'switch_fiber_ports_number' => (string)$fiberPortCount,
            'rj45_count' => 0,
            'notes' => 'ITM HUMAN TEST MISMATCH SFP ' . strtoupper((string)$nameSuffix),
        ]
    );

    $positionRow = itm_test_db_one(
        $db,
        "SELECT id
         FROM idf_positions
         WHERE company_id = ? AND idf_id = ? AND position_no = ?
         LIMIT 1",
        'iii',
        [$companyId, $idfId, $positionNo]
    );
    $positionId = (int)($positionRow['id'] ?? 0);
    itm_test_assert($positionId > 0, 'Temporary fiber seed position created (' . $nameSuffix . ')');
    $createdTempPositionIds[] = $positionId;
    itm_test_out('[PASS] Seeded temporary fiber-only patch position ' . (int)$positionNo . ' for port-type tests (' . $nameSuffix . ')');
}

function itm_test_unlinked_ports_have_family(array $ports, $family)
{
    foreach ($ports as $port) {
        if (itm_test_port_type_family((string)($port['port_type_label'] ?? '')) === $family) {
            return true;
        }
    }
    return false;
}

function itm_test_ensure_mixed_family_unlinked_pair(
    $db,
    $baseUrl,
    $cookieFile,
    $csrf,
    $companyId,
    $idfId,
    $switchDeviceTypeId,
    $patchPanelTypeId,
    $rj45EightId,
    $layoutVerticalId,
    $statusUnknownId,
    $colorGrayId,
    array &$createdTempEquipmentIds,
    array &$createdTempPositionIds
) {
    $unlinkedPorts = itm_test_fetch_unlinked_ports($db, $companyId, $idfId);
    $mixedPair = itm_test_find_mixed_family_unlinked_pair($unlinkedPorts);
    if ($mixedPair !== null) {
        return $mixedPair;
    }

    if (!itm_test_unlinked_ports_have_family($unlinkedPorts, 'rj45')) {
        $positionNo = itm_test_next_idf_position_no($db, $companyId, $idfId);
        itm_test_create_temp_switch_rack_position(
            $db,
            $baseUrl,
            $cookieFile,
            $csrf,
            $companyId,
            $idfId,
            $positionNo,
            $switchDeviceTypeId,
            $rj45EightId,
            $layoutVerticalId,
            $statusUnknownId,
            $colorGrayId,
            'MISMATCH_RJ45',
            $createdTempEquipmentIds,
            $createdTempPositionIds
        );
        $unlinkedPorts = itm_test_fetch_unlinked_ports($db, $companyId, $idfId);
    }

    if (!itm_test_unlinked_ports_have_family($unlinkedPorts, 'fiber')) {
        $positionNo = itm_test_next_idf_position_no($db, $companyId, $idfId);
        itm_test_create_temp_fiber_only_patch_position(
            $db,
            $baseUrl,
            $cookieFile,
            $csrf,
            $companyId,
            $idfId,
            $positionNo,
            $patchPanelTypeId,
            $layoutVerticalId,
            2,
            'MISMATCH_SFP',
            $createdTempPositionIds
        );
    }

    $unlinkedPorts = itm_test_fetch_unlinked_ports($db, $companyId, $idfId);
    $mixedPair = itm_test_find_mixed_family_unlinked_pair($unlinkedPorts);
    itm_test_assert($mixedPair !== null, 'Found RJ45 and SFP unlinked ports for port-type mismatch test after TEST seed');
    return $mixedPair;
}

function itm_test_ensure_rj45_link_test_pair(
    $db,
    $baseUrl,
    $cookieFile,
    $csrf,
    $companyId,
    $idfId,
    $switchDeviceTypeId,
    $rj45EightId,
    $layoutVerticalId,
    $statusUnknownId,
    $colorGrayId,
    array &$createdTempEquipmentIds,
    array &$createdTempPositionIds
) {
    $unlinkedPorts = itm_test_fetch_unlinked_ports($db, $companyId, $idfId);
    $rj45Pair = itm_test_find_compatible_unlinked_pair($unlinkedPorts, 'rj45', true);
    if ($rj45Pair !== null) {
        return $rj45Pair;
    }

    if (empty($unlinkedPorts)) {
        $positionNo = itm_test_next_idf_position_no($db, $companyId, $idfId);
        itm_test_create_temp_switch_rack_position(
            $db,
            $baseUrl,
            $cookieFile,
            $csrf,
            $companyId,
            $idfId,
            $positionNo,
            $switchDeviceTypeId,
            $rj45EightId,
            $layoutVerticalId,
            $statusUnknownId,
            $colorGrayId,
            'A',
            $createdTempEquipmentIds,
            $createdTempPositionIds
        );
        $positionNoB = $positionNo + 1;
        itm_test_assert($positionNoB <= 100, 'Second temporary link seed position number is within allowed range');
        itm_test_create_temp_switch_rack_position(
            $db,
            $baseUrl,
            $cookieFile,
            $csrf,
            $companyId,
            $idfId,
            $positionNoB,
            $switchDeviceTypeId,
            $rj45EightId,
            $layoutVerticalId,
            $statusUnknownId,
            $colorGrayId,
            'B',
            $createdTempEquipmentIds,
            $createdTempPositionIds
        );
    } else {
        if (!itm_test_unlinked_ports_have_rj45_switch_mirror($unlinkedPorts)) {
            $positionNo = itm_test_next_idf_position_no($db, $companyId, $idfId);
            itm_test_create_temp_switch_rack_position(
                $db,
                $baseUrl,
                $cookieFile,
                $csrf,
                $companyId,
                $idfId,
                $positionNo,
                $switchDeviceTypeId,
                $rj45EightId,
                $layoutVerticalId,
                $statusUnknownId,
                $colorGrayId,
                'SRC',
                $createdTempEquipmentIds,
                $createdTempPositionIds
            );
        }
        $positionNo = itm_test_next_idf_position_no($db, $companyId, $idfId);
        itm_test_create_temp_switch_rack_position(
            $db,
            $baseUrl,
            $cookieFile,
            $csrf,
            $companyId,
            $idfId,
            $positionNo,
            $switchDeviceTypeId,
            $rj45EightId,
            $layoutVerticalId,
            $statusUnknownId,
            $colorGrayId,
            'DST',
            $createdTempEquipmentIds,
            $createdTempPositionIds
        );
    }

    $unlinkedPorts = itm_test_fetch_unlinked_ports($db, $companyId, $idfId);
    itm_test_assert(!empty($unlinkedPorts), 'At least one unlinked IDF port exists for human link tests');
    $rj45Pair = itm_test_find_compatible_unlinked_pair($unlinkedPorts, 'rj45', true);
    itm_test_assert($rj45Pair !== null, 'Found compatible RJ45 unlinked port pair for sync test');
    return $rj45Pair;
}

function itm_test_assert_linked_ports_color_sync($db, $portAId, $portBId, $expectedHex)
{
    $expectedHex = strtoupper(trim($expectedHex));
    $rowA = itm_test_db_one(
        $db,
        "SELECT UPPER(hex_color) AS hex_color, cable_color FROM idf_ports WHERE id = ?",
        'i',
        [$portAId]
    );
    $rowB = itm_test_db_one(
        $db,
        "SELECT UPPER(hex_color) AS hex_color, cable_color FROM idf_ports WHERE id = ?",
        'i',
        [$portBId]
    );
    itm_test_assert(strtoupper((string)($rowA['hex_color'] ?? '')) === $expectedHex, 'Source port hex_color matches selected cable color');
    itm_test_assert(strtoupper((string)($rowB['hex_color'] ?? '')) === $expectedHex, 'Destination port hex_color matches selected cable color');

    $linkRow = itm_test_db_one(
        $db,
        "SELECT UPPER(cable_color_hex) AS cable_color_hex, cable_color_id
         FROM idf_links
         WHERE (port_id_a = ? AND port_id_b = ?) OR (port_id_a = ? AND port_id_b = ?)
         LIMIT 1",
        'iiii',
        [$portAId, $portBId, $portBId, $portAId]
    );
    itm_test_assert(strtoupper((string)($linkRow['cable_color_hex'] ?? '')) === $expectedHex, 'idf_links.cable_color_hex matches selected cable color');
}

function itm_test_assert_slot_shows_cable_hex($slotHtml, $expectedHex, $message)
{
    $needle = strtoupper(trim($expectedHex));
    itm_test_assert(stripos($slotHtml, $needle) !== false, $message . ' shows cable hex ' . $needle . ' in rack visualizer');
}

function itm_test_assert_slot_port_shows_cable_hex($slotHtml, $portId, $expectedHex, $message)
{
    $portId = (int)$portId;
    $needle = strtoupper(trim($expectedHex));
    $pattern = '/data-port-id="' . $portId . '"[^>]*style="[^"]*background-color:\s*' . preg_quote($needle, '/') . '/i';
    if (preg_match($pattern, $slotHtml) !== 1) {
        $patternAlt = '/data-port-id="' . $portId . '"[^>]*style="[^"]*background:\s*' . preg_quote($needle, '/') . '/i';
        itm_test_assert(preg_match($patternAlt, $slotHtml) === 1, $message . ' port ' . $portId . ' shows cable hex ' . $needle);
        return;
    }
    itm_test_assert(true, $message . ' port ' . $portId . ' shows cable hex ' . $needle);
}

$baseUrl = getenv('ITM_BASE_URL') ?: 'http://localhost/it-management';
$username = getenv('ITM_USER') ?: 'Admin';
$password = getenv('ITM_PASS') ?: 'Admin';
$companyId = (int)(getenv('ITM_COMPANY_ID') ?: '4');
$idfId = (int)(getenv('ITM_IDF_ID') ?: '4');

$cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'itm_sync_human_' . uniqid('', true) . '.cookie';
$db = null;
$createdTempEquipmentId = 0;
$createdTempEquipmentIds = [];
$createdTempPositionIds = [];
$createdTempLinkIds = [];
$itmTestExitCode = 0;

itm_test_browser_init();

try {
    $db = itm_test_db_connect();
    $resolvedTenant = itm_test_resolve_company_and_idf($db, $companyId, $idfId);
    $companyId = (int)$resolvedTenant['company_id'];
    $idfId = (int)$resolvedTenant['idf_id'];
    itm_test_out('[INFO] Using company_id=' . $companyId . ' idf_id=' . $idfId . ' for HTTP sync test.');
    $switchLabelColumn = itm_test_switch_label_column($db);

    $statusUpId = itm_test_lookup_status_id($db, $companyId, 'Up');
    $statusDownId = itm_test_lookup_status_id($db, $companyId, 'Down');
    $statusUnknownId = itm_test_lookup_status_id($db, $companyId, 'Unknown');
    $colorGreenId = itm_test_lookup_color_id($db, $companyId, 'Green');
    $colorRedId = itm_test_lookup_color_id($db, $companyId, 'Red');
    $colorGrayId = itm_test_lookup_color_id($db, $companyId, 'Gray');
    $rj45EightId = itm_test_lookup_rj45_id_by_count($db, $companyId, 8);
    $rj45TwentyFourId = itm_test_lookup_rj45_id_by_count($db, $companyId, 24);
    $layoutVerticalId = itm_test_lookup_layout_id($db, $companyId, 'Vertical');
    $layoutHorizontalId = itm_test_lookup_layout_id($db, $companyId, 'Horizontal');
    $legacyVerticalLayout = itm_test_db_one(
        $db,
        "SELECT id
         FROM switch_port_numbering_layout
         WHERE company_id <> ?
           AND LOWER(name) = 'vertical'
         ORDER BY id ASC
         LIMIT 1",
        'i',
        [$companyId]
    );
    $legacyVerticalLayoutId = (int)($legacyVerticalLayout['id'] ?? 0);
    itm_test_assert($statusUpId > 0 && $statusDownId > 0 && $statusUnknownId > 0, 'Switch status IDs are available (Up/Down/Unknown)');
    itm_test_assert($colorGreenId > 0 && $colorRedId > 0 && $colorGrayId > 0, 'Cable color IDs are available (Green/Red/Gray)');
    itm_test_assert($rj45EightId > 0 && $rj45TwentyFourId > 0, 'RJ45 capacity options are available (8/24)');
    itm_test_assert($layoutVerticalId > 0 && $layoutHorizontalId > 0, 'Numbering layout options are available (Vertical/Horizontal)');
    itm_test_assert($legacyVerticalLayoutId > 0, 'Legacy non-company Vertical layout option exists for linked-equipment fallback test');

    $loginPageStatus = 0;
    $loginPageHtml = itm_test_http_request('GET', rtrim($baseUrl, '/') . '/login.php', $cookieFile, null, [], $loginPageStatus);
    itm_test_assert($loginPageStatus === 200, 'Login page is reachable');
    $loginCsrf = itm_test_extract_csrf($loginPageHtml);
    itm_test_assert($loginCsrf !== '', 'CSRF token extracted from login page');

    $loginStatus = 0;
    $loginBody = http_build_query([
        'csrf_token' => $loginCsrf,
        'email' => $username,
        'password' => $password,
    ]);
    itm_test_http_request(
        'POST',
        rtrim($baseUrl, '/') . '/login.php',
        $cookieFile,
        $loginBody,
        ['Content-Type: application/x-www-form-urlencoded'],
        $loginStatus
    );
    itm_test_assert($loginStatus === 302 || $loginStatus === 200, 'Login request completed');

    itm_test_select_company_in_session($baseUrl, $cookieFile, $companyId);

    $idfViewStatus = 0;
    $idfViewHtml = itm_test_http_request(
        'GET',
        rtrim($baseUrl, '/') . '/modules/idfs/view.php?id=' . $idfId,
        $cookieFile,
        null,
        [],
        $idfViewStatus
    );
    itm_test_assert($idfViewStatus === 200, 'IDF view page is reachable after login');
    $csrf = itm_test_extract_csrf($idfViewHtml);
    itm_test_assert($csrf !== '', 'CSRF token extracted from IDF view page');

    $switchTypeRow = itm_test_db_one(
        $db,
        "SELECT id FROM idf_device_type WHERE company_id = ? AND LOWER(idfdevicetype_name) = 'switch' ORDER BY id ASC LIMIT 1",
        'i',
        [$companyId]
    );
    $switchDeviceTypeId = (int)($switchTypeRow['id'] ?? 0);
    itm_test_assert($switchDeviceTypeId > 0, 'IDF switch device type exists for temp create/delete sync test');

    $rj45Pair = itm_test_ensure_rj45_link_test_pair(
        $db,
        $baseUrl,
        $cookieFile,
        $csrf,
        $companyId,
        $idfId,
        $switchDeviceTypeId,
        $rj45EightId,
        $layoutVerticalId,
        $statusUnknownId,
        $colorGrayId,
        $createdTempEquipmentIds,
        $createdTempPositionIds
    );
    $portA = $rj45Pair['port_a'];
    $portB = $rj45Pair['port_b'];

    $portAId = (int)$portA['port_id'];
    $portBId = (int)$portB['port_id'];
    $portANumber = (int)$portA['port_no'];
    $portATypeId = (int)$portA['port_type'];
    $portAEquipmentId = (int)$portA['equipment_id'];
    $portAPositionNo = (int)$portA['position_no'];
    $portBPositionNo = (int)$portB['position_no'];

    $linkCreate = itm_test_api_post_json(
        $baseUrl,
        '/modules/idfs/api/link_create.php',
        $cookieFile,
        [
            'csrf_token' => $csrf,
            'port_id_a' => $portAId,
            'port_id_b' => $portBId,
            'status_id' => $statusUpId,
            'cable_color_id' => $colorGreenId,
            'cable_label' => 'ITM HUMAN TEST LINK',
            'notes' => 'ITM HUMAN TEST NOTES',
        ]
    );
    $linkId = (int)($linkCreate['link_id'] ?? 0);
    itm_test_assert($linkId > 0, 'Link created successfully');
    $createdTempLinkIds[] = $linkId;

    $greenHex = itm_test_lookup_color_hex($db, $companyId, $colorGreenId);
    itm_test_assert_linked_ports_color_sync($db, $portAId, $portBId, $greenHex);
    $rackAfterLinkHtml = itm_test_fetch_idf_view($baseUrl, $idfId, $cookieFile);
    $slotAfterLinkA = itm_test_extract_position_slot_html($rackAfterLinkHtml, $portAPositionNo);
    $slotAfterLinkB = itm_test_extract_position_slot_html($rackAfterLinkHtml, $portBPositionNo);
    itm_test_assert_slot_port_shows_cable_hex($slotAfterLinkA, $portAId, $greenHex, 'Source rack slot after Green link create');
    itm_test_assert_slot_port_shows_cable_hex($slotAfterLinkB, $portBId, $greenHex, 'Destination rack slot after Green link create');

    $linkCountRow = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM idf_links
         WHERE company_id = ?
           AND ((port_id_a = ? AND port_id_b = ?) OR (port_id_a = ? AND port_id_b = ?))",
        'iiiii',
        [$companyId, $portAId, $portBId, $portBId, $portAId]
    );
    itm_test_assert((int)($linkCountRow['c'] ?? 0) === 2, 'Bi-directional idf_links rows were created');

    $portStatusRowA = itm_test_db_one(
        $db,
        "SELECT status_id, cable_color, hex_color
         FROM idf_ports
         WHERE id = ?",
        'i',
        [$portAId]
    );
    $portStatusRowB = itm_test_db_one(
        $db,
        "SELECT status_id, cable_color, hex_color
         FROM idf_ports
         WHERE id = ?",
        'i',
        [$portBId]
    );
    itm_test_assert((int)($portStatusRowA['status_id'] ?? 0) === $statusUpId, 'Source idf_ports status synced to Up after create-link');
    itm_test_assert((int)($portStatusRowB['status_id'] ?? 0) === $statusUpId, 'Destination idf_ports status synced to Up after create-link');
    itm_test_assert(strcasecmp((string)($portStatusRowA['cable_color'] ?? ''), 'Green') === 0, 'Source idf_ports cable color synced to Green after create-link');
    itm_test_assert(strcasecmp((string)($portStatusRowB['cable_color'] ?? ''), 'Green') === 0, 'Destination idf_ports cable color synced to Green after create-link');

    $switchPortRow = itm_test_db_one(
        $db,
        "SELECT id, status_id, color_id, comments, {$switchLabelColumn} AS label
         FROM switch_ports
         WHERE company_id = ? AND equipment_id = ? AND port_number = ?
         ORDER BY id ASC
         LIMIT 1",
        'iii',
        [$companyId, $portAEquipmentId, $portANumber]
    );
    itm_test_assert($switchPortRow !== null, 'Source switch_ports row found for sync assertion');
    itm_test_assert((int)($switchPortRow['status_id'] ?? 0) === $statusUpId, 'Source switch_ports status synced to Up after create-link');
    itm_test_assert((int)($switchPortRow['color_id'] ?? 0) === $colorGreenId, 'Source switch_ports color synced to Green after create-link');

    itm_test_api_post_json(
        $baseUrl,
        '/modules/idfs/api/port_update.php',
        $cookieFile,
        [
            'csrf_token' => $csrf,
            'port_id' => $portAId,
            'port_type_id' => $portATypeId,
            'status_id' => $statusDownId,
            'cable_color_id' => $colorRedId,
            'label' => 'ITM HUMAN TEST LABEL',
            'connected_to' => '',
            'notes' => 'ITM HUMAN TEST NOTES',
        ]
    );
    itm_test_out('[PASS] Port edit API completed');

    $portAfterEditA = itm_test_db_one(
        $db,
        "SELECT status_id, cable_color FROM idf_ports WHERE id = ?",
        'i',
        [$portAId]
    );
    $portAfterEditB = itm_test_db_one(
        $db,
        "SELECT status_id, cable_color FROM idf_ports WHERE id = ?",
        'i',
        [$portBId]
    );
    itm_test_assert((int)($portAfterEditA['status_id'] ?? 0) === $statusDownId, 'Source idf_ports status synced to Down after edit');
    itm_test_assert((int)($portAfterEditB['status_id'] ?? 0) === $statusDownId, 'Peer idf_ports status synced to Down after edit');
    itm_test_assert(strcasecmp((string)($portAfterEditA['cable_color'] ?? ''), 'Red') === 0, 'Source idf_ports color synced to Red after edit');
    itm_test_assert(strcasecmp((string)($portAfterEditB['cable_color'] ?? ''), 'Red') === 0, 'Peer idf_ports color synced to Red after edit');

    $switchAfterEdit = itm_test_db_one(
        $db,
        "SELECT status_id, color_id, comments, {$switchLabelColumn} AS label
         FROM switch_ports
         WHERE company_id = ? AND equipment_id = ? AND port_number = ?
         ORDER BY id ASC
         LIMIT 1",
        'iii',
        [$companyId, $portAEquipmentId, $portANumber]
    );
    itm_test_assert((int)($switchAfterEdit['status_id'] ?? 0) === $statusDownId, 'Source switch_ports status synced to Down after edit');
    itm_test_assert((int)($switchAfterEdit['color_id'] ?? 0) === $colorRedId, 'Source switch_ports color synced to Red after edit');

    $linkMetaAfterEdit = itm_test_db_one(
        $db,
        "SELECT equipment_status_id, cable_color_id
         FROM idf_links
         WHERE company_id = ? AND port_id_a = ? AND port_id_b = ?
         LIMIT 1",
        'iii',
        [$companyId, $portAId, $portBId]
    );
    itm_test_assert((int)($linkMetaAfterEdit['equipment_status_id'] ?? 0) === $statusDownId, 'idf_links equipment_status_id synced after edit');
    itm_test_assert((int)($linkMetaAfterEdit['cable_color_id'] ?? 0) === $colorRedId, 'idf_links cable_color_id synced after edit');

    itm_test_api_post_json(
        $baseUrl,
        '/modules/idfs/api/link_delete.php',
        $cookieFile,
        [
            'csrf_token' => $csrf,
            'link_id' => $linkId,
        ]
    );
    itm_test_out('[PASS] Unlink API completed');

    $linkCountAfterDelete = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM idf_links
         WHERE company_id = ?
           AND ((port_id_a = ? AND port_id_b = ?) OR (port_id_a = ? AND port_id_b = ?))",
        'iiiii',
        [$companyId, $portAId, $portBId, $portBId, $portAId]
    );
    itm_test_assert((int)($linkCountAfterDelete['c'] ?? 0) === 0, 'Both idf_links rows removed after unlink');

    $portAfterUnlinkA = itm_test_db_one(
        $db,
        "SELECT status_id, cable_color, hex_color, connected_to
         FROM idf_ports
         WHERE id = ?",
        'i',
        [$portAId]
    );
    $portAfterUnlinkB = itm_test_db_one(
        $db,
        "SELECT status_id, cable_color, hex_color, connected_to
         FROM idf_ports
         WHERE id = ?",
        'i',
        [$portBId]
    );
    itm_test_assert((int)($portAfterUnlinkA['status_id'] ?? 0) === $statusUnknownId, 'Source idf_ports status reset to Unknown after unlink');
    itm_test_assert((int)($portAfterUnlinkB['status_id'] ?? 0) === $statusUnknownId, 'Peer idf_ports status reset to Unknown after unlink');
    itm_test_assert(strcasecmp((string)($portAfterUnlinkA['cable_color'] ?? ''), 'Gray') === 0, 'Source idf_ports cable color reset to Gray after unlink');
    itm_test_assert(strcasecmp((string)($portAfterUnlinkB['cable_color'] ?? ''), 'Gray') === 0, 'Peer idf_ports cable color reset to Gray after unlink');

    $switchAfterUnlink = itm_test_db_one(
        $db,
        "SELECT status_id, color_id
         FROM switch_ports
         WHERE company_id = ? AND equipment_id = ? AND port_number = ?
         ORDER BY id ASC
         LIMIT 1",
        'iii',
        [$companyId, $portAEquipmentId, $portANumber]
    );
    itm_test_assert((int)($switchAfterUnlink['status_id'] ?? 0) === $statusUnknownId, 'Source switch_ports status reset to Unknown after unlink');
    itm_test_assert((int)($switchAfterUnlink['color_id'] ?? 0) === $colorGrayId, 'Source switch_ports color reset to Gray after unlink');

    $usedPortIds = [$portAId, $portBId];
    $randomColorCases = [
        ['name' => 'Green', 'id' => $colorGreenId],
        ['name' => 'Red', 'id' => $colorRedId],
        ['name' => 'Gray', 'id' => $colorGrayId],
    ];
    shuffle($randomColorCases);
    foreach (array_slice($randomColorCases, 0, 2) as $randomColorCase) {
        $unlinkedPorts = itm_test_fetch_unlinked_ports($db, $companyId, $idfId);
        $randomPair = itm_test_find_compatible_unlinked_pair($unlinkedPorts, 'rj45', false, $usedPortIds);
        if ($randomPair === null) {
            $extraPositionNo = itm_test_next_idf_position_no($db, $companyId, $idfId);
            itm_test_create_temp_switch_rack_position(
                $db,
                $baseUrl,
                $cookieFile,
                $csrf,
                $companyId,
                $idfId,
                $extraPositionNo,
                $switchDeviceTypeId,
                $rj45EightId,
                $layoutVerticalId,
                $statusUnknownId,
                $colorGrayId,
                'RANDOM_' . strtoupper((string)$randomColorCase['name']),
                $createdTempEquipmentIds,
                $createdTempPositionIds
            );
            $extraPositionNoB = $extraPositionNo + 1;
            itm_test_assert($extraPositionNoB <= 100, 'Second random-link seed position number is within allowed range');
            itm_test_create_temp_switch_rack_position(
                $db,
                $baseUrl,
                $cookieFile,
                $csrf,
                $companyId,
                $idfId,
                $extraPositionNoB,
                $switchDeviceTypeId,
                $rj45EightId,
                $layoutVerticalId,
                $statusUnknownId,
                $colorGrayId,
                'RANDOM_' . strtoupper((string)$randomColorCase['name']) . '_B',
                $createdTempEquipmentIds,
                $createdTempPositionIds
            );
            $unlinkedPorts = itm_test_fetch_unlinked_ports($db, $companyId, $idfId);
            $randomPair = itm_test_find_compatible_unlinked_pair($unlinkedPorts, 'rj45', false, $usedPortIds);
        }
        if ($randomPair === null) {
            itm_test_out('[SKIP] No extra RJ45 pair available for random cable color link test (' . $randomColorCase['name'] . ')');
            continue;
        }

        $randomPortA = $randomPair['port_a'];
        $randomPortB = $randomPair['port_b'];
        $randomPortAId = (int)$randomPortA['port_id'];
        $randomPortBId = (int)$randomPortB['port_id'];
        $randomColorId = (int)$randomColorCase['id'];
        $randomHex = itm_test_lookup_color_hex($db, $companyId, $randomColorId);

        $randomLinkCreate = itm_test_api_post_json(
            $baseUrl,
            '/modules/idfs/api/link_create.php',
            $cookieFile,
            [
                'csrf_token' => $csrf,
                'port_id_a' => $randomPortAId,
                'port_id_b' => $randomPortBId,
                'status_id' => $statusUpId,
                'cable_color_id' => $randomColorId,
                'cable_label' => 'ITM HUMAN RANDOM ' . $randomColorCase['name'],
                'notes' => 'ITM HUMAN RANDOM LINK',
            ]
        );
        $randomLinkId = (int)($randomLinkCreate['link_id'] ?? 0);
        itm_test_assert($randomLinkId > 0, 'Random ' . $randomColorCase['name'] . ' link created successfully');
        $createdTempLinkIds[] = $randomLinkId;
        $usedPortIds[] = $randomPortAId;
        $usedPortIds[] = $randomPortBId;

        itm_test_assert_linked_ports_color_sync($db, $randomPortAId, $randomPortBId, $randomHex);
        $rackRandomHtml = itm_test_fetch_idf_view($baseUrl, $idfId, $cookieFile);
        $randomSlotA = itm_test_extract_position_slot_html($rackRandomHtml, (int)$randomPortA['position_no']);
        $randomSlotB = itm_test_extract_position_slot_html($rackRandomHtml, (int)$randomPortB['position_no']);
        itm_test_assert_slot_port_shows_cable_hex(
            $randomSlotA,
            $randomPortAId,
            $randomHex,
            'Random ' . $randomColorCase['name'] . ' link source rack slot'
        );
        itm_test_assert_slot_port_shows_cable_hex(
            $randomSlotB,
            $randomPortBId,
            $randomHex,
            'Random ' . $randomColorCase['name'] . ' link destination rack slot'
        );

        itm_test_api_post_json(
            $baseUrl,
            '/modules/idfs/api/link_delete.php',
            $cookieFile,
            [
                'csrf_token' => $csrf,
                'link_id' => $randomLinkId,
            ]
        );
        itm_test_out('[PASS] Random ' . $randomColorCase['name'] . ' link deleted after cable color sync assertion');
    }

    $patchPanelTypeId = itm_test_lookup_device_type_id($db, $companyId, 'patch_panel');
    itm_test_assert($patchPanelTypeId > 0, 'Patch panel IDF device type exists for unlinked fiber-only test');

    $mixedPair = itm_test_ensure_mixed_family_unlinked_pair(
        $db,
        $baseUrl,
        $cookieFile,
        $csrf,
        $companyId,
        $idfId,
        $switchDeviceTypeId,
        $patchPanelTypeId,
        $rj45EightId,
        $layoutVerticalId,
        $statusUnknownId,
        $colorGrayId,
        $createdTempEquipmentIds,
        $createdTempPositionIds
    );
    $mismatchError = itm_test_api_post_json_expect_fail(
        $baseUrl,
        '/modules/idfs/api/link_create.php',
        $cookieFile,
        [
            'csrf_token' => $csrf,
            'port_id_a' => (int)$mixedPair['port_a']['port_id'],
            'port_id_b' => (int)$mixedPair['port_b']['port_id'],
            'status_id' => $statusUpId,
            'cable_color_id' => $colorGreenId,
        ]
    );
    itm_test_assert(stripos($mismatchError, 'Cannot link') !== false, 'RJ45↔SFP link attempt is rejected with port-type message');
    $unlinkedPatchPositionRow = itm_test_db_one(
        $db,
        "SELECT COALESCE(MAX(position_no), 0) AS max_position
         FROM idf_positions
         WHERE company_id = ? AND idf_id = ?",
        'ii',
        [$companyId, $idfId]
    );
    $unlinkedPatchPositionNo = ((int)($unlinkedPatchPositionRow['max_position'] ?? 0)) + 1;
    itm_test_assert($unlinkedPatchPositionNo <= 100, 'Unlinked patch panel test position is within allowed range');
    $unlinkedPatchName = 'ITM Human Unlinked Patch ' . date('YmdHis');

    itm_test_api_post_json(
        $baseUrl,
        '/modules/idfs/api/position_save.php',
        $cookieFile,
        [
            'csrf_token' => $csrf,
            'idf_id' => $idfId,
            'position_no' => $unlinkedPatchPositionNo,
            'device_type' => $patchPanelTypeId,
            'device_name' => $unlinkedPatchName,
            'equipment_id' => '',
            'switch_port_numbering_layout_id' => $layoutVerticalId,
            'switch_fiber_ports_number' => '4',
            'rj45_count' => 0,
            'notes' => 'ITM HUMAN UNLINKED PATCH PANEL',
        ]
    );

    $unlinkedPatchPosition = itm_test_db_one(
        $db,
        "SELECT id, equipment_id
         FROM idf_positions
         WHERE company_id = ? AND idf_id = ? AND position_no = ?
         LIMIT 1",
        'iii',
        [$companyId, $idfId, $unlinkedPatchPositionNo]
    );
    itm_test_assert($unlinkedPatchPosition !== null, 'Unlinked patch panel position created');
    $unlinkedPatchPositionId = (int)($unlinkedPatchPosition['id'] ?? 0);
    $createdTempPositionIds[] = $unlinkedPatchPositionId;
    $unlinkedPatchEquipmentRaw = trim((string)($unlinkedPatchPosition['equipment_id'] ?? ''));
    itm_test_assert(
        $unlinkedPatchEquipmentRaw === '' || !ctype_digit($unlinkedPatchEquipmentRaw),
        'Unlinked patch panel position stores a token equipment_id instead of a linked asset'
    );

    $unlinkedPatchFiberCount = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM idf_ports ip
         JOIN switch_port_types spt
           ON spt.company_id = ip.company_id
          AND spt.id = ip.port_type
         WHERE ip.company_id = ?
           AND ip.position_id = ?
           AND LOWER(spt.type) LIKE '%sfp%'",
        'ii',
        [$companyId, $unlinkedPatchPositionId]
    );
    itm_test_assert((int)($unlinkedPatchFiberCount['c'] ?? 0) === 4, 'Unlinked fiber-only patch panel materialized four SFP ports');

    $unlinkedPatchSwitchRows = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM switch_ports
         WHERE company_id = ?
           AND hostname = ?",
        'is',
        [$companyId, $unlinkedPatchName]
    );
    itm_test_assert((int)($unlinkedPatchSwitchRows['c'] ?? 0) === 0, 'Unlinked patch panel did not create switch_ports rows');

    $idfViewUnlinkedPatchHtml = itm_test_fetch_idf_view($baseUrl, $idfId, $cookieFile);
    itm_test_assert_idf_slot_sfp_count($idfViewUnlinkedPatchHtml, $unlinkedPatchPositionNo, 4, 'Unlinked patch panel rack slot');

    $nextPositionRow = itm_test_db_one(
        $db,
        "SELECT COALESCE(MAX(position_no), 0) AS max_position
         FROM idf_positions
         WHERE company_id = ? AND idf_id = ?",
        'ii',
        [$companyId, $idfId]
    );
    $tempPositionNo = ((int)($nextPositionRow['max_position'] ?? 0)) + 1;
    itm_test_assert($tempPositionNo <= 100, 'Temporary test position number is within allowed range');

    $tempEquipmentName = 'ITM Human Sync Temp ' . date('YmdHis');
    itm_test_db_exec(
        $db,
        "INSERT INTO equipment (
            company_id, equipment_type_id, status_id, idf_id, name,
            switch_rj45_id, switch_port_numbering_layout_id, switch_environment_id, active
         ) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, 1)",
        'iiisiii',
        [$companyId, 37, 25, $tempEquipmentName, $rj45EightId, $layoutVerticalId, 8]
    );
    $createdTempEquipmentId = (int)mysqli_insert_id($db);
    $createdTempEquipmentIds[] = $createdTempEquipmentId;
    itm_test_assert($createdTempEquipmentId > 0, 'Temporary equipment created for create/delete sync test');

    itm_test_db_exec(
        $db,
        "INSERT INTO switch_ports (
            company_id, equipment_id, hostname, port_type, port_number,
            to_patch_port, status_id, color_id, idf_id, management_id, comments
         ) VALUES (?, ?, ?, 'RJ45', 1, '0', ?, ?, NULL, 8, '')",
        'iisii',
        [$companyId, $createdTempEquipmentId, $tempEquipmentName, $statusUnknownId, $colorGrayId]
    );
    itm_test_out('[PASS] Temporary switch port row created');

    itm_test_api_post_json(
        $baseUrl,
        '/modules/idfs/api/position_save.php',
        $cookieFile,
        [
            'csrf_token' => $csrf,
            'idf_id' => $idfId,
            'position_no' => $tempPositionNo,
            'device_type' => $switchDeviceTypeId,
            'device_name' => $tempEquipmentName,
            'equipment_id' => $createdTempEquipmentId,
            'switch_rj45_id' => $rj45EightId,
            'switch_port_numbering_layout_id' => $layoutVerticalId,
            'switch_fiber_ports_number' => '2',
            'rj45_count' => 8,
            'notes' => 'ITM HUMAN TEMP POSITION',
        ]
    );
    itm_test_out('[PASS] Position create API completed');

    $tempPositionRow = itm_test_db_one(
        $db,
        "SELECT id
         FROM idf_positions
         WHERE company_id = ? AND idf_id = ? AND position_no = ? AND equipment_id = ?
         LIMIT 1",
        'iiis',
        [$companyId, $idfId, $tempPositionNo, (string)$createdTempEquipmentId]
    );
    itm_test_assert($tempPositionRow !== null, 'Temporary linked position created');
    $tempPositionId = (int)$tempPositionRow['id'];
    $createdTempPositionIds[] = $tempPositionId;

    $tempPositionMeta = itm_test_db_one(
        $db,
        "SELECT rj45_count, sfp_count, switch_port_numbering_layout_id
         FROM idf_positions
         WHERE id = ? AND company_id = ?
         LIMIT 1",
        'ii',
        [$tempPositionId, $companyId]
    );
    itm_test_assert((int)($tempPositionMeta['rj45_count'] ?? 0) === 8, 'Position create persisted rj45_count');
    itm_test_assert((int)($tempPositionMeta['switch_port_numbering_layout_id'] ?? 0) === $layoutVerticalId, 'Position create persisted numbering layout');

    $tempIdfPortLayout = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM idf_ports ip
         JOIN switch_port_types spt
           ON spt.company_id = ip.company_id
          AND spt.id = ip.port_type
         WHERE ip.company_id = ?
           AND ip.position_id = ?
           AND ip.switch_port_numbering_layout_id = ?
           AND LOWER(spt.type) = 'rj45'",
        'iii',
        [$companyId, $tempPositionId, $layoutVerticalId]
    );
    itm_test_assert((int)($tempIdfPortLayout['c'] ?? 0) >= 8, 'Position create synced numbering layout to IDF ports');
    itm_test_assert((int)($tempIdfPortLayout['c'] ?? 0) === 8, 'Position create materialized exactly selected RJ45 IDF ports');

    $switchPortsAfterCreateCount = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM switch_ports
         WHERE company_id = ?
           AND equipment_id = ?
           AND LOWER(COALESCE(port_type, '')) = 'rj45'",
        'ii',
        [$companyId, $createdTempEquipmentId]
    );
    itm_test_assert((int)($switchPortsAfterCreateCount['c'] ?? 0) === 8, 'Position create materialized exactly selected switch_ports rows');

    $equipmentFiberAfterCreate = itm_test_db_one(
        $db,
        "SELECT switch_fiber_ports_number
         FROM equipment
         WHERE id = ? AND company_id = ?
         LIMIT 1",
        'ii',
        [$createdTempEquipmentId, $companyId]
    );
    itm_test_assert((string)($equipmentFiberAfterCreate['switch_fiber_ports_number'] ?? '') === '2', 'Position create synced Fiber Ports Number to linked equipment');

    $idfFiberPortsAfterCreate = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM idf_ports ip
         JOIN switch_port_types spt
           ON spt.company_id = ip.company_id
          AND spt.id = ip.port_type
         WHERE ip.company_id = ?
           AND ip.position_id = ?
           AND LOWER(spt.type) LIKE '%sfp%'",
        'ii',
        [$companyId, $tempPositionId]
    );
    itm_test_assert((int)($idfFiberPortsAfterCreate['c'] ?? 0) === 2, 'Position create materialized selected Fiber Ports Number in IDF ports');

    $switchFiberPortsAfterCreate = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM switch_ports
         WHERE company_id = ?
           AND equipment_id = ?
           AND LOWER(COALESCE(port_type, '')) LIKE '%sfp%'",
        'ii',
        [$companyId, $createdTempEquipmentId]
    );
    itm_test_assert((int)($switchFiberPortsAfterCreate['c'] ?? 0) === 2, 'Position create materialized selected Fiber Ports Number in switch_ports');

    $idfViewAfterCreateHtml = itm_test_fetch_idf_view($baseUrl, $idfId, $cookieFile);
    itm_test_assert_idf_slot_rendered(
        $idfViewAfterCreateHtml,
        $tempPositionNo,
        $tempPositionId,
        8,
        'Vertical',
        'vertical',
        4,
        2,
        'Position create UI',
        2
    );
    itm_test_assert_idf_slot_sfp_count($idfViewAfterCreateHtml, $tempPositionNo, 2, 'Position create UI');

    itm_test_db_exec(
        $db,
        "UPDATE equipment
         SET switch_port_numbering_layout_id = ?
         WHERE id = ? AND company_id = ?
         LIMIT 1",
        'iii',
        [$legacyVerticalLayoutId, $createdTempEquipmentId, $companyId]
    );
    itm_test_db_exec(
        $db,
        "UPDATE idf_positions
         SET switch_port_numbering_layout_id = NULL
         WHERE id = ? AND company_id = ?
         LIMIT 1",
        'ii',
        [$tempPositionId, $companyId]
    );
    $legacyLayoutPosition = itm_test_api_post_json(
        $baseUrl,
        '/modules/idfs/api/position_get.php',
        $cookieFile,
        [
            'csrf_token' => $csrf,
            'position_id' => $tempPositionId,
        ]
    );
    $legacyLayoutPayload = $legacyLayoutPosition['position'] ?? [];
    itm_test_assert((int)($legacyLayoutPayload['equipment_switch_port_numbering_layout_id'] ?? 0) === $layoutVerticalId, 'Position edit payload maps legacy linked-equipment layout to company layout option');
    itm_test_assert((int)($legacyLayoutPayload['effective_switch_port_numbering_layout_id'] ?? 0) === $layoutVerticalId, 'Position edit payload uses linked equipment layout when position layout is blank');
    itm_test_assert((string)($legacyLayoutPayload['equipment_switch_fiber_ports_number'] ?? '') === '2', 'Position edit payload exposes linked equipment Fiber Ports Number');

    $idfViewAfterLegacyLayoutHtml = itm_test_fetch_idf_view($baseUrl, $idfId, $cookieFile);
    itm_test_assert_idf_slot_rendered(
        $idfViewAfterLegacyLayoutHtml,
        $tempPositionNo,
        $tempPositionId,
        8,
        'Vertical',
        'vertical',
        4,
        2,
        'Position linked equipment legacy layout UI',
        2
    );

    itm_test_db_exec(
        $db,
        "UPDATE equipment
         SET switch_port_numbering_layout_id = NULL
         WHERE id = ? AND company_id = ?
         LIMIT 1",
        'ii',
        [$createdTempEquipmentId, $companyId]
    );
    itm_test_db_exec(
        $db,
        "UPDATE idf_ports
         SET switch_port_numbering_layout_id = ?
         WHERE company_id = ? AND position_id = ?",
        'iii',
        [$legacyVerticalLayoutId, $companyId, $tempPositionId]
    );
    $legacyPortLayoutPosition = itm_test_api_post_json(
        $baseUrl,
        '/modules/idfs/api/position_get.php',
        $cookieFile,
        [
            'csrf_token' => $csrf,
            'position_id' => $tempPositionId,
        ]
    );
    $legacyPortLayoutPayload = $legacyPortLayoutPosition['position'] ?? [];
    itm_test_assert((int)($legacyPortLayoutPayload['equipment_switch_port_numbering_layout_id'] ?? 0) === 0, 'Position edit payload leaves linked equipment layout blank when equipment layout is blank');
    itm_test_assert((int)($legacyPortLayoutPayload['effective_switch_port_numbering_layout_id'] ?? 0) === $layoutVerticalId, 'Position edit payload maps legacy IDF port layout to company layout option');

    $equipmentAfterAssign = itm_test_db_one(
        $db,
        "SELECT idf_id
         FROM equipment
         WHERE id = ? AND company_id = ?
         LIMIT 1",
        'ii',
        [$createdTempEquipmentId, $companyId]
    );
    itm_test_assert((int)($equipmentAfterAssign['idf_id'] ?? 0) === $idfId, 'equipment.idf_id synced after position create');

    $switchAfterAssign = itm_test_db_one(
        $db,
        "SELECT idf_id
         FROM switch_ports
         WHERE company_id = ? AND equipment_id = ? AND port_number = 1
         ORDER BY id ASC
         LIMIT 1",
        'ii',
        [$companyId, $createdTempEquipmentId]
    );
    itm_test_assert((int)($switchAfterAssign['idf_id'] ?? 0) === $idfId, 'switch_ports.idf_id synced after position create');

    itm_test_api_post_json(
        $baseUrl,
        '/modules/idfs/api/position_save.php',
        $cookieFile,
        [
            'csrf_token' => $csrf,
            'idf_id' => $idfId,
            'position_no' => $tempPositionNo,
            'position_id' => $tempPositionId,
            'device_type' => $switchDeviceTypeId,
            'device_name' => $tempEquipmentName,
            'equipment_id' => $createdTempEquipmentId,
            'switch_rj45_id' => $rj45TwentyFourId,
            'switch_port_numbering_layout_id' => $layoutHorizontalId,
            'switch_fiber_ports_number' => '4',
            'rj45_count' => 24,
            'notes' => 'ITM HUMAN TEMP POSITION EDITED',
        ]
    );
    itm_test_out('[PASS] Position edit API completed for layout/rj45_count sync');

    $tempPositionAfterEdit = itm_test_db_one(
        $db,
        "SELECT rj45_count, sfp_count, switch_port_numbering_layout_id
         FROM idf_positions
         WHERE id = ? AND company_id = ?
         LIMIT 1",
        'ii',
        [$tempPositionId, $companyId]
    );
    itm_test_assert((int)($tempPositionAfterEdit['rj45_count'] ?? 0) === 24, 'Position edit persisted rj45_count');
    itm_test_assert((int)($tempPositionAfterEdit['switch_port_numbering_layout_id'] ?? 0) === $layoutHorizontalId, 'Position edit persisted numbering layout');

    $equipmentAfterEdit = itm_test_db_one(
        $db,
        "SELECT switch_rj45_id, switch_port_numbering_layout_id, switch_fiber_ports_number
         FROM equipment
         WHERE id = ? AND company_id = ?
         LIMIT 1",
        'ii',
        [$createdTempEquipmentId, $companyId]
    );
    itm_test_assert((int)($equipmentAfterEdit['switch_rj45_id'] ?? 0) === $rj45TwentyFourId, 'Position edit synced RJ45 capacity to linked equipment');
    itm_test_assert((int)($equipmentAfterEdit['switch_port_numbering_layout_id'] ?? 0) === $layoutHorizontalId, 'Position edit synced numbering layout to linked equipment');
    itm_test_assert((string)($equipmentAfterEdit['switch_fiber_ports_number'] ?? '') === '4', 'Position edit synced Fiber Ports Number to linked equipment');

    $idfPortsAfterEdit = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM idf_ports ip
         JOIN switch_port_types spt
           ON spt.company_id = ip.company_id
          AND spt.id = ip.port_type
         WHERE ip.company_id = ?
           AND ip.position_id = ?
           AND ip.switch_port_numbering_layout_id = ?
           AND LOWER(spt.type) = 'rj45'",
        'iii',
        [$companyId, $tempPositionId, $layoutHorizontalId]
    );
    itm_test_assert((int)($idfPortsAfterEdit['c'] ?? 0) >= 24, 'Position edit synced numbering layout to IDF ports');
    itm_test_assert((int)($idfPortsAfterEdit['c'] ?? 0) === 24, 'Position edit materialized exactly selected RJ45 IDF ports');

    $switchPortsAfterEditCount = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM switch_ports
         WHERE company_id = ?
           AND equipment_id = ?
           AND LOWER(COALESCE(port_type, '')) = 'rj45'",
        'ii',
        [$companyId, $createdTempEquipmentId]
    );
    itm_test_assert((int)($switchPortsAfterEditCount['c'] ?? 0) === 24, 'Position edit materialized exactly selected switch_ports rows');

    $idfFiberPortsAfterEdit = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM idf_ports ip
         JOIN switch_port_types spt
           ON spt.company_id = ip.company_id
          AND spt.id = ip.port_type
         WHERE ip.company_id = ?
           AND ip.position_id = ?
           AND LOWER(spt.type) LIKE '%sfp%'",
        'ii',
        [$companyId, $tempPositionId]
    );
    itm_test_assert((int)($idfFiberPortsAfterEdit['c'] ?? 0) === 4, 'Position edit materialized selected Fiber Ports Number in IDF ports');

    $switchFiberPortsAfterEdit = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM switch_ports
         WHERE company_id = ?
           AND equipment_id = ?
           AND LOWER(COALESCE(port_type, '')) LIKE '%sfp%'",
        'ii',
        [$companyId, $createdTempEquipmentId]
    );
    itm_test_assert((int)($switchFiberPortsAfterEdit['c'] ?? 0) === 4, 'Position edit materialized selected Fiber Ports Number in switch_ports');

    $idfViewAfterEditHtml = itm_test_fetch_idf_view($baseUrl, $idfId, $cookieFile);
    itm_test_assert_idf_slot_rendered(
        $idfViewAfterEditHtml,
        $tempPositionNo,
        $tempPositionId,
        24,
        'Horizontal',
        'horizontal',
        24,
        1,
        'Position edit UI',
        4
    );
    itm_test_assert_idf_slot_sfp_count($idfViewAfterEditHtml, $tempPositionNo, 4, 'Position edit UI');

    $copyTargetPositionNo = $tempPositionNo + 1;
    itm_test_api_post_json(
        $baseUrl,
        '/modules/idfs/api/position_copy.php',
        $cookieFile,
        [
            'csrf_token' => $csrf,
            'position_id' => $tempPositionId,
            'target_position' => $copyTargetPositionNo,
            'overwrite' => 0,
        ]
    );
    itm_test_out('[PASS] Position copy API completed for layout/rj45_count sync');

    $copiedPosition = itm_test_db_one(
        $db,
        "SELECT id, equipment_id, rj45_count, sfp_count, switch_port_numbering_layout_id
         FROM idf_positions
         WHERE company_id = ? AND idf_id = ? AND position_no = ?
         LIMIT 1",
        'iii',
        [$companyId, $idfId, $copyTargetPositionNo]
    );
    itm_test_assert($copiedPosition !== null, 'Copied position created for layout/rj45_count sync');
    $copiedPositionId = (int)($copiedPosition['id'] ?? 0);
    $createdTempPositionIds[] = $copiedPositionId;
    $copiedEquipmentId = (int)($copiedPosition['equipment_id'] ?? 0);
    if ($copiedEquipmentId > 0) {
        $createdTempEquipmentIds[] = $copiedEquipmentId;
    }
    itm_test_assert((int)($copiedPosition['rj45_count'] ?? 0) === 24, 'Position copy preserved rj45_count');
    itm_test_assert((int)($copiedPosition['switch_port_numbering_layout_id'] ?? 0) === $layoutHorizontalId, 'Position copy preserved numbering layout');

    $copiedPortLayout = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM idf_ports ip
         JOIN switch_port_types spt
           ON spt.company_id = ip.company_id
          AND spt.id = ip.port_type
         WHERE ip.company_id = ?
           AND ip.position_id = ?
           AND ip.switch_port_numbering_layout_id = ?
           AND LOWER(spt.type) = 'rj45'",
        'iii',
        [$companyId, $copiedPositionId, $layoutHorizontalId]
    );
    itm_test_assert((int)($copiedPortLayout['c'] ?? 0) >= 24, 'Position copy synced numbering layout to copied IDF ports');
    itm_test_assert((int)($copiedPortLayout['c'] ?? 0) === 24, 'Position copy materialized exactly selected RJ45 IDF ports');

    $copiedSwitchPortsCount = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM switch_ports
         WHERE company_id = ?
           AND equipment_id = ?
           AND LOWER(COALESCE(port_type, '')) = 'rj45'",
        'ii',
        [$companyId, $copiedEquipmentId]
    );
    itm_test_assert((int)($copiedSwitchPortsCount['c'] ?? 0) === 24, 'Position copy materialized exactly selected switch_ports rows');

    $copiedEquipmentFiber = itm_test_db_one(
        $db,
        "SELECT switch_fiber_ports_number
         FROM equipment
         WHERE id = ? AND company_id = ?
         LIMIT 1",
        'ii',
        [$copiedEquipmentId, $companyId]
    );
    itm_test_assert((string)($copiedEquipmentFiber['switch_fiber_ports_number'] ?? '') === '4', 'Position copy preserved Fiber Ports Number on copied equipment');

    $copiedIdfFiberPorts = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM idf_ports ip
         JOIN switch_port_types spt
           ON spt.company_id = ip.company_id
          AND spt.id = ip.port_type
         WHERE ip.company_id = ?
           AND ip.position_id = ?
           AND LOWER(spt.type) LIKE '%sfp%'",
        'ii',
        [$companyId, $copiedPositionId]
    );
    itm_test_assert((int)($copiedIdfFiberPorts['c'] ?? 0) === 4, 'Position copy preserved Fiber Ports Number in copied IDF ports');

    $copiedSwitchFiberPorts = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM switch_ports
         WHERE company_id = ?
           AND equipment_id = ?
           AND LOWER(COALESCE(port_type, '')) LIKE '%sfp%'",
        'ii',
        [$companyId, $copiedEquipmentId]
    );
    itm_test_assert((int)($copiedSwitchFiberPorts['c'] ?? 0) === 4, 'Position copy preserved Fiber Ports Number in copied switch_ports');

    $idfViewAfterCopyHtml = itm_test_fetch_idf_view($baseUrl, $idfId, $cookieFile);
    itm_test_assert_idf_slot_rendered(
        $idfViewAfterCopyHtml,
        $copyTargetPositionNo,
        $copiedPositionId,
        24,
        'Horizontal',
        'horizontal',
        24,
        1,
        'Position copy UI',
        4
    );
    itm_test_assert_idf_slot_sfp_count($idfViewAfterCopyHtml, $copyTargetPositionNo, 4, 'Position copy UI');

    itm_test_api_post_json(
        $baseUrl,
        '/modules/idfs/api/position_delete.php',
        $cookieFile,
        [
            'csrf_token' => $csrf,
            'position_id' => $tempPositionId,
        ]
    );
    itm_test_out('[PASS] Position delete API completed');

    $positionAfterDelete = itm_test_db_one(
        $db,
        "SELECT id FROM idf_positions WHERE id = ? LIMIT 1",
        'i',
        [$tempPositionId]
    );
    itm_test_assert($positionAfterDelete === null, 'Temporary position deleted');

    $portsAfterDelete = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c FROM idf_ports WHERE position_id = ?",
        'i',
        [$tempPositionId]
    );
    itm_test_assert((int)($portsAfterDelete['c'] ?? 0) === 0, 'Temporary position idf_ports rows removed');

    $equipmentAfterDelete = itm_test_db_one(
        $db,
        "SELECT idf_id FROM equipment WHERE id = ? AND company_id = ? LIMIT 1",
        'ii',
        [$createdTempEquipmentId, $companyId]
    );
    itm_test_assert(($equipmentAfterDelete['idf_id'] ?? null) === null, 'equipment.idf_id reset to NULL after position delete');

    $switchAfterDelete = itm_test_db_one(
        $db,
        "SELECT idf_id FROM switch_ports WHERE company_id = ? AND equipment_id = ? AND port_number = 1 ORDER BY id ASC LIMIT 1",
        'ii',
        [$companyId, $createdTempEquipmentId]
    );
    itm_test_assert(($switchAfterDelete['idf_id'] ?? null) === null, 'switch_ports.idf_id reset to NULL after position delete');

    itm_test_out(PHP_EOL . 'All human-style IDF sync tests passed.');
} catch (Throwable $e) {
    itm_test_err('[FAIL] ' . $e->getMessage());
    $itmTestExitCode = 1;
} finally {
    if ($db instanceof mysqli) {
        if (!empty($createdTempLinkIds)) {
            foreach ($createdTempLinkIds as $tempLinkId) {
                $tempLinkId = (int)$tempLinkId;
                if ($tempLinkId > 0) {
                    @mysqli_query($db, 'DELETE FROM idf_links WHERE id = ' . $tempLinkId . ' OR link_id = ' . $tempLinkId);
                }
            }
        }
        if (!empty($createdTempPositionIds)) {
            foreach ($createdTempPositionIds as $positionId) {
                $positionId = (int)$positionId;
                if ($positionId > 0) {
                    @mysqli_query($db, "DELETE FROM idf_links WHERE port_id_a IN (SELECT id FROM idf_ports WHERE position_id = {$positionId}) OR port_id_b IN (SELECT id FROM idf_ports WHERE position_id = {$positionId})");
                    @mysqli_query($db, "DELETE FROM idf_ports WHERE position_id = {$positionId}");
                    @mysqli_query($db, "DELETE FROM idf_positions WHERE id = {$positionId} LIMIT 1");
                }
            }
        }
        $equipmentIdsToDelete = array_values(array_unique(array_filter(array_map('intval', array_merge($createdTempEquipmentIds, [$createdTempEquipmentId])))));
        foreach ($equipmentIdsToDelete as $equipmentIdToDelete) {
            if ($equipmentIdToDelete > 0) {
                @mysqli_query($db, "DELETE FROM switch_ports WHERE equipment_id = " . (int)$equipmentIdToDelete);
                @mysqli_query($db, "DELETE FROM equipment WHERE id = " . (int)$equipmentIdToDelete . " LIMIT 1");
            }
        }
        mysqli_close($db);
    }
    if (is_file($cookieFile)) {
        @unlink($cookieFile);
    }
    itm_test_browser_close();
}

exit($itmTestExitCode);

itm_script_output_end();
