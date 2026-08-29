# Copy PHP extensions (Xdebug + optional DLLs) from Laragon portable into Dunebox and write php.ini.
# Why: stock Dunebox PHP under D:\dunebox-v1.0.6 ships without a loaded php.ini (no mbstring / Xdebug for PHPUnit).
#
# Usage (repo root):
#   powershell -ExecutionPolicy Bypass -File scripts/setup_dunebox_php_from_laragon.ps1
#
# Optional env:
#   ITM_LARAGON_PHP_ROOT  — Laragon PHP folder (default: <laragon-portable>/bin/php/php-7.4.33-nts-Win32-vc15-x64)
#   ITM_DUNEBOX_PHP_ROOT   — Dunebox PHP folder (default: D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64)
#   ITM_DUNEBOX_PHP_INI    — Dunebox central ini used by php74.cmd (default: D:\dunebox-v1.0.6\config\php\php-7.4.ini)

$ErrorActionPreference = 'Stop'
$RepoRoot = Split-Path -Parent $PSScriptRoot

$LaragonPhpRoot = $env:ITM_LARAGON_PHP_ROOT
if (-not $LaragonPhpRoot) {
    $LaragonPortableRoot = Split-Path (Split-Path $RepoRoot -Parent) -Parent
    $LaragonPhpRoot = Join-Path $LaragonPortableRoot 'bin\php\php-7.4.33-nts-Win32-vc15-x64'
}

$DuneboxPhpRoot = $env:ITM_DUNEBOX_PHP_ROOT
if (-not $DuneboxPhpRoot) {
    $DuneboxPhpRoot = 'D:\dunebox-v1.0.6\system\apps\php\php-7.4.33-nts-Win32-vc15-x64'
}

$srcExt = Join-Path $LaragonPhpRoot 'ext'
$dstExt = Join-Path $DuneboxPhpRoot 'ext'
$dstIni = Join-Path $DuneboxPhpRoot 'php.ini'
$template = Join-Path $RepoRoot 'scripts\data\php.ini.dunebox-7.4.template'
$xdebugSnippetTemplate = Join-Path $RepoRoot 'scripts\data\php.ini.dunebox-xdebug-snippet.ini'

foreach ($path in @($LaragonPhpRoot, $srcExt, $dstExt, $template, $xdebugSnippetTemplate)) {
    if (-not (Test-Path $path)) {
        Write-Error "Missing required path: $path"
    }
}

$copyDlls = @(
    'php_xdebug.dll',
    'php_curl.dll',
    'php_fileinfo.dll',
    'php_gd2.dll',
    'php_intl.dll',
    'php_ldap.dll',
    'php_openssl.dll'
)
foreach ($dll in $copyDlls) {
    $src = Join-Path $srcExt $dll
    $dst = Join-Path $dstExt $dll
    if (-not (Test-Path $src)) {
        Write-Host "Skip (not in Laragon ext): $dll"
        continue
    }
    try {
        Copy-Item -Force $src $dst
        Write-Host "Copied $dll -> $dstExt"
    } catch {
        Write-Warning "Could not copy $dll (in use or locked): $($_.Exception.Message)"
    }
}

$extDirForward = ($dstExt -replace '\\', '/')
$iniBody = (Get-Content -Raw -Encoding UTF8 $template) -replace '__EXT_DIR__', $extDirForward
$utf8NoBom = New-Object System.Text.UTF8Encoding $false
[System.IO.File]::WriteAllText($dstIni, $iniBody, $utf8NoBom)

$DuneboxCentralIni = $env:ITM_DUNEBOX_PHP_INI
if (-not $DuneboxCentralIni) {
    $DuneboxCentralIni = 'D:\dunebox-v1.0.6\config\php\php-7.4.ini'
}
if (Test-Path $DuneboxCentralIni) {
    $xdebugBlock = (Get-Content -Raw -Encoding UTF8 $xdebugSnippetTemplate) -replace '__EXT_DIR__', $extDirForward
    $centralRaw = Get-Content -Raw -Encoding UTF8 $DuneboxCentralIni
    if ($centralRaw -match 'ITM_XDEBUG_BEGIN') {
        $centralRaw = [regex]::Replace(
            $centralRaw,
            '(?s); ITM_XDEBUG_BEGIN.*?; ITM_XDEBUG_END\r?\n?',
            $xdebugBlock.TrimEnd() + "`n"
        )
    } else {
        $centralRaw = $centralRaw.TrimEnd() + "`n`n" + $xdebugBlock
    }
    [System.IO.File]::WriteAllText($DuneboxCentralIni, $centralRaw, $utf8NoBom)
    Write-Host "Patched Xdebug block in $DuneboxCentralIni (php74.cmd / central Dunebox ini)"
} else {
    Write-Host "Skip central ini (not found): $DuneboxCentralIni"
}

$phpExe = Join-Path $DuneboxPhpRoot 'php.exe'
Write-Host "Wrote $dstIni"
Write-Host "Verify: $phpExe -m"
& $phpExe -m 2>&1 | Select-String -Pattern 'mbstring|mysqli|xdebug|Xdebug'
