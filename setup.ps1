[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$projectDirectory = (Resolve-Path $PSScriptRoot).Path
$environmentFile = Join-Path $projectDirectory '.env'
$besuEnvironmentFile = Join-Path $projectDirectory 'storage/app/private/besu/.env.besu'

function Invoke-SetupStep {
    param(
        [Parameter(Mandatory)] [string] $Label,
        [Parameter(Mandatory)] [string] $Command,
        [Parameter(Mandatory)] [string[]] $Arguments
    )

    Write-Host "`n==> $Label"
    & $Command @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "$Label failed with exit code $LASTEXITCODE."
    }
}

function Get-DotEnvValue {
    param(
        [Parameter(Mandatory)] [string] $Contents,
        [Parameter(Mandatory)] [string] $Key
    )

    $match = [regex]::Match($Contents, "(?m)^$([regex]::Escape($Key))=(.*)$")
    if (-not $match.Success) {
        return $null
    }

    return $match.Groups[1].Value.Trim().Trim('"')
}

function Merge-DotEnvFile {
    param(
        [Parameter(Mandatory)] [string] $TargetPath,
        [Parameter(Mandatory)] [string] $SourcePath
    )

    $targetContents = Get-Content -Raw -LiteralPath $TargetPath
    foreach ($sourceLine in Get-Content -LiteralPath $SourcePath) {
        $line = $sourceLine.Trim()
        if ([string]::IsNullOrWhiteSpace($line) -or $line.StartsWith('#')) {
            continue
        }

        $separator = $line.IndexOf('=')
        if ($separator -lt 1) {
            continue
        }

        $key = $line.Substring(0, $separator)
        $value = $line.Substring($separator + 1)
        $replacement = "$key=$value"
        $pattern = "(?m)^$([regex]::Escape($key))=.*$"

        if ([regex]::IsMatch($targetContents, $pattern)) {
            $evaluator = [System.Text.RegularExpressions.MatchEvaluator] { param($match) $replacement }
            $targetContents = [regex]::Replace($targetContents, $pattern, $evaluator, 1)
        } else {
            $targetContents = $targetContents.TrimEnd("`r", "`n") + "`r`n$replacement`r`n"
        }
    }

    $utf8NoBom = [System.Text.UTF8Encoding]::new($false)
    [System.IO.File]::WriteAllText($TargetPath, $targetContents, $utf8NoBom)
}

Push-Location $projectDirectory
try {
    Write-Host 'LGU Payroll System local setup'
    Write-Host 'This command prepares a local development environment and requires Docker Desktop for Besu.'

    foreach ($tool in @('php', 'composer', 'node', 'npm', 'docker', 'powershell')) {
        if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) {
            throw "Required tool '$tool' was not found on PATH."
        }
    }

    Invoke-SetupStep -Label 'Install PHP dependencies' -Command 'composer' -Arguments @('install', '--no-interaction', '--prefer-dist')

    if (-not (Test-Path -LiteralPath $environmentFile)) {
        Copy-Item -LiteralPath '.env.example' -Destination $environmentFile
        Write-Host 'Created .env from .env.example.'
    }

    $environmentContents = Get-Content -Raw -LiteralPath $environmentFile
    if ((Get-DotEnvValue -Contents $environmentContents -Key 'APP_ENV') -eq 'production') {
        throw 'setup.ps1 is for local development and refuses to seed a production environment.'
    }

    if ([string]::IsNullOrWhiteSpace((Get-DotEnvValue -Contents $environmentContents -Key 'APP_KEY'))) {
        Invoke-SetupStep -Label 'Generate the Laravel application key' -Command 'php' -Arguments @('artisan', 'key:generate', '--no-interaction')
    } else {
        Write-Host 'Laravel application key already exists; preserving it.'
    }

    $environmentContents = Get-Content -Raw -LiteralPath $environmentFile
    $dbConnection = Get-DotEnvValue -Contents $environmentContents -Key 'DB_CONNECTION'
    if ([string]::IsNullOrWhiteSpace($dbConnection)) {
        $dbConnection = 'sqlite'
    }
    if ($dbConnection -eq 'sqlite') {
        $sqliteFile = Join-Path $projectDirectory 'database/database.sqlite'
        if (-not (Test-Path -LiteralPath $sqliteFile)) {
            New-Item -ItemType File -Path $sqliteFile -Force | Out-Null
            Write-Host 'Created the SQLite database file.'
        }
    }

    Invoke-SetupStep -Label 'Install frontend dependencies' -Command 'npm' -Arguments @('ci', '--ignore-scripts')
    Invoke-SetupStep -Label 'Run migrations and seed local demo data' -Command 'php' -Arguments @('artisan', 'migrate', '--seed', '--no-interaction')
    Invoke-SetupStep -Label 'Build production frontend assets' -Command 'npm' -Arguments @('run', 'build')
    Invoke-SetupStep -Label 'Start Besu, generate local keys, and deploy the anchor contract' -Command 'powershell' -Arguments @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', 'config/besu/setup.ps1')

    if (-not (Test-Path -LiteralPath $besuEnvironmentFile)) {
        throw 'Besu setup completed without producing storage/app/private/besu/.env.besu.'
    }

    Merge-DotEnvFile -TargetPath $environmentFile -SourcePath $besuEnvironmentFile
    Invoke-SetupStep -Label 'Clear cached Laravel configuration' -Command 'php' -Arguments @('artisan', 'config:clear', '--no-interaction')

    Write-Host "`nSetup complete. Laravel, the local database, and Besu are ready."
    Write-Host 'Run: php artisan serve'
    Write-Host 'Besu RPC: http://127.0.0.1:28545'
    Write-Host 'Web3Signer: http://127.0.0.1:29000'
} finally {
    Pop-Location
}
