# BESU-02 audit - Smart Contract Development

Date: 2026-10-30 (America/Los_Angeles). Scope: current registry, contract tooling,
published ABI, deployment evidence and integration compatibility. Audit only; no
implementation fixes, permanent-test changes or BESU-03 work.

## Verdict

**Implemented but needs validation.** All seven explicit BESU-02 acceptance
criteria pass against real Besu. No defect was confirmed in the exercised Solidity
commitment/access-control behavior. Two deployment-tooling defects are reproduced:
F1 medium (redirect bypass of the endpoint boundary) and F2 low (unchecked recorded
block metadata). Passing existing tests does not cover either case. Neither is a
claim that the existing deployment receipt or saved commitments are false.

## Findings

### F1 - Medium: HTTP redirects bypass the local RPC endpoint restriction

Location: `blockchain/contracts/scripts/runtime.mjs:9` through provider creation
at line 14. Related missing coverage: `test/tooling.test.mjs` endpoint test.

`connect()` checks only the initial URL. It creates an ethers `FetchRequest` with
a timeout but does not prohibit or validate redirects. The installed ethers 6.17.0
transport follows 301/302/307/308 redirects, preserving the request body. Its
redirect target checks allow other HTTP/HTTPS hosts; they do not enforce this
workspace's loopback/credential boundary.

Reproduction: `.local/besu-02-audit.mjs` starts an initial HTTP server on
127.0.0.1 and a second relay on 127.0.0.2, both using ephemeral ports. The second
URL is rejected when passed directly to `connect()`. Returning HTTP 307 from the
allowed first URL to that second URL nevertheless makes `connect()` succeed.
The relay forwards only allowlisted read methods to the actual Besu node, and the
returned genesis matches the deployed chain. Observed methods were eth_chainId,
web3_clientVersion and eth_getBlockByNumber. Both temporary servers close afterward.

No public host was contacted and no signed transaction was sent through the relay.
The implication for a redirect to an external host follows from the installed
transport implementation, not a performed public-network experiment. Subsequent
requests can traverse the same redirect, so the initial URL test alone does not
guarantee locally confined RPC traffic, including transaction submission traffic.
This does **not** expose private keys through the demonstrated read-only probe.

Recommended correction, not applied: reject redirects for this local-only tooling
or validate every actual destination before requests are sent. Add a redirect
regression test; validating only direct input URLs is insufficient. The current
direct BESU-01 endpoint did not redirect in the exercised path.

### F2 - Low: deployment verifier ignores recorded block metadata

Location: `blockchain/contracts/scripts/runtime.mjs:43` and receipt canonicality
check at line 47. The verifier checks the actual receipt against the canonical
chain, but never compares `record.blockNumber` or `record.blockHash` with that
receipt.

Reproduced using an in-memory copy of the real deployment manifest: change
blockNumber to -1 and blockHash to 32 zero bytes, then call verifyDeployment with
the real provider/current compilation. Verification still succeeds. The on-disk
manifest and real chain were not modified. Altered genesisHash/runtimeCodeHash
copies are correctly rejected, so the omission is specifically recorded block
metadata rather than a failure of every integrity check.

Impact: public deployment evidence can contain internally inconsistent block
references while tooling reports success. The contract/transaction/source checks
remain valid, so this does not manufacture a successful receipt or accept a
different contract. Suggested correction, not applied: compare recorded block
number/hash with the verified receipt and test mismatches explicitly.

## Acceptance criteria

| BESU-02 criterion | Result | Evidence |
| --- | --- | --- |
| Contract compiles | Pass | Pinned solc 0.8.38, warning-free compile in tests, Shanghai output and matching published ABI. |
| Contract deploys | Pass | Registry 0xDE556481A1DbF7a9bB1C152Ed574f122706dfFB6 remains deployed; current source/constructor input and runtime checks pass. |
| Authorized writer records a commitment | Pass | Real-node fixture test confirms exact SHA-256 bytes, writer, timestamp, existence and event. |
| Read-only lookup returns expected hash | Pass | Lookup is independently called by a non-writer and returns exact saved digest. |
| Unauthorized writes rejected | Pass | Call rejection and actual failed transaction receipt status 0; absent record remains absent. |
| Conflicting duplicate writes rejected | Pass | Actual status-0 receipt and unchanged original commitment; identical authorized retry preserves metadata without duplicate events. |
| Deployment transaction confirmed on Besu | Pass | Transaction 0x433615052af8b0cae4e2cd76f6c50f1677c21ae68c833367a27c8caf13ec19a3, canonical successful deployment block 1062, chain 20261030. |

Additional tests confirm controlled writer grants/revocations, administrator/writer
separation, zero input rejection, retained history and independent synthetic
correction references. These are contract version references, not application
correction-workflow integration.

## Security review

- Solidity write paths check authorization first; saved commitment mutation is
  impossible through the exposed API after creation. Identical retries retain
  the first writer/time and require current authorization.
- The contract has no external calls, transfers, delegatecall, selfdestruct,
  upgrade mechanism or unbounded iteration in write paths. No reentrancy path or
  related access bypass was confirmed by this review. This is not formal proof.
- On-chain types contain only bytes32 identifiers/digests, address, timestamp and
  existence. Tests use synthetic/random values. The contract cannot identify an
  incorrect hashing algorithm or prevent an authorized writer choosing a
  sensitive/poorly chosen identifier; callers remain responsible for privacy.
- Administrator is immutable and may authorize its own address as a writer. This
  is explicit control, not implicit write permission. Key loss/compromise,
  multisig governance and administrator rotation remain documented prototype
  limitations. No new requirement for governance was implemented in this audit.
- New signer keystores/password files remain Git-ignored and protected by operator/
  SYSTEM ACLs. Encryption/password colocation is development custody, not a
  hardware-backed or externally managed signing mechanism. Credentials were not
  printed or changed. Older BESU-01 validator-key ACL findings remain separate.
- Dependency audit reports zero known vulnerabilities, including the pinned tmp
  0.2.7 override. This does not replace source review or compiler assurance.
- Pending deployment intent is persisted before broadcast and uncertain sends
  stop. The normal reuse path was rerun without broadcasting. Interrupted-send,
  disk-failure and concurrent-process recovery were not fault-injection tested.

Reviewed official references:
[ethers JSON-RPC transport](https://docs.ethers.org/v6/api/providers/jsonrpc/) and
[Solidity security considerations](https://docs.soliditylang.org/en/latest/security-considerations.html).
F1 was confirmed against installed transport source and a running local probe.

## Tests performed

From `blockchain/contracts/`:

- `npm test`: **12 passed / 0 failed / 0 skipped**, 49.647 seconds. Contract cases
  submit actual successful/failed transactions to isolated fixture registries on
  real Besu; the recorded main registry is not changed by those tests.
- `npm run verify`: passes real genesis, source/constructor input, canonical receipt,
  runtime and current initial-writer authorization for the recorded deployment.
- `npm audit --audit-level=low`: zero reported vulnerabilities.
- `npm run deploy`: existing deployment verified; **no transaction sent**.

From root:

- `node .local/besu-02-audit.mjs`: reproduces F1/F2, verifies actual deployment and
  confirms rejection of altered genesis/runtime hashes. Scratch probe is ignored;
  no implementation/permanent-test file was changed.
- `powershell -NoProfile -File blockchain/besu/Test-Configuration.ps1`: pass.
- `powershell -NoProfile -File blockchain/besu/Test-Network.ps1`: pass, all five
  nodes/four peers each, zero fees and advancing height 1269 -> 1277.
- `git diff --name-only -- application`: empty. `git diff --check`: pass, with
  existing Git LF/CRLF normalization notices only.
- Key ACL and Git exclusion checks: pass for the new contract signer state,
  generated artifacts and audit scratch files.

From `application/`:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never --filter 'Payroll(Anchoring|Verification|Correction|Workflow)Test'
```

**133 tests / 2266 assertions passed**, 01:04.780, 72 MB. These confirm compatibility
with the existing application workflows and controlled blockchain interfaces;
they do not establish a Laravel-to-Besu transaction.

## Integration compatibility and limits

The compiler target matches the existing Shanghai genesis. Solidity SHA-256
commitments remain bytes32 and are distinct from Ethereum Keccak transaction/code
hashes. The ABI uses no dynamic payroll document fields; it is suitable for later
backend integration without requiring a new frontend or database framework.

AppServiceProvider still binds BlockchainReader/BlockchainSubmitter to unavailable
adapters. No payroll formula, schema, permissions, snapshot representation or
version lineage changed. Deterministic application reference mapping and exact
snapshot-byte preservation remain later-phase work; those absences do not count
as BESU-02 contract defects.

No new shutdown/restart, restoration, stress test, public-chain submission or
production security certification was performed. Existing node/network/client
policy limitations remain. Current contract tests do not cover F1/F2 or deployment
interruption/recovery; those are test gaps despite the passing suite.

## Files changed and stopping point

Documentation only: this audit plus progress, requirements and testing records.
Ignored audit probe created; its temporary HTTP servers were closed. Main registry,
manifest, signer files, contract source, tooling and permanent tests remain
unchanged. The five-node network remains running.

**STOPPED - F1/F2 reported without fixes; BESU-03 not implemented.**
