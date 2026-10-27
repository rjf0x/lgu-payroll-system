# Phase 11 - Blockchain Anchoring (local-interface fallback)

Date: 2026-10-27 (Asia/Manila). Scope: Phase 11 only, after Phase 10 commit
`b4e62fe`. The user explicitly confirmed that no test network is available and
authorized the master prompt's local-interface fallback.

## Implemented behavior

- Versioned deterministic snapshot envelope and SHA-256 batch digest. Full saved
  finalization values and workflow evidence are covered, without live payroll
  recalculation. The format and reproducible vector are specified in
  `CANONICAL_PAYROLL_COMMITMENT_V1.md`.
- One immutable local commitment per finalization, enforced by a database unique
  constraint; repeated preparation preserves its digest, creator and retry key.
  The existing snapshot and payroll status are never modified.
- Read-only status page linked from the finalized review. Payroll Officer,
  Approver and Viewer retain existing whole-office read access; Administrator has
  no payroll-record access. GET performs no preparation or submission.
- Only an active independent Approver can prepare a commitment or explicitly
  attempt submission. The write transaction rereads actor/session, batch and
  finalization, checks current digest and reviewed demonstration confirmation,
  and enforces existing preparer/submitter separation after role changes.
- Hash-only submission contract, with an unavailable implementation bound in the
  application container. It has no network transport, credentials or simulated
  transactions. The interface receives only the digest and an opaque retry key,
  never employee, salary, deduction or full snapshot data.
- Retained attempt records separate from payroll status. Default submissions
  record `unavailable`, with no network/transaction reference. A definite rejection
  is `failed`; an interface-returned transaction is only `pending`; unexpected
  exceptions are `unknown`. Interrupted `submitting`, `unknown` and `pending`
  attempts block blind resubmission. Safe retries retain previous failures and
  reuse the original digest/key. Raw provider exception details are not stored or
  shown. All pending/failure paths beyond the unavailable adapter are exercised
  by test doubles only, not represented as live blockchain evidence.
- Application-level immutable commitments/attempts, restricted foreign-key
  deletion, CSRF, throttled writes and private/no-store status responses.

The service rejects submission inside an ambient caller transaction with HTTP
409, before creating an attempt or invoking the interface. It completes its
outermost reservation transaction before invoking the interface, without an
automatic database retry around network effects. A process crash leaves
`submitting` and blocks another send. A future real transport must additionally
establish network-specific idempotency/reconciliation, validate chain/receipt
identity and handle confirmation policy. Rollback-only MySQL verification checks
this rejection without creating an attempt and explicitly requires the unavailable
adapter; it cannot broadcast.

## Files and database changes

Created: `app/Services/Blockchain/` contract, canonical encoder, pending-reference
result and unavailable/rejected exceptions/adapter; `PayrollAnchoringService`,
`PayrollAnchoringController`, `PayrollCommitment`, `PayrollAnchorAttempt`, status
Blade view, `PayrollAnchoringTest`, canonical-format specification and this handoff.

Updated: service binding, existing batch policy/routes/finalized review link,
rollback-only MySQL workflow verifier, README and the four tracking documents.
No dependency, statutory rule, payroll calculation, document template or asset
source change.

Migration `2026_10_27_000002_create_payroll_commitment_tables` adds:

- `payroll_commitments`: unique finalization FK, canonicalization/algorithm/digest,
  unique UUID retry key, actor and creation time.
- `payroll_anchor_attempts`: commitment FK, submission status, nullable network and
  transaction reference, actor and start/completion times.

Rollback refuses to discard retained commitments. No existing payroll table or
snapshot was rewritten. Apply the migration before accessing the new page.

## Acceptance and limits

| Phase 11 acceptance | Result |
| --- | --- |
| Finalized demonstration payroll successfully anchored | Incomplete: no authorized test network; only local fallback implemented |
| Blockchain transaction independently retrievable | Incomplete: no real transaction exists |
| Failed submissions do not falsely appear successful | Passed for unavailable implementation and injected pending/rejection/ambiguous/interrupted interface scenarios |

No confirmed/verified state, confirmation polling, chain commitment retrieval,
on-chain comparison, finalized correction versions or Phase 12 feature is
implemented. Local disagreement guards only prevent submitting a snapshot that
differs from the saved commitment; they do not prove original authenticity or
detect coordinated privileged rewriting of both records.

Network selection, governance, signer/key custody, cost/funding, confirmation
thresholds, independent retrieval and outage/recovery procedures remain unresolved.
No live network, load/concurrency, process-crash recovery or browser/accessibility
acceptance is claimed. Existing client/statutory/attendance/document/deployment
limitations and earlier Phase 2/3 audit findings remain unchanged. Real payroll
stays disabled.

## Manual verification

1. Apply `php artisan migrate` inside `application/`; start the application using
   the existing README workflow and fictional demonstration payroll only.
2. Finalize payroll with an independent Approver. On the finalized review, open
   **Blockchain anchoring status and local commitment**. It must state local-only,
   not prepared and not submitted.
3. As that independent Approver, confirm demonstration use and **Prepare local
   commitment**. Reload: a 64-character digest is saved, with no transaction.
4. Select **Attempt submission through configured interface**. It must record
   **Unavailable**, explicitly stating no network submission occurred. A later
   retry keeps the same digest and retains earlier attempts.
5. Check the page as Payroll Officer/Viewer: read access with no write controls.
   Administrator, guest, inactive/revoked accounts and a preparer changed into an
   Approver must not prepare or submit. Finalized registers/payslips and workflow
   values must remain unchanged.

## Original implementation verification

Focused Phase 11 tests pass **22 cases / 363 assertions**. Full isolation passes
**502 tests / 5949 assertions**, including all 480 earlier cases, with hostile
inherited production/MySQL settings pinned to in-memory SQLite. The extended
MySQL workflow/document verifier passes with all fictional fixtures rolled back;
the new migration was applied and all 14 migrations are Ran. MySQL's initial
stopped state was restored. Application/verifier Pint, production asset build
(60 modules) and whitespace checks pass. Detailed evidence and remaining coverage
gaps are in TESTING_STATUS.md. These results predate the correction below.

## Phase 11-only correction - 2026-10-27

The review corrected both audit findings without adding functionality:

- F1: attempt-history pagination rejects pages whose SQL offset cannot fit an
  integer before multiplying that offset. `PHP_INT_MAX` and the first unsafe
  boundary now return 404. The largest safe offset, normal pages, empty out-of-range
  pages and existing malformed-query fallback remain supported. Authorization
  and finalized-snapshot validation still happen first.
- H1: submission is allowed only when the service owns the outermost database
  transaction. Calls inside another transaction return 409 before an attempt or
  interface call. This applies to every adapter, including the unavailable one;
  there is no test-environment bypass. Local-only preparation remains safe within
  a caller transaction. After it ends, the same reviewed hash/key can be submitted
  normally, with the reservation committed before the interface executes.

Three permanent regressions cover offset boundaries and record preservation,
21-attempt pagination/malformed queries/authorization, and submission rejection
under both committed and rolled-back outer transactions followed by a normal
single pending test-double submission. The latter checks transaction level zero
and a retained `submitting` reservation at the interface boundary. Phase 11 tests
now use fresh disposable in-memory SQLite databases without a wrapping test
transaction, so the same durability guard is exercised as in normal requests.

Before correction, the new tests reproduced F1's HTTP 500 and H1's unsafe interface
call. Corrected focused verification passes **25 tests / 458 assertions**.
Nine applicable ignored audit cases pass **161 assertions**. The original F1/H1
reproducers remain historical and are excluded from corrected-behavior checks.
Full isolation passes **505 tests / 6044 assertions**, including all 480 earlier
cases. The full-regression and MySQL execution evidence is in TESTING_STATUS.md.

The rollback-only MySQL verifier now expects the guard's 409 with no attempt,
instead of invoking the unavailable interface inside its open transaction. It
still checks local preparation idempotency and saved JSON digest preservation.
No schema/migration, dependency, asset-source or canonical-hash format change.

For manual correction verification, append `?page=9223372036854775807` to a
finalized anchoring page with attempt history: expect 404, then remove the query
to retrieve the normal page. Existing preparation and standalone unavailable
submission steps above still apply. Live anchoring remains incomplete.

## Commit review - 2026-10-27

The user-authorized commit review reran full isolation (**505 tests / 6044
assertions**), rollback-only MySQL workflow/document/commitment verification,
application/verifier Pint, JS syntax, Composer strict/platform validation and the
production build (60 modules). All pass; no migration or dependency change was
needed. The reviewed scope contains 27 phase-related files with no secret/debug
or generated-file scan findings. No application behavior changed during commit
review; all 165 compared source/permanent-test/verifier files retain their hashes.
Detailed evidence is in TESTING_STATUS.md. One local-interface commit authorized;
no push or Phase 12 implementation. Live anchoring remains incomplete.

Recommended next phase: Phase 12 - Blockchain Verification and History, only on
explicit authorization. A genuine network must first allow completion of the
outstanding Phase 11 live anchoring/independent retrieval acceptance criteria.

STOPPED - Phase 11 local-interface scope only. Live anchoring remains incomplete.
