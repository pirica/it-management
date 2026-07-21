$cpu_load = Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average | Select-Object -ExpandProperty Average
$per_core = Get-Counter '\Processor(*)\% Processor Time' -ErrorAction SilentlyContinue | Select-Object -ExpandProperty CounterSamples | Where-Object { $_.InstanceName -ne '_Total' } | ForEach-Object {
    @{
        core = $_.InstanceName
        load = [math]::Round($_.CookedValue, 2)
    }
}

$result = @{
    status = "success"
    data = @{
        cpu_load = [math]::Round($cpu_load, 2)
        per_core = $per_core
    }
}

$result | ConvertTo-Json
