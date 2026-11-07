param()

$ErrorActionPreference = 'Stop'
$projectDirectory = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$stateDirectory = Join-Path $projectDirectory 'storage/app/private/besu'
$composeFile = Join-Path $PSScriptRoot 'compose.yaml'

function Invoke-Docker {
    param([string[]] $Arguments)
    & docker @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Docker command failed: $($Arguments[0])"
    }
}

function Invoke-BesuRpc {
    param([string] $Url, [string] $Method, [object[]] $Parameters = @())
    $body = @{ jsonrpc = '2.0'; id = 1; method = $Method; params = @($Parameters) } | ConvertTo-Json -Depth 12 -Compress
    $response = Invoke-RestMethod -Uri $Url -Method Post -ContentType 'application/json' -Body $body -TimeoutSec 10
    if ($response.error) {
        throw "RPC operation failed: $Method ($($response.error.code))"
    }
    return $response.result
}

function Wait-BesuRpc {
    param([string] $Url, [string] $Method)
    for ($attempt = 0; $attempt -lt 45; $attempt++) {
        try {
            return Invoke-BesuRpc -Url $Url -Method $Method
        } catch {
            Start-Sleep -Seconds 2
        }
    }
    throw "RPC did not become available: $Url"
}

Invoke-Docker -Arguments @('info', '--format', '{{.ServerVersion}}')
New-Item -ItemType Directory -Force -Path $stateDirectory | Out-Null
$genesisFile = Join-Path $stateDirectory 'network/genesis.json'

if (-not (Test-Path -LiteralPath $genesisFile)) {
    if (Test-Path -LiteralPath (Join-Path $stateDirectory 'network')) {
        throw 'Incomplete Besu state exists. Inspect it before generating a different chain; this script never deletes chain data.'
    }
    Invoke-Docker -Arguments @('run', '--rm', '--user', '0:0', '--entrypoint', '/opt/besu/bin/besu', '--mount', "type=bind,source=$PSScriptRoot,target=/input,readonly", '--mount', "type=bind,source=$stateDirectory,target=/state", 'hyperledger/besu:26.10.2', 'operator', 'generate-blockchain-config', '--config-file=/input/qbft.json', '--to=/state/network', '--private-key-file-name=key')
}
if (-not (Test-Path -LiteralPath (Join-Path $stateDirectory 'network/permissions.toml'))) {
    $nodeKeys = @(Get-ChildItem -LiteralPath (Join-Path $stateDirectory 'network/keys') -Directory | Sort-Object Name)
    if ($nodeKeys.Count -ne 4) {
        throw 'Expected four generated QBFT validator keys.'
    }
    $enodes = @()
    for ($index = 0; $index -lt 4; $index++) {
        $validatorDirectory = Join-Path $stateDirectory "validator$($index + 1)"
        New-Item -ItemType Directory -Force -Path $validatorDirectory | Out-Null
        $validatorKey = Join-Path $validatorDirectory 'key'
        if (-not (Test-Path -LiteralPath $validatorKey)) {
            Copy-Item -LiteralPath (Join-Path $nodeKeys[$index].FullName 'key') -Destination $validatorKey
        }
        $publicKey = (Get-Content -LiteralPath (Join-Path $nodeKeys[$index].FullName 'key.pub') -Raw).Trim() -replace '^0x', ''
        $enodes += "enode://$publicKey@172.29.74.$($index + 11):30303"
    }
    $nodesJson = ConvertTo-Json -InputObject @($enodes)
    for ($index = 1; $index -le 4; $index++) {
        $nodesJson | Set-Content -LiteralPath (Join-Path $stateDirectory "validator$index/static-nodes.json") -Encoding ascii
    }
    ('nodes-allowlist=[' + (($enodes | ForEach-Object { '"' + $_ + '"' }) -join ',') + ']') | Set-Content -LiteralPath (Join-Path $stateDirectory 'network/permissions.toml') -Encoding ascii
}

$signerDirectory = Join-Path $stateDirectory 'signer'
New-Item -ItemType Directory -Force -Path $signerDirectory | Out-Null
$signerFile = Join-Path $signerDirectory 'payroll.yaml'
if (-not (Test-Path -LiteralPath $signerFile)) {
    $keyBytes = New-Object byte[] 32
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $generator.GetBytes($keyBytes)
    } finally {
        $generator.Dispose()
    }
    $keyBytes[0] = ($keyBytes[0] -band 0x7f) -bor 0x01
    $privateKey = -join ($keyBytes | ForEach-Object { $_.ToString('x2') })
    "type: file-raw`nkeyType: SECP256K1`nprivateKey: '$privateKey'" | Set-Content -LiteralPath $signerFile -Encoding ascii
    [Array]::Clear($keyBytes, 0, $keyBytes.Length)
    $privateKey = $null
}

Invoke-Docker -Arguments @('compose', '-f', $composeFile, 'up', '-d')
$chainId = Wait-BesuRpc -Url 'http://127.0.0.1:28545' -Method 'eth_chainId'
if ($chainId -ne '0x539') {
    throw 'Unexpected chain ID; expected 1337.'
}
$accounts = @(Wait-BesuRpc -Url 'http://127.0.0.1:29000' -Method 'eth_accounts')
if ($accounts.Count -ne 1 -or $accounts[0] -notmatch '^0x[0-9a-fA-F]{40}$') {
    throw 'Expected one valid Web3Signer account.'
}
$sender = $accounts[0]
$contractDirectory = Join-Path $stateDirectory 'contract'
New-Item -ItemType Directory -Force -Path $contractDirectory | Out-Null
$deploymentFile = Join-Path $stateDirectory 'deployment.json'

if (-not (Test-Path -LiteralPath $deploymentFile)) {
    $sourceDirectory = Join-Path $projectDirectory 'app/Services/Blockchain'
    Invoke-Docker -Arguments @('run', '--rm', '--mount', "type=bind,source=$sourceDirectory,target=/source,readonly", '--mount', "type=bind,source=$contractDirectory,target=/output", 'ethereum/solc:0.8.30', '--evm-version', 'london', '--bin', '--abi', '--overwrite', '-o', '/output', '/source/PayrollAnchor.sol')
    $bytecode = (Get-Content -LiteralPath (Join-Path $contractDirectory 'PayrollAnchor.bin') -Raw).Trim()
    $transaction = @{ from = $sender; data = "0x$bytecode"; gas = '0x1e8480'; gasPrice = '0x0'; value = '0x0' }
    $transactionId = Invoke-BesuRpc -Url 'http://127.0.0.1:29000' -Method 'eth_sendTransaction' -Parameters @($transaction)
    @{ transaction_id = $transactionId; sender = $sender; chain_id = 1337 } | ConvertTo-Json | Set-Content -LiteralPath $deploymentFile -Encoding ascii
}
$deployment = Get-Content -LiteralPath $deploymentFile -Raw | ConvertFrom-Json
if ($deployment.sender -ne $sender -or $deployment.chain_id -ne 1337) {
    throw 'Saved deployment does not belong to the configured signer and chain. Inspect the existing state before continuing.'
}
$receipt = $null
for ($attempt = 0; $attempt -lt 45; $attempt++) {
    $receipt = Invoke-BesuRpc -Url 'http://127.0.0.1:28545' -Method 'eth_getTransactionReceipt' -Parameters @($deployment.transaction_id)
    if ($null -ne $receipt) {
        break
    }
    Start-Sleep -Seconds 2
}
if ($null -eq $receipt -or $receipt.status -ne '0x1' -or -not $receipt.contractAddress) {
    throw 'Contract deployment is pending or failed. The transaction ID is saved; rerun to check it without resubmitting.'
}

@"
BLOCKCHAIN_DRIVER=besu
BLOCKCHAIN_NETWORK=besu:1337
BLOCKCHAIN_CONTRACT_ADDRESS=$($receipt.contractAddress)
BESU_RPC_URL=http://127.0.0.1:28545
BESU_SIGNER_URL=http://127.0.0.1:29000
BESU_CHAIN_ID=1337
BESU_FROM_ADDRESS=$sender
BESU_GAS=0x30d40
BESU_GAS_PRICE=0x0
"@ | Set-Content -LiteralPath (Join-Path $stateDirectory '.env.besu') -Encoding ascii

Write-Output "Besu chain 1337 is running with four QBFT validators. Contract: $($receipt.contractAddress)"
Write-Output "Public connection settings saved in $stateDirectory/.env.besu. Merge these entries into the application .env and run php artisan config:clear."
