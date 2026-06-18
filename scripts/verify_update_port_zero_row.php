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

$companyId = 1;
$switchId = 0;
$statusName = '';
$colorName = '';

$stmtEquipment = $conn->prepare(
    'SELECT e.id
     FROM equipment e
     INNER JOIN switch_ports sp ON sp.equipment_id = e.id AND sp.company_id = e.company_id
     WHERE e.company_id = ?
     LIMIT 1'
);
if (!$stmtEquipment) {
    echo colorText('[FAIL] Unable to prepare equipment lookup.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
$stmtEquipment->bind_param('i', $companyId);
$stmtEquipment->execute();
$equipmentRow = $stmtEquipment->get_result()->fetch_assoc();
$stmtEquipment->close();

if (!is_array($equipmentRow)) {
    echo colorText('[SKIP] No equipment with switch_ports for company_id=' . $companyId . '.', 'warn') . $nl;
    itm_script_output_end();
    exit(0);
}
$switchId = (int)$equipmentRow['id'];

$resStatus = mysqli_query(
    $conn,
    'SELECT status_name FROM switch_status WHERE company_id = ' . (int)$companyId . ' LIMIT 1'
);
if ($resStatus && ($statusRow = mysqli_fetch_assoc($resStatus))) {
    $statusName = (string)($statusRow['status_name'] ?? '');
}
$resColor = mysqli_query(
    $conn,
    'SELECT color_name FROM cable_colors WHERE company_id = ' . (int)$companyId . ' LIMIT 1'
);
if ($resColor && ($colorRow = mysqli_fetch_assoc($resColor))) {
    $colorName = (string)($colorRow['color_name'] ?? '');
}

if ($statusName === '' || $colorName === '') {
    echo colorText('[SKIP] Missing switch_status or cable_colors seed rows.', 'warn') . $nl;
    itm_script_output_end();
    exit(0);
}

$idfCountBefore = 0;
$resIdf = mysqli_query(
    $conn,
    'SELECT COUNT(*) AS c FROM idf_ports WHERE company_id = ' . (int)$companyId
);
if ($resIdf && ($idfRow = mysqli_fetch_assoc($resIdf))) {
    $idfCountBefore = (int)($idfRow['c'] ?? 0);
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
$resIdfAfter = mysqli_query(
    $conn,
    'SELECT COUNT(*) AS c FROM idf_ports WHERE company_id = ' . (int)$companyId
);
if ($resIdfAfter && ($idfAfterRow = mysqli_fetch_assoc($resIdfAfter))) {
    $idfCountAfter = (int)($idfAfterRow['c'] ?? 0);
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

itm_script_output_end();
exit($pass ? 0 : 1);
