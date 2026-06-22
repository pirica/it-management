<?php
/**
 * Bolt Profiler: Equipment Module
 *
 * Measures query count and timing for modules/equipment/index.php.
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the CLI.\n");
}

define('ITM_CLI_SCRIPT', true);
$repoRoot = dirname(dirname(dirname(__DIR__)));

// Mocking some server variables for the include
$_SERVER['PHP_SELF'] = '/it-management/modules/equipment/index.php';
$_SERVER['SCRIPT_FILENAME'] = $repoRoot . '/modules/equipment/index.php';

require_once $repoRoot . '/config/config.php';

function profile_equipment_index($conn, $companyId, $spm = '0') {
    $repoRoot = dirname(dirname(dirname(__DIR__)));

    // Reset session for the test
    $_SESSION['employee_id'] = 1; // Admin
    $_SESSION['company_id'] = $companyId;
    $_SESSION['username'] = 'admin';
    $_SESSION['csrf_token'] = 'bolt-test-token';

    // Set GET params
    $_GET['spm'] = $spm;
    $_GET['page'] = '1';

    // Capture baseline questions
    $res = mysqli_query($conn, "SHOW SESSION STATUS LIKE 'Questions'");
    $row = mysqli_fetch_assoc($res);
    $before = (int)$row['Value'];

    $start = microtime(true);

    // Buffer output to avoid cluttering CLI
    ob_start();
    try {
        // Change directory to the module directory so relative requires work
        $oldDir = getcwd();
        chdir($repoRoot . '/modules/equipment');
        include 'index.php';
        chdir($oldDir);
    } catch (Throwable $e) {
        ob_end_clean();
        return ["error" => $e->getMessage()];
    }
    ob_end_clean();

    $end = microtime(true);

    // Capture end questions
    $res = mysqli_query($conn, "SHOW SESSION STATUS LIKE 'Questions'");
    $row = mysqli_fetch_assoc($res);
    $after = (int)$row['Value'];

    // Subtract 2 for the SHOW SESSION STATUS queries themselves and potentially some internal ones
    $delta = $after - $before - 1;

    return [
        "queries" => $delta,
        "time_ms" => ($end - $start) * 1000
    ];
}

echo "--- Bolt Profiling: Equipment Module ---\n";
echo "Scenario: spm=0 (Default List View)\n";

$result = profile_equipment_index($conn, 1, '0');

if (isset($result['error'])) {
    echo "Error: " . $result['error'] . "\n";
} else {
    echo "Queries: " . $result['queries'] . "\n";
    echo "Execution Time: " . number_format($result['time_ms'], 2) . " ms\n";
}
