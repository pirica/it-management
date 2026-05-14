<?php
/**
 * Human-style end-to-end sync test for IDF rack/device workflows.
 *
 * Why: validates real HTTP flows used by modules/idfs/view.php and modules/idfs/device.php
 * and asserts synchronization across idf_ports, switch_ports, equipment, and idf_links.
 *
 * Usage:
 *   php scripts/idfs_sync_human_test.php
 *
 * Optional env vars:
 *   ITM_BASE_URL   (default: http://localhost/it-management)
 *   ITM_USER       (default: Admin)
 *   ITM_PASS       (default: Admin)
 *   ITM_DB_HOST    (default: 127.0.0.1)
 *   ITM_DB_USER    (default: root)
 *   ITM_DB_PASS    (default: itmanagement)
 *   ITM_DB_NAME    (default: itmanagement)
 *   ITM_COMPANY_ID (default: 4)
 *   ITM_IDF_ID     (default: 4)
 */

declare(strict_types=1);

function itm_test_out($message)
{
    fwrite(STDOUT, $message . PHP_EOL);
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

function itm_test_extract_csrf($html)
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $html, $matches) === 1) {
        return trim((string)$matches[1]);
    }
    return '';
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

function itm_test_assert_idf_slot_rendered($html, $positionNo, $positionId, $portCount, $layoutName, $layoutSlug, $gridCols, $gridRows, $message)
{
    $slotHtml = itm_test_extract_position_slot_html($html, $positionNo);
    $slotDebug = '';
    if (preg_match_all('/data-(?:layout|port-total|grid-cols|grid-rows)="[^"]*"/i', $slotHtml, $slotMatches)) {
        $slotDebug = ' Rendered visualizer attrs: ' . implode(' ', array_unique($slotMatches[0]));
    }
    itm_test_assert_slot_contains($slotHtml, 'data-has-device="1"', $message . ' has a device in rendered rack slot', $slotDebug);
    itm_test_assert_slot_contains($slotHtml, 'data-position-id="' . (int)$positionId . '"', $message . ' exposes rendered position id', $slotDebug);
    itm_test_assert_slot_contains($slotHtml, 'data-port-count="' . (int)$portCount . '"', $message . ' exposes rendered port count', $slotDebug);
    itm_test_assert_slot_contains($slotHtml, 'data-layout-name="' . $layoutName . '"', $message . ' exposes rendered numbering layout', $slotDebug);
    itm_test_assert_slot_contains($slotHtml, 'data-layout="' . $layoutSlug . '"', $message . ' renders visualizer with selected layout', $slotDebug);
    itm_test_assert_slot_contains($slotHtml, 'data-port-total="' . (int)$portCount . '"', $message . ' renders visualizer with selected port total', $slotDebug);
    itm_test_assert_slot_contains($slotHtml, 'data-grid-cols="' . (int)$gridCols . '"', $message . ' renders expected visualizer columns', $slotDebug);
    itm_test_assert_slot_contains($slotHtml, 'data-grid-rows="' . (int)$gridRows . '"', $message . ' renders expected visualizer rows', $slotDebug);
}

function itm_test_api_post_json($baseUrl, $path, $cookieFile, array $payload)
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
    if ($statusCode >= 400 || empty($decoded['ok'])) {
        $error = (string)($decoded['error'] ?? 'Unknown API error');
        itm_test_fail("API {$path} failed (HTTP {$statusCode}): {$error}");
    }
    return $decoded;
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
$itmTestExitCode = 0;

try {
    $db = itm_test_db_connect();
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

    $portA = itm_test_db_one(
        $db,
        "SELECT pr.id AS port_id, pr.port_no, pr.port_type, p.id AS position_id, CAST(p.equipment_id AS UNSIGNED) AS equipment_id
         FROM idf_ports pr
         JOIN idf_positions p ON p.id = pr.position_id AND p.company_id = pr.company_id
         LEFT JOIN idf_links l ON l.company_id = pr.company_id AND (l.port_id_a = pr.id OR l.port_id_b = pr.id)
         JOIN switch_ports sp ON sp.company_id = p.company_id
                              AND sp.equipment_id = CAST(p.equipment_id AS UNSIGNED)
                              AND sp.port_number = pr.port_no
         WHERE pr.company_id = ?
           AND p.idf_id = ?
           AND p.equipment_id REGEXP '^[0-9]+$'
           AND l.id IS NULL
         ORDER BY pr.id ASC
         LIMIT 1",
        'ii',
        [$companyId, $idfId]
    );
    itm_test_assert($portA !== null, 'Found source IDF port with switch mirror for sync test');

    $portB = itm_test_db_one(
        $db,
        "SELECT pr.id AS port_id, pr.port_no, pr.port_type, p.id AS position_id
         FROM idf_ports pr
         JOIN idf_positions p ON p.id = pr.position_id AND p.company_id = pr.company_id
         LEFT JOIN idf_links l ON l.company_id = pr.company_id AND (l.port_id_a = pr.id OR l.port_id_b = pr.id)
         WHERE pr.company_id = ?
           AND p.idf_id = ?
           AND l.id IS NULL
           AND p.id <> ?
           AND pr.id <> ?
         ORDER BY pr.id ASC
         LIMIT 1",
        'iiii',
        [$companyId, $idfId, (int)$portA['position_id'], (int)$portA['port_id']]
    );
    if ($portB === null) {
        $fallbackPositionRow = itm_test_db_one(
            $db,
            "SELECT COALESCE(MAX(position_no), 0) AS max_position
             FROM idf_positions
             WHERE company_id = ? AND idf_id = ?",
            'ii',
            [$companyId, $idfId]
        );
        $fallbackPositionNo = ((int)($fallbackPositionRow['max_position'] ?? 0)) + 1;
        itm_test_assert($fallbackPositionNo <= 100, 'Temporary link destination position number is within allowed range');

        $fallbackEquipmentName = 'ITM Human Link Dest ' . date('YmdHis');
        itm_test_db_exec(
            $db,
            "INSERT INTO equipment (
                company_id, equipment_type_id, status_id, idf_id, name,
                switch_rj45_id, switch_port_numbering_layout_id, switch_environment_id, active
             ) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, 1)",
            'iiisiii',
            [$companyId, 37, 25, $fallbackEquipmentName, $rj45EightId, $layoutVerticalId, 8]
        );
        $fallbackEquipmentId = (int)mysqli_insert_id($db);
        $createdTempEquipmentIds[] = $fallbackEquipmentId;
        itm_test_assert($fallbackEquipmentId > 0, 'Temporary destination equipment created for link sync test');

        itm_test_db_exec(
            $db,
            "INSERT INTO switch_ports (
                company_id, equipment_id, hostname, port_type, port_number,
                to_patch_port, status_id, color_id, idf_id, management_id, comments
             ) VALUES (?, ?, ?, 'RJ45', 1, '0', ?, ?, NULL, 8, '')",
            'iisii',
            [$companyId, $fallbackEquipmentId, $fallbackEquipmentName, $statusUnknownId, $colorGrayId]
        );

        itm_test_api_post_json(
            $baseUrl,
            '/modules/idfs/api/position_save.php',
            $cookieFile,
            [
                'csrf_token' => $csrf,
                'idf_id' => $idfId,
                'position_no' => $fallbackPositionNo,
                'device_type' => $switchDeviceTypeId,
                'device_name' => $fallbackEquipmentName,
                'equipment_id' => $fallbackEquipmentId,
                'switch_rj45_id' => $rj45EightId,
                'switch_port_numbering_layout_id' => $layoutVerticalId,
                'port_count' => 8,
                'notes' => 'ITM HUMAN TEMP LINK DESTINATION',
            ]
        );

        $fallbackPosition = itm_test_db_one(
            $db,
            "SELECT id
             FROM idf_positions
             WHERE company_id = ? AND idf_id = ? AND position_no = ? AND equipment_id = ?
             LIMIT 1",
            'iiis',
            [$companyId, $idfId, $fallbackPositionNo, (string)$fallbackEquipmentId]
        );
        $fallbackPositionId = (int)($fallbackPosition['id'] ?? 0);
        itm_test_assert($fallbackPositionId > 0, 'Temporary destination position created for link sync test');
        $createdTempPositionIds[] = $fallbackPositionId;

        $portB = itm_test_db_one(
            $db,
            "SELECT pr.id AS port_id, pr.port_no, pr.port_type, p.id AS position_id
             FROM idf_ports pr
             JOIN idf_positions p ON p.id = pr.position_id AND p.company_id = pr.company_id
             LEFT JOIN idf_links l ON l.company_id = pr.company_id AND (l.port_id_a = pr.id OR l.port_id_b = pr.id)
             WHERE pr.company_id = ?
               AND p.id = ?
               AND l.id IS NULL
             ORDER BY pr.id ASC
             LIMIT 1",
            'ii',
            [$companyId, $fallbackPositionId]
        );
    }
    itm_test_assert($portB !== null, 'Found destination IDF port on a different position');

    $portAId = (int)$portA['port_id'];
    $portBId = (int)$portB['port_id'];
    $portANumber = (int)$portA['port_no'];
    $portATypeId = (int)$portA['port_type'];
    $portAEquipmentId = (int)$portA['equipment_id'];

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
            'port_count' => 8,
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
        "SELECT port_count, switch_port_numbering_layout_id
         FROM idf_positions
         WHERE id = ? AND company_id = ?
         LIMIT 1",
        'ii',
        [$tempPositionId, $companyId]
    );
    itm_test_assert((int)($tempPositionMeta['port_count'] ?? 0) === 8, 'Position create persisted port_count');
    itm_test_assert((int)($tempPositionMeta['switch_port_numbering_layout_id'] ?? 0) === $layoutVerticalId, 'Position create persisted numbering layout');

    $tempIdfPortLayout = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM idf_ports
         WHERE company_id = ?
           AND position_id = ?
           AND switch_port_numbering_layout_id = ?",
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
           AND equipment_id = ?",
        'ii',
        [$companyId, $createdTempEquipmentId]
    );
    itm_test_assert((int)($switchPortsAfterCreateCount['c'] ?? 0) === 8, 'Position create materialized exactly selected switch_ports rows');

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
        'Position create UI'
    );

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
        'Position linked equipment legacy layout UI'
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
            'port_count' => 24,
            'notes' => 'ITM HUMAN TEMP POSITION EDITED',
        ]
    );
    itm_test_out('[PASS] Position edit API completed for layout/port_count sync');

    $tempPositionAfterEdit = itm_test_db_one(
        $db,
        "SELECT port_count, switch_port_numbering_layout_id
         FROM idf_positions
         WHERE id = ? AND company_id = ?
         LIMIT 1",
        'ii',
        [$tempPositionId, $companyId]
    );
    itm_test_assert((int)($tempPositionAfterEdit['port_count'] ?? 0) === 24, 'Position edit persisted port_count');
    itm_test_assert((int)($tempPositionAfterEdit['switch_port_numbering_layout_id'] ?? 0) === $layoutHorizontalId, 'Position edit persisted numbering layout');

    $equipmentAfterEdit = itm_test_db_one(
        $db,
        "SELECT switch_rj45_id, switch_port_numbering_layout_id
         FROM equipment
         WHERE id = ? AND company_id = ?
         LIMIT 1",
        'ii',
        [$createdTempEquipmentId, $companyId]
    );
    itm_test_assert((int)($equipmentAfterEdit['switch_rj45_id'] ?? 0) === $rj45TwentyFourId, 'Position edit synced RJ45 capacity to linked equipment');
    itm_test_assert((int)($equipmentAfterEdit['switch_port_numbering_layout_id'] ?? 0) === $layoutHorizontalId, 'Position edit synced numbering layout to linked equipment');

    $idfPortsAfterEdit = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM idf_ports
         WHERE company_id = ?
           AND position_id = ?
           AND switch_port_numbering_layout_id = ?",
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
           AND equipment_id = ?",
        'ii',
        [$companyId, $createdTempEquipmentId]
    );
    itm_test_assert((int)($switchPortsAfterEditCount['c'] ?? 0) === 24, 'Position edit materialized exactly selected switch_ports rows');

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
        'Position edit UI'
    );

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
    itm_test_out('[PASS] Position copy API completed for layout/port_count sync');

    $copiedPosition = itm_test_db_one(
        $db,
        "SELECT id, equipment_id, port_count, switch_port_numbering_layout_id
         FROM idf_positions
         WHERE company_id = ? AND idf_id = ? AND position_no = ?
         LIMIT 1",
        'iii',
        [$companyId, $idfId, $copyTargetPositionNo]
    );
    itm_test_assert($copiedPosition !== null, 'Copied position created for layout/port_count sync');
    $copiedPositionId = (int)($copiedPosition['id'] ?? 0);
    $createdTempPositionIds[] = $copiedPositionId;
    $copiedEquipmentId = (int)($copiedPosition['equipment_id'] ?? 0);
    if ($copiedEquipmentId > 0) {
        $createdTempEquipmentIds[] = $copiedEquipmentId;
    }
    itm_test_assert((int)($copiedPosition['port_count'] ?? 0) === 24, 'Position copy preserved port_count');
    itm_test_assert((int)($copiedPosition['switch_port_numbering_layout_id'] ?? 0) === $layoutHorizontalId, 'Position copy preserved numbering layout');

    $copiedPortLayout = itm_test_db_one(
        $db,
        "SELECT COUNT(*) AS c
         FROM idf_ports
         WHERE company_id = ?
           AND position_id = ?
           AND switch_port_numbering_layout_id = ?",
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
           AND equipment_id = ?",
        'ii',
        [$companyId, $copiedEquipmentId]
    );
    itm_test_assert((int)($copiedSwitchPortsCount['c'] ?? 0) === 24, 'Position copy materialized exactly selected switch_ports rows');

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
        'Position copy UI'
    );

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
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . PHP_EOL);
    $itmTestExitCode = 1;
} finally {
    if ($db instanceof mysqli) {
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
}

exit($itmTestExitCode);
