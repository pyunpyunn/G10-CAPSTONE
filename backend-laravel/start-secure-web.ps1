param([string]$CloudflaredPath = 'C:/backend/tools/cloudflared.exe')
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot
$webRoot = Join-Path $projectRoot 'frontend-web'
$tunnelLog = Join-Path $PSScriptRoot 'storage/logs/web-cloudflare.stderr.log'
if (-not (Test-Path -LiteralPath $CloudflaredPath)) { throw 'Install cloudflared and pass its executable path with -CloudflaredPath.' }
$tunnel = Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'cloudflared.exe' -and $_.CommandLine -match '127\.0\.0\.1:5175' }
if (-not $tunnel) {
    Start-Process -FilePath $CloudflaredPath -ArgumentList @('tunnel','--url','http://127.0.0.1:5175','--no-autoupdate') -WindowStyle Hidden -RedirectStandardOutput (Join-Path $PSScriptRoot 'storage/logs/web-cloudflare.stdout.log') -RedirectStandardError $tunnelLog | Out-Null
}
$secureUrl = $null
for ($attempt = 0; $attempt -lt 20; $attempt++) {
    if (Test-Path -LiteralPath $tunnelLog) {
        $matches = [regex]::Matches((Get-Content -LiteralPath $tunnelLog -Raw), 'https://[a-z0-9-]+\.trycloudflare\.com')
        if ($matches.Count) { $secureUrl = $matches[$matches.Count-1].Value; break }
    }
    Start-Sleep -Seconds 1
}
if (-not $secureUrl) { throw 'The secure tunnel has not returned an address. Check web-cloudflare.stderr.log.' }
$envPath = Join-Path $webRoot '.env'
$envText = if (Test-Path -LiteralPath $envPath) { Get-Content -LiteralPath $envPath -Raw } else { '' }
if ($envText -match '(?m)^VITE_PUBLIC_WEB_URL=') { $envText = $envText -replace '(?m)^VITE_PUBLIC_WEB_URL=.*$', "VITE_PUBLIC_WEB_URL=$secureUrl" }
else { $envText += "`nVITE_PUBLIC_WEB_URL=$secureUrl`n" }
Set-Content -LiteralPath $envPath -Value $envText -NoNewline
if (-not (Get-NetTCPConnection -LocalPort 5175 -State Listen -ErrorAction SilentlyContinue)) {
    Start-Process -FilePath (Get-Command node).Source -ArgumentList @('node_modules/vite/bin/vite.js','--host','0.0.0.0','--port','5175') -WorkingDirectory $webRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $PSScriptRoot 'storage/logs/web-vite.stdout.log') -RedirectStandardError (Join-Path $PSScriptRoot 'storage/logs/web-vite.stderr.log') | Out-Null
}
& (Join-Path $PSScriptRoot 'start-web-notifications.ps1')
Write-Output "Secure web address: $secureUrl"
Write-Output 'Sign in and choose Enable web notifications above Logout. The address changes when a quick tunnel is recreated.'
