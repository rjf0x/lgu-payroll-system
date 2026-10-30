# BESU-01 - Private Blockchain Network

Date: 2026-10-30 (America/Los_Angeles). Authorized scope: next Besu phase only,
after inspection of BESU-00 and the separate integration plan.

## Implemented

An isolated Docker Compose project with four QBFT validators and one additional
non-validator RPC node. Besu is pinned to 26.10.2, chain ID is 20261030
(`0x13528a6`), block period is two seconds and every node uses zero minimum gas
price and zero base fee. London/Shanghai are active at genesis. No prefunded
accounts or confidential application data are in genesis.

Local node permissioning allows only five generated identities. Static peers use
an internal Docker subnet; validator ports are not published. RPC alone has a
second bridge for host forwarding to `127.0.0.1:18545`. Host port 8545 was already
occupied by `besu-integrity-lab`; that container and its data were not modified.
Only ETH, NET, WEB3 and QBFT APIs are enabled. Browser CORS remains disabled.

Initialization generates fresh keys into ignored state and refuses to overwrite
existing state. Each running node mounts only its own keys, shared public network
configuration and its own persistent named data volume. Real RPC readiness tests
replace the image's PID-file health check, which depends on its entrypoint.

## Important files changed

- `blockchain/besu/compose.yaml`, `node.toml`, `qbft-config.json`: network and genesis.
- `blockchain/besu/Initialize-Network.ps1`: protected one-time initialization.
- `blockchain/besu/Test-Configuration.ps1`: automated structural/security checks.
- `blockchain/besu/Test-Network.ps1`: real node acceptance checks.
- `blockchain/besu/.gitignore`, `README.md`: secret exclusions and operations.
- This handoff and development, requirements, decisions and testing records.

No application source, dependency, database, payroll formula, access policy or
snapshot was changed. No Git commit or push was made.

## Blockchain status

**Real local network running and tested.** Five nodes report
`besu/v26.10.2/linux-x86_64/openjdk-java-25`, chain 20261030 and four peers each.
The RPC validator set matches the four generated validator addresses. Logs show
each validator producing blocks. Initial passing test observed height 9 -> 17;
after full Compose shutdown/recreation, height advanced 26 -> 36.

The fixed block at height 5 retained hash
`0x02a796fd5b237820bab99689ddea3a025d34be1962f5a486ceee154a9d422e6d`
across shutdown/restart. This is a recorded real **block** hash, not a transaction
or payroll anchoring claim. There is no registry, signer, application connection
or payroll transaction in this phase. Existing unavailable adapters remain bound.

## Tests performed

From repository root unless otherwise stated:

| Command/check | Actual result |
| --- | --- |
| `docker version`, `docker compose version`, `Get-PSDrive C,D` | Docker initially stopped; CLI 29.9.2, Compose 5.6.1; about 64.95 GB free on C: and 925.31 GB on D:. |
| `docker desktop start` | Linux engine started successfully. |
| `docker pull hyperledger/besu:26.10.2` | Successful; repository digest `sha256:bc599f5ef95a3d5d03f966cd2c1f7dfd7353834c84bc5651ffb4af884860a2c7`. |
| `docker compose -f blockchain/besu/compose.yaml config --quiet` | Passed. |
| `powershell -NoProfile -File blockchain/besu/Test-Configuration.ps1` | Passed five-node topology, version, loopback publication, key isolation, internal network, volume, genesis and syntax checks. |
| `powershell -NoProfile -File blockchain/besu/Initialize-Network.ps1` | Passed after entrypoint fix; generated four validators plus distinct RPC identity. Repeated invocation correctly exits nonzero and preserves identities. |
| `docker compose -f blockchain/besu/compose.yaml up -d` | All five nodes started. |
| `powershell -NoProfile -File blockchain/besu/Test-Network.ps1` | Passed real client version, chain ID, peer counts, exact validator membership, gas price, base fee and block advancement on every required node. |
| `docker compose -f blockchain/besu/compose.yaml logs --no-color` (per-validator production checks) | All four validators contain `Produced #` entries. |
| `docker compose -f blockchain/besu/compose.yaml down`, then `up -d`, then network test | Passed; all five data volumes retained, block 5 hash unchanged, consensus resumed and height advanced 26 -> 36. |
| `git check-ignore` for active key and diagnostic state paths | Both ignored. |
| `git diff --check` | Passed. |

Tests exercised `web3_clientVersion`, `eth_chainId`, `net_peerCount`,
`eth_blockNumber`, `qbft_getValidatorsByBlockNumber`, `eth_gasPrice` and
`eth_getBlockByNumber`. No blockchain doubles were used. PHP tests were not rerun
because no application code/configuration/dependencies changed.

Initial failures were corrected: the image root entrypoint executes the operator
command twice; direct binary invocation fixes it. Empty CORS arrays are rejected,
so the default disabled setting is used. The internal-only RPC bridge did not
publish a usable host endpoint; RPC now also joins a forwarding bridge. An early
probe reached the existing single-node lab on 8545 and rejected its validator set;
18545 isolates the new network. Startup/recreation probes initially hit unready
RPC/peer counts; readiness now waits for all five nodes to have four peers.

## Manual verification

See [network operations](../blockchain/besu/README.md). Current state is already
initialized: do **not** initialize again. From `blockchain/besu/`:

```powershell
docker compose up -d
powershell -NoProfile -File ./Test-Configuration.ps1
powershell -NoProfile -File ./Test-Network.ps1
docker compose down
```

`down` retains keys and chain data; never use `down -v` for routine shutdown.
The network is left running following successful validation.

## Issues and limits

- Single-host prototype with root containers, trusted-local unauthenticated RPC
  and QBFT inspection APIs; not a production governance/security design.
- Generated keys and failed-initialization diagnostics remain ignored local
  state. Do not publish them. Backup/restore and host compromise are not tested.
- Runtime is configured for Shanghai; future contracts must compile for that EVM
  unless a later fork is explicitly configured. Upgrades need separate validation.
- This phase demonstrates a genuine chain, not payroll anchoring or integrity
  verification. Those features and contract deployment remain incomplete.
- Existing client-policy, real-payroll and earlier audit limitations remain.

Official references consulted: [QBFT tutorial](https://docs.besu-eth.org/private-networks/tutorials/qbft),
[free gas configuration](https://docs.besu-eth.org/private-networks/how-to/configure/free-gas),
[26.10.2 release](https://github.com/besu-eth/besu/releases/tag/26.10.2).

## Next phase and final status

**BESU-02 - Smart Contract Development** is next and has not been implemented.

**STOPPED - Awaiting authorization to continue.**
