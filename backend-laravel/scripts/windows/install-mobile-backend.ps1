param(
    [string]$PhpPath = 'C:\backend\php\php.exe',
    [string]$DatabaseService = 'MySQL95',
    [switch]$TestRecovery
)
$ErrorActionPreference = 'Stop'
$backendPath = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$installLog = Join-Path $backendPath 'storage\logs\mobile-backend-install.log'
$resultPath = Join-Path $backendPath 'storage\app\mobile-backend-install.json'
Start-Transcript -Path $installLog -Append | Out-Null
try {
    $administrator = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    if (!$administrator) { throw 'Run this installer once from PowerShell as administrator. The tasks run without administrator rights afterward.' }
    if (!(Test-Path -LiteralPath $PhpPath)) { throw "PHP was not found: $PhpPath" }
    $env:APP_DEBUG = 'false'

    foreach ($serviceName in @($DatabaseService, 'Tailscale')) {
        Set-Service -Name $serviceName -StartupType Automatic
        Start-Service -Name $serviceName
        (Get-Service -Name $serviceName).WaitForStatus('Running', [TimeSpan]::FromSeconds(60))
    }
    & sc.exe failure $DatabaseService reset= 86400 actions= restart/5000/restart/15000/restart/30000
    if ($LASTEXITCODE -ne 0) { throw 'Could not configure database service crash recovery.' }
    & 'C:\Program Files\Tailscale\tailscale.exe' set --unattended=true
    if ($LASTEXITCODE -ne 0) { throw 'Could not enable unattended Tailscale access.' }

    # Limit the service account's new write grants to Laravel's runtime folders.
    foreach ($directory in @('storage', 'bootstrap\cache')) {
        $target = (Resolve-Path (Join-Path $backendPath $directory)).Path
        if (!$target.StartsWith($backendPath + '\', [StringComparison]::OrdinalIgnoreCase)) { throw 'Runtime folder is outside the backend.' }
        & icacls.exe $target /grant '*S-1-5-19:(OI)(CI)M' /T /Q
        if ($LASTEXITCODE -ne 0) { throw "Could not grant runtime access: $target" }
    }

    $ruleName = 'RESQPERATION-Mobile-API-Tailscale'
    if (!(Get-NetFirewallRule -Name $ruleName -ErrorAction SilentlyContinue)) {
        New-NetFirewallRule -Name $ruleName -DisplayName 'RESQPERATION mobile API via Tailscale' `
            -Direction Inbound -Action Allow -Enabled True -Profile Any -Protocol TCP -LocalPort 8000 `
            -RemoteAddress '100.64.0.0/10' -Program $PhpPath | Out-Null
    }

    Push-Location $backendPath
    try {
        & $PhpPath artisan config:clear
        if ($LASTEXITCODE -ne 0) { throw 'Could not clear the Laravel config cache.' }
        & $PhpPath artisan queue:restart
        if ($LASTEXITCODE -ne 0) { throw 'Could not gracefully restart the existing queue worker.' }
    } finally { Pop-Location }

    $powershellPath = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
    $runner = Join-Path $PSScriptRoot 'run-mobile-service.ps1'
    $principal = New-ScheduledTaskPrincipal -UserId 'NT AUTHORITY\LOCAL SERVICE' -LogonType ServiceAccount -RunLevel Limited
    $settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -StartWhenAvailable `
        -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit ([TimeSpan]::Zero) `
        -RestartCount 999 -RestartInterval ([TimeSpan]::FromMinutes(1))
    $boot = New-ScheduledTaskTrigger -AtStartup
    $boot.Delay = 'PT20S'
    $logon = New-ScheduledTaskTrigger -AtLogOn
    $scheduler = New-Object -ComObject 'Schedule.Service'
    $scheduler.Connect()
    $taskFolder = $scheduler.GetFolder('\')
    $interactiveUserSid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
    foreach ($role in @('Api', 'Queue')) {
        $name = "RESQPERATION Mobile $role"
        $action = New-ScheduledTaskAction -Execute $powershellPath `
            -Argument "-NoProfile -NonInteractive -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$runner`" -Role $role -PhpPath `"$PhpPath`"" `
            -WorkingDirectory $backendPath
        Register-ScheduledTask -TaskName $name -Action $action -Trigger @($boot, $logon) -Settings $settings `
            -Principal $principal -Description 'Automatic mobile backend with process supervision and file logs.' -Force | Out-Null
        $registeredTask = $taskFolder.GetTask($name)
        $securityDescriptor = $registeredTask.GetSecurityDescriptor(4)
        if (!$securityDescriptor.Contains(";;;$interactiveUserSid)")) {
            $registeredTask.SetSecurityDescriptor($securityDescriptor + "(A;;GRGX;;;$interactiveUserSid)", 0)
        }
        Start-ScheduledTask -TaskName $name
    }

    function Wait-ApiReady {
        for ($attempt = 0; $attempt -lt 30; $attempt++) {
            try {
                $response = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:8000/api/v1/health/ready' -TimeoutSec 5
                if ($response.StatusCode -eq 200) { return }
            } catch { }
            Start-Sleep -Seconds 2
        }
        throw 'The API did not become ready. Check storage/logs/mobile-backend.'
    }
    function Wait-Role([string]$Role, [int]$PreviousChild = 0) {
        $stateFile = Join-Path $backendPath "storage\app\mobile-backend\$Role.json"
        for ($attempt = 0; $attempt -lt 40; $attempt++) {
            try {
                $state = Get-Content -LiteralPath $stateFile -Raw | ConvertFrom-Json
                if ($state.status -eq 'running' -and $state.child_pid -ne $PreviousChild) {
                    $process = Get-Process -Id $state.child_pid -ErrorAction SilentlyContinue
                    if ($process) { return $state }
                }
            } catch { }
            Start-Sleep -Seconds 2
        }
        throw "The $Role supervisor did not start a child process."
    }
    Wait-ApiReady
    $apiState = Wait-Role 'Api'
    $queueState = Wait-Role 'Queue'
    $recoveryChecked = $false
    if ($TestRecovery) {
        # Stop only the exact child recorded by the new task. The supervisor
        # remains running and must restore it without a manual command.
        Stop-Process -Id $apiState.child_pid -Force
        $apiState = Wait-Role 'Api' ([int]$apiState.child_pid)
        Wait-ApiReady
        Push-Location $backendPath
        try {
            & $PhpPath artisan queue:restart
            if ($LASTEXITCODE -ne 0) { throw 'Queue recovery check could not request a graceful restart.' }
        } finally { Pop-Location }
        $queueState = Wait-Role 'Queue' ([int]$queueState.child_pid)
        $recoveryChecked = $true
    }
    $result = [ordered]@{
        installed = $true; account = 'NT AUTHORITY\LOCAL SERVICE'; api_ready = $true
        recovery_tested = $recoveryChecked; api_pid = $apiState.child_pid; queue_pid = $queueState.child_pid
        startup = 'Windows boot (20-second delay) and logon'; completed_at = [DateTimeOffset]::Now.ToString('o')
    }
    $result | ConvertTo-Json | Set-Content -LiteralPath $resultPath -Encoding UTF8
    $result | ConvertTo-Json | Write-Output
} catch {
    @{ installed = $false; error = $_.Exception.Message; completed_at = [DateTimeOffset]::Now.ToString('o') } |
        ConvertTo-Json | Set-Content -LiteralPath $resultPath -Encoding UTF8
    Write-Error $_
    exit 1
} finally { Stop-Transcript | Out-Null }
