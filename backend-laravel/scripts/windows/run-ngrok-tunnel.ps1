param(
    [Parameter(Mandatory = $true)][string]$NgrokPath,
    [Parameter(Mandatory = $true)][uri]$PublicUrl,
    [string]$LocalApiUrl = 'http://127.0.0.1:8000'
)
$ErrorActionPreference = 'Stop'
$backendPath = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$logDirectory = Join-Path $backendPath 'storage\logs\mobile-backend'
New-Item -ItemType Directory -Force $logDirectory | Out-Null
$supervisorLog = Join-Path $logDirectory 'ngrok-supervisor.log'
$mutex = New-Object System.Threading.Mutex($false, 'Local\RESQPERATION-Ngrok-Tunnel')
$hasMutex = $false
$child = $null

function Write-TunnelLog([string]$Message) {
    Add-Content -LiteralPath $supervisorLog -Value ("{0} {1}" -f [DateTimeOffset]::Now.ToString('o'), $Message)
}

try {
    try { $hasMutex = $mutex.WaitOne(0) }
    catch [System.Threading.AbandonedMutexException] { $hasMutex = $true }
    if (!$hasMutex) { exit 0 }

    while ($true) {
        $stamp = Get-Date -Format 'yyyyMMdd-HHmmss-fff'
        $stdoutLog = Join-Path $logDirectory "ngrok-$stamp.stdout.log"
        $stderrLog = Join-Path $logDirectory "ngrok-$stamp.stderr.log"
        try {
            $arguments = "http `"$LocalApiUrl`" --url=`"$($PublicUrl.AbsoluteUri.TrimEnd('/'))`""
            $child = Start-Process -FilePath $NgrokPath -ArgumentList $arguments `
                -WorkingDirectory $backendPath -WindowStyle Hidden `
                -RedirectStandardOutput $stdoutLog -RedirectStandardError $stderrLog -PassThru
            Write-TunnelLog "Started ngrok process PID $($child.Id)."
            $child.WaitForExit()
            Write-TunnelLog "ngrok process PID $($child.Id) exited with code $($child.ExitCode); retrying in 10 seconds."
        } catch {
            Write-TunnelLog ("Could not start ngrok: " + $_.Exception.Message)
        }
        $child = $null
        Start-Sleep -Seconds 10
    }
} finally {
    if ($child -and !$child.HasExited) { Stop-Process -Id $child.Id -Force -ErrorAction SilentlyContinue }
    if ($hasMutex) { $mutex.ReleaseMutex() }
    $mutex.Dispose()
}
