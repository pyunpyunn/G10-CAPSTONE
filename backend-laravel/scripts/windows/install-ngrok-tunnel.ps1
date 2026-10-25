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
$startupPath = Join-Path $env:APPDATA 'Microsoft\Windows\Start Menu\Programs\Startup'
$shortcutPath = Join-Path $startupPath 'RESQPERATION Ngrok Tunnel.lnk'
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

New-Item -ItemType Directory -Force $startupPath | Out-Null
$powershellPath = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
$arguments = "-NoProfile -NonInteractive -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$runnerPath`" -NgrokPath `"$NgrokPath`" -PublicUrl `"$($PublicUrl.AbsoluteUri.TrimEnd('/'))`" -LocalApiUrl `"$LocalApiUrl`""
$shell = New-Object -ComObject WScript.Shell
$shortcut = $shell.CreateShortcut($shortcutPath)
$shortcut.TargetPath = $powershellPath
$shortcut.Arguments = $arguments
$shortcut.WorkingDirectory = $backendPath
$shortcut.WindowStyle = 7
$shortcut.Description = 'Starts and supervises the RESQPERATION ngrok tunnel.'
$shortcut.Save()

$runningSupervisor = Get-CimInstance Win32_Process -Filter "Name = 'powershell.exe'" |
    Where-Object { $_.CommandLine -and $_.CommandLine.IndexOf($runnerPath, [StringComparison]::OrdinalIgnoreCase) -ge 0 } |
    Select-Object -First 1
if (!$runningSupervisor) {
    Start-Process -FilePath $powershellPath -ArgumentList $arguments -WorkingDirectory $backendPath -WindowStyle Hidden | Out-Null
}

$ready = $false
for ($attempt = 0; $attempt -lt 30; $attempt++) {
    Start-Sleep -Seconds 2
    try {
        $response = Invoke-RestMethod -Uri $publicHealthUrl -Headers @{ 'ngrok-skip-browser-warning' = '1' } -TimeoutSec 5
        if ($response.status -eq 'ready') {
            $ready = $true
            break
        }
    } catch { }
}

if (!$ready) {
    throw "The startup entry was installed but the public API did not become ready at $publicHealthUrl. Check the ngrok account/domain and ngrok logs in storage\logs\mobile-backend."
}

[ordered]@{
    installed = $true
    startup_entry = $shortcutPath
    trigger = 'When the current Windows user signs in'
    local_api_ready = $true
    public_api_ready = $true
    public_api = $PublicUrl.AbsoluteUri.TrimEnd('/')
} | ConvertTo-Json
