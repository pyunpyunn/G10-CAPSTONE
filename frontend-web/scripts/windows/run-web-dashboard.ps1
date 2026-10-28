param(
    [string]$NodePath = 'C:\Program Files\nodejs\node.exe'
)
$ErrorActionPreference = 'Stop'
$webPath = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$backendPath = Join-Path (Split-Path $webPath -Parent) 'backend-laravel'
$stateDirectory = Join-Path $backendPath 'storage\app\mobile-backend'
$logDirectory = Join-Path $backendPath 'storage\logs\mobile-backend'
New-Item -ItemType Directory -Force $stateDirectory, $logDirectory | Out-Null
$statePath = Join-Path $stateDirectory 'Web.json'
$supervisorLog = Join-Path $logDirectory 'Web-supervisor.log'
$vite = Join-Path $webPath 'node_modules\vite\bin\vite.js'

function Write-ServiceLog([string]$Message) {
    Add-Content -LiteralPath $supervisorLog -Value ("{0} {1}" -f [DateTimeOffset]::Now.ToString('o'), $Message)
}
function Write-ServiceState($Child, [string]$Status, [string]$OutputLog, [string]$ErrorLog) {
    $state = [ordered]@{
        role = 'Web'; status = $Status; supervisor_pid = $PID
        child_pid = if ($Child) { $Child.Id } else { $null }
        updated_at = [DateTimeOffset]::Now.ToString('o')
        stdout_log = $OutputLog; stderr_log = $ErrorLog
    }
    $temporaryState = "$statePath.tmp"
    $state | ConvertTo-Json | Set-Content -LiteralPath $temporaryState -Encoding UTF8
    Move-Item -LiteralPath $temporaryState -Destination $statePath -Force
}

$hash = [System.Security.Cryptography.SHA256]::Create()
try { $identity = ([BitConverter]::ToString($hash.ComputeHash([Text.Encoding]::UTF8.GetBytes($backendPath)))).Replace('-', '').Substring(0, 16) }
finally { $hash.Dispose() }
$mutex = New-Object System.Threading.Mutex($false, "Global\ResqMobile-$identity-Web")
$hasMutex = $false
$child = $null
try {
    try { $hasMutex = $mutex.WaitOne(0) }
    catch [System.Threading.AbandonedMutexException] { $hasMutex = $true }
    if (!$hasMutex) { exit 0 }
    Write-ServiceLog "Supervisor started; role=Web; executable=$NodePath"

    if (Test-Path -LiteralPath $statePath) {
        try {
            $previous = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
            $candidate = Get-CimInstance Win32_Process -Filter "ProcessId = $([int]$previous.child_pid)" -ErrorAction SilentlyContinue
            if ($candidate -and $candidate.ExecutablePath -eq $NodePath -and $candidate.CommandLine -match [regex]::Escape($vite)) {
                $child = Get-Process -Id $candidate.ProcessId
                Write-ServiceLog "Recovered child PID $($child.Id)."
            }
        } catch { Write-ServiceLog 'No recoverable child was found.' }
    }

    while ($true) {
        if (!$child) {
            $stamp = Get-Date -Format 'yyyyMMdd-HHmmss-fff'
            $outputLog = Join-Path $logDirectory "Web-$stamp.stdout.log"
            $errorLog = Join-Path $logDirectory "Web-$stamp.stderr.log"
            $arguments = @(('"{0}"' -f $vite), '--host', '0.0.0.0', '--port', '5175', '--strictPort')
            $child = Start-Process -FilePath $NodePath -ArgumentList $arguments -WorkingDirectory $webPath `
                -WindowStyle Hidden -RedirectStandardOutput $outputLog -RedirectStandardError $errorLog -PassThru
            Write-ServiceLog "Started child PID $($child.Id). Output: $outputLog; errors: $errorLog"
        } else {
            $outputLog = $previous.stdout_log
            $errorLog = $previous.stderr_log
        }
        Write-ServiceState $child 'running' $outputLog $errorLog
        $child.WaitForExit()
        Write-ServiceLog "Child PID $($child.Id) exited with code $($child.ExitCode); restarting in 5 seconds."
        $child = $null
        Write-ServiceState $null 'restarting' $outputLog $errorLog
        Start-Sleep -Seconds 5
    }
} catch {
    Write-ServiceLog ("Supervisor failed: " + $_.Exception.Message)
    throw
} finally {
    if ($hasMutex) { $mutex.ReleaseMutex() }
    $mutex.Dispose()
}
