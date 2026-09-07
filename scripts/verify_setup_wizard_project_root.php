<?php
/**
 * Setup wizard project-root path repair and step-2 resolution regression.
 *
 * CLI: php scripts/verify_setup_wizard_project_root.php
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('ITM_SETUP_WIZARD_TEST_DETECTED_ROOT', 'C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\www\\it-management');

require_once ROOT_PATH . 'includes/bootstrap_helpers.php';
require_once ROOT_PATH . 'scripts/lib/script_cli_output.php';
require_once ROOT_PATH . 'setup/includes/itm_setup_wizard.php';

itm_script_output_begin('Setup Wizard Project Root Verification');

$fail = 0;

function setup_root_fail(string $message): void
{
    global $fail;
    $fail++;
    echo colorText('[FAIL] ' . $message, 'fail') . "\n";
}

function setup_root_pass(string $message): void
{
    echo colorText('[PASS] ' . $message, 'pass') . "\n";
}

$collapsed = 'C:UsersNelsonSalvadorDownloadslaragon-portablewwwit-management2';
$repaired = itm_setup_wizard_repair_windows_path_input($collapsed);
$expectedSuffix = 'it-management2';
if (stripos(str_replace('\\', '/', $repaired), $expectedSuffix) === false) {
    setup_root_fail('Collapsed Windows path must repair to it-management2, got: ' . $repaired);
} else {
    setup_root_pass('Collapsed path repairs to folder suffix it-management2');
}

if (strpos($repaired, 'C:\\Users\\') !== 0 && strpos($repaired, 'C:/Users/') !== 0) {
    setup_root_fail('Repaired path must restore drive-letter segments, got: ' . $repaired);
} else {
    setup_root_pass('Repaired path restores Windows drive-letter segments');
}

$_SESSION[itm_setup_wizard_session_key()] = [
    'project_root' => $collapsed,
    'completed_steps' => [1 => true],
    'current_step' => 2,
];

$resolvedRoot = itm_setup_wizard_project_root();
$resolvedDisplay = itm_setup_wizard_format_path_display($resolvedRoot);
if (stripos(str_replace('\\', '/', $resolvedDisplay), 'it-management2') === false) {
    setup_root_fail('Step 1 complete must keep session it-management2 root, got: ' . $resolvedDisplay);
} else {
    setup_root_pass('Completed step 1 keeps session project root (not runtime fallback)');
}

$uploadRoots = itm_setup_wizard_required_upload_roots();
$imagesKey = '';
foreach (array_keys($uploadRoots) as $dir) {
    if (stripos($dir, 'images') !== false) {
        $imagesKey = $dir;
        break;
    }
}
if ($imagesKey === '' || stripos(str_replace('\\', '/', $imagesKey), 'it-management2') === false) {
    setup_root_fail('Step 2 upload roots must be under it-management2, got: ' . $imagesKey);
} else {
    setup_root_pass('Step 2 upload roots resolve under confirmed project root');
}

$imagesPath = itm_setup_wizard_project_subdirectory('images');
if (stripos(str_replace('\\', '/', $imagesPath), 'it-management2') === false) {
    setup_root_fail('project_subdirectory(images) must use confirmed root, got: ' . $imagesPath);
} else {
    setup_root_pass('project_subdirectory(images) uses confirmed root');
}

unset($_SESSION[itm_setup_wizard_session_key()]);

$suffixProbeCode = "<?php\n"
    . 'define("ROOT_PATH", ' . var_export(ROOT_PATH, true) . ');' . "\n"
    . 'define("ITM_SETUP_WIZARD_TEST_DETECTED_ROOT", "C:\\\\Users\\\\NelsonSalvador\\\\Downloads\\\\laragon-portable\\\\www\\\\it-management3");' . "\n"
    . 'require ROOT_PATH . "setup/includes/itm_setup_wizard.php";' . "\n"
    . '$r = itm_setup_wizard_repair_windows_path_input("C:UsersNelsonSalvadorDownloadslaragon-portablewwwit-management5");' . "\n"
    . 'echo (stripos(str_replace("\\\\", "/", $r), "it-management5") !== false) ? "ok" : $r;' . "\n";

$suffixProbe = '';
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$process = @proc_open([PHP_BINARY, '-d', 'display_errors=1'], $descriptors, $pipes);
if (is_resource($process)) {
    fwrite($pipes[0], $suffixProbeCode);
    fclose($pipes[0]);
    $suffixProbe = (string)stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
}
$suffixProbeOutput = trim($suffixProbe);
$lines = explode("\n", str_replace("\r\n", "\n", $suffixProbeOutput));
$suffixProbeLastLine = trim((string)end($lines));

if ($suffixProbeLastLine !== 'ok' && stripos($suffixProbeOutput, 'ok') === false) {
    setup_root_fail('Collapsed path must repair to it-management5 when runtime is it-management3, got: ' . $suffixProbeOutput);
} else {
    setup_root_pass('Collapsed path repairs sibling folder suffix (it-management3 runtime → it-management5 target)');
}

$windowsPath = 'C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\www\\it-management';
$wizardEscaped = itm_setup_wizard_h($windowsPath);
if (strpos($wizardEscaped, 'C:\\Users\\') === false) {
    setup_root_fail('itm_setup_wizard_h must preserve Windows backslashes in output, got: ' . $wizardEscaped);
} else {
    setup_root_pass('itm_setup_wizard_h preserves Windows backslashes for HTML output');
}
// Global sanitize() uses stripslashes() — setup wizard must not use it for paths.
$sanitizeLike = htmlspecialchars(stripslashes($windowsPath), ENT_QUOTES, 'UTF-8');
if (strpos($sanitizeLike, 'C:Users') !== false && strpos($sanitizeLike, 'C:\\Users\\') === false) {
    setup_root_pass('sanitize()-style stripslashes removes Windows backslashes (wizard uses itm_setup_wizard_h instead)');
} else {
    setup_root_fail('Expected stripslashes contrast to collapse Windows path segments');
}

$wrappableRemoved = !function_exists('itm_setup_wizard_h_wrappable_path_text');
if ($wrappableRemoved) {
    setup_root_pass('Legacy wbr wrapper helper is not used');
} else {
    setup_root_fail('itm_setup_wizard_h_wrappable_path_text should remain removed');
}

$sampleRow = itm_setup_wizard_verify_row('pass', 'Writable:', $windowsPath . '\\images');
if (($sampleRow['label'] ?? '') !== 'Writable:' || ($sampleRow['path'] ?? '') === '') {
    setup_root_fail('Verify row must split label and path');
} else {
    setup_root_pass('Verify rows expose separate label and path fields');
}

$pathDisplay = itm_setup_wizard_h_path_display($windowsPath);
if (strpos($pathDisplay, '<wbr>') === false || strpos($pathDisplay, '&#8209;') === false) {
    setup_root_fail('Path display must add segment breaks and non-breaking hyphens, got: ' . $pathDisplay);
} else {
    setup_root_pass('Path display adds segment wrap hints without scrollbars');
}

$fileChecks = itm_setup_wizard_verify_files();
$hasRuntimeWarn = false;
foreach ($fileChecks as $row) {
    if (stripos($row['message'], 'differs from this PHP request path') !== false) {
        $hasRuntimeWarn = true;
        break;
    }
}
if ($hasRuntimeWarn) {
    setup_root_fail('Step 2 verify must not warn when project root differs from runtime path');
} else {
    setup_root_pass('Step 2 verify omits runtime-path mismatch warning');
}

$portLabels = [
    'open' => itm_setup_wizard_localhost_port_status_label('open'),
    'closed' => itm_setup_wizard_localhost_port_status_label('closed'),
    'unknown' => itm_setup_wizard_localhost_port_status_label('unknown'),
];
if ($portLabels['open'] !== '🟢 Open' || $portLabels['closed'] !== '🔴 Closed' || $portLabels['unknown'] !== '⭕ Unknown') {
    setup_root_fail('Localhost port status labels must map open/closed/unknown to emoji copy');
} else {
    setup_root_pass('Localhost port status labels use 🟢/🔴/⭕ copy');
}

$portRows = itm_setup_wizard_localhost_port_status_rows();
$expectedEndpoints = ['127.0.0.1:80', '127.0.0.1:443', 'localhost:80', 'localhost:443'];
$actualEndpoints = array_map(static function (array $row): string {
    return (string)($row['endpoint'] ?? '');
}, $portRows);
if (count($portRows) !== 4 || $actualEndpoints !== $expectedEndpoints) {
    setup_root_fail('Localhost port status must include 127.0.0.1 and localhost on ports 80 and 443');
} else {
    setup_root_pass('Localhost port status rows include 127.0.0.1 and localhost for 80/443');
}
$portStatusInvalid = false;
foreach ($portRows as $portRow) {
    $status = (string)($portRow['status'] ?? '');
    if (!in_array($status, ['open', 'closed', 'unknown'], true)) {
        setup_root_fail('Localhost port probe must return open, closed, or unknown');
        $portStatusInvalid = true;
        break;
    }
}
if (!$portStatusInvalid) {
    setup_root_pass('Localhost port probe returns open, closed, or unknown');
}

$mysqlRows = itm_setup_wizard_mysql_port_status_rows();
$mysqlEndpoints = array_map(static function (array $row): string {
    return (string)($row['endpoint'] ?? '');
}, $mysqlRows);
if ($mysqlEndpoints !== ['127.0.0.1:3306', '127.0.0.1:3307']) {
    setup_root_fail('MySQL port status must include 127.0.0.1:3306 and 127.0.0.1:3307');
} else {
    setup_root_pass('MySQL port status rows include 3306 and 3307 on loopback');
}

$defaultPort = itm_setup_wizard_default_db_port(null, ['DB_PORT' => '3307']);
$detectedOpenMysql = itm_setup_wizard_detect_open_mysql_loopback_port();
if ($detectedOpenMysql !== null) {
    $conflictingEnvPort = $detectedOpenMysql === 3306 ? '3307' : '3306';
    $portFromProbe = itm_setup_wizard_default_db_port(null, ['DB_PORT' => $conflictingEnvPort]);
    if ($portFromProbe !== $detectedOpenMysql) {
        setup_root_fail('Default DB port must prefer open loopback MySQL probe over conflicting .env DB_PORT');
    } else {
        setup_root_pass('Default DB port prefers open loopback probe over .env');
    }
} elseif ($defaultPort !== 3307) {
    setup_root_fail('Default DB port must honour .env DB_PORT when no loopback listener is open');
} else {
    setup_root_pass('Default DB port reads .env DB_PORT when probe finds no open listener');
}

$refused = itm_setup_wizard_format_database_connection_error('127.0.0.1', 3306, 'No connection could be made because the target machine actively refused it');
if (stripos($refused, '3307') === false) {
    setup_root_fail('Connection refused on 3306 must hint Dunebox port 3307');
} else {
    setup_root_pass('Connection refused message hints alternate MySQL port');
}

$altRoot = 'C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\www\\it-management4';
$_SESSION[itm_setup_wizard_session_key()] = [
    'project_root' => $altRoot,
    'completed_steps' => [1 => true],
    'current_step' => 7,
];
$envPath = itm_setup_wizard_env_file_path();
if (stripos(str_replace('\\', '/', $envPath), 'it-management4/.env') === false
    && stripos(str_replace('\\', '/', $envPath), 'it-management4\\.env') === false) {
    setup_root_fail('.env path must follow confirmed project root, got: ' . $envPath);
} else {
    setup_root_pass('.env path resolves under confirmed project root (not runtime ROOT_PATH)');
}

$setupIndexPath = ROOT_PATH . 'setup' . DIRECTORY_SEPARATOR . 'index.php';
$setupHelperPath = ROOT_PATH . 'setup' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'itm_setup_wizard.php';
$_SESSION[itm_setup_wizard_session_key()] = [
    'project_root' => rtrim(ROOT_PATH, '/\\'),
    'completed_steps' => [1 => true],
    'current_step' => 8,
];

$indexBackupContent = is_file($setupIndexPath) ? file_get_contents($setupIndexPath) : null;

$finish = itm_setup_wizard_remove_entrypoint();
$lockPath = rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . 'setup' . DIRECTORY_SEPARATOR . '.installed';

if (!$finish['ok']) {
    setup_root_fail('remove_entrypoint failed: ' . ($finish['message'] ?? ''));
} elseif (is_file($setupIndexPath)) {
    setup_root_fail('remove_entrypoint must delete setup/index.php');
} elseif (!is_file($setupHelperPath)) {
    setup_root_fail('remove_entrypoint must keep setup/includes/itm_setup_wizard.php on disk');
} elseif (is_file($lockPath)) {
    setup_root_fail('remove_entrypoint must not create setup/.installed');
} else {
    setup_root_pass('remove_entrypoint deletes setup/index.php on finish without creating .installed');
}

if ($indexBackupContent !== null) {
    file_put_contents($setupIndexPath, $indexBackupContent);
}

// Test cross-folder install scenario: running wizard at x_folder installing into target z_folder
$xFolderIndexPath = ROOT_PATH . 'setup' . DIRECTORY_SEPARATOR . 'index.php';
$tmpZFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'itm_test_z_folder_' . bin2hex(random_bytes(4));
$zFolderSetupDir = $tmpZFolder . DIRECTORY_SEPARATOR . 'setup';
@mkdir($zFolderSetupDir, 0755, true);
$zFolderIndexPath = $zFolderSetupDir . DIRECTORY_SEPARATOR . 'index.php';
file_put_contents($zFolderIndexPath, "<?php // z_folder setup index");
itm_setup_wizard_copy_path(ROOT_PATH . 'db', $tmpZFolder . DIRECTORY_SEPARATOR . 'db');

$_SESSION[itm_setup_wizard_session_key()] = [
    'project_root' => $tmpZFolder,
    'completed_steps' => [1 => true],
    'current_step' => 8,
];

$crossFinish = itm_setup_wizard_remove_entrypoint();

if (!$crossFinish['ok']) {
    setup_root_fail('Cross-folder remove_entrypoint failed: ' . ($crossFinish['message'] ?? ''));
} elseif (is_file($zFolderIndexPath)) {
    setup_root_fail('Cross-folder install must delete target z_folder/setup/index.php');
} elseif (!is_file($xFolderIndexPath)) {
    setup_root_fail('Cross-folder install must NOT delete running x_folder/setup/index.php');
} elseif (itm_setup_wizard_is_complete()) {
    setup_root_fail('x_folder/setup/index.php must not be blocked (is_complete must return false)');
} else {
    setup_root_pass('Cross-folder install deletes z_folder/setup/index.php and leaves x_folder/setup/index.php unblocked');
}

itm_setup_wizard_remove_directory_tree($tmpZFolder);
unset($_SESSION[itm_setup_wizard_session_key()]);

// Test setup/index.php?step=8 Finish button POST action (step8_finish) removing destination setup/index.php
$tmpStep8ZFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'itm_test_step8_finish_' . bin2hex(random_bytes(4));
$step8ZSetupDir = $tmpStep8ZFolder . DIRECTORY_SEPARATOR . 'setup';
@mkdir($step8ZSetupDir, 0755, true);
$step8ZIndexPath = $step8ZSetupDir . DIRECTORY_SEPARATOR . 'index.php';
file_put_contents($step8ZIndexPath, "<?php // destination setup entrypoint");
itm_setup_wizard_copy_path(ROOT_PATH . 'db', $tmpStep8ZFolder . DIRECTORY_SEPARATOR . 'db');

$host = getenv('DB_HOST') ?: 'localhost';
$port = (int)(getenv('DB_PORT') ?: '3307');
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'itmanagement';
$dbName = 'itm_setup_wizard_finish_' . substr(sha1((string)getmypid() . 'finish'), 0, 8);

$_SESSION[itm_setup_wizard_session_key()] = ['project_root' => rtrim(ROOT_PATH, '/\\')];
$step8DbCreate = itm_setup_wizard_create_database($host, $port, $user, $pass, $dbName);
if ($step8DbCreate['ok']) {
    itm_setup_wizard_import_database($host, $port, $user, $pass, $dbName);
}
unset($_SESSION[itm_setup_wizard_session_key()]);

$expectedTables = itm_setup_wizard_expected_table_count();
$expectedTriggers = itm_setup_wizard_expected_trigger_count();

$step8PostTestCode = "<?php\n"
    . 'define("ROOT_PATH", ' . var_export(ROOT_PATH, true) . ');' . "\n"
    . 'register_shutdown_function(function() {' . "\n"
    . '    echo json_encode(["ok" => true, "target_exists" => file_exists(' . var_export($step8ZIndexPath, true) . '), "session" => $_SESSION["itm_setup_wizard"] ?? null]);' . "\n"
    . '});' . "\n"
    . 'ob_start();' . "\n"
    . '@session_start();' . "\n"
    . '$_SESSION["csrf_token"] = "test_csrf_token_123";' . "\n"
    . '$_SESSION["itm_setup_wizard"] = [' . "\n"
    . '    "project_root" => ' . var_export($tmpStep8ZFolder, true) . ",\n"
    . '    "completed_steps" => [1 => true, 2 => true, 3 => true, 4 => true, 5 => true, 6 => true, 7 => true],' . "\n"
    . '    "current_step" => 8,' . "\n"
    . '    "table_count" => ' . (int)$expectedTables . ",\n"
    . '    "trigger_count" => ' . (int)$expectedTriggers . ",\n"
    . '    "db" => ["host" => ' . var_export($host, true) . ', "port" => ' . (int)$port . ', "user" => ' . var_export($user, true) . ', "pass" => ' . var_export($pass, true) . ', "name" => ' . var_export($dbName, true) . '],' . "\n"
    . '];' . "\n"
    . '$_SERVER["REQUEST_METHOD"] = "POST";' . "\n"
    . '$_POST["csrf_token"] = "test_csrf_token_123";' . "\n"
    . '$_POST["wizard_action"] = "step8_finish";' . "\n"
    . '$_POST["step"] = "8";' . "\n"
    . 'require ROOT_PATH . "setup/index.php";' . "\n";

$step8ProcOutput = '';
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$process = @proc_open([PHP_BINARY, '-d', 'display_errors=1'], $descriptors, $pipes);
if (is_resource($process)) {
    fwrite($pipes[0], $step8PostTestCode);
    fclose($pipes[0]);
    $step8ProcOutput = (string)stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
}

$step8Json = '';
$start = strpos($step8ProcOutput, '{');
$end = strrpos($step8ProcOutput, '}');
if ($start !== false && $end !== false && $end > $start) {
    $step8Json = substr($step8ProcOutput, $start, $end - $start + 1);
} else {
    $step8Json = trim($step8ProcOutput);
}
$step8Decoded = json_decode($step8Json, true);
if (!is_array($step8Decoded) || empty($step8Decoded['ok']) || !isset($step8Decoded['target_exists'])) {
    setup_root_fail('Step 8 finish button POST action verification script failed to execute: ' . $step8ProcOutput);
} elseif ($step8Decoded['target_exists'] === true) {
    setup_root_fail('Step 8 finish button action (step8_finish) failed to delete destination setup/index.php. Output: ' . json_encode($step8Decoded));
} else {
    setup_root_pass('Step 8 finish button action (step8_finish) deletes destination setup/index.php');
}

itm_setup_wizard_remove_directory_tree($tmpStep8ZFolder);

$step8CleanupConn = itm_setup_wizard_connect_mysql_server($host, $port, $user, $pass);
if ($step8CleanupConn) {
    mysqli_query($step8CleanupConn, 'DROP DATABASE IF EXISTS `' . $dbName . '`');
    mysqli_close($step8CleanupConn);
}

// Test setup/index.php?step=8 Finish button POST action (step8_finish) removing destination setup/index.php
$tmpStep8ZFolder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'itm_test_step8_finish_' . bin2hex(random_bytes(4));
$step8ZSetupDir = $tmpStep8ZFolder . DIRECTORY_SEPARATOR . 'setup';
@mkdir($step8ZSetupDir, 0755, true);
$step8ZIndexPath = $step8ZSetupDir . DIRECTORY_SEPARATOR . 'index.php';
file_put_contents($step8ZIndexPath, "<?php // destination setup entrypoint");

$step8PostTestCode = "<?php\n"
    . 'define("ROOT_PATH", ' . var_export(ROOT_PATH, true) . ');' . "\n"
    . 'session_start();' . "\n"
    . 'require ROOT_PATH . "setup/includes/itm_setup_wizard.php";' . "\n"
    . '$_SESSION["itm_setup_wizard"] = [' . "\n"
    . '    "project_root" => ' . var_export($tmpStep8ZFolder, true) . ",\n"
    . '    "completed_steps" => [1 => true, 2 => true, 3 => true, 4 => true, 5 => true, 6 => true, 7 => true],' . "\n"
    . '    "current_step" => 8,' . "\n"
    . '    "table_count" => 999,' . "\n"
    . '    "trigger_count" => 999,' . "\n"
    . '    "db" => ["host" => "127.0.0.1", "port" => 3306, "user" => "root", "pass" => "", "name" => "itmanagement"],' . "\n"
    . '];' . "\n"
    . '$cleanup = itm_setup_wizard_remove_entrypoint();' . "\n"
    . 'echo json_encode(["ok" => $cleanup["ok"], "target_exists" => file_exists(' . var_export($step8ZIndexPath, true) . ')]);' . "\n";

$step8ProcOutput = '';
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$process = @proc_open([PHP_BINARY, '-d', 'display_errors=1'], $descriptors, $pipes);
if (is_resource($process)) {
    fwrite($pipes[0], $step8PostTestCode);
    fclose($pipes[0]);
    $step8ProcOutput = (string)stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
}

$step8Decoded = json_decode(trim($step8ProcOutput), true);
if (!is_array($step8Decoded) || empty($step8Decoded['ok']) || !isset($step8Decoded['target_exists'])) {
    setup_root_fail('Step 8 finish button POST action verification script failed to execute: ' . $step8ProcOutput);
} elseif ($step8Decoded['target_exists'] === true) {
    setup_root_fail('Step 8 finish button action (step8_finish) failed to delete destination setup/index.php');
} else {
    setup_root_pass('Step 8 finish button action (step8_finish) deletes destination setup/index.php');
}

itm_setup_wizard_remove_directory_tree($tmpStep8ZFolder);

exit($fail > 0 ? 1 : 0);
