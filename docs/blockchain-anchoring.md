# Blockchain Anchoring & Verification (Stage 9)

**Stage:** 9 of the development plan
**Status:** Versioned registry handles new blockchain proofs; legacy Besu is verification-only and simulation is explicitly local.
**Related:** `payroll-approval-workflow.md`, `system-architecture-diagram.md`, `database-schema.md` §9

---

## 1. Approach

The header's F13 node badge is a limited connectivity indicator. Its authenticated, active-account-protected, uncached endpoint validates a JSON-RPC `eth_blockNumber` response and labels it **Node connected** or **Node unavailable**. A failed session/request displays **Node status unavailable**. `scope: node_connectivity` makes that boundary explicit; no registry bridge/signer/contract readiness or payroll confirmation/MATCH is inferred from a responding node. Review the registration and a fresh integrity check separately.

F11 retires new legacy network submission at both `BlockchainAnchorService` and `BesuBlockchainDriver`. New blockchain proofs use the versioned registry: finalization captures a versioned payload and durable outbox, then the worker/bridge journal submits and reconciles it. An older finalized run can be reviewed for the existing `blockchain:register-finalized {payroll}` console command. This captures its current baseline, not proof from the original finalization date, and preserves its original legacy anchor. Registry enablement, approved source evidence and the pinned target configuration remain required.

Historical legacy proofs remain readable/verifiable under their original digest. Only an explicit `simulated:` network with no contract may use local comparison; unknown/malformed network identity cannot silently become a simulated MATCH. A local simulation remains available only with registry disabled and the concrete simulated driver selected; it performs no external network operation and creates no independent blockchain evidence. Changing BLOCKCHAIN_DRIVER or enabling the former live-test switch cannot restore legacy network writes.

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
| `BesuBlockchainDriver` | Verification-only: reads historical receipts, transactions and contract state from the original Besu target. Its anchor method rejects with 422 before any network call. |
| `TestnetBlockchainDriver` | Stub for a real test network; throws until configured. |

Selected via `config/blockchain.php` (`BLOCKCHAIN_DRIVER`, `BLOCKCHAIN_NETWORK`,
`BLOCKCHAIN_CONTRACT_ADDRESS`) and bound in `AppServiceProvider`.

---

## 4. Anchoring & verification

**Anchor** (`BlockchainAnchorService::anchor`):
- Allowed **only for `FINALIZED` runs** (`422` otherwise).
- Locks and reloads the run before checking finalization and submission. An existing anchor, including a pending or failed transaction, is returned without resubmission.
- New legacy network requests are rejected with 422 before any driver/network call. With registry enabled, even an unregistered run cannot bypass registration by requesting a new simulated legacy record.
- Writes `blockchain_anchors`: `payload_hash`, `hash_algorithm`, `transaction_id`,
  `block_number`, `network_name`, `contract_address`, `anchor_status`,
  `anchored_by`, `anchored_at`.
- New records here are local simulations only. The local record and its audit commit atomically; audit failure rolls both back. Historical PENDING/FAILED/ANCHORED metadata is retained without creating or retrying a transaction. Historical confirmation can still record ANCHORED and PAYROLL_ANCHORED.

**Verify** (`BlockchainAnchorService::verify`):
- Recomputes the hash from the current stored payroll data.
- For Besu, checks chain ID, original contract, successful receipt, canonical block, transaction destination, sender, and method input, then reads `anchored(bytes32)` at the receipt block. The hash extracted from that transaction must match both the recalculated payroll hash and the local stored hash.
- Pending/unavailable transactions return 409; failed receipts mark the anchor `FAILED` and return 422. RPC failures return 503. These paths do not create a MATCH/MISMATCH log.
- Simulated historical anchors retain their local hash comparison; they do not gain independent on-chain evidence.
- Writes `blockchain_verification_logs`: `stored_hash`, `calculated_hash`,
  `verification_result` (`MATCH` / `MISMATCH`), `verified_by`, `verified_at`.
- Audited as `PAYROLL_VERIFIED`.

A legacy `MATCH`/`MISMATCH` applies only to its original delimiter-joined digest: employee number/name, basic/gross/deductions/pre-LBP/LBP/net amounts, and earning/deduction names/amounts. It excludes employer contributions, period/approval evidence, payable days and other registry-v1 fields. Historical digests are not rewritten or upgraded, and the detail page explains this boundary.

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
- The **run page** links to a versioned registration or historical anchor. With registry disabled and simulation selected, it offers **Record simulated local proof** with an explicit non-blockchain explanation. Other unregistered finalized runs show retrospective-registration guidance rather than a legacy network submit action.

---

## 7. Tests

`tests/Feature/BlockchainAnchorTest.php`:

- The payroll hash is deterministic across calls.
- Only a finalized run can be anchored (`422` otherwise).
- Local simulation stores a 64-char hash and clearly labeled simulated metadata.
- Legacy network submission is refused without network traffic; historical pending receipt fixtures retain confirmation/tamper coverage.
- Verification of an untampered run returns `MATCH`.
- Tampering with a stored detail after anchoring returns `MISMATCH`.
- A Viewer cannot access blockchain verification.

`tests/Feature/LegacyAnchorBoundaryTest.php` adds rejection/no-write/no-network checks, historical identity and idempotency, local audit rollback/retry, limited hash-scope disclosure and retrospective registry capture with original legacy proof preserved. Driver tests retain wrong-chain, pending/failed and untrusted receipt coverage. The former opt-in legacy live-write test now checks that live settings cannot reenable submission without sending HTTP; registry pilot/restore tests remain separate opt-in acceptance.

---

## 8. Local Besu setup

This section describes the historical PayrollAnchor deployment for verifying old proofs. It does not enable new legacy writes. Use the versioned PayrollIntegrityRegistry setup and operating instructions later in this document for new registrations; do not redeploy old contracts or replace original target identities to make a historical verification pass.

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

To inspect a historical proof, open its anchor detail and select **Check confirmation and verify** or **Verify integrity**. Existing simulated anchors remain simulated. New blockchain submissions use versioned registration; the legacy submit control has been removed from the network workflow.

## 9. Verification and operational limits

The normal suite fakes outbound RPC and tests pending/failed receipts, wrong-chain rejection, untrusted transaction evidence, repeated historical access without resubmission, and local-hash tampering. Legacy driver submission always returns 422, including with BESU_LIVE_TESTS enabled. Actual registry pilot/restore and live journal-recovery acceptance require their separate explicit test configuration and authorization; ordinary PHP/Node regressions do not establish a fresh live-chain result.

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

The core tests exercise real SQLite persistence and HTTP handlers with a fake upstream gateway. Gateway tests exercise ethers ABI/signature handling and evidence validation with offline synthetic signatures and fake RPC responses. The runnable-service test starts the actual entry point in a child process and uses ethers HTTP transport against a local mock RPC server to verify authenticated startup, signing, journaling, receipt/event decoding, confirmation, and read-only verification during a signer outage. It submits only to the mock server; no real network transactions are broadcast. A real deployed-registry/Web3Signer end-to-end pilot remains required before production rollout. Registry deployment, permission grants, payment policy, monitoring thresholds, and incident ownership remain rollout work. Laravel integration is documented in the following sections.

References: [ethers providers](https://docs.ethers.org/v6/api/providers/), [Web3Signer sign-only transaction API](https://docs.web3signer.consensys.com/reference/api/json-rpc/), and [Node SQLite](https://nodejs.org/download/release/v26.8.2/docs/api/sqlite.html).

## 11. Laravel finalization and registration outbox (Phase 4)

`PAYROLL_REGISTRY_ENABLED=false` is the default. Setting it to true makes authorized finalization capture a version 1 payload and a `payroll_registrations` outbox row in the same MySQL transaction as the finalized status and approval/audit entries. Invalid covered data or capture persistence failure rolls the transaction back. No bridge or blockchain call runs during finalization. Existing finalized records are not backfilled automatically.

`PayrollCanonicalizer` implements the published Phase 1 byte rules and reproduces all three fixed SHA-256 vectors. It validates exact object keys, required snapshots, identifier types, UTF-8, decimals, dates, booleans, UUID references, and schema versions. It sorts numeric employment identifiers without integer overflow, sorts line objects by canonical JSON bytes, and preserves duplicate lines. Monetary normalization uses decimal strings. It accepts structured arrays built inside Laravel, not external raw JSON; duplicate object keys cannot be produced by the builder. A future external JSON import must reject duplicate keys before decoding into arrays.

`PayrollPayloadBuilder` reconstructs covered current run/detail/line data using the frozen period and successful-approval snapshots stored in the registration. Canonical payload bytes, digest, organization namespace, reference, revision, and target network are persisted. These snapshot fields and the full archived payload are hidden from default model serialization. The namespace and network are read from configuration only at capture and never silently substituted during retries.

Initial runs receive a fresh UUID v4 payroll reference and revision 1. The schema has uniqueness constraints on run, idempotency key, and organization/reference/revision. A user workflow for linking a newly approved correction run to the same family and allocating later revisions is not part of Phase 4. Do not alter a finalized row to make a correction. Payroll registration rows are operational outbox records created by finalization; they are not populated with synthetic proofs by production seeders.

### Configuration and activation

Deploy the Phase 2 registry separately, grant the bridge account registrar permission for the organization's namespace, populate the Phase 3 private bridge environment, and run the bridge. Apply Laravel migrations using the usual release process before enabling capture. This implementation does not apply migrations to a live database or change a populated `.env` file automatically.

The `.env.example` contains `PAYROLL_REGISTRY_*` settings. Set the organization namespace, bridge URL/token, chain ID, genesis hash, registry address, deployed runtime-code hash, signer address, and registry deployment block. Network settings must describe the same deployment as the bridge's `BESU_BRIDGE_*` values. The token must match the bridge token and have at least 32 bytes. Set `PAYROLL_REGISTRY_ENABLED=true` only after these values and the schema are ready, then refresh the application's configuration cache using the deployment's normal process.

The bridge's authenticated `/health` now returns the configured `network` identity alongside readiness. Laravel compares its saved identity with current configuration and with this handshake before submitting. Network/code identity is checked by the bridge against the actual node. JSON object key order does not affect identity matching, including with MySQL's JSON storage. Contract addresses in bridge evidence are normalized to lowercase.

Run an asynchronous worker for the `blockchain` queue and the Laravel scheduler. For the default database queue:

```powershell
php artisan queue:work database --queue=blockchain --timeout=60 --tries=3 --no-interaction
php artisan schedule:work --no-interaction
```

For production, supervise the worker and use the normal scheduler cron or hosting integration. Queue reservation/visibility timeout must exceed the 60-second job timeout; the default database queue reserves for 90 seconds. The dispatcher runs every minute and dispatches at most 100 due rows by default. A manual bounded pass is available:

```powershell
php artisan blockchain:dispatch-registrations --limit=100 --no-interaction
```

The dispatcher passes only registration IDs to jobs, and the bridge receives only the six metadata fields defined in Phase 3. The complete payroll payload and credentials are never put into queue job arguments. Credentials are read from Laravel configuration at request time, not copied into the outbox.

### Failure and recovery behavior

- A worker claims a due row under a short database lock, increments its attempt count, and reserves a two-minute lease. HTTP runs after that transaction commits. A unique queue job reduces duplicate dispatches; the persisted lease is the processing boundary. An expired lease can be recovered by a later scheduled pass. Stale workers cannot overwrite a newer lease's result.
- A restart or dispatch failure after payroll finalization leaves the durable outbox available to the next scheduler pass. There is deliberately no dependency on a successful immediate after-commit dispatch from the user's finalization request.
- Workers validate the archived canonical bytes, digest, schema, identity, and idempotency key before contacting the bridge. They submit the captured digest even if current payroll data has since been altered; the original approved commitment is preserved for subsequent verification. Invalid local capture becomes terminal `FAILED` without sending it.
- The client reads existing authoritative registration evidence or a known bridge submission before enqueueing an idempotent POST. It never marks a payroll confirmed solely because a local bridge status says `CONFIRMED` without corresponding registration evidence.
- Matching evidence becomes `CONFIRMED`; a different registered hash becomes `CONFLICT`. Reverted receipt evidence becomes `FAILED` and retains transaction/block evidence. These terminal states are not automatically resubmitted. Operational retries retain the original request and target, with polling/backoff up to five minutes. Bridge failures do not reverse finalization or report an integrity mismatch.
- Changing chain, contract, signer, deployment block, or bridge endpoint configuration does not retarget outstanding requests: processing fails closed with `REGISTRY_TARGET_CHANGED`. Restore the original configuration and reconcile before migrating operations to another identity. The bridge journal independently pins its own identity.
- Current record edits and recomputation still use the existing payroll parent-lock convention. Public single-detail and totals recomputation now recheck editability under that lock. Privileged direct database writes can still change MySQL records; detecting those changes is the purpose of subsequent integrity verification.

Version 1 registrations are separate from legacy `blockchain_anchors`. The legacy manual anchor endpoint rejects a run with a versioned registration, avoiding a second proof under the old hashing algorithm. Legacy verification retains its original algorithm; the separate version 1 interface is described in Phase 5 below. Payment-release policy remains unchanged.

### Verification performed

The new PHPUnit tests cover all fixed vectors, individual covered-field mutations, malformed inputs, normalization and ordering, atomic capture/rollback, authorized finalization, disabled capture, snapshot independence, UTC conversion, stale computation guards, outage recovery, duplicate-worker leases, scheduler redispatch, target pinning, confirmation evidence, conflicts, and failed receipts. The Node transport tests also validate the new network handshake. All HTTP calls are faked or use a local mock RPC server; no live chain transactions or production migrations are part of these checks.

Phase 4 validation: 161 selected PHPUnit cases, with 158 passed and 3 skipped (two require an isolated MySQL concurrency database; one requires opt-in live Besu access), plus 27 passing Node bridge cases. Pint and whitespace checks passed. The minute-based schedule definition was checked using a temporary in-memory cache. The configured local MySQL login was rejected during the default scheduler inspection; correct those runtime credentials before migration/activation. MySQL concurrency and real-chain confirmation remain pilot checks.

## 12. Versioned payroll verification and pages (Phase 5)

Deploy the updated bridge and apply the additional `create_payroll_registration_verifications_table` migration before serving the new verification pages. The authenticated read endpoint now returns a `network` descriptor alongside `registration` and `submission`; the signer-dependent health endpoint is not called during verification. Existing submission/outbox behavior is preserved.

The existing `blockchain.verify` permission controls both new routes, inside the application's authentication and active-account middleware:

```text
GET  /payroll-registrations/{payroll_registration}
POST /payroll-registrations/{payroll_registration}/verify
```

The service also checks active-user eligibility and this permission, so an internal caller cannot bypass the endpoint authorization. Viewer and unauthenticated requests cannot access the records or create verification attempts.

### Evidence and snapshot rules

Each attempt makes a fresh authenticated GET to the bridge. It validates the original saved chain ID, genesis hash, registry address, runtime-code hash, and deployment block against the bridge's read response. Read-only verification ignores the current signing account, so signer rotation or downtime does not prevent reading historical registrations. Capture may also be disabled while verification remains available.

The bridge must serve the original saved contract deployment. A replacement contract is never substituted automatically. Operators can point Laravel's current bridge URL/token at a service configured for the original deployment, including after endpoint migration; the saved network/code identity must still match. This implementation does not automatically route among multiple historical registry services.

Remote reads run outside payroll database locks. After evidence is retrieved, a short transaction locks the payroll run and registration, confirms that registration metadata did not change during the request, reconstructs the covered current payroll payload using the saved period/approval snapshots, and appends the verification and audit records atomically. Results describe the data read at that check, not a continuing guarantee about future edits.

The authoritative hash comes from fresh blockchain evidence. The service compares it with the current calculated hash and validates the locally captured digest, archived payload bytes, and idempotency metadata. Replacing both the payroll data and its local saved hash cannot create a match against the original blockchain commitment. Verification never trusts the archived payload as a substitute for reconstructing current data and never falls back to a previous successful log during an outage.

### Results

| Result | Meaning |
| --- | --- |
| `MATCH` | Covered current payroll data and saved registration metadata match the registered version |
| `MISMATCH` | Covered current data or saved registration data differs; required current data that no longer satisfies the registered schema is also a mismatch |
| `PENDING` | The outbox or bridge submission is awaiting a successful registration; no integrity conclusion is made |
| `NOT_REGISTERED` | No successful on-chain registration was found, including an absent previously reported confirmation |
| `UNAVAILABLE` | Fresh evidence could not be obtained; no integrity conclusion is made |
| `INVALID_EVIDENCE` | Evidence identity, schema, receipt metadata, or request context is inconsistent or unsupported; no integrity conclusion is made |

Mismatches prompt investigation rather than automatic resubmission. Unsupported hash schemas do not silently use the legacy algorithm. Bridge errors are classified without exposing raw exception details or credentials. A local bridge status of `CONFIRMED` with no corresponding registration is invalid evidence.

`payroll_registration_verifications` records the acting user, result, checked time, blockchain hash, current calculated hash when available, captured hash, sanitized evidence, and a readable finding. Unsuccessful attempts are retained alongside successful ones. The separate legacy log table and its original verification behavior remain intact. Verification does not mutate payroll data, reset the outbox, create replacement anchors, or change payment-release policy.

### User interface

The blockchain index lists versioned registrations separately from legacy anchors and labels simulated local proofs explicitly. Submission status and last integrity result are separate columns. The finalized payroll page links to its registry verification page instead of offering the legacy anchor action.

The registry detail page prioritizes the latest outcome, its timestamp, and a Verify current payroll action. Technical hashes and transaction/block evidence use expandable details. History is paginated at 15 attempts per page; registration listings use a separate pagination parameter from legacy anchors. Loading feedback, semantic alerts, labelled tables, focusable native disclosure controls, and wrapping hashes reuse the existing design components. Snapshot text is escaped, including both registry and legacy proof page headings.

### Checks and remaining rollout

Thirty new verification cases cover fresh matches, payroll/local-hash tampering, archived-copy tampering, missing required data, outages after a match, pending and absent registrations, invalid evidence, signer-independent reads, metadata races, service/route authorization, protected metadata rendering, history pagination, and heading escaping. Existing registration, legacy Besu, UI, and payroll-flow suites were also run. All 27 Node tests and the production asset build passed. These checks use isolated test databases and fake/mock RPC services; they do not migrate a live database or register real payrolls. The local Phase 6 pilot has now passed; operational ownership and production activation remain pending, as described below.
## 13. Pilot and operations (Phase 6)

The local pilot uses synthetic payrolls, four repository-owned Besu 26.10.2 QBFT containers, Web3Signer 26.10.2, and isolated MySQL 8.4 on loopback port 33316. It does not change the application's `.env`. Four containers on one host do not provide production fault separation.

Run `npm run besu:pilot` with Docker, Node 26, PHP 8.5, and the installed project dependencies available. It starts the local stack, deploys or reuses the registry through `npm run besu:registry:setup`, grants the pilot namespace, and runs real integration tests. Bootstrap accepts only the configured local chain 1337 with zero fees and four validators. Its deployment journal retains signed operations before broadcasting, so reruns reuse deployment identity.

The runner deliberately resets only the guarded source database `lgu_payroll_qa_registry_pilot`. MySQL uses a Docker named volume. Generated credentials, profiles, journals, SQL backups, and reports remain under ignored `storage/app/private/besu/`; protect these files as secrets. Do not copy their contents into logs or commits. Existing application database credentials are not repaired or replaced by the pilot.

### Pilot evidence

The real-network pilot passed these checks:

- Laravel computation, independent approval/finalization, database queue dispatch, signed registration, and MATCH → MISMATCH after a synthetic data change → MATCH after restoring the original data: 1 test, 19 assertions.
- A consistent MySQL dump restored into a new guarded QA database verifies against the original blockchain commitment: 1 test, 6 assertions.
- A lost broadcast response followed by journal backup, restoration, and bridge-instance restart retains the original transaction bytes/hash and signs only once. This tests instance recovery, not sudden host power loss.
- Existing MySQL concurrency tests pass: 2 tests, 18 assertions.

`pilot-latest.json` identifies the successful run directory and restored database. `pilot-recovery-report.json` records the journal drill. `registry-deployment.json` contains public deployment identity. The successful run's `restore.env` targets the retained restored payroll; concurrency tests subsequently reset the source QA database. The bridge process is stopped when the runner exits. Normal test runs skip the opt-in pilot/restore tests.

The live MySQL run exposed a verification-history index name exceeding MySQL's 64-character limit. The unapplied migration now uses the explicit short name `payroll_registry_verification_time_index`; fresh migration and backup restoration both passed on MySQL.

### Monitoring and ownership

Run `php artisan blockchain:readiness --json --no-interaction` under the intended application profile. Add `--production` to require configured validator, signer, and payroll incident owners. Exit code 0 means these checks passed at that instant; code 1 requires investigation. This command does not dispatch jobs or sign transactions.

The report checks migrations, asynchronous queue configuration, namespace registrar permission, pinned chain/contract/signer health, node synchronization, recent block production, backlog age, overdue leases, failed/conflicting registrations, failed blockchain jobs, and unresolved integrity findings. Defaults are 600 seconds for backlog age and 60 seconds for latest-block age, configured through `PAYROLL_REGISTRY_STALE_AFTER_SECONDS` and `PAYROLL_REGISTRY_MAX_BLOCK_AGE_SECONDS`. An unavailable verification does not clear an earlier mismatch; a subsequent conclusive assessment is required.

Configure `PAYROLL_REGISTRY_VALIDATOR_OWNER`, `PAYROLL_REGISTRY_SIGNER_OWNER`, and `PAYROLL_REGISTRY_PAYROLL_OWNER` with accountable teams before production activation. These are intentionally blank by default. Validator membership/backup administration, signing-key custody, and payroll incident investigation need explicit ownership. Contract administration must also have an assigned custodian. No owners are appointed by this implementation.

Supervise the bridge, queue workers, scheduler, RPC, validators, and signer separately. An empty queue does not prove worker or scheduler liveness. Alert on process failure, stalled blocks, backlog age, expired leases, terminal failures, and integrity findings. `deployment:check` accepts versioned registry configuration but performs only a static transport check; readiness provides live checks. Neither command grants production approval.

### Incident handling

For pending or overdue requests, inspect worker/scheduler supervision and bridge/node/signer readiness, restore the original pinned target, and let the persisted lease/reconciliation path recover. A lost response may mean the transaction was accepted: retain the journal and reconcile the original hash before taking further action. Never reset nonce state or delete a journal to clear a queue.

For MISMATCH, preserve payroll, audit history, captured payload, and chain evidence; assign investigation to the payroll owner. Do not reanchor modified data to conceal a finding. For INVALID_EVIDENCE or a missing previously confirmed registration, inspect the original chain, deployment identity, receipts, and RPC access. For FAILED/CONFLICT, retain terminal evidence and investigate with the signer/contract custodian. There is no force-retry endpoint or automatic replacement registration. Payment-release policy is unchanged; the business correction/revision workflow still needs approval and implementation.

### Backup and restoration

Use `node config/besu/backup-journal.mjs <source.sqlite> <new-backup.sqlite>` for online bridge journal backups. It uses the [SQLite backup API](https://sqlite.org/backup.html), preserving committed WAL-backed state, validates integrity and network identity, and refuses to overwrite an existing target. Copying only a live `.sqlite` file can omit WAL state. Signed bytes in journal backups are sensitive.

To restore, preserve the original files, stop the current bridge writer, back up into a new destination, and point the bridge's journal configuration at that restored path. Keep chain/genesis/contract/code/signer identity unchanged, enforce a single writer, then reconcile against the chain before resuming submissions. Do not overwrite or discard historical journals. Restore MySQL using a consistent backup into a new guarded QA database first, then verify original commitments before any approved production recovery. The pilot performs this SQL restore drill automatically.

Back up validator configuration/keys and chain data and the managed signer configuration/keys through their infrastructure owners' procedures. The journal and SQL drills do not substitute for validator or signing-key disaster recovery. Production needs separate validator failure domains, restricted RPC, private authenticated transport, managed signing custody, supervision, approved release/correction policy, and a scheduled recovery exercise.

To stop only this repository's local pilot services while preserving histories and volumes:

```powershell
docker compose -f config/besu/compose.yaml stop
docker compose -f config/besu/pilot.compose.yaml stop
```

Do not use volume deletion as a shutdown procedure.
