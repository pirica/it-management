$php_bin = (Get-Command php.exe -ErrorAction SilentlyContinue).Source
if (-not $php_bin) {
    # Fallback to standard Laragon path if not in PATH
    $laragon_php = "C:\laragon\bin\php\php-*\php.exe"
    $php_bin = (Get-ChildItem $laragon_php | Sort-Object LastWriteTime -Descending | Select-Object -First 1).FullName
}

if ($php_bin) {
    $version = & $php_bin -v | Select-Object -First 1
    $ini_path = & $php_bin --ini | Select-Object -String "Loaded Configuration File" | ForEach-Object { $_.Split(":")[1].Trim() }

    $result = @{
        status = "success"
        data = @{
            php_binary = $php_bin
            version = $version
            ini_path = $ini_path
        }
    }
} else {
    $result = @{
        status = "error"
        message = "PHP binary not found"
    }
}

$result | ConvertTo-Json
