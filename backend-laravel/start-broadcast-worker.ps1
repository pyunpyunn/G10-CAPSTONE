$ErrorActionPreference = 'Stop'
$backendPath = $PSScriptRoot
$phpPath = (Get-Command php -ErrorAction Stop).Source
$existing = Get-CimInstance Win32_Process | Where-Object {
    $_.Name -eq 'php.exe' -and
    $_.CommandLine -match 'artisan\s+queue:work\s+operations_outbox\b'
}
if ($existing) {
    Write-Output "Operations worker is already running (PID $($existing.ProcessId -join ', '))."
    exit 0
}
$worker = Start-Process -FilePath $phpPath `
    -ArgumentList @('artisan', 'queue:work', 'operations_outbox', '--queue=operations', '--sleep=1', '--tries=3', '--timeout=120') `
    -WorkingDirectory $backendPath -WindowStyle Hidden `
    -RedirectStandardOutput (Join-Path $backendPath 'storage/logs/operations-worker.stdout.log') `
    -RedirectStandardError (Join-Path $backendPath 'storage/logs/operations-worker.stderr.log') `
    -PassThru
Write-Output "Operations worker started (PID $($worker.Id)). Broadcast delivery runs automatically while it is running."
