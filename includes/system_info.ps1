$os = Get-CimInstance Win32_OperatingSystem
$cpu = Get-CimInstance Win32_Processor | Select-Object -First 1
$ram_total = $os.TotalVisibleMemorySize * 1KB
$ram_free = $os.FreePhysicalMemory * 1KB
$ram_used = $ram_total - $ram_free

$disks = @(Get-CimInstance Win32_LogicalDisk -Filter "DriveType=3" | ForEach-Object {
    @{
        DeviceID = $_.DeviceID
        Size = $_.Size
        FreeSpace = $_.FreeSpace
    }
})

$networks = @(Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.InterfaceAlias -notmatch 'Loopback' } | ForEach-Object {
    @{
        InterfaceAlias = $_.InterfaceAlias
        IPAddress = $_.IPAddress
    }
})

$uptime = (Get-Date) - $os.LastBootUpTime
$uptime_str = "{0} days, {1} hours, {2} minutes" -f $uptime.Days, $uptime.Hours, $uptime.Minutes

$result = @{
    status = "success"
    data = @{
        os_version = $os.Caption + " " + $os.Version
        hostname = $os.CSName
        uptime = $uptime_str
        cpu_model = $cpu.Name.Trim()
        cpu_cores = $cpu.NumberOfCores
        cpu_threads = $cpu.NumberOfLogicalProcessors
        ram_total = $ram_total
        ram_used = $ram_used
        ram_free = $ram_free
        disks = $disks
        networks = $networks
    }
}

$result | ConvertTo-Json -Depth 5
