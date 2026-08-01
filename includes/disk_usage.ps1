$disks = @(Get-CimInstance Win32_LogicalDisk -Filter "DriveType=3" | ForEach-Object {
    @{
        drive = $_.DeviceID
        total = $_.Size
        used = $_.Size - $_.FreeSpace
        free = $_.FreeSpace
        percent_used = [math]::Round((($_.Size - $_.FreeSpace) / $_.Size) * 100, 2)
    }
})

$result = @{
    status = "success"
    data = $disks
}

$result | ConvertTo-Json
