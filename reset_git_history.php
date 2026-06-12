<?php
/**
 * Completely reset Git history and force push a clean master branch.
 *
 * Why: Used in development to purge history or reset a branch to a clean state.
 * DANGER: This is a destructive operation that rewrites history and force-pushes.
 *
 * Browser: open scripts/reset_git_history.php (login required).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '\it-management\config\config.php';
require_once __DIR__ . '\scripts\lib\script_browser_nav.php';

if (PHP_SAPI === 'cli') {
    echo "This script is designed for browser use with visual feedback. Run with caution.\n";
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reset Git History</title>
    <style>
        body { font-family: Segoe UI, system-ui, sans-serif; margin: 16px; line-height: 1.4; }
        .alert-danger { background: #ffebe9; border: 1px solid #ff8182; color: #82071e; padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        pre { background: #f6f8fa; padding: 12px; border-radius: 6px; overflow-x: auto; font-family: Consolas, monospace; font-size: 13px; }
    </style>
</head>
<body>
<?php itm_script_browser_nav_echo(); ?>
<h1>Reset Git History</h1>
<div class="alert-danger">
    <strong>WARNING:</strong> This will delete all Git history and force-push a single "Initial clean commit" to master.
    Ensure you have a backup and absolute certainty before proceeding.
</div>

<?php
if (($_GET['confirm'] ?? '') !== '1') {
    echo '<p><a href="?confirm=1" class="btn btn-danger" onclick="return confirm(\'ARE YOU ABSOLUTELY SURE? THIS CANNOT BE UNDONE.\');" style="display:inline-block;padding:8px 16px;background:#cf222e;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">Confirm History Reset & Force Push</a></p>';
    echo '</body></html>';
    exit;
}

// Define the absolute path to your git executable
$git = '"C:\Program Files\Git\cmd\git.exe"';

// FIX: Changed --global to local repo configuration to fix "fatal: $HOME not set"
$commands = [
    "$git config user.email \"nelson.salvador@gmail.com\"",
    "$git config user.name \"Nelson Salvador\"",
    "$git checkout --orphan clean_branch",
    "$git add -A",
    "$git commit -m \"Initial clean commit\"",
    "$git branch -M master",
    "$git push -f origin master"
];

$isCli = PHP_SAPI === 'cli';
$nl = $isCli ? "\n" : "<br>";

echo "🚀 Starting Git history reset..." . $nl . $nl;

foreach ($commands as $command) {
    if ($isCli) {
        echo "Running: $command\n";
    } else {
        echo "<strong>Running:</strong> <code>" . htmlspecialchars($command) . "</code><br>";
    }

    $output = [];
    $resultCode = null;

    exec($command . ' 2>&1', $output, $resultCode);

    if ($isCli) {
        echo implode("\n", $output) . "\n\n";
    } else {
        echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
    }

    if ($resultCode !== 0) {
        if ($isCli) {
            echo "❌ ERROR: Command failed with exit code $resultCode. Aborting to prevent corruption.\n";
        } else {
            echo "<div class='alert-danger'>❌ ERROR: Command failed with exit code $resultCode. Aborting to prevent corruption.</div>";
        }
        exit(1);
    }
}

echo "🎉 Done! Repository history has been successfully reset." . $nl;

// Import Database Section
$mysqlExe = 'C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe';
$dbUser   = 'root';
$dbPass   = 'itmanagement';
$dbName   = 'itmanagement';
$sqlFile  = dirname(__DIR__) . '/database.sql';
$expectedTables = 101; 

if ($isCli) {
    echo "\n📥 Importing Database\n";
} else {
    echo "<h2>📥 Importing Database</h2>";
}

$importCmd = 'cmd /c "type ' . escapeshellarg($sqlFile) . ' | ' . $mysqlExe . ' -u' . $dbUser . ' -p' . $dbPass . ' ' . $dbName . '"';
$importOutput = [];
$importCode = 0;

exec($importCmd . ' 2>&1', $importOutput, $importCode);

if ($isCli) {
    echo "Exit Code: $importCode\n";
    echo "🔍 Checking table count...\n";
} else {
    echo "<strong>Exit Code:</strong> $importCode<br>";
    echo "🔍 Checking table count...<br>";
}

$mysqli = @new mysqli('127.0.0.1', $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) {
    echo "❌ MySQL connection failed: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error . $nl;
} else {
    $res = $mysqli->query("SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = '$dbName'");
    if ($res) {
        $row = $res->fetch_assoc();
        $tableCount = (int)$row['cnt'];
        if ($isCli) {
            echo "📊 Tables found: $tableCount (Expected: $expectedTables)\n";
        } else {
            echo "📊 Tables found: $tableCount (Expected: $expectedTables)<br>";
        }

        if ($tableCount === $expectedTables) {
            echo "✅ Database imported successfully." . $nl;
        } else {
            echo "❌ ERROR: Table count mismatch." . $nl;
        }
    }
    $mysqli->close();
}
?>
</body>
</html>
