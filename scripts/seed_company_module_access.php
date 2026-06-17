<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';

$isCli = (php_sapi_name() === 'cli');
$nl = itm_script_output_nl();

if (!$isCli) {
    itm_script_browser_nav_echo();
    echo '<h1>Seed Company Module Access</h1>';
}

if (!$conn instanceof mysqli) {
    echo 'Database connection is required.' . $nl;
    exit(1);
}

itm_sync_modules_registry_from_filesystem($conn);

$companyId = isset($argv[1]) ? (int)$argv[1] : 0;
$total = 0;

if ($companyId > 0) {
    $total = itm_seed_company_module_access_for_company($conn, $companyId);
    echo 'Seeded ' . $total . ' rows for company ' . $companyId . '.' . $nl;
    exit(0);
}

$res = mysqli_query($conn, 'SELECT id FROM companies WHERE active = 1');
while ($res && ($row = mysqli_fetch_assoc($res))) {
    $total += itm_seed_company_module_access_for_company($conn, (int)$row['id']);
}

echo 'Seeded ' . $total . ' company_module_access rows across active companies.' . $nl;
exit(0);
