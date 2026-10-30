# Local Besu QBFT network (BESU-01)

Run from this directory in PowerShell with Docker Desktop's Linux engine running:

```powershell
docker compose config --quiet
powershell -NoProfile -File ./Test-Configuration.ps1
powershell -NoProfile -File ./Initialize-Network.ps1
docker compose up -d
powershell -NoProfile -File ./Test-Network.ps1
docker compose down
```

Initialize once. Startup thereafter is `docker compose up -d`. `down` preserves
all five named data volumes and identities. Do not use `down -v` or delete `state`
to troubleshoot: both are needed to retain this chain. Initialization refuses to
overwrite existing state, including a partially failed initialization. Inspect a
failure before manually moving its state aside. No application environment change
is needed in this phase.

Chain ID **20261030** (`0x13528a6`), two-second QBFT blocks, four validators and
one non-validator RPC node, pinned Besu **26.10.2**. London and Shanghai are active
at genesis; zero base fee and minimum gas price zero support free development
transactions. There are no prefunded accounts, contract, writer keys or payroll
transactions in this phase.

Only `127.0.0.1:18545` is published. P2P and validator RPC remain
inside the isolated Docker network; host RPC exposes ETH, NET, WEB3 and QBFT for
local inspection, with no ADMIN, DEBUG, PERM or account-unlocking API. CORS uses
the default disabled setting. Local node permissioning permits exactly the five generated identities;
discovery is disabled and static peers use the dedicated `172.29.61.0/24` subnet.
If that subnet or port conflicts, resolve the conflict before starting this network.
Only the RPC node also joins a separate bridge for host port forwarding; validator
nodes remain on the internal network. Its P2P address is pinned to the internal
interface. Full sync needs one peer, appropriate for this five-node topology.

`state/` is ignored and contains private validator/node keys. Each running node
mounts only its own keys. Never commit, upload or print private keys. This is a
single-host development network, with no RPC authentication/TLS and containers
running as root for Docker Desktop bind-mount compatibility. Local host users and
Docker administrators are trusted. It is not a production deployment or a backup
strategy; do not expose the port or connect untrusted containers to its network.

The configuration test runs without downloading the image and checks topology,
port publication, key isolation, persistence, genesis parameters and script syntax.
The network test checks actual JSON-RPC client versions, chain ID, all node peer counts,
the four generated validator addresses, gas configuration and advancing blocks.
It fails on missing RPC, wrong chain, incomplete connectivity or stalled consensus.
It uses Bash TCP and HTTP/1.0 inside the pinned image for internal RPC probes. To check
persistence: record `eth_getBlockByNumber` at a fixed height, run `docker compose
down`, start again, rerun the test and compare the fixed block hash.

Configuration follows the official [QBFT tutorial](https://docs.besu-eth.org/private-networks/tutorials/qbft),
[free gas guidance](https://docs.besu-eth.org/private-networks/how-to/configure/free-gas)
and [Besu releases](https://github.com/besu-eth/besu/releases/tag/26.10.2).

Stop at BESU-01. Registry development/deployment is BESU-02; application RPC,
signing, anchoring and verification belong to later phases.

