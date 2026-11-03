param(
    [string]$NgrokPath = '',
    [uri]$PublicUrl = 'https://sturdily-morse-sitter.ngrok-free.dev',
    [string]$LocalApiUrl = 'http://127.0.0.1:8000'
)
$ErrorActionPreference = 'Stop'

if (!$NgrokPath) {
    $ngrok = Get-Command ngrok.exe -ErrorAction SilentlyContinue
    if (!$ngrok) { throw 'ngrok.exe was not found. Install ngrok and sign in on this backend computer first.' }
    $NgrokPath = $ngrok.Source
}
if (!(Test-Path -LiteralPath $NgrokPath)) { throw "ngrok.exe was not found: $NgrokPath" }
if ($PublicUrl.Scheme -ne 'https' -or !$PublicUrl.Host) { throw 'Use the HTTPS reserved ngrok URL configured in the mobile APK.' }

$backendPath = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$runnerPath = Join-Path $PSScriptRoot 'run-ngrok-tunnel.ps1'
$healthUrl = "$($LocalApiUrl.TrimEnd('/'))/api/v1/health/ready"
$publicHealthUrl = "$($PublicUrl.AbsoluteUri.TrimEnd('/'))/api/v1/health/ready"

try {
    $localHealth = Invoke-RestMethod -Uri $healthUrl -TimeoutSec 5
} catch {
    throw "Laravel is not reachable at $healthUrl. Start or repair the RESQPERATION Mobile API task first."
}
if ($localHealth.status -ne 'ready') { throw 'Laravel is reachable, but its configured database is not ready.' }

& $NgrokPath config check *> $null
if ($LASTEXITCODE -ne 0) { throw 'ngrok configuration is invalid. Run ngrok config check and sign in on this backend computer.' }

$powershellPath = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
$taskName = 'RESQPERATION Ngrok Tunnel'
$currentUser = [Security.Principal.WindowsIdentity]::GetCurrent().Name
$arguments = '-NoProfile -NonInteractive -ExecutionPolicy Bypass -WindowStyle Hidden -File "{0}" -NgrokPath "{1}" -PublicUrl "{2}" -LocalApiUrl "{3}"' -f `
    $runnerPath, $NgrokPath, $PublicUrl.AbsoluteUri.TrimEnd('/'), $LocalApiUrl
$action = New-ScheduledTaskAction -Execute $powershellPath -Argument $arguments -WorkingDirectory $backendPath
$trigger = New-ScheduledTaskTrigger -AtLogOn -User $currentUser
$principal = New-ScheduledTaskPrincipal -UserId $currentUser -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -StartWhenAvailable `
    -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit ([TimeSpan]::Zero) `
    -RestartCount 999 -RestartInterval ([TimeSpan]::FromMinutes(1))
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings `
    -Principal $principal -Description 'Starts and supervises the RESQPERATION Ngrok tunnel at user sign-in.' -Force | Out-Null

$runningSupervisor = Get-CimInstance Win32_Process -Filter "Name = 'powershell.exe'" |
    Where-Object { $_.CommandLine -and $_.CommandLine.IndexOf($runnerPath, [StringComparison]::OrdinalIgnoreCase) -ge 0 } |
    Select-Object -First 1
if (!$runningSupervisor) {
    Start-ScheduledTask -TaskName $taskName
}

$curl = Get-Command curl.exe -ErrorAction SilentlyContinue
if (!$curl) { throw 'curl.exe is required to verify the public Ngrok HTTPS route.' }
$addresses = @()
try {
    $addresses = @(Resolve-DnsName -Name $PublicUrl.Host -Type A -DnsOnly -ErrorAction Stop |
        Where-Object IPAddress | Select-Object -ExpandProperty IPAddress -Unique)
} catch { }
if (!$addresses) {
    foreach ($dnsServer in @('1.1.1.1', '8.8.8.8')) {
        try {
            $addresses = @(Resolve-DnsName -Name $PublicUrl.Host -Type A -Server $dnsServer -DnsOnly -ErrorAction Stop |
                Where-Object IPAddress | Select-Object -ExpandProperty IPAddress -Unique)
            if ($addresses) { break }
        } catch { }
    }
}
if (!$addresses) { throw "Ngrok is running, but the public hostname could not be resolved. Check the network DNS settings for $($PublicUrl.Host)." }

$ready = $false
for ($attempt = 0; $attempt -lt 30; $attempt++) {
    foreach ($address in $addresses) {
        $probe = & $curl.Source --silent --show-error --resolve "$($PublicUrl.Host):443:$address" `
            --connect-timeout 5 --max-time 10 -H 'ngrok-skip-browser-warning: 1' `
            -w "`n__HTTP_STATUS__%{http_code}" $publicHealthUrl 2>$null
        $curlExitCode = $LASTEXITCODE
        $probeText = $probe -join "`n"
        if ($curlExitCode -eq 0 -and $probeText -match '(?s)\{"status"\s*:\s*"ready"\}\s*__HTTP_STATUS__200\s*$') {
            $ready = $true
            break
        }
    }
    if ($ready) { break }
    Start-Sleep -Seconds 2
}

if (!$ready) {
    throw "The scheduled task was installed, but the public API did not become ready at $publicHealthUrl. Check the ngrok account/domain and ngrok logs in storage\logs\mobile-backend."
}

$startupShortcut = Join-Path $env:APPDATA 'Microsoft\Windows\Start Menu\Programs\Startup\RESQPERATION Ngrok Tunnel.lnk'
if (Test-Path -LiteralPath $startupShortcut) {
    Remove-Item -LiteralPath $startupShortcut -Force
}

[ordered]@{
    installed = $true
    scheduled_task = $taskName
    trigger = 'When the current Windows user signs in'
    local_api_ready = $true
    public_api_ready = $true
    public_api = $PublicUrl.AbsoluteUri.TrimEnd('/')
} | ConvertTo-Json
