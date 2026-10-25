param(
    [ValidateSet('Start', 'Stop')]
    [string]$Action = 'Start',
    [string]$MySqlBin
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$localDir = Join-Path $projectRoot '.local/mysql'
$dataDir = Join-Path $localDir 'data'
$configFile = Join-Path $localDir 'my.ini'
$adminFile = Join-Path $localDir 'admin.cnf'

if (-not $MySqlBin) {
    $serverCommand = Get-Command mysqld.exe -ErrorAction SilentlyContinue
    if ($serverCommand) {
        $MySqlBin = Split-Path -Parent $serverCommand.Source
    } else {
        $installation = Get-ChildItem 'C:/laragon/bin/mysql' -Directory -ErrorAction SilentlyContinue |
            Sort-Object Name -Descending | Select-Object -First 1
        if ($installation) { $MySqlBin = Join-Path $installation.FullName 'bin' }
    }
}
if (-not $MySqlBin -or -not (Test-Path (Join-Path $MySqlBin 'mysqld.exe'))) {
    throw 'MySQL was not found. Pass -MySqlBin with the MySQL 8.4 bin directory.'
}
$serverExe = Join-Path $MySqlBin 'mysqld.exe'
$adminExe = Join-Path $MySqlBin 'mysqladmin.exe'

if ($Action -eq 'Stop') {
    if (-not (Test-Path $adminFile)) { throw 'No initialized project database was found.' }
    & $adminExe "--defaults-extra-file=$adminFile" --connect-timeout=3 shutdown
    if ($LASTEXITCODE -ne 0) { throw 'Could not stop the project database. It may already be stopped.' }
    Write-Output 'Project MySQL stopped.'
    exit 0
}

if (-not (Test-Path (Join-Path $projectRoot 'application/.env'))) {
    throw 'Create application/.env from .env.example before starting MySQL.'
}
New-Item -ItemType Directory -Path $localDir -Force | Out-Null
$forwardData = $dataDir.Replace('\', '/')
$forwardLocal = $localDir.Replace('\', '/')
$forwardBase = (Split-Path -Parent $MySqlBin).Replace('\', '/')
@"
[mysqld]
basedir="$forwardBase"
datadir="$forwardData"
bind-address=127.0.0.1
port=3307
mysqlx=0
mysqlx-bind-address=127.0.0.1
pid-file="$forwardLocal/mysql.pid"
log-error="$forwardLocal/mysql-error.log"
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
"@ | Set-Content -LiteralPath $configFile -Encoding ASCII

if (-not (Test-Path (Join-Path $dataDir 'mysql'))) {
    & $serverExe "--defaults-file=$configFile" --initialize-insecure
    if ($LASTEXITCODE -ne 0) { throw "MySQL initialization failed. See $localDir/mysql-error.log" }
}

$listener = Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue
if ($listener) {
    $pidFile = Join-Path $localDir 'mysql.pid'
    if (-not (Test-Path $pidFile) -or
        $listener.OwningProcess -notcontains [int](Get-Content $pidFile)) {
        throw 'Port 3307 belongs to another process. Stop that process or use your own database configuration.'
    }
} else {
    $serverProcess = Start-Process -FilePath $serverExe -ArgumentList "--defaults-file=`"$configFile`"" -WindowStyle Hidden -PassThru
    $ready = $false
    for ($attempt = 0; $attempt -lt 40; $attempt++) {
        Start-Sleep -Milliseconds 500
        $serverProcess.Refresh()
        if ($serverProcess.HasExited) { throw "MySQL stopped during startup. See $localDir/mysql-error.log" }
        $socket = New-Object Net.Sockets.TcpClient
        try {
            $socket.Connect('127.0.0.1', 3307)
            $ready = $true
            break
        } catch {
            # The server is still starting.
        } finally {
            $socket.Dispose()
        }
    }
    if (-not $ready) { throw "MySQL did not become ready. See $localDir/mysql-error.log" }
}

& php (Join-Path $PSScriptRoot 'configure-local-database.php')
if ($LASTEXITCODE -ne 0) { throw 'Project database configuration failed.' }
Write-Output 'Project MySQL is ready at 127.0.0.1:3307. Run php artisan migrate inside application/.'
