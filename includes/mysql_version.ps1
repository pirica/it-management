$mysql_bin = (Get-Command mysql.exe -ErrorAction SilentlyContinue).Source
if (-not $mysql_bin) {
    $laragon_mysql = "C:\laragon\bin\mysql\mysql-*\bin\mysql.exe"
    $mysql_bin = (Get-ChildItem $laragon_mysql | Sort-Object LastWriteTime -Descending | Select-Object -First 1).FullName
}

if ($mysql_bin) {
    $version = & $mysql_bin --version

    $result = @{
        status = "success"
        data = @{
            binary = $mysql_bin
            version = $version
        }
    }
} else {
    $result = @{
        status = "error"
        message = "MySQL binary not found"
    }
}

$result | ConvertTo-Json
