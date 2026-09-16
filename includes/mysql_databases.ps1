$mysql_bin = (Get-Command mysql.exe -ErrorAction SilentlyContinue).Source
if (-not $mysql_bin) {
    $laragon_mysql = "C:\laragon\bin\mysql\mysql-*\bin\mysql.exe"
    $mysql_bin = (Get-ChildItem $laragon_mysql | Sort-Object LastWriteTime -Descending | Select-Object -First 1).FullName
}

if ($mysql_bin) {
    # Using -u root with no password as per default Laragon setup
    $databases = & $mysql_bin -u root -e "SHOW DATABASES;" | Where-Object { $_ -ne "Database" }

    $result = @{
        status = "success"
        data = $databases
    }
} else {
    $result = @{
        status = "error"
        message = "MySQL binary not found"
    }
}

$result | ConvertTo-Json
