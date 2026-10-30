$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
Set-Location $PSScriptRoot
$raw = & docker compose config --format json
if ($LASTEXITCODE -ne 0) { throw 'Compose validation failed.' }
$config = $raw | ConvertFrom-Json
$names = @('validator1','validator2','validator3','validator4','rpc')
if (@(Compare-Object ($config.services.PSObject.Properties.Name | Sort-Object) ($names | Sort-Object)).Count) { throw 'Incorrect node topology.' }
$publications = 0
foreach ($name in $names) {
    $service = $config.services.$name
    if ($service.image -ne 'hyperledger/besu:26.10.2') { throw 'Unpinned/unexpected Besu version.' }
    if ($service.PSObject.Properties.Name -contains 'ports') {
        $publications += @($service.ports).Count
        if ($name -ne 'rpc' -or $service.ports[0].host_ip -ne '127.0.0.1' -or $service.ports[0].published -ne '18545') { throw 'Unsafe port publication.' }
    }
    $keys = @($service.volumes | Where-Object { $_.target -eq '/keys' })
    if ($keys.Count -ne 1 -or !$keys[0].read_only -or (Split-Path $keys[0].source -Leaf) -ne $name) { throw 'Node identity isolation failed.' }
    if (@($service.volumes | Where-Object { $_.target -eq '/network' }).Count) { throw 'Shared state directory exposes other node keys.' }
    if (@($service.volumes | Where-Object { $_.target -eq '/var/lib/besu' -and $_.type -eq 'volume' }).Count -ne 1) { throw 'Missing persistent data volume.' }
}
if ($publications -ne 1 -or !$config.networks.besu.internal) { throw 'Network isolation failed.' }
$genesis = Get-Content ./qbft-config.json -Raw | ConvertFrom-Json
if ($genesis.blockchain.nodes.count -ne 4 -or $genesis.genesis.config.chainId -ne 20261030 -or !$genesis.genesis.config.zeroBaseFee) { throw 'Incorrect QBFT genesis parameters.' }
foreach ($file in Get-ChildItem *.ps1) {
    $parseErrors = $null; $tokens = $null
    [System.Management.Automation.Language.Parser]::ParseFile($file.FullName, [ref]$tokens, [ref]$parseErrors) | Out-Null
    if ($parseErrors.Count) { throw $parseErrors }
}
Write-Output 'PASS: five pinned nodes, four genesis validators, loopback-only RPC, isolated identities/network, persistent volumes and script syntax.'
