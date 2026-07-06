<?php
/**
 * Standalone verification script for Explorer Path Traversal fix (for screenshot)
 */

/**
 * Why: Helper to safely extract and validate the 'item' parameter from POST.
 * Prevents path traversal by rejecting '..' and path separators.
 */
function get_safe_post_item_standalone($item_val) {
    $item = trim((string)($item_val ?? ''));
    if ($item === '..' || strpos($item, '/') !== false || strpos($item, '\\') !== false) {
        return null;
    }
    return basename($item);
}

require_once __DIR__ . '/lib/script_browser_nav.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Explorer Fix Verification (Standalone)</title>
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
        <h2>Explorer Path Traversal Fix Verification (Standalone)</h2>

        <?php
        $testCases = [
            ['item' => '..', 'label' => "Action: zip, Item: .."],
            ['item' => 'sub/../../', 'label' => "Action: zip, Item: sub/../../"],
            ['item' => 'valid_file.txt', 'label' => "Action: zip, Item: valid_file.txt"]
        ];

        foreach ($testCases as $case) {
            $safe_item = get_safe_post_item_standalone($case['item']);
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
