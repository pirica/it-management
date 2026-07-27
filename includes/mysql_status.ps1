function Get-ItmMysqlWindowsService {
    $exactNames = @('mysql', 'MariaDB', 'MySQL80', 'MySQL', 'MySQL57')
    foreach ($name in $exactNames) {
        $svc = Get-Service -Name $name -ErrorAction SilentlyContinue
        if ($svc) {
            return $svc
        }
    }

    return Get-Service -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match 'mysql|maria' -or $_.DisplayName -match 'mysql|maria' } |
        Select-Object -First 1
}

function Test-ItmMysqlTcpPort([int]$Port) {
    $client = $null
    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $connect = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        $waited = $connect.AsyncWaitHandle.WaitOne(500, $false)
        if ($waited -and $client.Connected) {
            return $true
        }
    } catch {
        return $false
    } finally {
        if ($null -ne $client) {
            $client.Close()
        }
    }
    return $false
}

$service = Get-ItmMysqlWindowsService

if ($service) {
    $result = @{
        status = 'success'
        data = @{
            service_name = $service.Name
            status = $service.Status.ToString()
            display_name = $service.DisplayName
            source = 'scm'
        }
    }
} else {
    $mysqld = Get-Process -Name mysqld -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($mysqld) {
        $result = @{
            status = 'success'
            data = @{
                service_name = 'mysqld'
                status = 'Running'
                display_name = "MySQL Server (process $($mysqld.Id))"
                source = 'process'
            }
        }
    } else {
        $openPort = $null
        foreach ($port in @(3307, 3306)) {
            if (Test-ItmMysqlTcpPort -Port $port) {
                $openPort = $port
                break
            }
        }
        if ($null -ne $openPort) {
            $result = @{
                status = 'success'
                data = @{
                    service_name = 'mysqld'
                    status = 'Running'
                    display_name = "MySQL Server (TCP $openPort)"
                    source = 'tcp'
                    port = $openPort
                }
            }
        } else {
            $result = @{
                status = 'error'
                message = 'MySQL/MariaDB service not found'
            }
        }
    }
}

$result | ConvertTo-Json
