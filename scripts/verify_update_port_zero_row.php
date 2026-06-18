<?php
/**
 * Verifies update_port.php returns HTTP 404 on zero-row switch_ports UPDATE before IDF auto-sync.
 */
if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Switch Port update_port Zero-Row Contract');

$nl = itm_script_output_nl();
echo 'Verifying update_port zero-row contract (404 before IDF sync)...' . $nl;

$companyId = (int)(getenv('ITM_TEST_COMPANY_ID') ?: 1);
$probeEquipmentId = 0;
$probePortId = 0;

/**
 * @return array{switch_id:int,status_name:string,color_name:string}|null
 */
function vupzr_find_existing_switch_context(mysqli $conn, int $companyId): ?array
{
    $stmt = $conn->prepare(
        'SELECT e.id AS switch_id, ss.status AS status_name, cc.color_name AS color_name
         FROM equipment e
         INNER JOIN switch_ports sp ON sp.equipment_id = e.id AND sp.company_id = e.company_id
         INNER JOIN switch_status ss ON ss.id = sp.status_id AND ss.company_id = sp.company_id
         INNER JOIN cable_colors cc ON cc.id = sp.color_id AND cc.company_id = sp.company_id
         WHERE e.company_id = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? [
        'switch_id' => (int)$row['switch_id'],
        'status_name' => (string)$row['status_name'],
        'color_name' => (string)$row['color_name'],
    ] : null;
}

function vupzr_lookup_tenant_id(mysqli $conn, int $companyId, string $sql, string $types, array $params): int
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['id'] ?? 0);
}

/**
 * @return array{switch_id:int,status_name:string,color_name:string,equipment_id:int,port_id:int}|null
 */
function vupzr_create_probe_switch_context(mysqli $conn, int $companyId): ?array
{
    $equipmentTypeId = vupzr_lookup_tenant_id(
        $conn,
        $companyId,
        "SELECT id FROM equipment_types WHERE company_id = ? AND LOWER(name) = 'switch' ORDER BY id ASC LIMIT 1",
        'i',
        [$companyId]
    );
    $equipmentStatusId = vupzr_lookup_tenant_id(
        $conn,
        $companyId,
        "SELECT id FROM equipment_statuses WHERE company_id = ? AND LOWER(name) = 'active' ORDER BY id ASC LIMIT 1",
        'i',
        [$companyId]
    );
    $switchStatusId = vupzr_lookup_tenant_id(
        $conn,
        $companyId,
        "SELECT id FROM switch_status WHERE company_id = ? AND LOWER(status) = 'unknown' ORDER BY id ASC LIMIT 1",
        'i',
        [$companyId]
    );
    $colorId = vupzr_lookup_tenant_id(
        $conn,
        $companyId,
        "SELECT id FROM cable_colors WHERE company_id = ? AND LOWER(color_name) = 'gray' ORDER BY id ASC LIMIT 1",
        'i',
        [$companyId]
    );

    if ($equipmentTypeId <= 0 || $equipmentStatusId <= 0 || $switchStatusId <= 0 || $colorId <= 0) {
        return null;
    }

    $statusRow = null;
    $stmtStatus = $conn->prepare('SELECT status FROM switch_status WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmtStatus) {
        $stmtStatus->bind_param('ii', $switchStatusId, $companyId);
        $stmtStatus->execute();
        $statusRow = $stmtStatus->get_result()->fetch_assoc();
        $stmtStatus->close();
    }
    $colorRow = null;
    $stmtColor = $conn->prepare('SELECT color_name FROM cable_colors WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmtColor) {
        $stmtColor->bind_param('ii', $colorId, $companyId);
        $stmtColor->execute();
        $colorRow = $stmtColor->get_result()->fetch_assoc();
        $stmtColor->close();
    }

    $statusName = is_array($statusRow) ? (string)$statusRow['status'] : '';
    $colorName = is_array($colorRow) ? (string)$colorRow['color_name'] : '';
    if ($statusName === '' || $colorName === '') {
        return null;
    }

    $probeName = 'ITM-VUPZR-probe-' . uniqid('', true);
    $stmtEquipment = $conn->prepare(
        'INSERT INTO equipment (company_id, equipment_type_id, status_id, name, active)
         VALUES (?, ?, ?, ?, 1)'
    );
    if (!$stmtEquipment) {
        return null;
    }
    $stmtEquipment->bind_param('iiis', $companyId, $equipmentTypeId, $equipmentStatusId, $probeName);
    if (!$stmtEquipment->execute()) {
        $stmtEquipment->close();
        return null;
    }
    $equipmentId = (int)$stmtEquipment->insert_id;
    $stmtEquipment->close();
    if ($equipmentId <= 0) {
        return null;
    }

    $stmtPort = $conn->prepare(
        "INSERT INTO switch_ports (company_id, equipment_id, port_type, port_number, to_patch_port, status_id, color_id)
         VALUES (?, ?, 'RJ45', 1, '0', ?, ?)"
    );
    if (!$stmtPort) {
        $conn->query('DELETE FROM equipment WHERE id = ' . (int)$equipmentId . ' AND company_id = ' . (int)$companyId);
        return null;
    }
    $stmtPort->bind_param('iiii', $companyId, $equipmentId, $switchStatusId, $colorId);
    if (!$stmtPort->execute()) {
        $stmtPort->close();
        $conn->query('DELETE FROM equipment WHERE id = ' . (int)$equipmentId . ' AND company_id = ' . (int)$companyId);
        return null;
    }
    $portId = (int)$stmtPort->insert_id;
    $stmtPort->close();

    return [
        'switch_id' => $equipmentId,
        'status_name' => $statusName,
        'color_name' => $colorName,
        'equipment_id' => $equipmentId,
        'port_id' => $portId,
    ];
}

try {
    $context = vupzr_find_existing_switch_context($conn, $companyId);
    if ($context === null) {
        $probe = vupzr_create_probe_switch_context($conn, $companyId);
        if ($probe === null) {
            echo colorText('[FAIL] Unable to resolve or create switch_ports probe context for company_id=' . $companyId . '.', 'fail') . $nl;
            itm_script_output_end();
            exit(1);
        }
        $context = $probe;
        $probeEquipmentId = (int)$probe['equipment_id'];
        $probePortId = (int)$probe['port_id'];
        echo colorText('[INFO] Created disposable probe equipment id=' . $probeEquipmentId . ' for zero-row test.', 'info') . $nl;
    }

    $switchId = (int)$context['switch_id'];
    $statusName = (string)$context['status_name'];
    $colorName = (string)$context['color_name'];

    $stmtIdfCount = $conn->prepare('SELECT COUNT(*) AS c FROM idf_ports WHERE company_id = ?');
    $idfCountBefore = 0;
    if ($stmtIdfCount) {
        $stmtIdfCount->bind_param('i', $companyId);
        $stmtIdfCount->execute();
        $idfRow = $stmtIdfCount->get_result()->fetch_assoc();
        $idfCountBefore = (int)($idfRow['c'] ?? 0);
        $stmtIdfCount->close();
    }

    $fakePortId = 2147483000;
    $postPayload = [
        'id' => $fakePortId,
        'switch_id' => $switchId,
        'status' => $statusName,
        'color' => $colorName,
        'csrf_token' => 'test',
        'company_id' => 999,
    ];

    $configPath = realpath(dirname(__DIR__) . '/config/config.php');
    $updatePortPath = realpath(dirname(__DIR__) . '/includes/update_port.php');
    $includesDir = realpath(dirname(__DIR__) . '/includes');

    $code = "<?php
define('ITM_CLI_SCRIPT', true);
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
function itm_validate_csrf_token(\$token) { return true; }
require " . var_export($configPath, true) . ";
\$_SESSION['user_id'] = 1;
\$_SESSION['company_id'] = " . (int)$companyId . ";
\$_POST = " . var_export($postPayload, true) . ";
\$_REQUEST = \$_POST;
chdir(" . var_export($includesDir, true) . ");
ob_start();
include " . var_export($updatePortPath, true) . ";
\$body = ob_get_clean();
\$status = http_response_code();
echo json_encode(['http_status' => \$status, 'body' => \$body], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>";

    $tmp = tempnam(sys_get_temp_dir(), 'update-port-zero');
    file_put_contents($tmp, $code);
    $phpBin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
    $shellOutput = shell_exec(escapeshellarg($phpBin) . ' ' . escapeshellarg($tmp) . ' 2>&1');
    unlink($tmp);

    $wrapper = json_decode(trim((string)$shellOutput), true);
    $httpStatus = is_array($wrapper) ? (int)($wrapper['http_status'] ?? 0) : 0;
    $body = is_array($wrapper) ? (string)($wrapper['body'] ?? '') : (string)$shellOutput;
    $decoded = json_decode($body, true);

    $idfCountAfter = $idfCountBefore;
    if ($stmtIdfCount = $conn->prepare('SELECT COUNT(*) AS c FROM idf_ports WHERE company_id = ?')) {
        $stmtIdfCount->bind_param('i', $companyId);
        $stmtIdfCount->execute();
        $idfAfterRow = $stmtIdfCount->get_result()->fetch_assoc();
        $idfCountAfter = (int)($idfAfterRow['c'] ?? 0);
        $stmtIdfCount->close();
    }

    $pass = $httpStatus === 404
        && is_array($decoded)
        && ($decoded['success'] ?? null) === false
        && $idfCountAfter === $idfCountBefore;

    if ($pass) {
        echo colorText('[PASS] Zero-row update_port returned HTTP 404 and idf_ports unchanged.', 'pass') . $nl;
    } else {
        echo colorText(
            '[FAIL] Expected HTTP 404 with success:false and unchanged idf_ports; '
            . 'http_status=' . $httpStatus
            . ' body=' . trim($body),
            'fail'
        ) . $nl;
    }
} finally {
    if ($probePortId > 0) {
        $conn->query(
            'DELETE FROM switch_ports WHERE id = ' . (int)$probePortId
            . ' AND company_id = ' . (int)$companyId
        );
    }
    if ($probeEquipmentId > 0) {
        $conn->query(
            'DELETE FROM equipment WHERE id = ' . (int)$probeEquipmentId
            . ' AND company_id = ' . (int)$companyId
        );
    }
}

itm_script_output_end();
exit(isset($pass) && $pass ? 0 : 1);
