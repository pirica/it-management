<?php

echo "--- A iniciar processo ---\n";

// Caminho absoluto para o Git
$git = "C:\\Program Files\\Git\\bin\\git.exe";

// 1. Caminho do repositório (pasta onde o script está)
$repoPath = __DIR__ . "/it-management";

// 2. Verificar se o Git está instalado
$gitCheck = shell_exec("\"$git\" --version 2>&1");

if (strpos($gitCheck, "git version") === false) {
    echo "<br>[!] Git não detetado pelo PHP.\n";
    echo "<br>Verifica se o Git está instalado em: C:\\Program Files\\Git\\bin\\git.exe\n";
    exit(1);
}

echo "<br>Git detetado: $gitCheck\n";

// 3. Remover pasta antiga se existir
if (is_dir($repoPath)) {
    echo "<br>A remover pasta antiga em: $repoPath\n";
    shell_exec("rmdir /s /q " . escapeshellarg($repoPath));
}

// 4. Clonar o repositório
echo "<br>A clonar do GitHub...\n";

$cmd = "\"$git\" clone https://github.com/pirica/it-management.git " . escapeshellarg($repoPath);
exec($cmd, $output, $returnCode);

if ($returnCode !== 0) {
    echo "<br>[!] ERRO: O Git falhou ao clonar o repositório.\n";
    exit(1);
}

echo "\n<br>Sucesso! Repositório atualizado.\n";
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
$phpExe   = 'C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe';
$mysqlExe = 'C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe';
$dbUser   = 'root';
$dbPass   = 'itmanagement';
$dbName   = 'itmanagement';
$sqlFile  = $repoPath . '\\database.sql';

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
// EXPECTED TABLE COUNT (count_db_tables.php)
// ------------------------------------------------------------
echo "🔢 Running count_db_tables.php...<br>";

$countScript = $repoPath . '\\scripts\\count_db_tables.php';
$countCmd = 'cmd /c "' . $phpExe . '" "' . $countScript . '" 2>&1';
$countOutput = [];
$countCode = 0;

exec($countCmd, $countOutput, $countCode);

if ($countCode !== 0) {
    echo "❌ ERROR: count_db_tables.php failed.<br>";
    if (!empty($countOutput)) {
        echo "<pre>" . htmlspecialchars(implode("\n", $countOutput)) . "</pre>";
    }
    exit(1);
}

$expectedTables = (int) trim(implode("\n", $countOutput));
$numberFile = $repoPath . '\\scripts\\number_db_tables.txt';

if ($expectedTables <= 0 && is_file($numberFile)) {
    $expectedTables = (int) trim((string) file_get_contents($numberFile));
}

if ($expectedTables <= 0) {
    echo "❌ ERROR: count_db_tables.php did not return a valid table count.<br>";
    exit(1);
}

echo "📌 Expected tables (count_db_tables.php): $expectedTables<br><br>";

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
