<?php
/**
 * PHP Script to completely reset Git history and force push a clean master branch.
 * Local configuration version to bypass Windows web server $HOME issues.
 */

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

echo "🚀 Starting Git history reset with absolute path...<br><br>";

foreach ($commands as $command) {
    echo "--------------------------------------------------<br>";
    echo "Running: $command<br>";
    echo "--------------------------------------------------<br>";
    
    $output = [];
    $resultCode = null;
    
    exec($command . ' 2>&1', $output, $resultCode);
    
    // Convert newlines to HTML breaks for browser readability
    echo nl2br(implode("\n", $output)) . "<br><br>";
    
    if ($resultCode !== 0) {
        echo "❌ ERROR: Command failed with exit code $resultCode.<br>";
        echo "Aborting script to prevent repository corruption.<br>";
        exit(1);
    }
}

echo "🎉 Done! Repository history has been successfully reset.<br>";
echo "<br><br><br>";
// ------------------------------------------------------------
// ENABLE FULL PHP ERROR REPORTING
// ------------------------------------------------------------

//error_reporting(E_ALL);
//ini_set('display_errors', 1);

//echo "<h3>🔧 DEBUG MODE ENABLED</h3>";

// ------------------------------------------------------------
// CONFIG
// ------------------------------------------------------------
$mysqlExe = 'C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe';
$dbUser   = 'root';
$dbPass   = 'itmanagement';
$dbName   = 'itmanagement';
$sqlFile  = 'C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management\database.sql';
$expectedTables = 91;

// ------------------------------------------------------------
// DEBUG: Check mysql.exe
// ------------------------------------------------------------
//echo "<b>mysql.exe exists?</b> " . (file_exists($mysqlExe) ? "YES" : "NO") . "<br>";
//echo "<b>mysql.exe path:</b> $mysqlExe<br><br>";

// ------------------------------------------------------------
// DEBUG: Check SQL file
// ------------------------------------------------------------
//echo "<b>database.sql exists?</b> " . (file_exists($sqlFile) ? "YES" : "NO") . "<br>";
//echo "<b>database.sql size:</b> " . (file_exists($sqlFile) ? filesize($sqlFile) : 0) . " bytes<br><br>";

// ------------------------------------------------------------
// DEBUG: Show first 20 lines of SQL file
// ------------------------------------------------------------
/*
echo "<b>First 20 lines of database.sql:</b><br><pre>";
exec('cmd /c "type "' . $sqlFile . '" | more +1"', $sqlPreview);
echo htmlspecialchars(implode("\n", array_slice($sqlPreview, 0, 20)));
echo "</pre><br>";
*/
// ------------------------------------------------------------
// DEBUG: MySQL version
// ------------------------------------------------------------
/*
echo "<b>mysql --version:</b><br><pre>";
exec('cmd /c "' . $mysqlExe . ' --version"', $mysqlVersion);
echo htmlspecialchars(implode("\n", $mysqlVersion));
echo "</pre><br>";
*/
// ------------------------------------------------------------
// IMPORT DATABASE USING PIPE (bulletproof)
// ------------------------------------------------------------
echo "📥 Importing database.sql...<br>";

$importCmd = 'cmd /c "type ' . $sqlFile . ' | ' . $mysqlExe . ' -u' . $dbUser . ' -p' . $dbPass . ' ' . $dbName . '"';

//echo "<b>Executing command:</b><br><pre>$importCmd</pre><br>";

$importOutput = [];
$importCode = 0;

exec($importCmd . ' 2>&1', $importOutput, $importCode);
/*
echo "<b>Import Output:</b><br><pre>";
echo htmlspecialchars(implode("\n", $importOutput));
echo "</pre><br>";
*/
echo "<b>Import Exit Code:</b> $importCode<br><br>";


// ------------------------------------------------------------
// COUNT TABLES
// ------------------------------------------------------------
echo "🔍 Checking table count via mysqli...<br>";

$mysqli = @new mysqli('127.0.0.1', $dbUser, $dbPass, $dbName);

if ($mysqli->connect_errno) {
    echo "❌ MySQL connection failed: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error . "<br>";
    exit;
}

$sql = "SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = '$dbName'";
$res = $mysqli->query($sql);

if (!$res) {
    echo "❌ Query failed: (" . $mysqli->errno . ") " . $mysqli->error . "<br>";
    exit;
}

$row = $res->fetch_assoc();
$tableCount = (int)$row['cnt'];

echo "📊 Tables found: $tableCount<br>";

if ($tableCount !== $expectedTables) {
    echo "❌ ERROR: Expected $expectedTables tables, found $tableCount.<br>";
    exit;
}

echo "✅ Database imported successfully with $tableCount tables.<br>";

$mysqli->close();

