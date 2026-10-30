# BESU-01 audit - Private Blockchain Network

Date: 2026-10-30 (America/Los_Angeles).
Scope: current BESU-01 sources and running five-node chain, against the separate
integration plan. Audit only: no implementation fixes or BESU-02 work.

## Verdict

**All six BESU-01 acceptance criteria pass for the documented single-host
development deployment.** No current network-functionality defect or application
regression was confirmed. One low-severity automated-test defect is confirmed;
host key permissions and trusted-local RPC remain security limitations. This is
not production acceptance or evidence of application anchoring.

## Findings

### F1 - Low: configuration test succeeds without QBFT configuration

Location: `blockchain/besu/Test-Configuration.ps1:24`.

The genesis assertion checks generation count, chain ID and zero base fee, but
does not check `genesis.config.qbft`. It therefore prints a successful QBFT-related
configuration result even if the entire consensus configuration has been removed.

Reproduced without modifying implementation files: copy `compose.yaml`,
`Test-Configuration.ps1` and `qbft-config.json` to the ignored
`.local/besu-01-config-mutation/` directory; remove the copied JSON object's
`genesis.config.qbft` property and run its copied test. It exits 0 and prints PASS.

Impact: this structural test can miss a broken future genesis edit. The checked-in
genesis does contain valid QBFT configuration and the real network test passes;
the live test provides an independent safeguard. Suggested correction, not applied:
assert the QBFT object's required parameters and reject missing/invalid consensus
configuration. Add a negative fixture covering the reproduced omission.

### H1 - Medium security hardening: private keys inherit broad host ACLs

Location: `blockchain/besu/Initialize-Network.ps1:7` and its subsequent key writes.

The initializer does not restrict host permissions on its generated secret files.
`Get-Acl blockchain/besu/state/rpc/key` shows inherited `Authenticated Users:
Modify` and `Users: ReadAndExecute`, in addition to SYSTEM/administrators. This
permits other local users to read or change that node key; inherited directory
permissions apply to the same state tree. Git exclusion and read-only container
mounts do not restrict host access. No private key contents were printed.

The operations guide explicitly trusts local host users and Docker administrators,
so this is an existing development trust boundary, not a demonstrated remote leak
or a new acceptance blocker for that stated environment. Before using a shared
host, restrict the state tree to the operator and required system principals and
verify both existing and newly created files. No ACLs were changed in this audit.

### Other documented security limits

- All five containers run as UID 0, although none is privileged and each mounts
  only its own identity. No host Docker socket is mounted.
- RPC has no authentication/TLS and enables the full QBFT namespace. That
  namespace includes validator-vote mutation methods, not only inspection. Host
  publication is loopback-only; Docker access and local callers are trusted.
  ADMIN/DEBUG/PERM are disabled, but that does not make QBFT read-only. See the
  official [QBFT methods](https://docs.besu-eth.org/private-networks/reference/api/qbft).
- Five validators/RPC processes on one host do not provide independent-host fault
  tolerance. Production key custody, backups, governance and monitoring remain
  outside this development deployment.
- The image is version-tag pinned, not digest-enforced. Its observed digest is
  recorded in the handoff; registry tag replacement is not checked by tests.

## Acceptance criteria

| BESU-01 criterion | Result | Current audit evidence |
| --- | --- | --- |
| All required nodes start | Pass | Four validators and one RPC node running; actual RPC on all nodes; zero container restarts. |
| Validators participate in consensus | Pass | Four generated validator addresses match membership. `qbft_getSignerMetrics` reports 25 proposed blocks each over the latest 100-block window. |
| Blocks are produced | Pass | Permanent test observes height 542 -> 550; independent probes show continuing progress. |
| RPC endpoint responds | Pass | Host loopback `127.0.0.1:18545` and internal RPC checks succeed. |
| Chain ID is correct | Pass | All five nodes report 20261030 (`0x13528a6`). |
| Peer connectivity is confirmed | Pass | All five nodes report four peers. A fresh unallowlisted node remains at zero peers/block 0. |

Additional functionality checks: all five nodes agree on fixed block `0x261`, hash
`0x24b2e8f625966fac1bb5bd86ddec2b81358e83f4cdea27e4e6c7580373756880`;
their validator lists agree. Gas price and base fee are zero. Historical block 5
still matches the prior shutdown/restart evidence. Shutdown/restart itself was
not repeated during this audit; its earlier recorded validation is retained.

## Security verification

- `admin_peers`, `debug_metrics`, `perm_getNodesAllowlist`: error -32604 (method
  not enabled); `personal_listAccounts`: -32601 (method not found).
- Request with `Host: untrusted.invalid`: HTTP 403.
- JSON-RPC request with `Origin: https://untrusted.invalid`: HTTP 403 CORS rejection.
- Docker inspection: only `127.0.0.1:18545` published, internal validator network,
  separate RPC forwarding bridge, read-only per-node key mounts, no privileged
  containers. These controls do not replace host-user/Docker access restrictions.
- Negative P2P probe: an ephemeral `besu-01-audit-outsider` container used the same
  genesis/static peers, a fresh in-container identity and no local permissioning
  on its own side. It stayed at zero peers/block 0 while original nodes stayed at
  four peers. The audit container was removed afterward; no real node permissions,
  validator votes or keys were changed.
- Active key paths and audit scratch paths are ignored by Git. Tracked/unignored
  phase files contain configuration and code, without generated private keys.

Official guidance reviewed: [local permissioning](https://docs.besu-eth.org/private-networks/how-to/use-local-permissioning)
and [JSON-RPC security](https://docs.besu-eth.org/public-networks/how-to/use-besu-api/json-rpc).

## Tests and integration compatibility

| Command | Result |
| --- | --- |
| `powershell -NoProfile -File blockchain/besu/Test-Configuration.ps1` | Pass on current source. F1 reproduced separately in an isolated copy. |
| `powershell -NoProfile -File blockchain/besu/Test-Network.ps1` | Pass: real five-node chain, correct membership/client/chain/peers, zero fees and advancing blocks. |
| `powershell -NoProfile -File .local/besu-01-audit.ps1` | Pass after correcting audit harness assumptions about PowerShell array member access, disabled-method codes and expected CORS rejection. Checks recent signers, fixed-block agreement, disabled APIs, Host/CORS, history and container state. Ignored scratch probe, not a permanent test change. |
| From `application/`: `php vendor/phpunit/phpunit/phpunit --colors=never --filter 'Payroll(Anchoring\|Verification\|Correction\|Workflow)Test'` | 133 tests / 2266 assertions passed, 02:09.866, 72 MB; PHP 8.5.12 / PHPUnit 12.5.40. Use ordinary regex alternation `|` inside the quoted filter when executing. |
| `git diff --name-only -- application` | Empty; no application changes. |
| `git diff --check` | Pass; Git reports existing LF/CRLF normalization notices only. |

Application bindings remain `UnavailableBlockchainSubmitter` and
`UnavailableBlockchainReader` at `application/app/Providers/AppServiceProvider.php:29`.
Existing finalization, approval separation, snapshots, corrections and controlled
blockchain tests remain compatible. Those controlled responses do not demonstrate
an application-to-Besu transaction. No contract/signing/application-connectivity
requirement is falsely counted as BESU-01 acceptance. Shanghai compatibility is
documented; later contract compilation must target the configured EVM or explicitly
introduce and validate a later fork.

Permanent network tests do not assert per-validator recent proposal counts or a
shared fixed-block hash. Independent audit probes supply that evidence here; a
future regression could benefit from preserving those assertions. Backup/restore,
validator outage tolerance, hostile-host access and production capacity were not
tested. No transaction was submitted during this audit.

## Changes and stopping point

Documentation only: this audit and progress/requirements/testing records. Ignored
audit probes were created; temporary outsider container was removed. Implementation
and permanent tests are unchanged; F1/H1 were reported without fixes. The five-node
network remains running, and the pre-existing Besu lab remains untouched.

**STOPPED - BESU-02 not implemented.**
