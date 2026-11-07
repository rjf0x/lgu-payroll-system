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
