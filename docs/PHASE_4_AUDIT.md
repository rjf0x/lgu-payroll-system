# Phase 4 audit - Payroll Period Management

Date: 2026-10-25. Scope: Phase 4 only. No application fixes or Phase 5 work.

## Acceptance assessment

Status: Implemented and tested for the documented prototype conventions. No new
Phase 4 application defect was confirmed in this audit. Client policy and live
deployment acceptance remain unverified; passing automated checks is limited to
the scenarios exercised below.

| Master-prompt acceptance criterion | Assessment | Evidence |
| --- | --- | --- |
| A draft payroll batch can be created and reopened | Passed for tested workflows | HTTP create/list/reopen cases, saved coverage and snapshot checks, and transactional MySQL create/reopen for both data-use modes and all three categories. |
| The correct employees and salary records are retrieved | Passed for supported eligibility/source conventions | Selected employee IDs and source record IDs are stored; latest first-day source is validated against category/mode/effective schedule and exact rate amount. Invalid, future-only, superseded and mid-month-changing sources are rejected. No cross-mode fallback is used. |

All six Phase 4 task areas are present: monthly period creation, covered employee
selection, salary retrieval, saved draft records, duplicate protection and visible
preparation status. No explicitly required Phase 4 feature was found absent from
the supported workflow. Earnings/deductions, calculations, approvals, reports and
blockchain work are later phases and are not counted as missing functionality.

## Bugs and regression assessment

- No new reproducible Phase 4 defect, authorization bypass, unintended duplicate
  or saved-snapshot overwrite was found in the reviewed paths.
- Batch month/mode uniqueness and employee/batch uniqueness back application
  checks. Stale draft versions and repeated submissions are rejected; a mixed
  valid/invalid selection rolls back rather than saving partial coverage.
- Payroll Officers write; Approvers/Viewers read; Administrators do not inherit
  employee/payroll access. Source amounts and status are selected server-side;
  submitted forged amounts and an approved status are ignored.
- Saved values remain unchanged on reopen, while changed profile/source details
  produce Needs review. Removal is scoped to the requested draft and leaves
  salary history intact. Refresh is an explicit removal/re-add.
- Earlier Phase 2 bugs and Phase 3 salary-workflow findings remain open. Phase 4
  refuses unusable sources; it does not repair a contaminated salary stream or
  validate the promotion/eligibility policy in Phase 3.
- The previously documented random Phase 2 employee-search fixture failure did
  not recur in this audit's full-suite runs. It remains a known test instability.

## Missing functionality and validation limits

These are documented restrictions or unverified requirements, not newly confirmed
implementation bugs:

1. **Mid-month preparation is unavailable pending client rules.** Employees must
   currently be active and employed by the first day. Salary/schedule changes
   inside the month are blocked. No prorating or treatment of mid-month joining,
   promotions or rate changes is inferred.
2. **Historical employment status cannot be reconstructed.** Employee activity
   is a current boolean, and current classification/start date are checked.
   An inactive former employee cannot be newly selected for an earlier month
   even if that employee worked then. Confirm the intended historical workflow;
   no employment timeline is claimed by the current schema.
3. **Grouping and salary-reference conventions need approval.** One fixed batch
   per month/data-use mode and a first-day salary basis are provisional. Separate
   office/category batches, identity editing or batch cancellation are not supplied;
   the master prompt does not explicitly require those features in Phase 4.
4. **Checks are per displayed entry, not whole-batch approval.** The list shows
   Draft/coverage count and the detail page checks its visible 20 entries. There
   is no overall clearance, calculated-pay result or approval readiness claim.
5. **Live concurrency, browser acceptance and office-scale performance remain
   unverified.** Code has row locks, current-actor reauthorization, transaction
   retries, unique constraints and version checks; sequential checks do not prove
   all concurrent publication, assignment and coverage race behavior.

## Checks executed in this audit

| Check | Result |
| --- | --- |
| `php vendor/phpunit/phpunit/phpunit --colors=never` | Passed: 148 tests / 1997 assertions |
| `scripts/verify-test-isolation.ps1` | Passed: 148 tests / 1997 assertions with conflicting inherited production/MySQL settings |
| Application and scripts Pint `--test` | Passed |
| `php ../scripts/verify-payroll-drafts.php` | Passed against MySQL: both modes, all categories, exact saved salary/source checks, create/reopen, duplicate prevention and fixture rollback |
| MySQL `migrate:status` | All seven migrations Ran; no migrations run by this audit |
| Three additional ignored audit probes | Passed: 3 cases / 36 assertions |
| `git diff --check` | Passed; normal Git LF/CRLF notices |

The extra probes exercised an actual Phase 2 employee / Phase 3 salary-authority
HTTP workflow into a real-mode monthly draft with two different rates, verified
that an unpublished mid-month schedule does not block a valid source, and checked
selection pagination plus read-only UI actions. They are diagnostic coverage
outside the permanent suite, at `.local/phase4-audit/Phase4AuditTest.php`:

```powershell
# Run from application/
php vendor/phpunit/phpunit/phpunit --colors=never --filter test_audit_ ../.local/phase4-audit/Phase4AuditTest.php
```

## Permanent test coverage and gaps

The 37 Phase 4 cases cover create/reopen, category/mode combinations, exact
amounts/source IDs, role actions, duplicate/stale/cross-batch requests, invalid
inputs/UTF-8, revoked actors/CSRF/guests, atomic rollback, applicability guards,
month/leap boundaries, preserved snapshots, review warnings and saved pagination.
All 111 earlier cases remain in the suite. No coverage percentage is claimed.

Missing permanent or independent evidence:

- The three extra integration/unpublished-schedule/selection-page probes are not
  permanent regressions. Existing salary fixtures generally use model creation,
  a single grade/step and the same nominal amount.
- Actual simultaneous MySQL writers, deadlock/retry paths, unique-conflict races
  and database-disconnection/recovery scenarios.
- Approved real salary tables, first-day and mid-month rules, historical employee
  eligibility, organizational visibility and payroll grouping examples.
- Visual multi-client browser acceptance, representative large-office datasets,
  request/query performance, deployment and backup/recovery.

The project MySQL instance was initially stopped, started temporarily for the
rollback-only check, and stopped afterward. No fictional records were retained.
Only this audit/tracking documentation and ignored diagnostic probes were added
during the audit. Application code and permanent tests were not modified, no
commit was created, and Phase 5 remains not started.
