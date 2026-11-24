# ActivityMind: запуск одним кликом — MySQL (XAMPP), сайт, AI-модуль и Desktop Agent.
# Что уже запущено, второй раз не запускается. Окна служб скрыты, журналы — в storage\logs\launcher-*.log.
param(
    [int]$Port = 8000,
    [string]$XamppDir = $(if ($env:XAMPP_DIR) { $env:XAMPP_DIR } else { 'C:\xampp' }),
    [switch]$NoBrowser,
    [switch]$NoAgent,
    [switch]$Lan        # открыть сайт для телефона и других устройств в той же Wi-Fi сети
)

$Root = Split-Path -Parent $PSScriptRoot
$Logs = Join-Path $Root 'storage\logs'
$Url  = "http://localhost:$Port"
New-Item -ItemType Directory -Force $Logs | Out-Null
Set-Location $Root
try { $Host.UI.RawUI.WindowTitle = 'ActivityMind — запуск' } catch {}

function Say([string]$text, [string]$color = 'Gray') { Write-Host $text -ForegroundColor $color }
function Ok([string]$text)   { Say "  [ok] $text" 'Green' }
function Skip([string]$text) { Say "  [--] $text" 'DarkGray' }
function Fail([string]$text) {
    Say "  [!!] $text" 'Red'
    Read-Host '  Нажмите Enter, чтобы закрыть' | Out-Null
    exit 1
}

function Test-Port([int]$p) {
    $c = New-Object Net.Sockets.TcpClient
    try { $c.Connect('127.0.0.1', $p); return $true } catch { return $false } finally { $c.Close() }
}

function Wait-Port([int]$p, [int]$seconds) {
    for ($i = 0; $i -lt $seconds * 2; $i++) {
        if (Test-Port $p) { return $true }
        Start-Sleep -Milliseconds 500
    }
    return $false
}

function Start-Hidden([string]$file, [string[]]$arguments, [string]$dir, [string]$name) {
    Start-Process -FilePath $file -ArgumentList $arguments -WorkingDirectory $dir -WindowStyle Hidden `
        -RedirectStandardOutput (Join-Path $Logs "launcher-$name.log") `
        -RedirectStandardError (Join-Path $Logs "launcher-$name.err.log") | Out-Null
}

# На каких адресах слушается порт (например 127.0.0.1 или 0.0.0.0)
function Get-ListenAddresses([int]$p) {
    @(netstat -ano -p TCP |
        Select-String -Pattern "^\s*TCP\s+(\S+):$p\s+\S+\s+LISTENING\s+(\d+)" |
        ForEach-Object { [pscustomobject]@{ Address = $_.Matches[0].Groups[1].Value; ProcId = [int]$_.Matches[0].Groups[2].Value } })
}

# Останавливает «php artisan serve» на порту (и его дочерний php -S)
function Stop-Site([int]$p) {
    foreach ($l in Get-ListenAddresses $p) {
        $proc = Get-CimInstance Win32_Process -Filter "ProcessId = $($l.ProcId)" -ErrorAction SilentlyContinue
        if ($proc) {
            $parent = Get-CimInstance Win32_Process -Filter "ProcessId = $($proc.ParentProcessId)" -ErrorAction SilentlyContinue
            if ($parent -and $parent.CommandLine -match 'artisan') { Stop-Process -Id $parent.ProcessId -Force -ErrorAction SilentlyContinue }
        }
        Stop-Process -Id $l.ProcId -Force -ErrorAction SilentlyContinue
    }
    for ($i = 0; $i -lt 10 -and (Test-Port $p); $i++) { Start-Sleep -Milliseconds 500 }
}

# IP-адрес компьютера в домашней сети: частный адрес (192.168.x.x, 10.x.x.x, 172.16–31.x.x)
# на физическом адаптере (Wi-Fi/Ethernet) — виртуальные адаптеры VPN пропускаются
function Get-LanAddress {
    $physical = @(Get-NetAdapter -Physical -ErrorAction SilentlyContinue | Where-Object Status -eq 'Up' | ForEach-Object ifIndex)
    $addrs = @(Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object {
        $_.IPAddress -match '^(192\.168\.|10\.|172\.(1[6-9]|2\d|3[01])\.)' -and $physical -contains $_.InterfaceIndex
    } | Sort-Object { if ($_.PrefixOrigin -eq 'Dhcp') { 0 } else { 1 } })
    if ($addrs.Count -gt 0) { return $addrs[0].IPAddress }
    return $null
}

# Правило брандмауэра: порт сайта открыт только для устройств из той же локальной сети
function Confirm-FirewallRule([int]$p) {
    $name = "ActivityMind site (TCP $p)"
    if (Get-NetFirewallRule -DisplayName $name -ErrorAction SilentlyContinue) { Ok 'Брандмауэр: порт уже разрешён'; return }
    Say "  Windows попросит разрешение, чтобы открыть порт $p для устройств из вашей Wi-Fi сети..."
    $cmd = "New-NetFirewallRule -DisplayName '$name' -Direction Inbound -Protocol TCP -LocalPort $p " +
           "-RemoteAddress LocalSubnet -Action Allow -Profile Any | Out-Null"
    try {
        Start-Process powershell -Verb RunAs -Wait -WindowStyle Hidden -ArgumentList @('-NoProfile', '-Command', $cmd)
    } catch { }
    if (Get-NetFirewallRule -DisplayName $name -ErrorAction SilentlyContinue) { Ok "Брандмауэр: порт $p разрешён для локальной сети" }
    else { Skip 'Брандмауэр: разрешение не получено — другие устройства, скорее всего, не смогут открыть сайт' }
}

Say ''
Say '  ActivityMind' 'Yellow'
Say ''

# --- 1. База данных (MySQL из XAMPP) ---
if (Test-Port 3306) {
    Ok 'MySQL уже работает'
} else {
    $mysqld = Join-Path $XamppDir 'mysql\bin\mysqld.exe'
    if (-not (Test-Path $mysqld)) { Fail "Не найден MySQL: $mysqld. Если XAMPP в другой папке: ActivityMind.cmd -XamppDir D:\xampp" }
    Say '  Запускаю MySQL...'
    Start-Hidden $mysqld @('--defaults-file=mysql\bin\my.ini', '--standalone') $XamppDir 'mysql'
    if (Wait-Port 3306 40) { Ok 'MySQL запущен' }
    else { Fail 'MySQL не запустился за 40 секунд. Подробности: storage\logs\launcher-mysql.err.log' }
}

# --- 2. Подготовка сайта: зависимости и сборка (только при первом запуске), миграции ---
if (-not (Test-Path (Join-Path $Root 'vendor\autoload.php'))) {
    Say '  Устанавливаю PHP-зависимости (первый запуск, пара минут)...'
    & composer install --no-interaction 2>&1 | Out-Null
}
if (-not (Test-Path (Join-Path $Root 'public\build\manifest.json'))) {
    Say '  Собираю стили и скрипты (первый запуск, пара минут)...'
    & npm install --no-audit --no-fund 2>&1 | Out-Null
    & npm run build 2>&1 | Out-Null
}
$migrate = & php artisan migrate --force 2>&1
if ($LASTEXITCODE -eq 0) { Ok 'База данных в актуальном состоянии' }
else {
    $migrate | Select-Object -Last 8 | ForEach-Object { Say "       $_" 'DarkGray' }
    Fail 'Миграции не выполнились (текст ошибки выше).'
}

# --- 3. Сайт ---
$bindHost = if ($Lan) { '0.0.0.0' } else { '127.0.0.1' }
if ($Lan -and (Test-Port $Port) -and -not (Get-ListenAddresses $Port | Where-Object { $_.Address -eq '0.0.0.0' })) {
    Say '  Сайт работает только для этого компьютера — перезапускаю его для Wi-Fi сети...'
    Stop-Site $Port
}
if (Test-Port $Port) {
    Ok "Сайт уже работает: $Url"
} else {
    Start-Hidden 'php' @('artisan', 'serve', "--host=$bindHost", "--port=$Port") $Root 'server'
    if (Wait-Port $Port 20) { Ok "Сайт запущен: $Url" }
    else { Fail 'Сайт не запустился. Подробности: storage\logs\launcher-server.err.log' }
}

# --- 4. AI-модуль (необязательно) ---
$aiPython = Join-Path $Root 'ai-module\.venv\Scripts\python.exe'
if (Test-Port 5000) {
    Ok 'AI-модуль уже работает'
} elseif (Test-Path $aiPython) {
    Start-Hidden $aiPython @('app.py') (Join-Path $Root 'ai-module') 'ai'
    if (Wait-Port 5000 15) { Ok 'AI-модуль запущен' } else { Skip 'AI-модуль не запустился — советы будут только по правилам' }
} else {
    Skip 'AI-модуль не установлен (см. ai-module\README.md) — советы будут только по правилам'
}

# --- 5. Desktop Agent (необязательно) ---
$agentDir = Join-Path $Root 'desktop-agent'
$pythonw = Get-Command pythonw -ErrorAction SilentlyContinue
if ($NoAgent) {
    Skip 'Desktop Agent пропущен (-NoAgent)'
} elseif (-not (Test-Path (Join-Path $agentDir 'config.ini'))) {
    Skip 'Desktop Agent не настроен (нет desktop-agent\config.ini)'
} elseif (-not $pythonw) {
    Skip 'Desktop Agent: не найден Python (pythonw)'
} else {
    # если агент уже работает, вторая копия сама завершится (блокировка agent.lock)
    Start-Process -FilePath $pythonw.Source -ArgumentList "`"$agentDir\agent.py`"" -WorkingDirectory $agentDir -WindowStyle Hidden | Out-Null
    Ok 'Desktop Agent запущен (значок в трее)'
}

Say ''
if ($NoBrowser) { Say "  Готово. Сайт: $Url" 'Yellow' }
else { Start-Process $Url; Say "  Готово. Сайт открыт в браузере: $Url" 'Yellow' }
if ($Lan) {
    Confirm-FirewallRule $Port
    $ip = Get-LanAddress
    Say ''
    if ($ip) {
        Say "  С телефона или другого ноутбука в той же Wi-Fi сети откройте:" 'Yellow'
        Say "      http://${ip}:$Port" 'Cyan'
    } else {
        Say '  Не удалось определить адрес в сети — посмотрите IPv4-адрес командой ipconfig.' 'Red'
    }
}
Say '  Остановить всё: ActivityMind-stop.cmd' 'DarkGray'
if ($Lan) { Say ''; Read-Host '  Нажмите Enter, чтобы закрыть это окно (сайт продолжит работать)' | Out-Null }
else { Start-Sleep -Seconds 4 }
exit 0
