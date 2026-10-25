$ErrorActionPreference = 'Stop'

$appDirectory = Join-Path (Split-Path -Parent $PSScriptRoot) 'application'
$overrides = @{
    APP_ENV = 'production'
    DB_CONNECTION = 'mysql'
    DB_DATABASE = 'must_not_touch'
    DB_URL = 'mysql://invalid:invalid@127.0.0.1:1/must_not_touch'
}
$previousValues = @{}
foreach ($key in $overrides.Keys) {
    $previousValues[$key] = [Environment]::GetEnvironmentVariable($key, 'Process')
}

Push-Location -LiteralPath $appDirectory
try {
    foreach ($key in $overrides.Keys) {
        [Environment]::SetEnvironmentVariable($key, $overrides[$key], 'Process')
    }

    # TestCase refuses non-memory configuration before RefreshDatabase can migrate.
    & php vendor/phpunit/phpunit/phpunit --colors=never
    if ($LASTEXITCODE -ne 0) {
        throw 'The test suite failed under conflicting inherited configuration.'
    }
}
finally {
    foreach ($key in $overrides.Keys) {
        [Environment]::SetEnvironmentVariable($key, $previousValues[$key], 'Process')
    }
    Pop-Location
}
