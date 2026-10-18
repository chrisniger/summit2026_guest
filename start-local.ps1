param(
    [string]$Php = 'D:\php84\php.exe',
    [int]$Port = 8000,
    [switch]$RestartWorkers
)
$ErrorActionPreference = 'Stop'
$projectRoot = $PSScriptRoot
$artisan = Join-Path $projectRoot 'artisan'
if (!(Test-Path -LiteralPath (Join-Path $projectRoot 'vendor\autoload.php')) -or !(Test-Path -LiteralPath (Join-Path $projectRoot '.env'))) {
    throw 'Install locked dependencies and configure the existing local .env first.'
}
if (!(Test-Path -LiteralPath (Join-Path $projectRoot 'public\build\manifest.json'))) {
    throw 'Build the frontend with npm ci and npm run build first.'
}
$listeners = @(Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue)
$webPid = $null
if ($listeners.Count -gt 0) {
    foreach ($listener in $listeners) {
        $process = Get-CimInstance Win32_Process -Filter "ProcessId = $($listener.OwningProcess)"
        if (!$process -or $process.Name -ne 'php.exe' -or $process.CommandLine -notlike "*$projectRoot*") {
            throw "Port $Port is owned by another application. No process was stopped."
        }
        $webPid = $process.ProcessId
    }
} else {
    $web = Start-Process -FilePath $Php -ArgumentList @(('"' + $artisan + '"'), 'serve', '--host=127.0.0.1', "--port=$Port", '--no-reload') -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $projectRoot 'storage\logs\web-server.log') -RedirectStandardError (Join-Path $projectRoot 'storage\logs\web-server-error.log') -PassThru
    $webPid = $web.Id
}
$running = @(Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | Where-Object {
    $_.CommandLine -like "*$artisan*" -and ($_.CommandLine -like '*queue:work*--queue=imports*' -or $_.CommandLine -like '*schedule:work*')
})
if ($RestartWorkers) {
    foreach ($process in $running) {
        Stop-Process -Id $process.ProcessId
    }
    $running = @()
}
$worker = $running | Where-Object { $_.CommandLine -like '*queue:work*--queue=imports*' } | Select-Object -First 1
$scheduler = $running | Where-Object { $_.CommandLine -like '*schedule:work*' } | Select-Object -First 1
if (!$worker) {
    $worker = Start-Process -FilePath $Php -ArgumentList @(('"' + $artisan + '"'), 'queue:work', 'database', '--queue=imports', '--sleep=1', '--tries=3', '--timeout=120') -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $projectRoot 'storage\logs\imports-worker.log') -RedirectStandardError (Join-Path $projectRoot 'storage\logs\imports-worker-error.log') -PassThru
}
if (!$scheduler) {
    $scheduler = Start-Process -FilePath $Php -ArgumentList @(('"' + $artisan + '"'), 'schedule:work') -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $projectRoot 'storage\logs\scheduler.log') -RedirectStandardError (Join-Path $projectRoot 'storage\logs\scheduler-error.log') -PassThru
}
$workerPid = if ($worker.ProcessId) { $worker.ProcessId } else { $worker.Id }
$schedulerPid = if ($scheduler.ProcessId) { $scheduler.ProcessId } else { $scheduler.Id }
$state = @{ project = $projectRoot; web = $webPid; worker = $workerPid; scheduler = $schedulerPid; port = $Port }
$state | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $projectRoot 'storage\app\local-processes.json')
Write-Output "Local app: http://127.0.0.1:$Port"
Write-Output "Web PID $webPid; imports worker PID $workerPid; scheduler PID $schedulerPid."
