$mysql_bin = (Get-Command mysql.exe -ErrorAction SilentlyContinue).Source
if (-not $mysql_bin) {
    $laragon_mysql = "C:\laragon\bin\mysql\mysql-*\bin\mysql.exe"
    $mysql_bin = (Get-ChildItem $laragon_mysql | Sort-Object LastWriteTime -Descending | Select-Object -First 1).FullName
}

if ($mysql_bin) {
    $sql = "SELECT table_schema AS 'Database', ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'SizeMB' FROM information_schema.TABLES GROUP BY table_schema;"
    $sizes = & $mysql_bin -u root -e $sql

    $result = @{
        status = "success"
        data = $sizes
    }
} else {
    $result = @{
        status = "error"
        message = "MySQL binary not found"
    }
}

$result | ConvertTo-Json
