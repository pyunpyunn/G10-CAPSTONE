param(
    [string]$NodePath = 'C:\Program Files\nodejs\node.exe',
    [switch]$TestRecovery
)
$ErrorActionPreference = 'Stop'
$backendPath = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$webPath = Join-Path (Split-Path $backendPath -Parent) 'frontend-web'
$resultPath = Join-Path $backendPath 'storage\app\web-dashboard-install.json'
Start-Transcript -Path (Join-Path $backendPath 'storage\logs\web-dashboard-install.log') -Append | Out-Null
try {
    $administrator = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    if (!$administrator) { throw 'Run this installer once from PowerShell as administrator.' }
    if (!(Test-Path -LiteralPath $NodePath) -or !(Test-Path -LiteralPath (Join-Path $webPath 'node_modules\vite\bin\vite.js'))) {
        throw 'Node.js and the installed frontend-web dependencies are required.'
    }
    # Vite writes only its dependency/config caches during development serving.
    foreach ($relative in @('node_modules\.vite', 'node_modules\.vite-temp')) {
        $cachePath = [IO.Path]::GetFullPath((Join-Path $webPath $relative))
        if (!$cachePath.StartsWith($webPath + '\', [StringComparison]::OrdinalIgnoreCase)) { throw 'Cache path is outside the frontend.' }
        New-Item -ItemType Directory -Force $cachePath | Out-Null
        & icacls.exe $cachePath /grant '*S-1-5-19:(OI)(CI)M' /T /Q
        if ($LASTEXITCODE -ne 0) { throw 'Could not grant Vite cache access.' }
    }
    $ruleName = 'RESQPERATION-Web-Dashboard-Tailscale'
    if (!(Get-NetFirewallRule -Name $ruleName -ErrorAction SilentlyContinue)) {
        New-NetFirewallRule -Name $ruleName -DisplayName 'RESQPERATION web dashboard via Tailscale' `
            -Direction Inbound -Action Allow -Enabled True -Profile Any -Protocol TCP -LocalPort 5175 `
            -RemoteAddress '100.64.0.0/10' -Program $NodePath | Out-Null
    }
    $taskName = 'RESQPERATION Web Dashboard'
    $powershellPath = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
    $runner = Join-Path $PSScriptRoot 'run-mobile-service.ps1'
    $action = New-ScheduledTaskAction -Execute $powershellPath `
        -Argument "-NoProfile -NonInteractive -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$runner`" -Role Web -NodePath `"$NodePath`"" `
        -WorkingDirectory $backendPath
    $principal = New-ScheduledTaskPrincipal -UserId 'NT AUTHORITY\LOCAL SERVICE' -LogonType ServiceAccount -RunLevel Limited
    $settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -StartWhenAvailable `
        -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit ([TimeSpan]::Zero) `
        -RestartCount 999 -RestartInterval ([TimeSpan]::FromMinutes(1))
    $boot = New-ScheduledTaskTrigger -AtStartup
    $boot.Delay = 'PT20S'
    Register-ScheduledTask -TaskName $taskName -Action $action -Trigger @($boot, (New-ScheduledTaskTrigger -AtLogOn)) `
        -Principal $principal -Settings $settings -Description 'Automatic Vite dashboard on port 5175, with process supervision and logs.' -Force | Out-Null
    $scheduler = New-Object -ComObject 'Schedule.Service'
    $scheduler.Connect()
    $registeredTask = $scheduler.GetFolder('\').GetTask($taskName)
    $descriptor = $registeredTask.GetSecurityDescriptor(4)
    $userSid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
    if (!$descriptor.Contains(";;;$userSid)")) { $registeredTask.SetSecurityDescriptor($descriptor + "(A;;GRGX;;;$userSid)", 0) }
    Start-ScheduledTask -TaskName $taskName

    function Wait-Web([int]$PreviousChild = 0) {
        $stateFile = Join-Path $backendPath 'storage\app\mobile-backend\Web.json'
        for ($attempt = 0; $attempt -lt 40; $attempt++) {
            try {
                $state = Get-Content -LiteralPath $stateFile -Raw | ConvertFrom-Json
                if ($state.status -eq 'running' -and $state.child_pid -ne $PreviousChild -and (Get-Process -Id $state.child_pid -ErrorAction SilentlyContinue)) {
                    $response = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:5175/mapping' -TimeoutSec 5
                    if ($response.StatusCode -eq 200) { return $state }
                }
            } catch { }
            Start-Sleep -Seconds 2
        }
        throw 'The web dashboard did not become ready. Check storage/logs/mobile-backend/Web-*.'
    }
    $state = Wait-Web
    $recoveryChecked = $false
    if ($TestRecovery) {
        Stop-Process -Id $state.child_pid -Force
        $state = Wait-Web ([int]$state.child_pid)
        $recoveryChecked = $true
    }
    $proxy = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:5175/api/v1/health/ready' -TimeoutSec 15
    if ($proxy.StatusCode -ne 200) { throw 'The dashboard cannot reach the Laravel API proxy.' }
    [ordered]@{ installed = $true; web_ready = $true; api_proxy_ready = $true; recovery_tested = $recoveryChecked
        child_pid = $state.child_pid; account = 'NT AUTHORITY\LOCAL SERVICE'; completed_at = [DateTimeOffset]::Now.ToString('o') } |
        ConvertTo-Json | Set-Content -LiteralPath $resultPath -Encoding UTF8
    Get-Content -LiteralPath $resultPath
} catch {
    @{ installed = $false; error = $_.Exception.Message; completed_at = [DateTimeOffset]::Now.ToString('o') } |
        ConvertTo-Json | Set-Content -LiteralPath $resultPath -Encoding UTF8
    Write-Error $_
    exit 1
} finally { Stop-Transcript | Out-Null }
