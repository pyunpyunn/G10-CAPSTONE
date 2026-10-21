$ErrorActionPreference = 'Stop'
$existing = Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'php.exe' -and $_.CommandLine -match 'artisan\s+web-push:work\b' }
if ($existing) { Write-Output "Web notification worker already running (PID $($existing.ProcessId -join ', '))."; exit 0 }
$phpPath = (Get-Command php).Source
$opensslConfig = Join-Path (Split-Path $phpPath) 'extras/ssl/openssl.cnf'
if (Test-Path -LiteralPath $opensslConfig) { $env:OPENSSL_CONF = $opensslConfig }
$worker = Start-Process -FilePath $phpPath -ArgumentList @('artisan', 'web-push:work') -WorkingDirectory $PSScriptRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $PSScriptRoot 'storage/logs/web-push.stdout.log') -RedirectStandardError (Join-Path $PSScriptRoot 'storage/logs/web-push.stderr.log') -PassThru
Write-Output "Web notifications and official advisory sync running (PID $($worker.Id))."
