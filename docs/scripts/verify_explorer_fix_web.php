<?php
/**
 * Web-friendly verification script for Explorer Path Traversal fix
 */

define('ITM_CLI_SCRIPT', true);
putenv('ITM_SKIP_DB_TESTS=1');
define('ITM_VERIFY_SKIP_ROUTER', true);

// Setup paths
$projectRoot = dirname(dirname(__DIR__));
require_once $projectRoot . '/config/config.php';
require_once $projectRoot . '/includes/itm_explorer_paths.php';

// Include the FIXED file logic
require_once $projectRoot . '/docs/fixed_files_vulnerability_explorer/fixed_files/modules/explorer/api.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Explorer Fix Verification</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f4f4f9; }
        .result { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .pass { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .fail { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        h2 { border-bottom: 2px solid #ccc; padding-bottom: 10px; }
    </style>
</head>
<body>
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
            [<?php echo $isPass ? 'PASS' : 'FAIL'; ?>] <?php echo htmlspecialchars($message); ?>
        </div>
        <?php
    }
    ?>
</body>
</html>
