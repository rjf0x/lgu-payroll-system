# Blockchain Monitoring and Verification Center

This phase upgrades the existing administrator-only `/admin/blockchain` page into a
read-only monitoring and verification center for the retained payroll ledger. It is
an enhancement of the existing Blockchain Operations module; no payroll computation,
certification rule, role, authentication path or external network was changed.

## Scope and design decisions

The system does **not** define a sequential previous-hash audit chain. A finalized
payroll is preserved as immutable canonical bytes (`payroll_snapshot_archives`) with a
SHA-256 digest, optionally linked to an immutable commitment (`payroll_commitments`)
and to a correction lineage (`payroll_correction_requests`,
`payroll_batches.correction_of_finalization_id`). The monitoring center therefore:

- verifies the records that actually exist (per-record digest, preserved canonical
  bytes, commitment/archive consistency and correction lineage), and
- clearly states that adjacent records are sequential but not cryptographically
  chained, and that a locally stored submission is never an externally confirmed
  blockchain transaction.

No local digest, submission or verification is presented as an on-chain
confirmation. No repair, rewrite or auto-correction of retained records exists.

## Files created

- `application/database/migrations/2026_11_04_000001_create_ledger_verifications.php`
  - append-only `ledger_verifications` history table.
- `application/app/Models/LedgerVerification.php` — immutable model.
- `application/app/Services/LedgerIntegrityVerificationService.php` — read-only,
  system-wide ledger integrity verification and history persistence.
- `application/app/Services/BlockchainMonitoringService.php` — cached, cheap
  monitoring summary for the overview and the navbar indicator.
- `application/app/Services/PayrollAnchorQueryService.php` — read-only anchor and
  submission queries with status labels and safe select lists.
- `application/resources/views/besu-operations/anchor.blade.php` — anchor detail.
- `application/resources/views/besu-operations/verification.blade.php` — verification
  report detail.
- `application/tests/Feature/LedgerMonitoringTest.php` — new feature coverage.
- `docs/BLOCKCHAIN_MONITORING_CENTER.md` — this report.

## Files modified

- `application/app/Http/Controllers/BesuOperationsController.php` — monitoring
  overview, verification action, anchor detail and report actions.
- `application/routes/web.php` — verify, anchor detail and report routes.
- `application/resources/views/besu-operations/index.blade.php` — rebuilt monitoring
  center (overview, ledger monitoring, anchoring, submissions, verification history
  and the preserved anchoring journal).
- `application/resources/views/components/ui/blockchain-status.blade.php` — accurate,
  clickable navbar indicator with a backend-fed dropdown.
- `application/resources/css/app.css` — additive styles for the monitor cards, ledger
  grid, filters and the status dropdown.
- `application/tests/Feature/BesuStatusIndicatorTest.php` — updated to the new states.

## Existing services and structures reused

- `App\Services\PayrollSnapshotService` (`SCHEMA`, canonical preserved bytes).
- `App\Services\Blockchain\CanonicalPayrollSnapshot` (SHA-256 canonical encoding).
- `App\Services\Blockchain\BesuStatus` (cached node reachability probe).
- Models and tables: `PayrollSnapshotArchive`, `PayrollFinalization`,
  `PayrollCommitment`, `PayrollCorrectionRequest`, `PayrollBatch`,
  `PayrollAnchorAttempt`, `BesuAnchor`, `besu_anchor_outbox`,
  `besu_anchor_transactions`, `besu_anchor_events`, `payroll_verifications`.
- Authorization: the existing `manage-blockchain` gate (active Administrator only)
  and the existing `active` middleware/`auth_version` recheck.

## Database changes

One additive migration adds `ledger_verifications`
(`request_key`, `status`, `records_checked`, `issues_found`, `report`, `created_by`,
`created_at`). It stores no payroll values, digests, signed bytes or provider
messages. Rollback refuses to drop retained verification history. No existing column,
index, table or row was changed or rewritten.

## Implemented features

- **Blockchain overview:** Ledger Integrity, Anchored Records, Pending Submissions and
  Integrity Issues cards, all sourced from retained records.
- **Ledger monitoring:** availability, hash algorithm, latest/previous record digest,
  last verification timestamp and result, plus an administrator-only, CSRF-protected,
  throttled verification action.
- **Integrity verification:** detects invalid record digests, modified audit records,
  unsupported record formats, missing records and broken commitment/correction
  lineage links. Read-only and immutable.
- **Payroll hash anchoring:** searchable, status/period-filtered, paginated table of
  commitments with digest details and a detailed record view.
- **Submission history:** recorded attempts with status, processing result and error
  information; statuses reuse the existing `payroll_anchor_attempts` states.
- **Verification history:** retained, immutable, paginated reports with per-issue
  detail.
- **Navbar indicator:** Ledger Operational, Integrity Verified, Integrity Issue,
  Verification Required and Service Unavailable, each derived from the backend, with a
  dropdown separating ledger availability, ledger integrity, latest verification,
  external blockchain connectivity and the latest successful anchor. The indicator is
  visible to authenticated roles (preserving the former badge), while the link to the
  administrator-only monitoring center is shown to administrators only.

## Security improvements

- Verification is restricted to active administrators, throttled and CSRF-protected.
- The service re-checks the actor is still active before writing history.
- Signed transaction payloads, encrypted bytes, provider messages and confidential
  payroll values are never selected or rendered.
- Safe operational logging only (`verification_id`, actor, status, counts) — no
  payroll values, digests or provider details.
- The centered summary is cached and never performs a full ledger scan on page or
  navbar renders; the cache is invalidated on a fresh verification.
- A missing/unavailable ledger degrades the navbar to **Service Unavailable** instead
  of breaking every workspace page.

## Test results

- `LedgerMonitoringTest`: 13 cases / 192 assertions — passing.
- `BesuStatusIndicatorTest`, `BesuAnchoringTest`, `BesuIntegrityUiTest`,
  `BesuConnectionTest`, `BesuJournalRecoveryTest`, `BesuTransactionVerifierTest`,
  `PayrollCorrectionTest`, `WorkspaceInterfaceTest`: passing (4 opt-in live cases
  skipped).
- Full existing + new suite: **721 passed, 4 skipped, 9087 assertions** (baseline
  before this change: 707 passed, 4 skipped, 8887 assertions). The four skipped
  cases are the pre-existing opt-in live Besu checks.

## Remaining limitations

- The schema has no sequential previous-hash chain and no external blockchain
  confirmation in this environment; the center reports that gap explicitly and never
  simulates it.
- Verification is bounded to 200 reported issues per run; the total count is complete.
- Whole-ledger verification remains an explicit, synchronous administrator action.
- Live end-to-end anchoring verification still depends on the pre-existing Besu
  validator clock condition documented in `BESU_06_HANDOFF.md`.
- `php vendor/laravel/pint/builds/pint --test` continues to report a pre-existing
  formatting violation in the untouched `tests/Feature/BesuJournalRecoveryTest.php`.
