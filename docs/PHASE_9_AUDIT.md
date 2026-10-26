# Phase 9 audit - Payroll Review and Approval

Correction follow-up: F1 was subsequently fixed under the user's Phase 9-only
authorization. The original audit below is preserved as pre-fix evidence. Current
verification: 443 tests / 4768 assertions and MySQL workflow pass; see the correction
sections in PHASE_9_HANDOFF.md and TESTING_STATUS.md. The old ignored F1 reproducer
asserts the former behavior and is superseded by permanent corrected-behavior tests.

Date: 2026-10-27 (Asia/Manila). Scope: current uncommitted Phase 9 implementation
against `master-prompt.md:537` tasks and acceptance criteria. Audit only; no
application fix, permanent-test change, migration, commit or Phase 10 work.

## Result

**Implemented but needs validation.** All three acceptance criteria pass for the
exercised demonstration workflows. One low-severity UI defect is confirmed; no
high/medium-severity workflow or snapshot-integrity defect was confirmed. Actual
production payroll and office acceptance remain blocked or unverified.

| Acceptance criterion | Assessment | Evidence and limits |
| --- | --- | --- |
| Payroll Officer cannot approve their own submission | Passed for exercised server paths | Officer cannot invoke review/finalization. After promotion to Approver, creator/submitter and retained preparation authors still receive 403. Current locked actor/session checks reject revoked finalizers. F1 concerns displayed controls, not a successful authorization bypass. Unknown pre-migration coverage-only editors cannot be reconstructed. |
| Returned payroll can be corrected and resubmitted | Passed for supported corrections | Loan correction, new input/core/statutory revisions and resubmission retain original values and reasons. Additional audit checks refresh removed/re-added coverage after an employee-office change and finalize corrected values. Six submission revisions remain retrievable through independent history pagination. |
| Finalized payroll cannot be silently modified | Passed at application boundary | All preparation endpoints reject writes while submitted/approved/finalized. Model guards protect finalized batches/coverage and immutable submissions/events/finalizations, including a stale batch instance. Later employee/configuration changes preserve finalized amounts. Unique constraints and transaction rollback prevent partial/duplicate records. Privileged SQL tampering, live races and recovery are not established by these tests. |

All seven task areas are present: review screen, submission, Approver review,
approve/return with reason, returned correction, authorized finalization and
locked snapshots. No additional missing Phase 9 task area was confirmed within
the supported demonstration scope.

## Confirmed finding

### F1 - Low: review screen offers self-review actions that always fail

Sources:

- `app/Policies/PayrollBatchPolicy.php:36` and `:41` check role/status only.
- `resources/views/payroll-reviews/show.blade.php:19` and `:23` use those policy
  checks to display review/finalization forms.
- `app/Services/PayrollWorkflowService.php:125` and `:169` enforce preparation
  independence later, inside the write transaction.

Reproduction using fictional data:

1. Payroll Officer prepares and submits a demonstration batch.
2. Change that account to Approver and establish a valid current session.
3. Open its own submitted payroll: **Approve payroll** and **Return for correction**
   forms are displayed. Completing either action returns HTTP 403.
4. Let an independent Approver approve the payroll. Reopen it as the former
   preparer: **Finalize and lock payroll** is displayed; submitting returns 403.

The isolated diagnostic confirms all three visible forms and server denials; no
finalization record is created by the former preparer. This misleads the user and
wastes completed form input. It does not violate server-side separation of duties.

Recommended correction: expose the preparation-independence eligibility to the
read/render path, hide or explain unavailable actions, and retain the locked
transactional recheck. Add permanent rendered-control assertions alongside the
existing endpoint-denial cases. No correction was implemented during this audit.

## Missing, blocked and deferred functionality

- Real payroll processing remains deliberately blocked. Client-approved category,
  contribution, withholding, attendance, minimum-net/refund and authority policies
  remain unavailable. Demonstration finalization keeps `production_ready=false`.
- The same independent Approver may approve and finalize. Whether office policy
  requires another signer or stronger external approval evidence needs validation.
- Historical coverage-only editors overwritten before Phase 9 are not recoverable.
  Migration backfills known creator/last-editor identities; saved input/core/
  statutory authors and all new coverage editors are also checked. Do not claim a
  complete reconstructed legacy preparation audit trail.
- Negative-net and credit/refund cases cannot be submitted until handling policy
  is validated. Their values are not silently changed or disbursed.
- Finalized corrections, document generation and blockchain are later phases.
  Their absence is outside this Phase 9 acceptance scope. No such feature added.
- Earlier Phase 2/3 audit findings remain open and were not relabeled or fixed.

## Checks executed

| Check | Result |
| --- | --- |
| `scripts/verify-test-isolation.ps1` | Passed: 438 tests / 4600 assertions, 73.636 seconds, 74 MB; inherited production/MySQL/DB_URL values remained isolated in-memory |
| Ignored audit diagnostics | Passed: 10 tests / 156 assertions, 6.473 seconds; F1 reproduction plus additional stale-submission, return-rollback, finalizer revocation, pagination, returned-source correction and migration-method checks |
| `php ../scripts/verify-payroll-workflow.php` | Passed on MySQL: three-category totals, JSON review token compatibility, independent approval, correction/resubmission, retained history, finalization/duplicate lock, later configuration preservation, real block and rollback |
| Existing MySQL verifiers | Configuration, core calculations and statutory previews passed with fixture rollback |
| `php artisan migrate:status` / `app:check` | Thirteen migrations already Ran; database/base tables ready; no migration executed |
| Application and workflow-verifier Pint `--test` | Passed |
| `git diff --check` | Passed |

MySQL was initially stopped, temporarily started for read/rollback-only checks and
stopped afterward. No fictional fixture, production rule, credential export,
database reset or actual payroll result was retained or changed by the audit.
Asset build was not rerun because application/asset sources were unchanged; the
last successful build remains recorded in TESTING_STATUS.md.

Audit diagnostics are ignored at `.local/phase9-audit/Phase9AuditTest.php` and reuse
the permanent fictional fixture. From `application/`:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never --filter=test_audit_ ../.local/phase9-audit/Phase9AuditTest.php
```

The initial legacy-migration probe failed during SQLite table reconstruction
inside RefreshDatabase's outer transaction: SQLite cannot disable foreign keys
within that transaction. The audit-only harness was adjusted to run the migration
methods outside that wrapper, still strictly in-memory. Backfill/input preservation
and rollback refusal with retained records then passed. This does not establish
populated transactional SQLite migrations or MySQL downgrade/recovery behavior;
the documented application database is MySQL.

## Coverage and remaining gaps

Permanent Phase 9 coverage remains **42 cases / 569 assertions**, plus all 396
earlier cases. The ten ignored diagnostics are separate; no coverage percentage
was measured. F1 lacks a permanent regression assertion for action visibility.

Covered: three personnel categories/four roles; all preparation endpoints in
three locked states; self-review after role changes; historical preparation actors;
stale/repeated transitions and review tokens; wrong submission identity; incomplete
reviews and changed sources/configuration; source/loan corrections and retention;
exact single/multi-employee totals; required reasons/confirmation; input isolation,
encoding/escaping; guest/CSRF/current actor checks; model immutability; five permanent
injected persistence failures plus an audit return-event failure; finalized values
after real configuration-service activation; and real-mode submission blocking.

Still unverified:

- Simultaneous MySQL submit/approve/return/finalize operations and employee/salary/
  configuration publication races. Sequential stale requests and injected failures
  do not establish live scheduling or isolation guarantees.
- Deadlock retries, connection-loss recovery and process interruption at commit.
- MySQL rollback/downgrade with populated legacy data and partial-migration recovery.
- Browser visual, keyboard, responsive and screen-reader interactions. HTTP rendered
  markup confirms F1; it is not live browser acceptance.
- Office-scale employee/history volume, query counts, memory, response time and
  client-approved expected payroll/authority scenarios.

Stop at Phase 9. Address F1 and validation gaps within the current phase as
separately authorized; no next-phase implementation is authorized by this audit.
