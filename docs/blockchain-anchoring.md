# Blockchain Anchoring & Verification (Stage 9)

**Stage:** 9 of the development plan
**Status:** Hyperledger Besu local Docker integration implemented; simulated driver retained for isolated QA
**Related:** `payroll-approval-workflow.md`, `system-architecture-diagram.md`, `database-schema.md` §9

---

## 1. Approach

Only a **deterministic cryptographic hash** of a finalized payroll run leaves the
application. No employee name, personal identifier, salary, deduction, or payslip content
is placed on-chain. MySQL remains the system of record.

```
Finalized payroll (MySQL) ──serialize──► SHA-256 hash ──► anchor ──► transaction metadata (MySQL)
                                                             │
                                        verification: recompute hash ──► compare with stored hash
```

---

## 2. Deterministic serialization

`BlockchainAnchorService::hashPayrollRun()` builds a stable byte string:

- Details are ordered by `employment_id`.
- Each detail contributes employee number, name, basic pay, gross, total deductions,
  NTHP-before-LBP, LBP, and net pay (2-decimal fixed formatting).
- Earnings and deductions are appended, each sorted by (`name`, `amount`) and formatted
  `name:amount`, so reordering rows does not change the hash.
- The concatenated payload is hashed with the configured algorithm (default `sha256`).

Because the serialization is deterministic, recomputing later reproduces the same digest
unless the underlying payroll data changed.

---

## 3. Driver interface

`App\Services\Blockchain\BlockchainDriver` abstracts the network:

| Driver | Behaviour |
|---|---|
| `SimulatedBlockchainDriver` (repository default) | Generates local transaction metadata with a `simulated:` network label and no contract. No external calls or independent blockchain proof. |
| `BesuBlockchainDriver` | Submits `anchor(bytes32)` through Web3Signer; confirms transaction receipts and reads the anchoring contract on Besu. |
| `TestnetBlockchainDriver` | Stub for a real test network; throws until configured. |

Selected via `config/blockchain.php` (`BLOCKCHAIN_DRIVER`, `BLOCKCHAIN_NETWORK`,
`BLOCKCHAIN_CONTRACT_ADDRESS`) and bound in `AppServiceProvider`.

---

## 4. Anchoring & verification

**Anchor** (`BlockchainAnchorService::anchor`):
- Allowed **only for `FINALIZED` runs** (`422` otherwise).
- Locks and reloads the run before checking finalization and submission. An existing anchor, including a pending or failed transaction, is returned without resubmission.
- Writes `blockchain_anchors`: `payload_hash`, `hash_algorithm`, `transaction_id`,
  `block_number`, `network_name`, `contract_address`, `anchor_status`,
  `anchored_by`, `anchored_at`.
- Besu submissions are saved as `PENDING`, with a transaction ID and no block or confirmation time; audited as `PAYROLL_ANCHOR_SUBMITTED`. Confirmation records `ANCHORED` and `PAYROLL_ANCHORED`. Simulated anchors are recorded immediately.

**Verify** (`BlockchainAnchorService::verify`):
- Recomputes the hash from the current stored payroll data.
- For Besu, checks chain ID, original contract, successful receipt, canonical block, transaction destination, sender, and method input, then reads `anchored(bytes32)` at the receipt block. The hash extracted from that transaction must match both the recalculated payroll hash and the local stored hash.
- Pending/unavailable transactions return 409; failed receipts mark the anchor `FAILED` and return 422. RPC failures return 503. These paths do not create a MATCH/MISMATCH log.
- Simulated historical anchors retain their local hash comparison; they do not gain independent on-chain evidence.
- Writes `blockchain_verification_logs`: `stored_hash`, `calculated_hash`,
  `verification_result` (`MATCH` / `MISMATCH`), `verified_by`, `verified_at`.
- Audited as `PAYROLL_VERIFIED`.

A `MISMATCH` means the stored payroll no longer matches what was anchored.

---

## 5. Authorization & routes

Requires the `blockchain.verify` permission (Admin, Payroll Officer, Approver; **not**
Viewer).

```php
Route::middleware('can:blockchain.verify')->group(function () {
    Route::get('blockchain', ...)->name('blockchain.index');
    Route::post('payroll-runs/{payroll_run}/anchor', ...)->whereNumber('payroll_run')->name('payroll-runs.anchor');
    Route::get('blockchain-anchors/{blockchain_anchor}', ...)->name('blockchain-anchors.show');
    Route::post('blockchain-anchors/{blockchain_anchor}/verify', ...)->name('blockchain-anchors.verify');
});
```

---

## 6. UI

- **Blockchain Verification** page lists all anchors (period/run, network, status,
  anchored by/at, hash prefix).
- **Anchor detail** shows the full hash, transaction id, block number, contract, and a
  **Verify integrity** action with the verification history. Pending anchors show **Check confirmation and verify**.
- The **run page** shows an anchoring panel for finalized runs: anchor the run, or view and
  verify an existing anchor.

---

## 7. Tests

`tests/Feature/BlockchainAnchorTest.php`:

- The payroll hash is deterministic across calls.
- Only a finalized run can be anchored (`422` otherwise).
- Anchoring stores a 64-char hash and transaction metadata.
- Verification of an untampered run returns `MATCH`.
- Tampering with a stored detail after anchoring returns `MISMATCH`.
- A Viewer cannot access blockchain verification.

---

## 8. Local Besu setup

The local stack uses **Hyperledger Besu 26.10.2**, four **QBFT** validators with a
node allowlist, and **Web3Signer 26.10.2**. Its chain ID is **1337**. Docker Compose
publishes Besu on loopback port **28545** and Web3Signer on loopback port **29000**.
The contract `app/Services/Blockchain/PayrollAnchor.sol` accepts hashes only from
its deployment account. No employee or payroll details are submitted.

With Docker Desktop running, from the project root:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File config/besu/setup.ps1
```

The script generates local keys and genesis, starts the network, compiles the
contract with Solidity 0.8.30 targeting London, and deploys it through Web3Signer.
It saves transaction IDs for subsequent receipt checks and reuses existing state
on reruns. Keys, chain data, compiled artifacts, and public connection settings
are stored under ignored `storage/app/private/besu/`. Merge that directory's
`.env.besu` entries into the application `.env`, then run:

```powershell
php artisan config:clear --no-interaction
```

The local application was configured for this Besu network during integration.
Fresh checkouts retain `BLOCKCHAIN_DRIVER=simulated` until setup and configuration.
The `testnet` driver remains an unrelated unimplemented placeholder.

Start or stop this checkout's stack without deleting chain history:

```powershell
docker compose -f config/besu/compose.yaml up -d
docker compose -f config/besu/compose.yaml stop
```

To demonstrate, open an unanchored finalized run, select **Anchor finalized
payroll**, then **Check confirmation and verify**. A transaction may take several
seconds to confirm. Existing simulated anchors remain simulated; they are not
automatically replaced or resubmitted.

## 9. Verification and operational limits

The normal suite fakes outbound RPC and tests pending/failed receipts, wrong-chain
rejection, untrusted transaction evidence, submission idempotency, and local-hash
tampering. The opt-in live test uses isolated test database records, submits a
real hash transaction, verifies MATCH, then changes only test data and verifies
MISMATCH even after changing its local hash:

```powershell
$env:BESU_LIVE_TESTS = 'true'
php artisan test --compact --group=besu
Remove-Item Env:BESU_LIVE_TESTS
```

Run this only against the local disposable network: the test database is isolated,
but its hash transactions remain in the development chain. Normal tests select
the simulated driver and skip the live case.

This is a development network. Its raw signing key is stored in an ignored local
file for Web3Signer; Laravel stores only public addresses. Production requires
managed signing credentials, restricted authenticated RPC access, independently
operated validators, backups, and monitoring. Four containers on one computer do
not protect against compromise of that computer or its administrator.

An RPC timeout after signing, process failure, or database failure after submission
can leave a transaction without a saved local anchor. No submission request is
automatically retried. Reconcile the sender's transactions and contract events
before retrying an ambiguous submission. The contract makes repeated hash storage
idempotent, but does not promise exactly-once transaction submission. Known pending
or failed transaction IDs are retained; there is no automatic resubmission or
administrator recovery UI. Historical contracts/signers require their original
configuration for verification. This is not a production outbox/recovery system.

References: [Besu private networks](https://docs.besu-eth.org/private-networks),
[Web3Signer transaction signing](https://docs.web3signer.consensys.com/development/reference/api/json-rpc).

## 10. Node.js registry bridge (Phase 3)

Implementation status: Phase 3 implemented and tested. Ethers.js 6.17.0 was explicitly approved and installed. All 27 core, gateway, and runnable-service tests passed. Existing Laravel registration routes and legacy deployment settings are unchanged. This service targets the separate `PayrollIntegrityRegistry` contract from Phase 2; do not point it at `PayrollAnchor`.

The bridge uses Node's built-in HTTP server and SQLite journal. The project environment was checked with Node 26.13.0. It requires a Node release supporting `node:sqlite` and global `fetch`; the complete service's intended runtime is Node 26.13.0 or later. No Express, SQLite npm package, test framework, or private-key dependency is introduced. Ethers.js is pinned to runtime version 6.17.0.

### Configuration and startup

Copy `config/besu/bridge.env.example` to an ignored private location such as `storage/app/private/besu/bridge.env`. Fill in a random service token of at least 32 bytes, the chain ID and genesis hash, registry contract address, deployment block, deployed runtime-code Keccak hash, and the dedicated Web3Signer account. The code hash is calculated over `eth_getCode` output at the deployed registry address; its immutable administrator makes it deployment-specific. Chain ID alone is not accepted as network identity.

After populating the private configuration, start explicitly:

```powershell
node --env-file=storage/app/private/besu/bridge.env config/besu/bridge.mjs
```

The server binds to loopback port 3100 by default. Every endpoint, including health, requires `Authorization: Bearer <token>`. Cross-host access requires an authenticated TLS transport/proxy managed by the deployment; no public listener is enabled by this change. Health validates chain/code identity and the signer's available accounts. Read-only verification does not require the signer service to be up.

| Endpoint | Behavior |
| --- | --- |
| `POST /registrations` | Validate metadata, save durably, return `202` with current local status; no synchronous broadcast |
| `GET /registrations/{organization_id}/{payroll_reference}/{revision}` | Return authoritative registration evidence and local submission state separately |
| `GET /transactions/{transaction_hash}` | Return canonical receipt summary or `PENDING_OR_UNKNOWN` |
| `GET /health` | Return `READY` only when the network, contract, and configured signing account are available |

POST accepts exactly these JSON keys: `organization_id`, `payroll_reference`, `revision`, `payload_hash`, `hash_schema_version`, and `idempotency_key`. The reference, digest, version, and idempotency rules are specified in [Phase 1](besu-phase-1-specification.md#protocol-identity). Requests are limited to 4096 bytes. Extra payroll fields are rejected. Invalid payloads return `422`, conflicting hashes return `409`, bad credentials return `401`, and unavailable upstreams return `503`. Upstream exceptions, signed bytes, and signing envelopes are never returned in HTTP responses.

### Durability, nonce ownership, and recovery

The journal defaults to ignored `storage/app/private/besu/bridge.sqlite`. `BESU_BRIDGE_JOURNAL_FILE` can select a different persistent journal for another signer/contract identity. SQLite is bridge operational storage only; payroll data remains in MySQL. Back up and protect the journal and its WAL alongside signing operations. A signed raw transaction is not a private key, but it is an authorization that can be broadcast; keep journal access private.

A lifetime SQLite exclusive lock on a companion `.lock` database permits one active writer process per journal and is released by process exit/crash. The service also serializes all in-process drains. Use one dedicated account and one journal per signer/network/contract, on a persistent local filesystem. Independent journals, external users of the same account, multiple hosts, shared network filesystems, and automatic active-active failover are not supported. Changing journal identity fails closed. Do not delete the journal to clear an error or rotate a key; preserve unresolved transactions and use a separate identity/journal with operator reconciliation.

The bridge reserves a nonce and envelope durably, asks Web3Signer to `eth_signTransaction` without broadcasting, validates all signed transaction fields using ethers, saves the signed bytes and derived transaction hash, then broadcasts. A failed journal write prevents broadcasting. After a lost response/restart it queries chain evidence and may replay exactly the same signed bytes; it never creates a fresh transaction merely because a response timed out. Nonce allocation considers the node's pending nonce and persisted reservations.

Signing/broadcast failures retain `QUEUED`/`RECONCILING` state with exponential backoff capped at five minutes. Drains run every five seconds. Retries preserve the original envelope and bytes; there is no automatic fee replacement or nonce cancellation. A pending transaction can remain pending indefinitely and needs operational age monitoring. Failed on-chain receipts become terminal `FAILED` evidence; an operator recovery/retry interface is deferred. Conflicting registrations become terminal `CONFLICT`, except when an already-signed nonce remains unresolved, which pauses later allocations and requires reconciliation. Failed signing before any durable raw bytes is safe to retry because the configured signing method does not broadcast.

Registration reads cross-check storage, the unique registration event, canonical block hash, successful receipt, receipt log membership, calldata, original registrar, and block timestamp. Registration timestamps are returned as Unix-second strings, with transaction and block metadata separately. They are never represented as payroll approval timestamps. Historical registrars do not have to equal the bridge's current signing account.

### Tests and remaining rollout

Dependency-free core tests:

```powershell
node --test tests/Unit/RegistryBridge.test.mjs
```

Run the complete Phase 3 suite:

```powershell
npm run test:besu-bridge
```

The core tests exercise real SQLite persistence and HTTP handlers with a fake upstream gateway. Gateway tests exercise ethers ABI/signature handling and evidence validation with offline synthetic signatures and fake RPC responses. The runnable-service test starts the actual entry point in a child process and uses ethers HTTP transport against a local mock RPC server to verify authenticated startup, signing, journaling, receipt/event decoding, and confirmation. It submits only to the mock server; no real network transactions are broadcast. A real deployed-registry/Web3Signer end-to-end pilot remains required before production rollout. Registry deployment, permission grants, Laravel finalization/outbox integration, payment policy, monitoring thresholds, and incident ownership remain later-phase work.

References: [ethers providers](https://docs.ethers.org/v6/api/providers/), [Web3Signer sign-only transaction API](https://docs.web3signer.consensys.com/reference/api/json-rpc/), and [Node SQLite](https://nodejs.org/download/release/v26.8.2/docs/api/sqlite.html).
