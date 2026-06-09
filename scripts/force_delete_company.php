<?php
/**
 * Force Delete Company
 *
 * This script allows an administrator to bypass triggers and foreign key constraints
 * to completely remove a company and its associated data (including audit logs).
 *
 * Browser: open scripts/force_delete_company.php (Admin login required).
 * CLI: php scripts/force_delete_company.php --id=6
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_browser_nav.php';

$conn = $GLOBALS['conn'];
$message = '';
$messageType = 'info';
$itmIsCli = PHP_SAPI === 'cli';

/**
 * Helper for deletion logic
 */
function itm_force_delete_company(mysqli $conn, int $companyId): string
{
    if ($companyId <= 0) {
        return "Error: Invalid company ID.";
    }

    mysqli_begin_transaction($conn);
    try {
        // Disable foreign key checks to bypass blocking triggers/constraints
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");

        // 1. Identify all tables that have a company_id column
        $schemaName = mysqli_real_escape_string($conn, (string) DB_NAME);
        $tablesQuery = "SELECT TABLE_NAME
                        FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = '{$schemaName}'
                        AND COLUMN_NAME = 'company_id'";
        $tablesResult = mysqli_query($conn, $tablesQuery);
        $tablesDeleted = [];

        while ($row = mysqli_fetch_assoc($tablesResult)) {
            $tableName = $row['TABLE_NAME'];
            if (itm_is_safe_identifier($tableName)) {
                $deleteSql = "DELETE FROM `$tableName` WHERE `company_id` = ?";
                $stmt = mysqli_prepare($conn, $deleteSql);
                mysqli_stmt_bind_param($stmt, "i", $companyId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $tablesDeleted[] = $tableName;
            }
        }

        // 2. Delete from companies table itself
        $deleteCompanySql = "DELETE FROM `companies` WHERE `id` = ?";
        $stmt = mysqli_prepare($conn, $deleteCompanySql);
        mysqli_stmt_bind_param($stmt, "i", $companyId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Re-enable foreign key checks
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
        mysqli_commit($conn);

        return "Successfully deleted company ID $companyId and records from " . count($tablesDeleted) . " related tables.";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
        return "Error during deletion: " . $e->getMessage();
    }
}

// CLI Mode
if ($itmIsCli) {
    $options = getopt("", ["id:"]);
    $cid = isset($options['id']) ? (int)$options['id'] : 0;

    if ($cid <= 0) {
        echo "Usage: php scripts/force_delete_company.php --id=N\n";
        die();
    }

    echo itm_force_delete_company($conn, $cid) . "\n";
    die();
}

// Browser Access Control: Admin only
if (strtolower($_SESSION['role_name'] ?? '') !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo "<h1>403 Forbidden</h1><p>Only administrators can access this tool.</p>";
    die();
}

// CSRF check for POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Error: Invalid CSRF token.";
        $messageType = 'error';
    } else {
        $companyId = (int)($_POST['company_id'] ?? 0);
        $result = itm_force_delete_company($conn, $companyId);
        if (strpos($result, 'Error') === 0) {
            $message = $result;
            $messageType = 'error';
        } else {
            $message = $result;
            $messageType = 'success';
        }
    }
}

// Fetch companies for the dropdown
$companies = [];
$res = mysqli_query($conn, "SELECT id, company, incode FROM companies ORDER BY company ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $companies[] = $row;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Force Delete Company</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        body { padding: 20px; background-color: #f6f8fa; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border: 1px solid #d0d7de; border-radius: 8px; padding: 24px; }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 16px; border: 1px solid transparent; }
        .alert-info { background: #ddf4ff; color: #0969da; border-color: #c0e6ff; }
        .alert-error { background: #ffebe9; color: #cf222e; border-color: #ffc1c0; }
        .alert-success { background: #dafbe1; color: #1a7f37; border-color: #aff5b4; }
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        select { width: 100%; padding: 8px; border: 1px solid #d0d7de; border-radius: 6px; }
        .btn-danger { background-color: #cf222e; color: #fff; border: 1px solid rgba(27,31,36,0.15); padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-danger:hover { background-color: #a40e26; }
        .warning-box { background: #fff8c5; border: 1px solid #d4a72c; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container">
        <?php itm_script_browser_nav_echo(); ?>
        <h1>Force Delete Company</h1>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="warning-box">
            <strong>DANGER:</strong> This action is irreversible. It will bypass all database triggers and foreign key constraints to delete the company and <strong>all associated data</strong> in all tables (Employees, Equipment, Audit Logs, etc.).
        </div>

        <form method="POST" onsubmit="return confirm('ARE YOU ABSOLUTELY SURE? This will permanently delete the company and all its data. This cannot be undone.');">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

            <div class="form-group">
                <label for="company_id">Select Company to Delete:</label>
                <select name="company_id" id="company_id" required>
                    <option value="">-- Choose a company --</option>
                    <?php foreach ($companies as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>">
                            <?php echo htmlspecialchars($c['company'] . ' (' . $c['incode'] . ') [ID: ' . $c['id'] . ']'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-danger">Force Delete Company</button>
        </form>
    </div>
</body>
</html>
