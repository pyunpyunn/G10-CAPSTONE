$ErrorActionPreference = 'Stop'
$backendPath = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$roles = @()
foreach ($role in @('Api', 'Queue', 'Web')) {
    $taskName = if ($role -eq 'Web') { 'RESQPERATION Web Dashboard' } else { "RESQPERATION Mobile $role" }
    $task = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
    $stateFile = Join-Path $backendPath "storage\app\mobile-backend\$role.json"
    $state = $null
    if (Test-Path -LiteralPath $stateFile) {
        try { $state = Get-Content -LiteralPath $stateFile -Raw | ConvertFrom-Json } catch { }
    }
    $child = $null
    if ($state -and $state.child_pid) { $child = Get-Process -Id $state.child_pid -ErrorAction SilentlyContinue }
    $roles += [pscustomobject]@{
        role = $role; task_state = if ($task) { [string]$task.State } else { 'Not installed' }
        process_running = [bool]$child; child_pid = if ($child) { $child.Id } else { $null }
        stdout_log = if ($state) { $state.stdout_log } else { $null }
        stderr_log = if ($state) { $state.stderr_log } else { $null }
    }
}
$apiReady = $false
try {
    $response = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:8000/api/v1/health/ready' -TimeoutSec 10
    $apiReady = $response.StatusCode -eq 200
} catch { }
$webReady = $false
try {
    $response = Invoke-WebRequest -UseBasicParsing -Uri 'http://127.0.0.1:5175/mapping' -TimeoutSec 10
    $webReady = $response.StatusCode -eq 200
} catch { }
[ordered]@{ api_ready = $apiReady; web_ready = $webReady; tasks = $roles } | ConvertTo-Json -Depth 4
