# Phase 12-A - Payroll Integrity Verification and Check History

Date: 2026-10-27 (Asia/Manila). Scope: Phase 12-A only, following Phase 11 commit
`f448daf`. The master prompt permits reviewable subphases. Phase 12 is split into
A (verification/history) and B (authorized finalized correction versions), because
corrections span the locked approval/finalization lifecycle, document retrieval,
version constraints and anchoring. Phase 12-B and Phase 13 are not implemented.

The user's previously confirmed no-test-network constraint remains in force.
The running application cannot establish a genuine blockchain verification.

## Implemented

- Scoped integrity page linked from finalized payroll reviews, showing current
  local hash consistency separately from saved verification history. GET does
  not perform network lookups or create check records. Responses are private/
  no-store, with no-sniff/no-referrer headers and overflow-safe history pagination.
- Payroll Officers and Approvers can explicitly run a check. Viewer reads saved
  results only; Administrator retains no payroll-record access. This action does
  not approve, finalize, reopen or release payroll, so approver/preparer separation
  is not an authorization condition for running a read-only integrity comparison.
  Existing approval/finalization/anchoring independence rules are unchanged.
- Server-side active-session/role, batch/finalization scope and demonstration-use
  checks are repeated under locks. CSRF and throttling protect POST. A UUID request
  key makes request retries idempotent; new requests append new immutable evidence.
  Keys are actor/finalization scoped, with conflicting reuse rejected.
- Recompute the complete finalized snapshot envelope using Phase 11's unchanged
  canonical v1 representation. A shared encoder helper preserves the original
  bytes. Verification hashes altered snapshots before checking their document
  arithmetic, so an inconsistent salary change becomes a local mismatch rather
  than escaping comparison. No snapshot, amount, local commitment or attempt is
  repaired or replaced. Unserializable values and invalid formats fail closed.
- Independent receipt-reader contract that accepts only saved network/transaction
  references, never employee data or full payroll. An unavailable reader is bound
  by default and performs no network operation. There is no fake receipt provider,
  operator-entered confirmation or live network implementation in application use.
- Record pending, failed, unavailable, invalid, local-mismatch, confirmed-chain-
  mismatch and verified comparison outcomes. A verified outcome requires a valid
  local snapshot and matching local digest plus an independently returned confirmed
  receipt for exactly the requested network and transaction, with a SHA-256 hash,
  block reference and positive confirmation count. The reader contract requires
  the eventual real adapter to enforce chain identity, successful exact-payload
  inclusion and the authorized confirmation/reorganization policy.
- Retain checked hashes, transaction/block references, confirmation count, safe
  reason code, actor and timestamp. Raw provider exceptions are suppressed.
  Check timestamps are explicitly recorded from the application clock rather
  than relying on a potentially different database-default timezone.
  Receipt mismatches and pending/failed receipts never become verified simply
  because a local hash matches. All successful confirmed-receipt comparisons in
  this phase's tests use explicit test doubles, not real blockchain evidence.

## Displayed states

| State | Meaning |
| --- | --- |
| Not anchored | No local commitment or no submission exists; a local match alone is insufficient |
| Unavailable | A network/receipt check is unavailable or failed to return trustworthy evidence |
| Pending | Submission acceptance is uncertain/interrupted, or a retrieved receipt is unconfirmed |
| Failed | Rejected submission/receipt, invalid reference/format, or wrong receipt identity |
| Invalid snapshot | The snapshot is invalid or cannot be serialized; no verified result |
| Mismatched | Current snapshot differs from the local commitment, or a confirmed chain digest differs; the reason distinguishes these cases |
| Verified | Checked snapshot/local digest match a confirmed independently retrieved commitment at the recorded check time; unreachable with the default unavailable reader |

All saved results are historical, not continuous proof against later chain
reorganizations. Changes in the checked local hash/commitment/attempt identity or
local consistency produce a fresh-check notice. No background polling is added.
This phase never submits or retries a blockchain transaction and does not change
Phase 11's pending/unknown resubmission blocks.

## Files and schema

Created `BlockchainReader`, `BlockchainReceipt`, `UnavailableBlockchainReader`,
`PayrollVerificationService`, `PayrollVerificationController`, immutable
`PayrollVerification`, the integrity Blade view, new permanent verification tests,
shared finalized-payroll test fixture and this handoff.

Updated canonical envelope helper/caller, role policy, container binding, routes,
finalized review link, Phase 11 tests to reuse their unchanged fixture, the MySQL
workflow verifier, README and four tracking documents. No dependency or statutory
rule change; no canonical format/hash change.

Migration `2026_10_27_000003_create_payroll_verifications_table` adds one retained
history table with unique request key, scoped finalization/commitment/attempt FKs,
status/local status/reason, compared hashes, network/transaction/block metadata,
confirmations and actor/time. Deletion is restricted and rollback refuses retained
checks. Existing finalization/commitment tables and saved versions are unchanged.

The migration was applied on local MySQL; all 15 migrations are Ran. The extended
rollback-only verifier checks saved JSON digest/history, key idempotency and an
isolated in-memory snapshot-copy mismatch without modifying the original. All
fictional business fixtures roll back; MySQL's original stopped state was restored.

## Acceptance and remaining scope

| Phase 12 criterion | Assessment |
| --- | --- |
| Original finalized snapshot verifies | Comparison logic passes with explicit confirmed-receipt test doubles. Genuine network acceptance remains incomplete |
| Modified isolated test snapshot mismatches | Passed for a changed salary amount even with inconsistent arithmetic, and for a different confirmed test-receipt hash. Original test snapshot/old check values are preserved |
| Previous payroll versions remain available | Existing finalized version 1 and all earlier check/attempt records remain unchanged/retrievable. Creating correction versions is Phase 12-B and remains not started |
| No sensitive payroll data disclosed on-chain | No chain write occurs. Reader receives only saved transaction/network references; local hashes contain the full confidential snapshot only off-chain |

No genuine chain adapter/network, independently confirmed/retrieved transaction,
real confirmation threshold/governance, reorganization handling or reconciliation
was validated. A future reader must use configured trusted networks, bounded
timeouts and approved receipt policy; reader calls currently occur inside the
local consistency transaction and office-scale/concurrent latency is unmeasured.
Real payroll, earlier Phase 2/3 findings and client/statutory/attendance/document/
deployment limitations remain unchanged. No live browser/accessibility or process-
crash/capacity acceptance is claimed. Local guards cannot authenticate coordinated
privileged rewriting without an independent network commitment.

## Executed verification

Focused new verification coverage passes **32 cases / 495 assertions**. Full
hostile-environment isolation passes **537 tests / 6539 assertions**, including
all 505 earlier cases. MySQL workflow/document/history/JSON/idempotency/isolated-
mismatch verification passes with fixture rollback and initial stopped state
restored; all 15 migrations are Ran. Application/verifier Pint, JS syntax,
Composer strict/platform checks, production build (60 modules) and whitespace
checks pass. These final results include the application-clock correction and
MySQL verification was rerun afterward. Execution evidence is in TESTING_STATUS.md.

The first focused run found two test-fixture comparisons missing database-default creation timestamps;
refreshing the newly inserted fixture before capturing its attributes corrected
the tests, without weakening snapshot-preservation assertions. A subsequent frozen-
clock regression reproduced a check timestamp based on the database clock;
recording application time explicitly corrects it without changing older payroll
or anchoring records.

## Manual verification

1. Apply `php artisan migrate`, build assets and start the existing application.
   Use only fictional payroll finalized by an independent Approver.
2. As Payroll Officer or Approver, select **Payroll integrity checks and history**
   on its finalized review. Initially there are no saved checks; GET alone must
   not create one. The unavailable-reader notice must be visible.
3. Select **Run integrity check**. Without a commitment/submission expect **Not
   anchored**. With the existing default unavailable anchoring attempt, expect
   **Unavailable**. A local match must never say the payroll is blockchain verified.
4. Run another check and confirm the earlier timestamp/result is retained. Viewer
   can read history but has no Run button; direct POST is forbidden. Administrator,
   guest, inactive/revoked sessions and cross-batch IDs cannot expose/write checks.
5. Run `php vendor/phpunit/phpunit/phpunit --filter=PayrollVerificationTest` from
   `application/` to exercise isolated tampering and confirmed-receipt test doubles.
   Do not tamper with saved office payroll to demonstrate mismatch.

## Commit review - 2026-10-27

The authorized commit review reran full isolation (**537 tests / 6539 assertions**),
rollback-only MySQL workflow/document/history/JSON/idempotency/isolated mismatch,
application/verifier Pint, JS syntax, Composer strict/platform checks and production
build (60 modules). All pass; all 15 migrations already Ran, none executed, and
MySQL's stopped state was restored. The reviewed scope contains 25 phase-related
files, with no secret/debug/generated-file scan finding. All 175 compared source/
test/verifier files retain their pre-review hashes; no application behavior changed.
Execution evidence is in TESTING_STATUS.md. One local-integrity/history commit is
authorized; no push or Phase 12-B/13 implementation. Genuine verification remains
incomplete without an authorized network.

Recommended next subphase: Phase 12-B - Authorized Correction Versions, only after
explicit authorization. Complete outstanding Phase 11/12 live-network acceptance
when an authorized test network becomes available. Phase 13 is not started.

STOPPED - Phase 12-A only; genuine blockchain verification remains incomplete.
