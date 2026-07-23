<?php
/**
 * SQL Insert Utility
 *
 * Why: Allows administrators to paste and execute raw INSERT commands
 * while maintaining audit logging and optionally toggling FK checks.
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
    if (!defined('ITM_CLI_SCRIPT')) {
        define('ITM_CLI_SCRIPT', true);
    }
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');

// Only Admins can access this script in the browser
itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

$action = $_POST['action'] ?? '';
$sqlInput = $_POST['sql_commands'] ?? '';
$disableFk = isset($_POST['disable_fk']) && $_POST['disable_fk'] === '1';

if ($isCli) {
    $options = getopt('', ['file:', 'disable-fk']);
    $filePath = $options['file'] ?? '';
    if ($filePath !== '' && is_readable($filePath)) {
        $sqlInput = file_get_contents($filePath);
    }
    $disableFk = isset($options['disable-fk']);
    $action = ($sqlInput !== '') ? 'execute' : '';
}

if ($action === 'execute' && $sqlInput !== '') {
    if (!$isCli) {
        if (!function_exists('itm_require_post_csrf')) {
            die('Security helper itm_require_post_csrf missing.');
        }
        itm_require_post_csrf();
    }

    itm_script_output_begin('SQL Insert Execution');
    $nl = itm_script_output_nl();

    if ($disableFk) {
        echo "Disabling Foreign Key Checks..." . $nl;
        itm_run_query($conn, 'SET FOREIGN_KEY_CHECKS = 0');
    }

    // Split by semicolon.
    $commands = explode(';', $sqlInput);
    $successCount = 0;
    $errorCount = 0;

    foreach ($commands as $cmd) {
        $cmd = trim($cmd);
        if ($cmd === '') {
            continue;
        }

        // Clean up leading comments for the execution check, but keep the command
        $cleanCmd = $cmd;
        while (strpos(trim($cleanCmd), '--') === 0) {
            $lines = explode("\n", $cleanCmd, 2);
            $cleanCmd = $lines[1] ?? '';
        }

        if (trim($cleanCmd) === '') {
            continue;
        }

        if (itm_run_query($conn, $cmd, $errorCode, $errorMessage)) {
            $successCount++;
        } else {
            echo "Error executing: " . substr($cmd, 0, 100) . "..." . $nl;
            echo "Message: $errorMessage (Code: $errorCode)" . $nl . $nl;
            $errorCount++;
        }
    }

    if ($disableFk) {
        echo "Re-enabling Foreign Key Checks..." . $nl;
        itm_run_query($conn, 'SET FOREIGN_KEY_CHECKS = 1');
    }

    echo $nl . "Summary:" . $nl;
    echo "Success: $successCount" . $nl;
    echo "Errors: $errorCount" . $nl;

    itm_script_output_end();
    if (!$isCli) {
        echo '<p><a href="sql_insert.php">Back to SQL Insert</a></p>';
    }
    die();
}

// Web Interface
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SQL Insert Utility</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; margin: 20px; color: #24292f; }
        .container { max-width: 800px; margin: 0 auto; }
        textarea { width: 100%; height: 300px; font-family: Consolas, "Courier New", monospace; padding: 10px; border: 1px solid #d0d7de; border-radius: 6px; box-sizing: border-box; font-size: 13px; }
        .btn { display: inline-block; padding: 8px 16px; font-size: 14px; font-weight: 600; text-align: center; color: #fff; background-color: #2da44e; border: 1px solid rgba(27,31,36,0.15); border-radius: 6px; cursor: pointer; text-decoration: none; }
        .btn:hover { background-color: #2c974b; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: 600; margin-bottom: 5px; }
        .help-text { font-size: 12px; color: #57606a; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <?php itm_script_browser_nav_echo(); ?>
        <h1>SQL Insert Utility</h1>
        <p>Paste your SQL <code>INSERT</code> commands below. Commands should be separated by semicolons.</p>

        <form method="post" action="sql_insert.php">
            <input type="hidden" name="action" value="execute">
            <input type="hidden" name="csrf_token" value="<?php echo itm_get_csrf_token(); ?>">

            <div class="form-group">
                <label for="sql_commands">SQL Commands</label>
                <textarea name="sql_commands" id="sql_commands" placeholder="INSERT INTO ..."></textarea>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="disable_fk" value="1"> Disable Foreign Key Checks
                </label>
                <p class="help-text">Check this if you are inserting data that might temporarily violate foreign key constraints (e.g. bulk imports with specific IDs).</p>
            </div>

            <div class="form-group">
                <button type="submit" class="btn">Execute SQL</button>
            </div>
        </form>

        <hr>
        <h3>CLI Usage</h3>
        <pre style="background: #f6f8fa; padding: 10px; border: 1px solid #d0d7de; border-radius: 6px;">php scripts/sql_insert.php --file=path/to/commands.sql [--disable-fk]</pre>
    </div>
</body>
</html>
