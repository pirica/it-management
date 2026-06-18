$os = Get-CimInstance Win32_OperatingSystem
$uptime = (Get-Date) - $os.LastBootUpTime
$uptime_str = "{0} days, {1} hours, {2} minutes, {3} seconds" -f $uptime.Days, $uptime.Hours, $uptime.Minutes, $uptime.Seconds

$result = @{
    status = "success"
    data = @{
        uptime_string = $uptime_str
        days = $uptime.Days
        hours = $uptime.Hours
        minutes = $uptime.Minutes
        seconds = $uptime.Seconds
    }
}

$result | ConvertTo-Json
