param([switch]$Live)

$ErrorActionPreference = 'Stop'
$repository = Split-Path $PSScriptRoot -Parent
$previousLive = $env:BESU_LIVE_TEST
$previousDemo = $env:BESU_LIVE_DEMO
$previousRecovery = $env:BESU_LIVE_RECOVERY_REF
try {
    # Default checks never opt into a real chain write, even with inherited flags.
    $env:BESU_LIVE_TEST = '0'
    $env:BESU_LIVE_DEMO = '0'
    Remove-Item Env:BESU_LIVE_RECOVERY_REF -ErrorAction SilentlyContinue
    Push-Location (Join-Path $repository 'application')
    try {
        & php vendor/bin/phpunit --filter 'BesuAnchoringTest|BesuJournalRecoveryTest|PayrollCorrectionTest|BesuIntegrityUiTest|BesuTransactionVerifierTest'
        if ($LASTEXITCODE -ne 0) { throw 'Controlled Besu regression checks failed.' }
        if ($Live) {
            Write-Host 'Live A/B/C/D demonstration: fictional in-memory payroll; up to two permanent development-chain commitments.'
            $env:BESU_LIVE_DEMO = '1'
            & php vendor/bin/phpunit --filter test_live_besu08_outage_recovery_corrected_versions_and_tamper_detection
            if ($LASTEXITCODE -ne 0) { throw 'Live demonstration failed or its network prerequisite is blocked.' }
        }
    } finally { Pop-Location }
    Push-Location (Join-Path $repository 'blockchain/contracts')
    try {
        & node --test test/backend-bridge.test.mjs
        if ($LASTEXITCODE -ne 0) { throw 'Offline bridge checks failed.' }
    } finally { Pop-Location }
    Write-Host 'Selected checks passed. This does not clear known audit defects; read BESU_08_HANDOFF.md.'
} finally {
    $env:BESU_LIVE_TEST = $previousLive
    $env:BESU_LIVE_DEMO = $previousDemo
    $env:BESU_LIVE_RECOVERY_REF = $previousRecovery
}
