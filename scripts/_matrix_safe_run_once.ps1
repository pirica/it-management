# Safe scripts matrix runner (tiers 1-3). Writes JSON for docs/scripts_errors.txt.
$ErrorActionPreference = 'Continue'
$Root = 'C:\Users\NelsonSalvador\Downloads\laragon-portable\www\it-management'
$Php = 'C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe'
$Bash = 'C:\Program Files\Git\bin\bash.exe'
$Matrix = Join-Path $Root 'scripts\SCRIPTS_TEST_MATRIX.md'
$OutDir = Join-Path $Root 'qa-reports'
$LogDir = Join-Path $OutDir 'scripts-matrix-logs'
$OutJson = Join-Path $OutDir 'scripts-matrix-safe-run-raw.json'
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null

$env:PATH = (Split-Path $Php -Parent) + ';' + $env:PATH
$env:PHP_BIN = $Php

$branch = (git -C $Root branch --show-current 2>$null).Trim()
$started = (Get-Date).ToString('o')

$entries = @()
Get-Content -Encoding UTF8 $Matrix | ForEach-Object {
    if ($_ -match '^\|\s*([0-5])\s*\|\s*`([^`]+)`\s*\|') {
        $entries += [pscustomobject]@{ tier = [int]$Matches[1]; script = $Matches[2] }
    }
}

function Get-TailText([string]$text, [int]$max = 2500) {
    $text = [regex]::Replace($text, '\x1b\[[0-9;]*m', '')
    if ($text.Length -le $max) { return $text }
    return $text.Substring($text.Length - $max)
}

function Invoke-MatrixScript {
    param(
        [string]$ScriptName,
        [string]$FullPath,
        [int]$TimeoutSec = 120
    )
    $ext = [IO.Path]::GetExtension($ScriptName).ToLowerInvariant()
    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError = $true
    $psi.UseShellExecute = $false
    $psi.CreateNoWindow = $true
    $psi.WorkingDirectory = $Root
    try {
        $psi.EnvironmentVariables['PATH'] = $env:PATH
        $psi.EnvironmentVariables['PHP_BIN'] = $Php
    } catch {}

    if ($ext -eq '.php') {
        $psi.FileName = $Php
        $psi.Arguments = '"' + $FullPath + '"'
    } elseif ($ext -eq '.sh') {
        $psi.FileName = $Bash
        $psi.Arguments = '"' + $FullPath + '"'
    } elseif ($ext -eq '.py') {
        $pyCmd = Get-Command python -ErrorAction SilentlyContinue
        if (-not $pyCmd) { $pyCmd = Get-Command python3 -ErrorAction SilentlyContinue }
        if (-not $pyCmd) {
            return @{ exit = 127; out = 'python-not-on-PATH'; sec = 0; skip = $true }
        }
        $psi.FileName = $pyCmd.Source
        $psi.Arguments = '"' + $FullPath + '"'
    } else {
        return @{ exit = 127; out = 'unsupported-ext'; sec = 0; skip = $true }
    }

    $sw = [Diagnostics.Stopwatch]::StartNew()
    $p = [Diagnostics.Process]::Start($psi)
    $outTask = $p.StandardOutput.ReadToEndAsync()
    $errTask = $p.StandardError.ReadToEndAsync()
    if (-not $p.WaitForExit($TimeoutSec * 1000)) {
        try { $p.Kill() } catch {}
        $sw.Stop()
        $combined = ($outTask.Result) + ($errTask.Result) + "`n[TIMEOUT after ${TimeoutSec}s]`n"
        return @{ exit = 124; out = $combined; sec = [math]::Round($sw.Elapsed.TotalSeconds, 2); skip = $false }
    }
    $sw.Stop()
    $combined = ($outTask.Result) + ($errTask.Result)
    return @{ exit = $p.ExitCode; out = $combined; sec = [math]::Round($sw.Elapsed.TotalSeconds, 2); skip = $false }
}

$results = New-Object System.Collections.Generic.List[object]
$smokePassed = $false

foreach ($entry in $entries) {
    $tier = $entry.tier
    $script = $entry.script
    $row = [ordered]@{
        tier = $tier
        script = $script
        status = 'SKIP'
        exit = $null
        sec = 0
        note = ''
        tail = ''
    }

    if ($tier -eq 0) {
        $row.status = 'SKIP'; $row.note = 'docs-only'
        $results.Add([pscustomobject]$row) | Out-Null
        Write-Host "SKIP tier0 $script"
        continue
    }
    if ($tier -ge 4) {
        $row.status = 'EXCLUDED'
        $row.note = if ($tier -eq 4) { 'tier4-mutates-DB' } else { 'tier5-destructive-or-maintenance' }
        $results.Add([pscustomobject]$row) | Out-Null
        continue
    }
    if ($script -eq 'verify_database_sql_import.sh') {
        $row.status = 'SKIP'
        $row.note = 'destroys-DB; substituted verify_database_schema.php + count_db_tables.php'
        $results.Add([pscustomobject]$row) | Out-Null
        Write-Host "SKIP wipe $script"
        continue
    }
    if ($smokePassed -and ($script -in @('check_csrf_coverage.php','check_fk_label_search_coverage.php','check_sql_injection_coverage.php'))) {
        $row.status = 'COVERED'; $row.note = 'covered by smoke_test.sh'
        $results.Add([pscustomobject]$row) | Out-Null
        Write-Host "COVERED $script"
        continue
    }

    $path = Join-Path $Root "scripts\$script"
    if (-not (Test-Path -LiteralPath $path)) {
        $row.status = 'SKIP'; $row.note = 'file-missing'
        $results.Add([pscustomobject]$row) | Out-Null
        Write-Host "SKIP missing $script"
        continue
    }

    $timeout = 120
    if ($script -in @('run_tests.php','smoke_test.sh')) { $timeout = 600 }

    Write-Host -NoNewline "RUN tier$tier $script ... "
    $run = Invoke-MatrixScript -ScriptName $script -FullPath $path -TimeoutSec $timeout
    if ($run.skip) {
        $row.status = 'SKIP'; $row.note = $run.out; $row.exit = $run.exit
        $results.Add([pscustomobject]$row) | Out-Null
        Write-Host "SKIP $($run.out)"
        continue
    }

    $row.exit = $run.exit
    $row.sec = $run.sec
    $row.tail = Get-TailText $run.out
    $safe = ($script -replace '[^a-zA-Z0-9._-]+', '_')
    Set-Content -Encoding UTF8 -Path (Join-Path $LogDir "$safe.log") -Value $run.out

    if ($run.exit -eq 0) {
        $row.status = 'PASS'; $row.note = 'ok'
        if ($script -eq 'smoke_test.sh') { $smokePassed = $true }
        Write-Host "PASS ($($run.sec)s)"
    } elseif ($run.exit -eq 124) {
        $row.status = 'FAIL'; $row.note = 'timeout'
        Write-Host 'TIMEOUT'
    } else {
        $row.status = 'FAIL'; $row.note = "exit-$($run.exit)"
        Write-Host "FAIL exit=$($run.exit) ($($run.sec)s)"
    }
    $results.Add([pscustomobject]$row) | Out-Null
}

# Ensure schema substitutes ran (already in tier 3 usually)
foreach ($extra in @('verify_database_schema.php','count_db_tables.php')) {
    $exists = $results | Where-Object { $_.script -eq $extra }
    if ($exists) { continue }
    $path = Join-Path $Root "scripts\$extra"
    if (-not (Test-Path $path)) { continue }
    Write-Host -NoNewline "RUN substitute $extra ... "
    $run = Invoke-MatrixScript -ScriptName $extra -FullPath $path -TimeoutSec 120
    $st = if ($run.exit -eq 0) { 'PASS' } else { 'FAIL' }
    $results.Add([pscustomobject]@{
        tier = 1; script = $extra; status = $st; exit = $run.exit; sec = $run.sec
        note = 'substitute-for-verify_database_sql_import.sh'; tail = (Get-TailText $run.out)
    }) | Out-Null
    Write-Host $st
}

$counts = @{ PASS = 0; FAIL = 0; SKIP = 0; EXCLUDED = 0; COVERED = 0 }
foreach ($r in $results) {
    if ($counts.ContainsKey($r.status)) { $counts[$r.status]++ }
}

$payload = [ordered]@{
    started = $started
    finished = (Get-Date).ToString('o')
    branch = $branch
    php = $Php
    counts = $counts
    results = $results
}
$payload | ConvertTo-Json -Depth 6 | Set-Content -Encoding UTF8 -Path $OutJson
Write-Host ""
Write-Host ("DONE counts=" + ($counts | ConvertTo-Json -Compress))
Write-Host "Wrote $OutJson"
