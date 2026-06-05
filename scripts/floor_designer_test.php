<?php
/**
 * Floor Designer Static Test
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

function itm_test_log(string $msg): void {
    echo $msg . PHP_EOL;
}

$errors = 0;

itm_test_log("Checking Floor Designer Tables...");
$tables = ['floor_designer', 'floor_designer_points'];
foreach ($tables as $t) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    if (!$res || mysqli_num_rows($res) === 0) {
        itm_test_log("[FAIL] Table $t is missing.");
        $errors++;
    } else {
        itm_test_log("[PASS] Table $t exists.");
    }
}

itm_test_log("\nChecking Index File...");
$indexPath = __DIR__ . '/../modules/floor_designer/index.php';
if (!is_file($indexPath)) {
    itm_test_log("[FAIL] modules/floor_designer/index.php is missing.");
    $errors++;
} else {
    $content = file_get_contents($indexPath);
    if (strpos($content, 'ajax_action') === false) {
        itm_test_log("[FAIL] ajax_action handlers not found in index.php");
        $errors++;
    } else {
        itm_test_log("[PASS] ajax_action handlers found.");
    }
    
    if (strpos($content, 'html2canvas') === false) {
        itm_test_log("[FAIL] html2canvas library not included.");
        $errors++;
    } else {
        itm_test_log("[PASS] html2canvas library found.");
    }
}

itm_test_log("\nSummary: " . ($errors === 0 ? "SUCCESS" : "FAILED with $errors errors"));
exit($errors === 0 ? 0 : 1);
