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

require_once ROOT_PATH . 'setup/includes/itm_setup_wizard.php';

$fail = 0;

function setup_root_fail(string $message): void
{
    global $fail;
    $fail++;
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
}

function setup_root_pass(string $message): void
{
    fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
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

$suffixProbe = shell_exec(
    'php -r '
    . escapeshellarg(
        'define("ROOT_PATH", ' . var_export(ROOT_PATH, true) . ');'
        . 'define("ITM_SETUP_WIZARD_TEST_DETECTED_ROOT", "C:\\\\Users\\\\NelsonSalvador\\\\Downloads\\\\laragon-portable\\\\www\\\\it-management3");'
        . 'require ROOT_PATH . "setup/includes/itm_setup_wizard.php";'
        . '$r = itm_setup_wizard_repair_windows_path_input("C:UsersNelsonSalvadorDownloadslaragon-portablewwwit-management5");'
        . 'echo (stripos(str_replace("\\\\", "/", $r), "it-management5") !== false) ? "ok" : $r;'
    )
);
$suffixProbe = is_string($suffixProbe) ? trim($suffixProbe) : '';
if ($suffixProbe !== 'ok') {
    setup_root_fail('Collapsed path must repair to it-management5 when runtime is it-management3, got: ' . $suffixProbe);
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
if ($defaultPort !== 3307) {
    setup_root_fail('Default DB port must honour .env DB_PORT');
} else {
    setup_root_pass('Default DB port reads .env DB_PORT');
}

$refused = itm_setup_wizard_format_database_connection_error('127.0.0.1', 3306, 'No connection could be made because the target machine actively refused it');
if (stripos($refused, '3307') === false) {
    setup_root_fail('Connection refused on 3306 must hint Dunebox port 3307');
} else {
    setup_root_pass('Connection refused message hints alternate MySQL port');
}

exit($fail > 0 ? 1 : 0);
