# ActivityMind: остановка сайта, AI-модуля, Desktop Agent и MySQL.
param(
    [int]$Port = 8000,
    [string]$XamppDir = $(if ($env:XAMPP_DIR) { $env:XAMPP_DIR } else { 'C:\xampp' }),
    [switch]$KeepDatabase
)

try { $Host.UI.RawUI.WindowTitle = 'ActivityMind — остановка' } catch {}

function Ok([string]$text)   { Write-Host "  [ok] $text" -ForegroundColor Green }
function Skip([string]$text) { Write-Host "  [--] $text" -ForegroundColor DarkGray }

# Процесс, который слушает порт, и его родитель «php artisan serve» (он запускает дочерний php -S)
function Stop-PortOwner([int]$p, [string]$name) {
    $pids = @(netstat -ano -p TCP |
        Select-String -Pattern "^\s*TCP\s+\S+:$p\s+\S+\s+LISTENING\s+(\d+)" |
        ForEach-Object { [int]$_.Matches[0].Groups[1].Value } | Sort-Object -Unique)
    if ($pids.Count -eq 0) { Skip "$name не был запущен"; return }
    foreach ($procId in $pids) {
        $proc = Get-CimInstance Win32_Process -Filter "ProcessId = $procId" -ErrorAction SilentlyContinue
        if ($proc) {
            $parent = Get-CimInstance Win32_Process -Filter "ProcessId = $($proc.ParentProcessId)" -ErrorAction SilentlyContinue
            if ($parent -and $parent.CommandLine -match 'artisan') { Stop-Process -Id $parent.ProcessId -Force -ErrorAction SilentlyContinue }
        }
        Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue
    }
    Ok "$name остановлен"
}

Write-Host ''
Stop-PortOwner $Port 'Сайт'
Stop-PortOwner 5000 'AI-модуль'

# Desktop Agent: python/pythonw, запущенный с agent.py из этого проекта
$agentPath = Join-Path (Split-Path -Parent $PSScriptRoot) 'desktop-agent\agent.py'
$agents = @(Get-CimInstance Win32_Process -Filter "Name like 'python%'" |
    Where-Object { $_.CommandLine -and $_.CommandLine.Contains($agentPath) })
if ($agents.Count -gt 0) {
    $agents | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
    Ok 'Desktop Agent остановлен'
} else { Skip 'Desktop Agent не был запущен' }

if ($KeepDatabase) {
    Skip 'MySQL оставлен работать (-KeepDatabase)'
} elseif (Get-Process mysqld -ErrorAction SilentlyContinue) {
    # корректное завершение; если у root есть пароль и оно не сработало — завершаем процесс, как это делает XAMPP
    $mysqladmin = Join-Path $XamppDir 'mysql\bin\mysqladmin.exe'
    if (Test-Path $mysqladmin) { & $mysqladmin -u root shutdown 2>&1 | Out-Null }
    for ($i = 0; $i -lt 10 -and (Get-Process mysqld -ErrorAction SilentlyContinue); $i++) { Start-Sleep -Milliseconds 500 }
    Get-Process mysqld -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
    Ok 'MySQL остановлен'
} else { Skip 'MySQL не был запущен' }

Write-Host ''
Write-Host '  Всё остановлено.' -ForegroundColor Yellow
Start-Sleep -Seconds 3
exit 0
