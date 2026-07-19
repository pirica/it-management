<?php
/**
 * Favicon pipeline diagnostic: Settings DB path, on-disk .ico, module <head> wiring.
 *
 * Why: Tab icons need (1) ui_configuration.favicon_path + file on disk, (2) config.php
 * $favicon_url, (3) itm_render_head_favicon_link() in module <head>. apply_head_favicon_link.php
 * fixes only (3); empty DB path or missing .ico keeps $favicon_url empty.
 *
 * Browser + CLI (Admin). Exit 1 when any seed-admin row cannot resolve a favicon URL.
 */
declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_ui_list_contract_checks.php';
require_once __DIR__ . '/lib/itm_fields_missing_report.php';
require_once __DIR__ . '/lib/itm_titles_list_audit.php';

itm_script_output_begin('Verify favicon root cause');
$nl = itm_script_output_nl();
$root = rtrim(ROOT_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$faviconsDir = $root . 'images' . DIRECTORY_SEPARATOR . 'favicons' . DIRECTORY_SEPARATOR;

if (!($conn instanceof mysqli)) {
    echo 'FAIL: database connection unavailable.' . $nl;
    if (getenv('ITM_SKIP_DB_TESTS') === '1') {
        echo 'Unset ITM_SKIP_DB_TESTS (it disables mysqli in CLI config.php).' . $nl;
    } else {
        echo 'Start MySQL and confirm config.php credentials.' . $nl;
    }
    itm_script_output_end();
    exit(1);
}

$sampleModule = trim((string) ($_GET['module'] ?? ''));
if ($sampleModule === '' && PHP_SAPI === 'cli') {
    global $argv;
    foreach ($argv ?? [] as $arg) {
        $arg = (string) $arg;
        if (strpos($arg, '--module=') === 0) {
            $sampleModule = trim(substr($arg, 9));
            break;
        }
    }
}
if ($sampleModule === '') {
    $sampleModule = 'employees';
}

$dataFailures = [];
$dbRows = 0;
$dbOk = 0;

echo '=== 1. Settings DB (ui_configuration seed admins) ===' . $nl;
$sql = "SELECT uc.company_id, uc.employee_id, e.username, uc.favicon_path, uc.app_name
        FROM ui_configuration uc
        INNER JOIN employees e ON e.id = uc.employee_id
        WHERE e.work_email LIKE 'admin@techcorp.example%.com'
        ORDER BY uc.company_id";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo 'FAIL: could not query ui_configuration.' . $nl;
    itm_script_output_end();
    exit(1);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $dbRows++;
    $companyId = (int) ($row['company_id'] ?? 0);
    $employeeId = (int) ($row['employee_id'] ?? 0);
    $username = (string) ($row['username'] ?? '');
    $path = trim((string) ($row['favicon_path'] ?? ''));
    $abs = $path !== '' ? $root . str_replace('/', DIRECTORY_SEPARATOR, $path) : '';
    $fileOk = $path !== '' && is_file($abs);
    $url = itm_ui_config_favicon_url(['favicon_path' => $path]);
    $urlOk = $url !== '';

    if ($urlOk) {
        $dbOk++;
        $status = 'OK';
    } else {
        $reason = $path === '' ? 'favicon_path empty in DB' : 'path set but .ico missing on disk';
        $dataFailures[] = "company {$companyId} ({$username}): {$reason}";
        $status = 'FAIL';
    }

    echo sprintf(
        '  [%s] company %d employee %d (%s) path=%s file=%s url=%s%s',
        $status,
        $companyId,
        $employeeId,
        $username,
        $path === '' ? '(empty)' : $path,
        $fileOk ? 'yes' : 'no',
        $urlOk ? $url : '(empty)',
        $nl
    );
}
mysqli_stmt_close($stmt);

if ($dbRows === 0) {
    echo '  WARN: no seed-admin ui_configuration rows (expected admin@techcorp.example*.com).' . $nl;
    $dataFailures[] = 'no seed-admin ui_configuration rows found';
}

echo $nl . '=== 2. images/favicons on disk ===' . $nl;
$icoFiles = glob($faviconsDir . '*.ico') ?: [];
echo '  .ico count: ' . count($icoFiles) . $nl;
foreach ($icoFiles as $icoPath) {
    echo '  - ' . basename($icoPath) . $nl;
}
if ($icoFiles === []) {
    echo '  (none — upload via Settings or place company_{id}.ico here)' . $nl;
}

echo $nl . '=== 3. Module PHP wiring (standalone <head>, favicon gate) ===' . $nl;
$modulesDir = $root . 'modules';
$wiringStats = [
    'index' => ['pass' => 0, 'fail' => 0, 'na' => 0],
    'create' => ['pass' => 0, 'fail' => 0, 'na' => 0],
    'edit' => ['pass' => 0, 'fail' => 0, 'na' => 0],
    'view' => ['pass' => 0, 'fail' => 0, 'na' => 0],
];
$entryNames = ['index.php' => 'index', 'create.php' => 'create', 'edit.php' => 'edit', 'view.php' => 'view'];

foreach ($entryNames as $fileName => $bucket) {
    foreach (glob($modulesDir . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $fileName) ?: [] as $entryPath) {
        $content = (string) file_get_contents($entryPath);
        if (!itm_fields_missing_file_has_standalone_html_head($content)) {
            $wiringStats[$bucket]['na']++;
            continue;
        }
        $check = itm_check_module_favicon_link($content, $content);
        $status = strtolower((string) ($check['status'] ?? ''));
        if ($status === 'pass') {
            $wiringStats[$bucket]['pass']++;
        } else {
            $wiringStats[$bucket]['fail']++;
        }
    }
}

foreach ($wiringStats as $bucket => $counts) {
    echo sprintf(
        '  %s: pass %d / fail %d / n/a %d%s',
        $bucket,
        (int) $counts['pass'],
        (int) $counts['fail'],
        (int) $counts['na'],
        $nl
    );
}

echo $nl . '=== 4. Sample module: ' . $sampleModule . ' ===' . $nl;
$sampleDir = $modulesDir . DIRECTORY_SEPARATOR . $sampleModule;
if (!is_dir($sampleDir)) {
    echo '  Module folder not found.' . $nl;
} else {
    foreach ($entryNames as $fileName => $label) {
        $entryPath = $sampleDir . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($entryPath)) {
            echo "  {$fileName}: (missing){$nl}";
            continue;
        }
        $content = (string) file_get_contents($entryPath);
        if (!itm_fields_missing_file_has_standalone_html_head($content)) {
            echo "  {$fileName}: no standalone <head> (skipped){$nl}";
            continue;
        }
        $check = itm_check_module_favicon_link($content, $content);
        echo sprintf(
            '  %s: gate=%s%s',
            $fileName,
            (string) ($check['status'] ?? '?'),
            $nl
        );
    }
}

echo $nl . '=== 5. Remediation ===' . $nl;
echo '  Data layer: Settings → upload .ico (sets favicon_path + file), or UPDATE ui_configuration.favicon_path then add images/favicons/company_{id}.ico.' . $nl;
echo '  PHP wiring: php scripts/apply_head_favicon_link.php (dry-run) then --apply via CLI (no browser Admin required).' . $nl;
echo '  Browser apply_head_favicon_link.php?apply=1 requires Admin PHPSESSID.' . $nl;
echo '  Contract scan: php scripts/verify_module_page_chrome.php' . $nl;

echo $nl . '--- Summary ---' . $nl;
echo 'Seed-admin rows with working favicon URL: ' . $dbOk . ' / ' . $dbRows . $nl;
$wiringFailTotal = 0;
foreach ($wiringStats as $counts) {
    $wiringFailTotal += (int) $counts['fail'];
}
echo 'Module <head> favicon gate failures: ' . $wiringFailTotal . $nl;

if ($dataFailures !== []) {
    echo $nl . 'Data-layer failures:' . $nl;
    foreach ($dataFailures as $line) {
        echo '  - ' . $line . $nl;
    }
    echo $nl . 'Result: fail (Settings DB and/or missing .ico — $favicon_url stays empty)' . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . 'Result: pass (data layer OK';
if ($wiringFailTotal > 0) {
    echo '; wiring failures remain — run apply_head_favicon_link.php --apply';
}
echo ')' . $nl;
itm_script_output_end();
exit($wiringFailTotal > 0 ? 1 : 0);
