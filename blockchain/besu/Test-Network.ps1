param([int] $TimeoutSeconds = 180)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
Set-Location $PSScriptRoot
function Rpc([string] $Node, [string] $Method, [object[]] $Params = @()) {
    $body = @{jsonrpc='2.0'; id=1; method=$Method; params=$Params} | ConvertTo-Json -Compress -Depth 5
    if ($Node -eq 'host') {
        $reply = Invoke-RestMethod http://127.0.0.1:18545 -Method Post -ContentType application/json -Body $body -TimeoutSec 5
    } else {
        # HTTP/1.0 avoids chunked responses. Bash TCP is available in the pinned
        # image; no curl installation or additional probe container is required.
        $request = "POST / HTTP/1.0`r`nHost: ${Node}`r`nContent-Type: application/json`r`nContent-Length: $([Text.Encoding]::UTF8.GetByteCount($body))`r`nConnection: close`r`n`r`n$body"
        $raw = $request | & docker compose exec -T rpc timeout 5 bash -c 'exec 3<>/dev/tcp/$1/8545; cat >&3; cat <&3' probe $Node
        if ($LASTEXITCODE -ne 0) { throw "RPC transport failed: $Node" }
        $parts = (($raw -join "`n") -split "`n`n", 2)
        if ($parts.Count -ne 2 -or $parts[0] -notmatch '^HTTP/1\.[01] 200') { throw "Invalid HTTP response from $Node" }
        $reply = $parts[1] | ConvertFrom-Json
    }
    if ($reply.PSObject.Properties.Name -contains 'error') { throw ($reply.error | ConvertTo-Json -Compress) }
    return $reply.result
}
function Height($Value) { [Convert]::ToInt64(($Value -replace '^0x',''),16) }
$deadline = (Get-Date).AddSeconds($TimeoutSeconds)
do {
    try {
        $start = Height (Rpc host eth_blockNumber)
        if ($start -gt 0 -and (Height (Rpc host net_peerCount)) -eq 4) {
            $ready = $true
            foreach ($candidate in @('validator1','validator2','validator3','validator4','rpc')) {
                if ((Height (Rpc $candidate net_peerCount)) -ne 4) { $ready = $false }
            }
            if ($ready) { break }
        }
    } catch { }
    Start-Sleep -Seconds 3
} while ((Get-Date) -lt $deadline)
if ((Get-Date) -ge $deadline) { throw 'Network did not produce blocks before timeout.' }
$validators = @(Rpc host qbft_getValidatorsByBlockNumber @('latest'))
if ($validators.Count -ne 4 -or @($validators | Select-Object -Unique).Count -ne 4) { throw 'Expected four unique validators.' }
$expected = @(Get-ChildItem "$PSScriptRoot/state/generated/keys" -Directory | ForEach-Object { $_.Name.ToLowerInvariant() })
if (@(Compare-Object ($validators | Sort-Object) ($expected | Sort-Object)).Count -ne 0) { throw 'Validator set differs from generated identities.' }
foreach ($node in @('host','validator1','validator2','validator3','validator4','rpc')) {
    $client = Rpc $node web3_clientVersion
    if ($client -notmatch 'besu/v26.10.2') { throw "Unexpected client on $node : $client" }
    if ((Height (Rpc $node eth_chainId)) -ne 20261030) { throw "Wrong chain on $node" }
    $peers = Height (Rpc $node net_peerCount)
    if ($peers -ne 4) { throw "Expected four peers on $node; got $peers" }
    if ((Height (Rpc $node eth_gasPrice)) -ne 0) { throw "Nonzero gas price on $node" }
    $block = Rpc $node eth_getBlockByNumber @('latest', $false)
    if ((Height $block.baseFeePerGas) -ne 0) { throw "Nonzero base fee on $node" }
    Write-Output "$node : chain=20261030 peers=$peers block=$($block.number) client=$client"
}
Start-Sleep -Seconds 6
$end = Height (Rpc host eth_blockNumber)
if ($end -le $start) { throw 'Block height did not advance.' }
Write-Output "PASS: four validators, five connected nodes, zero gas, blocks advanced $start -> $end."
