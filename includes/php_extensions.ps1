$php_bin = (Get-Command php.exe -ErrorAction SilentlyContinue).Source
if (-not $php_bin) {
    $laragon_php = "C:\laragon\bin\php\php-*\php.exe"
    $php_bin = (Get-ChildItem $laragon_php | Sort-Object LastWriteTime -Descending | Select-Object -First 1).FullName
}

if ($php_bin) {
    $extensions = & $php_bin -m | Where-Object { $_ -ne "" -and $_ -notmatch '\[PHP Modules\]' -and $_ -notmatch '\[Zend Modules\]' }

    $result = @{
        status = "success"
        data = $extensions
    }
} else {
    $result = @{
        status = "error"
        message = "PHP binary not found"
    }
}

$result | ConvertTo-Json
