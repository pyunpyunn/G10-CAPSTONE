param(
    [Parameter(Mandatory = $true)][ValidateSet('Api', 'Queue')][string]$Role,
    [string]$PhpPath = 'C:\backend\php\php.exe'
)
$ErrorActionPreference = 'Stop'
$backendPath = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$stateDirectory = Join-Path $backendPath 'storage\app\mobile-backend'
$logDirectory = Join-Path $backendPath 'storage\logs\mobile-backend'
New-Item -ItemType Directory -Force $stateDirectory, $logDirectory | Out-Null
$statePath = Join-Path $stateDirectory "$Role.json"
$supervisorLog = Join-Path $logDirectory "$Role-supervisor.log"
$env:APP_DEBUG = 'false'
$executablePath = $PhpPath

function Write-ServiceLog([string]$Message) {
    Add-Content -LiteralPath $supervisorLog -Value ("{0} {1}" -f [DateTimeOffset]::Now.ToString('o'), $Message)
}
function Write-ServiceState($Child, [string]$Status, [string]$OutputLog, [string]$ErrorLog) {
    $state = [ordered]@{
        role = $Role; status = $Status; supervisor_pid = $PID
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
$mutex = New-Object System.Threading.Mutex($false, "Global\ResqMobile-$identity-$Role")
$hasMutex = $false
$child = $null
try {
    try { $hasMutex = $mutex.WaitOne(0) }
    catch [System.Threading.AbandonedMutexException] { $hasMutex = $true }
    if (!$hasMutex) { exit 0 }
    Write-ServiceLog "Supervisor started; role=$Role; executable=$executablePath"

    # Recover a still-running child after a supervisor restart, without creating
    # duplicate API listeners or workers. Only accept this exact PHP command.
    if (Test-Path -LiteralPath $statePath) {
        try {
            $previous = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
            $candidate = Get-CimInstance Win32_Process -Filter "ProcessId = $([int]$previous.child_pid)" -ErrorAction SilentlyContinue
            $expected = if ($Role -eq 'Api') {
                [regex]::Escape((Join-Path $backendPath 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'))
            } else { [regex]::Escape((Join-Path $backendPath 'artisan')) + '"?\s+queue:work\s+operations_outbox\b' }
            if ($candidate -and $candidate.ExecutablePath -eq $executablePath -and $candidate.CommandLine -match $expected) {
                $child = Get-Process -Id $candidate.ProcessId
                Write-ServiceLog "Recovered child PID $($child.Id)."
            }
        } catch { Write-ServiceLog 'No recoverable child was found.' }
    }

    while ($true) {
        $outputLog = $null
        $errorLog = $null
        if (!$child) {
            $stamp = Get-Date -Format 'yyyyMMdd-HHmmss-fff'
            $outputLog = Join-Path $logDirectory "$Role-$stamp.stdout.log"
            $errorLog = Join-Path $logDirectory "$Role-$stamp.stderr.log"
            if ($Role -eq 'Api') {
                # Match artisan serve's public directory and Laravel router, but
                # run PHP directly so there is only one server process to manage.
                $publicPath = Join-Path $backendPath 'public'
                $router = Join-Path $backendPath 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
                $arguments = @('-d', 'display_errors=stderr', '-S', '127.0.0.1:8000', '-t', ('"{0}"' -f $publicPath), ('"{0}"' -f $router))
                $workingDirectory = $publicPath
            } else {
                $arguments = @(('"{0}"' -f (Join-Path $backendPath 'artisan')), 'queue:work', 'operations_outbox', '--queue=broadcasts,operations,navigation', '--sleep=1', '--tries=3', '--timeout=120')
                $workingDirectory = $backendPath
            }
            $child = Start-Process -FilePath $executablePath -ArgumentList $arguments -WorkingDirectory $workingDirectory `
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
