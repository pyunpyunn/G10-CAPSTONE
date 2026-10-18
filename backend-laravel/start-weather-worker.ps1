$ErrorActionPreference = 'Stop'
$backendPath = $PSScriptRoot
$phpPath = (Get-Command php -ErrorAction Stop).Source
$existing = Get-CimInstance Win32_Process | Where-Object {
    $_.Name -eq 'php.exe' -and $_.CommandLine -match 'artisan\s+weather:work\b'
}
if ($existing) {
    Write-Output "Weather updater is already running (PID $($existing.ProcessId -join ', '))."
    exit 0
}
$worker = Start-Process -FilePath $phpPath -ArgumentList @('artisan', 'weather:work') `
    -WorkingDirectory $backendPath -WindowStyle Hidden `
    -RedirectStandardOutput (Join-Path $backendPath 'storage/logs/weather-worker.stdout.log') `
    -RedirectStandardError (Join-Path $backendPath 'storage/logs/weather-worker.stderr.log') -PassThru
Write-Output "Weather updater started (PID $($worker.Id)). Fetches weather directly every three hours."
