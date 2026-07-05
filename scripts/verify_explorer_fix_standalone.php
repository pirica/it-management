<?php
/**
 * Standalone verification script for Explorer Path Traversal fix (for screenshot)
 */

/**
 * Why: Helper to safely extract and validate the 'item' parameter from POST.
 * Prevents path traversal by rejecting '..' and path separators.
 */
function get_safe_post_item($item_val) {
    $item = trim((string)($item_val ?? ''));
    if ($item === '..' || strpos($item, '/') !== false || strpos($item, '\\') !== false) {
        return null;
    }
    return basename($item);
}

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
        $safe_item = get_safe_post_item($case['item']);
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
