# BESU-03 - Payroll Snapshot and Hashing

Date: 2026-10-30 (America/Los_Angeles). Scope: next Besu phase only, after inspection
of committed BESU-02 (`53222c0`), its audit and existing payroll interfaces.

## Implemented

New finalized payroll versions atomically retain their exact v1 canonical UTF-8
envelope bytes, SHA-256 digest, snapshot schema and canonicalization identifiers
in `payroll_snapshot_archives`. One archive is unique to each finalization; the
envelope contains the batch ID, finalization ID and payroll version. Archived
bytes have no JSON cast/re-encoding, BOM or trailing newline. The existing frozen
payroll payload still contains amounts, earnings/deductions/totals, rule references,
inputs, submission metadata and workflow evidence.

The existing v1 encoder and monetary/date/null conventions are unchanged. Amounts
remain exact two-decimal strings rather than a new centavo schema that would
invalidate historical digests. Map keys are sorted; lists retain their original
order. Submission creation already orders employees by ID and is now exercised
with multiple employees submitted in reverse selection order. Verification does
not silently sort a changed stored list back into its original order.

The new snapshot service validates the frozen document projection/scope, archives
within the existing finalization transaction and checks current JSON against
archived bytes/hash. Archive failure rolls back status, workflow event and
finalization. Ordinary archive updates/deletes are rejected; unique/restrictive
foreign keys and rollback guards retain history.

Manual commitment preparation copies the validated archived digest. Existing
commitments must agree before a legacy archive is captured; old digests and retry
UUIDs are never replaced. Local verification/correction authorization also reject
archive mismatches, including before manual commitment preparation. Returning a
correction request no longer clears a mismatch against the original archived
snapshot, which remains independently preserved.

Legacy archival is explicit: the operator command defaults to dry-run, supports
one finalization ID and writes only with `--apply`. Each record is validated and
captured independently. No snapshot bytes or database exception contents are
printed; blocked records produce a nonzero exit and remain unchanged.

## Important files changed

- `application/app/Models/PayrollSnapshotArchive.php` and the new
  `2026_10_30_000001_create_payroll_snapshot_archives.php` migration.
- `application/app/Services/PayrollSnapshotService.php`.
- Existing workflow, anchoring, verification and correction services: atomic
  archival and compatibility/integrity checks, without calculation/access changes.
- `application/app/Console/Commands/ArchivePayrollSnapshots.php`.
- `application/tests/Feature/PayrollSnapshotTest.php`, a fictional encoder vector,
  the shared finalization fixture and correction-version retention assertions.
- `scripts/verify-payroll-workflow.php`: actual MySQL archive/retention probes.
- Canonical format documentation and phase/progress/requirements/testing records.

No payroll formulas, statutory rules, web permissions, routes, frontend framework,
contract, ABI, deployment metadata, signer keys, network configuration or transport
bindings changed. Existing uncommitted documentation was retained. No commit/push.

## Blockchain status

**Local byte preservation and hashing only. No transaction submitted in this
phase.** New finalizations do not create an anchoring attempt, commitment or queue
job; the original manual preparation stage remains distinct. Both application
blockchain interfaces still use unavailable adapters. No integrity verification
against the deployed Besu registry is claimed. Existing BESU-01/02 audit findings
are unchanged; fixing their tooling is outside this phase.

## Acceptance criteria and evidence

| BESU-03 criterion | Evidence |
| --- | --- |
| Identical snapshots produce identical hashes | Exact persisted-byte equality; archive retry idempotency; JSON map reorder and later employee-profile change leave bytes/digest unchanged. |
| Amount changes change the hash | Independent encoder vector changes a two-decimal amount and produces a different digest; existing correction workflow stores different version hashes/archives. |
| Ordering is deterministic | Three-employee finalization orders by employee ID despite reverse selection. Canonical map ordering is independent of database JSON key order; stored list reversal changes hash and fails preservation checks. |
| Historical snapshots remain reproducible | Legacy dry-run/apply/rerun tests retain original hash, key, JSON and version; mismatching legacy hashes are blocked. Prior correction archives remain unchanged. |
| Fixtures produce expected hashes | Fictional v1 vector independently calculated with Node crypto: b59b444791271872119426f8ffa81071b2d4c41cf047b765d779f2a1f9bde575. |

The short checked-in vector exercises encoding, not full document validity. Service
tests use complete fictional finalized workflows in all supported classifications.
Digest hex decodes to exactly 32 bytes; SHA-256 is not Ethereum Keccak-256.

## Tests performed

From `application/`:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never --filter 'Payroll(Snapshot|Anchoring|Verification|Correction|Workflow)Test'
php vendor/phpunit/phpunit/phpunit --colors=never
composer validate --strict
composer check-platform-reqs
```

Full suite: **629 tests / 7809 assertions passed**, 03:26.497, 102 MB.
Focused suite: **147 tests / 2507 assertions passed**, 01:14.869, 74 MB. Includes
14 new snapshot cases and strengthened correction-history checks. New tests bind
reader/submitter mocks that reject any call and disallow stray HTTP requests.
Command evidence is also recorded in [TESTING_STATUS.md](TESTING_STATUS.md).

Pint checks on changed/new PHP files and Composer checks pass. From root,
`php -l scripts/verify-payroll-workflow.php` and
`php scripts/verify-payroll-workflow.php` pass. The MySQL test exercises full
approval/finalization/documents/corrections, exact archive bytes/hash, preserved
version history, a payload over 64 KB with Unicode/escaped string line endings,
archive-tamper rejection and complete rollback of fictional rows.

Initial regression run: 132/133 passed; the correction warning expectation changed
because the archive now independently detects changed sources. The test was
updated to require the stronger warning, including after a request is returned.
Initial new suite: 13/14 passed; one fixture baseline needed a database refresh
to include default created_at/attribute order. No compatibility hash was altered.

## Database and manual verification

The configured local MySQL had exactly the existing 18 migrations applied and only
the new archive migration pending. Applied `php artisan migrate --force --no-ansi`:
the new table was created successfully. Ran the legacy command in dry-run and apply
modes: both reported **Validated: 0; blocked: 0**, so no historical application
record was changed/populated. Foundation database readiness passed.

For another checkout, from `application/`:

```powershell
php artisan migrate
php artisan payroll:archive-snapshots
# After reviewing any blocked records, explicitly capture eligible legacy bytes:
php artisan payroll:archive-snapshots --apply
```

Then use an existing fictional workflow to submit, independently approve and
finalize a batch. Its archive is created in that finalization transaction. Manual
Prepare still creates the existing local commitment; it does not submit to Besu.
Use automated isolated fixtures for tamper demonstrations; never edit real frozen
payroll records. No new route exports confidential canonical bytes.

## Issues and limits

- Application immutability guards do not prevent privileged SQL/query-builder
  rewrites/deletions of all local evidence. Independent on-chain comparison is
  later work; a local match is not blockchain verification.
- Legacy records without an earlier digest/archive can only establish a baseline
  of their current frozen contents, not authenticate past changes. Existing raw
  database JSON bytes were not preserved and cannot be reconstructed as originals.
- A legacy apply run commits valid records individually; another blocked record
  does not erase completed valid archives. Re-runs validate/reuse retained bytes.
- Archive table rollback is refused once it contains retained records. No populated
  database downgrade, disaster restore, real concurrency/crash or production-scale
  capacity claim is made. MySQL test data was transactionally rolled back.
- Existing real-payroll/client-policy and BESU-01/02 audit limitations remain.

Next: **BESU-04 - Backend Blockchain Connection**, not implemented.

**STOPPED - Awaiting authorization to continue.**

## BESU-03 bug-fix follow-up - 2026-10-30

The subsequent audit's F1/F2 are corrected: all document issuance now compares
against existing archived bytes, and the existing anchoring page suppresses
actions when that comparison fails. Diagnostic hashing uses a separate frozen
projection validator to avoid recursion and still reveal changed source bytes.
Legacy records without an archive remain readable without automatic baseline writes.

Five permanent regression cases were added (four mismatch/preparation combinations
and one legacy read case); all 19 snapshot tests / 382 assertions pass. MySQL
rollback-only checks also reject both corrupt archives and changed source JSON
for document issuance, then reproduce the original CSV after fixture restoration.
Full suite: **634 tests / 7953 assertions passed**, 03:16.533, 106 MB; all final
check evidence is recorded in TESTING_STATUS.md. No additional
functionality, migration, chain operation or future phase was introduced.
