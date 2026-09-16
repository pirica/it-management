$php_bin = (Get-Command php.exe -ErrorAction SilentlyContinue).Source
if (-not $php_bin) {
    $laragon_php = "C:\laragon\bin\php\php-*\php.exe"
    $php_bin = (Get-ChildItem $laragon_php | Sort-Object LastWriteTime -Descending | Select-Object -First 1).FullName
}

if ($php_bin) {
    $ini_raw = & $php_bin -i
    $memory_limit = $ini_raw | Select-Object -String "memory_limit =>" | ForEach-Object { $_.Split("=>")[1].Trim() }
    $upload_max = $ini_raw | Select-Object -String "upload_max_filesize =>" | ForEach-Object { $_.Split("=>")[1].Trim() }
    $post_max = $ini_raw | Select-Object -String "post_max_size =>" | ForEach-Object { $_.Split("=>")[1].Trim() }
    $max_exec = $ini_raw | Select-Object -String "max_execution_time =>" | ForEach-Object { $_.Split("=>")[1].Trim() }

    $result = @{
        status = "success"
        data = @{
            memory_limit = $memory_limit
            upload_max_filesize = $upload_max
            post_max_size = $post_max
            max_execution_time = $max_exec
        }
    }
} else {
    $result = @{
        status = "error"
        message = "PHP binary not found"
    }
}

$result | ConvertTo-Json
