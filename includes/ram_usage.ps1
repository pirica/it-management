$os = Get-CimInstance Win32_OperatingSystem
$total = $os.TotalVisibleMemorySize * 1KB
$free = $os.FreePhysicalMemory * 1KB
$used = $total - $free

$result = @{
    status = "success"
    data = @{
        total = $total
        used = $used
        free = $free
        percent_used = [math]::Round(($used / $total) * 100, 2)
    }
}

$result | ConvertTo-Json
