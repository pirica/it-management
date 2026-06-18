<?php
/**
 * Verifies update_port.php returns HTTP 404 on zero-row switch_ports UPDATE before IDF auto-sync.
 */
if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/itm_role_module_permissions.php';
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
    $row = itm_mysqli_stmt_fetch_assoc($stmt);
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
    $row = itm_mysqli_stmt_fetch_assoc($stmt);
    $stmt->close();

    return (int)($row['id'] ?? 0);
}

function vupzr_count_idf_ports(mysqli $conn, int $companyId): int
{
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM idf_ports WHERE company_id = ?');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $row = itm_mysqli_stmt_fetch_assoc($stmt);
    $stmt->close();

    return (int)($row['c'] ?? 0);
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
        $statusRow = itm_mysqli_stmt_fetch_assoc($stmtStatus);
        $stmtStatus->close();
    }
    $colorRow = null;
    $stmtColor = $conn->prepare('SELECT color_name FROM cable_colors WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmtColor) {
        $stmtColor->bind_param('ii', $colorId, $companyId);
        $stmtColor->execute();
        $colorRow = itm_mysqli_stmt_fetch_assoc($stmtColor);
        $stmtColor->close();
    }

    $statusName = is_array($statusRow) ? (string)$statusRow['status'] : '';
    $colorName = is_array($colorRow) ? (string)$colorRow['color_name'] : '';
    if ($statusName === '' || $colorName === '') {
        return null;
    }

    $probeName = 'ITM-VUPZR-probe-' . uniqid('', true);
    $equipmentId = 0;
    $portId = 0;

    if (!mysqli_begin_transaction($conn)) {
        return null;
    }

    try {
        $stmtEquipment = $conn->prepare(
            'INSERT INTO equipment (company_id, equipment_type_id, status_id, name, active)
             VALUES (?, ?, ?, ?, 1)'
        );
        if (!$stmtEquipment) {
            throw new RuntimeException('equipment prepare failed');
        }
        $stmtEquipment->bind_param('iiis', $companyId, $equipmentTypeId, $equipmentStatusId, $probeName);
        if (!$stmtEquipment->execute()) {
            $stmtEquipment->close();
            throw new RuntimeException('equipment insert failed');
        }
        $equipmentId = (int)$stmtEquipment->insert_id;
        $stmtEquipment->close();
        if ($equipmentId <= 0) {
            throw new RuntimeException('equipment insert id missing');
        }

        $stmtPort = $conn->prepare(
            "INSERT INTO switch_ports (company_id, equipment_id, port_type, port_number, to_patch_port, status_id, color_id)
             VALUES (?, ?, 'RJ45', 1, '0', ?, ?)"
        );
        if (!$stmtPort) {
            throw new RuntimeException('switch_ports prepare failed');
        }
        $stmtPort->bind_param('iiii', $companyId, $equipmentId, $switchStatusId, $colorId);
        if (!$stmtPort->execute()) {
            $stmtPort->close();
            throw new RuntimeException('switch_ports insert failed');
        }
        $portId = (int)$stmtPort->insert_id;
        $stmtPort->close();
        if ($portId <= 0) {
            throw new RuntimeException('switch_ports insert id missing');
        }

        if (!mysqli_commit($conn)) {
            throw new RuntimeException('commit failed');
        }
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        return null;
    }

    return [
        'switch_id' => $equipmentId,
        'status_name' => $statusName,
        'color_name' => $colorName,
        'equipment_id' => $equipmentId,
        'port_id' => $portId,
    ];
}

/**
 * @return array{http_status:int,body:string,error:string}
 */
function vupzr_invoke_update_port_contract(
    int $companyId,
    array $postPayload,
    string $configPath,
    string $updatePortPath,
    string $includesDir
): array {
    $code = "<?php
define('ITM_HTTP_ENDPOINT_CONTRACT_TEST', true);
define('ITM_CLI_SCRIPT', true);
function itm_validate_csrf_token(\$token) { return true; }
function itm_api_json_response(array \$payload, \$httpStatus = 200) {
    \$status = (int)\$httpStatus;
    echo json_encode([
        'http_status' => \$status,
        'body' => json_encode(\$payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(0);
}
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\$_SERVER['HTTP_HOST'] = 'localhost';
require " . var_export($configPath, true) . ";
\$_SESSION['user_id'] = 1;
\$_SESSION['company_id'] = " . (int)$companyId . ";
\$_POST = " . var_export($postPayload, true) . ";
\$_REQUEST = \$_POST;
chdir(" . var_export($includesDir, true) . ");
ob_start();
include " . var_export($updatePortPath, true) . ";
\$body = ob_get_clean();
echo json_encode([
    'http_status' => (int)http_response_code(),
    'body' => \$body,
    'error' => 'update_port returned without JSON response',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>";

    $tmp = tempnam(sys_get_temp_dir(), 'update-port-zero');
    if ($tmp === false) {
        return ['http_status' => 0, 'body' => '', 'error' => 'tempnam() failed'];
    }
    if (file_put_contents($tmp, $code) === false) {
        @unlink($tmp);
        return ['http_status' => 0, 'body' => '', 'error' => 'file_put_contents() failed'];
    }

    $phpBin = (defined('PHP_BINARY') && PHP_BINARY !== '') ? PHP_BINARY : 'php';

    $output = null;
    if (function_exists('proc_open')) {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open([$phpBin, $tmp], $descriptors, $pipes);
        if (is_resource($proc)) {
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
            if (trim((string)$output) === '' && trim((string)$stderr) !== '') {
                @unlink($tmp);
                return ['http_status' => 0, 'body' => '', 'error' => trim((string)$stderr)];
            }
        }
    }

    if ($output === null) {
        if (!function_exists('shell_exec')) {
            @unlink($tmp);
            return ['http_status' => 0, 'body' => '', 'error' => 'shell_exec and proc_open unavailable'];
        }
        $stderrDiscard = function_exists('itm_script_shell_stderr_discard')
            ? itm_script_shell_stderr_discard()
            : '2>/dev/null';
        $output = shell_exec(escapeshellarg($phpBin) . ' ' . escapeshellarg($tmp) . ' ' . $stderrDiscard);
        if ($output === null || trim((string)$output) === '') {
            @unlink($tmp);
            return ['http_status' => 0, 'body' => '', 'error' => 'subprocess returned no output'];
        }
    }

    @unlink($tmp);

    $wrapper = json_decode(trim((string)$output), true);
    if (!is_array($wrapper)) {
        return [
            'http_status' => 0,
            'body' => trim((string)$output),
            'error' => 'subprocess JSON wrapper decode failed',
        ];
    }

    return [
        'http_status' => (int)($wrapper['http_status'] ?? 0),
        'body' => (string)($wrapper['body'] ?? ''),
        'error' => (string)($wrapper['error'] ?? ''),
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
    $idfCountBefore = vupzr_count_idf_ports($conn, $companyId);

    $postPayload = [
        'id' => 2147483000,
        'switch_id' => $switchId,
        'status' => $statusName,
        'color' => $colorName,
        'csrf_token' => 'test',
        'company_id' => 999,
    ];

    $configPath = realpath(dirname(__DIR__) . '/config/config.php');
    $updatePortPath = realpath(dirname(__DIR__) . '/includes/update_port.php');
    $includesDir = realpath(dirname(__DIR__) . '/includes');
    if ($configPath === false || $updatePortPath === false || $includesDir === false) {
        echo colorText('[FAIL] Unable to resolve config/update_port/includes paths.', 'fail') . $nl;
        itm_script_output_end();
        exit(1);
    }

    $invoke = vupzr_invoke_update_port_contract(
        $companyId,
        $postPayload,
        $configPath,
        $updatePortPath,
        $includesDir
    );
    $httpStatus = (int)$invoke['http_status'];
    $body = (string)$invoke['body'];
    $invokeError = trim((string)$invoke['error']);
    $decoded = json_decode($body, true);
    $idfCountAfter = vupzr_count_idf_ports($conn, $companyId);

    $pass = $httpStatus === 404
        && is_array($decoded)
        && ($decoded['success'] ?? null) === false
        && $idfCountAfter === $idfCountBefore;

    if ($pass) {
        echo colorText('[PASS] Zero-row update_port returned HTTP 404 and idf_ports unchanged.', 'pass') . $nl;
    } else {
        $detail = 'http_status=' . $httpStatus . ' body=' . trim($body);
        if ($invokeError !== '') {
            $detail .= ' error=' . $invokeError;
        }
        echo colorText(
            '[FAIL] Expected HTTP 404 with success:false and unchanged idf_ports; ' . $detail,
            'fail'
        ) . $nl;
    }
} finally {
    if ($probePortId > 0) {
        $stmt = $conn->prepare('DELETE FROM switch_ports WHERE id = ? AND company_id = ?');
        if ($stmt) {
            $stmt->bind_param('ii', $probePortId, $companyId);
            $stmt->execute();
            $stmt->close();
        }
    }
    if ($probeEquipmentId > 0) {
        $stmt = $conn->prepare('DELETE FROM equipment WHERE id = ? AND company_id = ?');
        if ($stmt) {
            $stmt->bind_param('ii', $probeEquipmentId, $companyId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

itm_script_output_end();
exit(isset($pass) && $pass ? 0 : 1);
