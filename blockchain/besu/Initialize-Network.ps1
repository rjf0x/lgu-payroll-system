$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
Set-Location $PSScriptRoot
$image = 'hyperledger/besu:26.10.2'
$state = Join-Path $PSScriptRoot 'state'
if (Test-Path $state) { throw 'state already exists. Reuse it; initialization never overwrites node identities.' }
New-Item -ItemType Directory -Path $state | Out-Null
function Invoke-Besu([string[]] $Arguments) {
    & docker run --rm --user 0:0 --entrypoint /opt/besu/bin/besu --mount "type=bind,source=$PSScriptRoot,target=/work" $image @Arguments
    if ($LASTEXITCODE -ne 0) { throw 'Besu initialization failed. Preserve state for diagnosis.' }
}
Invoke-Besu @('operator','generate-blockchain-config','--config-file=/work/qbft-config.json','--to=/work/state/generated','--private-key-file-name=key')
Copy-Item "$state/generated/genesis.json" "$state/genesis.json"
$keys = @(Get-ChildItem "$state/generated/keys" -Directory | Sort-Object Name)
if ($keys.Count -ne 4) { throw 'Expected four validator identities.' }
for ($i = 0; $i -lt 4; $i++) {
    New-Item -ItemType Directory "$state/validator$($i+1)" | Out-Null
    Copy-Item "$($keys[$i].FullName)/key*" "$state/validator$($i+1)/"
}
New-Item -ItemType Directory "$state/rpc" | Out-Null
Invoke-Besu @('--data-path=/work/state/rpc','public-key','export','--to=/work/state/rpc/key.pub')
$names = @('validator1','validator2','validator3','validator4','rpc')
$enodes = @($names | ForEach-Object {
    $publicKey = (Get-Content "$state/$_/key.pub" -Raw).Trim() -replace '^0x',''
    if ($publicKey -notmatch '^[0-9a-fA-F]{128}$') { throw 'Invalid public node key.' }
    $ip = '172.29.61.' + (10 + [array]::IndexOf($names, $_))
    "enode://${publicKey}@${ip}:30303"
})
$enodes | ConvertTo-Json | Set-Content "$state/static-nodes.json" -Encoding ascii
('nodes-allowlist=[' + (($enodes | ForEach-Object { '"' + $_ + '"' }) -join ',') + ']') | Set-Content "$state/permissions_config.toml" -Encoding ascii
Write-Output 'Initialized four validators and a separate RPC identity. Keys are in ignored state; do not share them.'
