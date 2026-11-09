param(
    [string] $RpcUrl = 'http://127.0.0.1:8545'
)

$ErrorActionPreference = 'Stop'
$projectDirectory = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$outputDirectory = Join-Path $projectDirectory 'storage/app/private/besu/registry-tests'
New-Item -ItemType Directory -Force -Path $outputDirectory | Out-Null

& docker run --rm --mount "type=bind,source=$projectDirectory,target=/source,readonly" --mount "type=bind,source=$outputDirectory,target=/output" ethereum/solc:0.8.30 --base-path /source --evm-version london --optimize --bin --abi --overwrite -o /output /source/tests/PayrollIntegrityRegistryTest.sol
if ($LASTEXITCODE -ne 0) {
    throw 'Registry compilation failed.'
}

function Invoke-RegistryTestRpc {
    param([string] $Method, [object[]] $Parameters)
    $body = @{ jsonrpc = '2.0'; id = 1; method = $Method; params = @($Parameters) } | ConvertTo-Json -Depth 8 -Compress
    $response = Invoke-RestMethod -Uri $RpcUrl -Method Post -ContentType 'application/json' -Body $body -TimeoutSec 30
    if ($response.error) {
        throw "$Method failed: $($response.error | ConvertTo-Json -Depth 8 -Compress)"
    }
    if ($response.jsonrpc -ne '2.0' -or $response.id -ne 1 -or $null -eq $response.result) {
        throw 'Invalid RPC response.'
    }
    return $response.result
}

$bytecode = (Get-Content -LiteralPath (Join-Path $outputDirectory 'PayrollIntegrityRegistryTest.bin') -Raw).Trim()
if ($bytecode -notmatch '^[0-9a-fA-F]+$') {
    throw 'Missing test harness bytecode.'
}

$scenarios = @(
    'Stores digest, schema, signer, timestamp and missing-record evidence',
    'Rejects unauthorized registration, permission grants and cross-organization writes',
    'Preserves original registration on duplicate or conflicting submissions',
    'Separates organizations, references and revisions with explicit ABI key encoding',
    'Supports registrar rotation and preserves historical signer evidence',
    'Rejects invalid revision, digest, schema and registrar; accepts max revision',
    'Enforces organization and UUID validation boundaries'
)

for ($index = 0; $index -lt $scenarios.Count; $index++) {
    $argument = $index.ToString('x').PadLeft(64, '0')
    $simulation = @{ data = "0x$bytecode$argument"; gas = '0x989680' }
    # Contract-creation eth_call runs constructor assertions without broadcasting or persisting state.
    $result = Invoke-RegistryTestRpc -Method 'eth_call' -Parameters @($simulation, 'latest')
    if ($result -notmatch '^0x[0-9a-fA-F]+$') {
        throw "Test returned invalid creation output: $($scenarios[$index])"
    }
    Write-Output "PASS: $($scenarios[$index])"
}

Write-Output "$($scenarios.Count) registry scenarios passed. No transactions were broadcast. ABI and bytecode: $outputDirectory"
