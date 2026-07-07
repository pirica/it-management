<?php
/**
 * Web-friendly verification script for Explorer Path Traversal fix
 */

define('ITM_CLI_SCRIPT', true);
putenv('ITM_SKIP_DB_TESTS=1');
define('ITM_VERIFY_SKIP_ROUTER', true);

// Setup paths
$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/config/config.php';
require_once $projectRoot . '/scripts/lib/script_cli_output.php';
require_once $projectRoot . '/scripts/lib/script_browser_nav.php';
require_once $projectRoot . '/includes/itm_explorer_paths.php';

// Include the LIVE file logic
require_once $projectRoot . '/modules/explorer/api.php';

// Mock session
$_SESSION['employee_id'] = 123;
$_SESSION['company_id'] = 1;
$_SESSION['username'] = 'attacker';
$_SESSION['csrf_token'] = 'test_token';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Explorer Fix Verification</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        body { padding: 20px; background: var(--bg-secondary, #f6f8fa); }
        .result { padding: 15px; margin: 15px 0; border-radius: 6px; border: 1px solid var(--border, #d0d7de); background: var(--bg-primary, #fff); }
        .pass { border-left: 5px solid #2da44e; }
        .fail { border-left: 5px solid #cf222e; }
        h2 { border-bottom: 1px solid var(--border, #d0d7de); padding-bottom: 10px; margin-top: 0; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.85em; font-weight: 600; margin-top: 8px; }
        .badge-pass { background: #dafbe1; color: #1a7f37; }
        .badge-fail { background: #ffebe9; color: #a40e26; }
    </style>
</head>
<body>
    <?php itm_script_browser_nav_echo(); ?>
    <div style="max-width: 800px; margin: 20px auto;">
        <h2>Explorer Path Traversal Fix Verification</h2>

        <?php
        $testCases = [
            ['item' => '..', 'label' => "Action: zip, Item: .."],
            ['item' => 'sub/../../', 'label' => "Action: zip, Item: sub/../../"],
            ['item' => 'valid_file.txt', 'label' => "Action: zip, Item: valid_file.txt"]
        ];

        foreach ($testCases as $case) {
            $_POST['item'] = $case['item'];
            $safe_item = get_safe_post_item();
            $isPass = false;
            $message = "";

            if ($case['item'] === 'valid_file.txt') {
                if ($safe_item === 'valid_file.txt') {
                    $isPass = true;
                    $message = "Valid item correctly allowed.";
                } else {
                    $message = "Valid item incorrectly blocked!";
                }
            } else {
                if ($safe_item === null) {
                    $isPass = true;
                    $message = "Path Traversal correctly blocked.";
                } else {
                    $message = "Path Traversal allowed! Item: " . htmlspecialchars($safe_item);
                }
            }
            ?>
            <div class="result <?php echo $isPass ? 'pass' : 'fail'; ?>">
                <strong><?php echo htmlspecialchars($case['label']); ?></strong><br>
                <?php echo htmlspecialchars($message); ?><br>
                <span class="status-badge <?php echo $isPass ? 'badge-pass' : 'badge-fail'; ?>">
                    <?php echo $isPass ? '[PASS]' : '[FAIL]'; ?>
                </span>
            </div>
            <?php
        }
        ?>
    </div>
</body>
</html>
<!-- Standard: scripts/SCRIPTS.md -->
