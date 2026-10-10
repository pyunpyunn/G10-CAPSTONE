param([switch]$Queue, [switch]$Restart, [switch]$Inline, [ValidateSet('realtime_database', 'realtime_redis')][string]$QueueConnection = 'realtime_database')
$ErrorActionPreference = 'Stop'
Push-Location $PSScriptRoot
try {
    php artisan config:clear
    if ($LASTEXITCODE -ne 0) { throw 'Unable to clear Laravel configuration.' }
    $runtimeSettings = php artisan realtime:status | ConvertFrom-Json
    if (-not $Inline -and $runtimeSettings.queue_connection -eq 'realtime_redis') {
        $Queue = $true
        if (-not $PSBoundParameters.ContainsKey('QueueConnection')) { $QueueConnection = 'realtime_redis' }
    }
    $statePath = Join-Path $PSScriptRoot 'storage/app/realtime-processes.json'
    $previous = @{}
    if (Test-Path -LiteralPath $statePath) {
        $previous = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json -AsHashtable
    }
    $services = @{
        reverb = @('artisan', 'reverb:start', '--host=0.0.0.0', '--port=8090')
        relay = @('artisan', 'realtime:dispatch', '--inline')
    }
    if ($Queue) {
        $services.relay = @('artisan', 'realtime:dispatch')
        $services.worker = @('artisan', 'queue:work', $QueueConnection, '--queue=realtime', '--sleep=1', '--tries=5', '--timeout=20')
    }
    $processState = @{}
    foreach ($service in $services.GetEnumerator()) {
        $existingProcess = $null
        if ($previous.ContainsKey($service.Key)) {
            $candidatePid = [int]$previous[$service.Key]
            $candidate = Get-CimInstance Win32_Process -Filter "ProcessId = $candidatePid"
            $expectedCommand = [regex]::Escape($service.Value[1])
            if ($candidate -and $candidate.Name -eq 'php.exe' -and $candidate.CommandLine -match $expectedCommand) {
                if ($Restart -or ($service.Key -eq 'relay' -and $Queue -and $candidate.CommandLine -match '--inline')) {
                    Stop-Process -Id $candidatePid
                } else { $existingProcess = $candidatePid }
            }
        }
        if ($existingProcess) { $processState[$service.Key] = $existingProcess; continue }
        if ($service.Key -eq 'reverb' -and (Get-NetTCPConnection -State Listen -LocalPort 8090 -ErrorAction SilentlyContinue)) {
            throw 'Port 8090 is already occupied. Stop or reconfigure the conflicting process before starting Reverb.'
        }
        $started = Start-Process -FilePath (Get-Command php).Source -ArgumentList $service.Value -WorkingDirectory $PSScriptRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $PSScriptRoot "storage/logs/realtime-$($service.Key).stdout.log") -RedirectStandardError (Join-Path $PSScriptRoot "storage/logs/realtime-$($service.Key).stderr.log")
        $processState[$service.Key] = $started.Id
    }
    $processState | ConvertTo-Json | Set-Content -LiteralPath $statePath
    $processState | ConvertTo-Json
} finally {
    Pop-Location
}
