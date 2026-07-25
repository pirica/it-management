$service = Get-Service -Name mysql -ErrorAction SilentlyContinue
if (-not $service) {
    $service = Get-Service -Name MariaDB -ErrorAction SilentlyContinue
}

if ($service) {
    $result = @{
        status = "success"
        data = @{
            service_name = $service.Name
            status = $service.Status.ToString()
            display_name = $service.DisplayName
        }
    }
} else {
    $result = @{
        status = "error"
        message = "MySQL/MariaDB service not found"
    }
}

$result | ConvertTo-Json
