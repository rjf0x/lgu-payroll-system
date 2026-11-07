# Blockchain Anchoring & Verification (Stage 9)

**Stage:** 9 of the development plan
**Status:** Implemented for the mock-defense build
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
| `SimulatedBlockchainDriver` (default) | Produces a verifiable local proof: a `0x…` transaction id, a block number, and the configured network/contract. No external calls. |
| `TestnetBlockchainDriver` | Stub for a real test network; throws until configured. |

Selected via `config/blockchain.php` (`BLOCKCHAIN_DRIVER`, `BLOCKCHAIN_NETWORK`,
`BLOCKCHAIN_CONTRACT_ADDRESS`) and bound in `AppServiceProvider`.

---

## 4. Anchoring & verification

**Anchor** (`BlockchainAnchorService::anchor`):
- Allowed **only for `FINALIZED` runs** (`422` otherwise).
- Idempotent — an already-anchored run is returned unchanged.
- Writes `blockchain_anchors`: `payload_hash`, `hash_algorithm`, `transaction_id`,
  `block_number`, `network_name`, `contract_address`, `anchor_status = ANCHORED`,
  `anchored_by`, `anchored_at`.
- Audited as `PAYROLL_ANCHORED`.

**Verify** (`BlockchainAnchorService::verify`):
- Recomputes the hash from the current stored payroll data.
- Compares with the anchored hash using `hash_equals`.
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
  **Verify integrity** action with the verification history.
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

## 8. Caveat

The default network is **simulated** for the capstone. Swap in a real driver
(`BLOCKCHAIN_DRIVER=testnet`) and implement its transaction submission before any
production anchoring.
