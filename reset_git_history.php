<?php
/**
 * Completely reset Git history and force push a clean master branch.
 * BETA USE ONLY — destructive operation.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '\it-management\config\config.php';
require_once __DIR__ . '\scripts\lib\script_browser_nav.php';

/* ---------------------------------------------------------
   Detect CLI vs Browser
--------------------------------------------------------- */

$isCli = PHP_SAPI === 'cli';
$nl = $isCli ? "\n" : "<br>";

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
    <strong>WARNING:</strong> This will delete all Git history and force-push a single clean commit.
</div>

<?php
/* ---------------------------------------------------------
   CONFIRMATION STEP — SHOW CHANGES BEFORE RESET
--------------------------------------------------------- */

if (($_GET['confirm'] ?? '') !== '1') {

    $git = '"C:\Program Files\Git\cmd\git.exe"';

    $changes = [];
    exec("$git status --porcelain 2>&1", $changes);

    echo "<h2>📄 Ficheiros alterados antes do reset</h2>";

    if (empty($changes)) {
        echo "<p><strong>Não há alterações no repositório.</strong></p>";
    } else {
        echo "<pre>";
        foreach ($changes as $line) {
            $status = substr($line, 0, 2);
            $file   = trim(substr($line, 3));

            switch (trim($status)) {
                case 'M':
                case 'MM':
                case 'AM':
                case 'MD':
                    echo "🟦 Modificado:   $file\n";
                    break;

                case 'A':
                    echo "🟩 Criado:       $file\n";
                    break;

                case 'D':
                    echo "🟥 Apagado:      $file\n";
                    break;

                case 'R':
                    echo "🟨 Renomeado:    $file\n";
                    break;

                case '??':
                    echo "⬜ Untracked:    $file\n";
                    break;

                default:
                    echo "❔ Outro ($status): $file\n";
            }
        }
        echo "</pre>";
    }

    echo '<p><a href="?confirm=1" class="btn btn-danger" onclick="return confirm(\'ARE YOU ABSOLUTELY SURE? THIS CANNOT BE UNDONE.\');" style="display:inline-block;padding:8px 16px;background:#cf222e;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">Confirm History Reset & Force Push</a></p>';

    echo '</body></html>';
    exit;
}

/* ---------------------------------------------------------
   GIT COMMANDS
--------------------------------------------------------- */

$git = '"C:\Program Files\Git\cmd\git.exe"';

$commands = [
    "$git config user.email \"nelson.salvador@gmail.com\"",
    "$git config user.name \"Nelson Salvador\"",
    "$git checkout --orphan clean_branch",
    "$git add -A",
    "$git commit -m \"Initial clean commit\"",
    "$git branch -M master",
    "$git push -f origin master"
];

echo "🚀 Starting Git history reset..." . $nl . $nl;

/* ---------------------------------------------------------
   RUN COMMANDS (NO PAUSES)
--------------------------------------------------------- */

foreach ($commands as $command) {

    echo $isCli
        ? "Running: $command\n"
        : "<strong>Running:</strong> <code>" . htmlspecialchars($command) . "</code><br>";

    $output = [];
    $resultCode = null;

    exec($command . ' 2>&1', $output, $resultCode);

    echo $isCli
        ? implode("\n", $output) . "\n\n"
        : "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";

    if ($resultCode !== 0) {
        echo $isCli
            ? "❌ ERROR: Command failed ($resultCode). Aborting.\n"
            : "<div class='alert-danger'>❌ ERROR: Command failed ($resultCode). Aborting.</div>";
        exit(1);
    }
}

echo "🎉 Done! Repository history has been successfully reset." . $nl;

/* ---------------------------------------------------------
   DATABASE IMPORT — AUTO TABLE COUNT
--------------------------------------------------------- */

$cmd = 'php ' . escapeshellarg(__DIR__ . '/scripts/count_db_tables.php');
$output = [];
exec($cmd . ' 2>&1', $output);

$expectedTables = (int)trim($output[0] ?? 0);

$mysqlExe = 'C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe';
$dbUser   = 'root';
$dbPass   = 'itmanagement';
$dbName   = 'itmanagement';
$sqlFile  = dirname(__DIR__) . '/database.sql';

echo $isCli ? "\n📥 Importing Database\n" : "<h2>📥 Importing Database</h2>";

$importCmd = 'cmd /c "type ' . escapeshellarg($sqlFile) . ' | ' . $mysqlExe . ' -u' . $dbUser . ' -p' . $dbPass . ' ' . $dbName . '"';

$importOutput = [];
$importCode = 0;

exec($importCmd . ' 2>&1', $importOutput, $importCode);

echo $isCli
    ? "Exit Code: $importCode\n"
    : "<strong>Exit Code:</strong> $importCode<br>";

echo $isCli
    ? "🔍 Checking table count...\n"
    : "🔍 Checking table count...<br>";

$mysqli = @new mysqli('127.0.0.1', $dbUser, $dbPass, $dbName);

if ($mysqli->connect_errno) {
    echo "❌ MySQL connection failed: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error . $nl;
} else {
    $res = $mysqli->query("SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = '$dbName'");
    if ($res) {
        $row = $res->fetch_assoc();
        $tableCount = (int)$row['cnt'];

        echo $isCli
            ? "📊 Tables found: $tableCount (Expected: $expectedTables)\n"
            : "📊 Tables found: $tableCount (Expected: $expectedTables)<br>";

        echo ($tableCount === $expectedTables)
            ? "✅ Database imported successfully.$nl"
            : "❌ ERROR: Table count mismatch.$nl";
    }
    $mysqli->close();
}
?>
</body>
</html>
