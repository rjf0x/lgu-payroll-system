# Phase 3 audit - Salary Grade and Step Management

Date: 2026-10-25. Scope: Phase 3 only. No implementation fixes or Phase 4 work.

## Acceptance assessment

Status: Implemented but needs validation. All Phase 3 task areas have a prototype
implementation, but applicable-salary retrieval and demonstration isolation need
correction or an explicitly validated acceptance contract before unqualified sign-off.

| Master-prompt acceptance criterion | Assessment | Evidence |
| --- | --- | --- |
| Salary retrieval uses the correct effective-dated schedule | Partially met | Schedule selection and assignment validate category/date/mode, but salary lookup returns the last employee snapshot even when its source has been superseded. The page warns; the service does not return an applicability status or reject the stale source. |
| Unauthorized salary updates are prevented | Passed for tested application actions | Four-role HTTP action matrix, actor reauthorization, CSRF, invalid sources, required authority/reference and confirmations. No role bypass was found. External authority authenticity remains an operational/client-validation matter. |
| Historical salaries remain unchanged | Passed for tested application operations | Exact immutable snapshots, published schedule/rate guards, append-only changes, SQLite feature cases and rollback-only MySQL workflow. No claim of database-administrator tamper protection or blockchain verification. |

## Findings

1. **High - Applicable salary lookup lacks a strict applicability guard.**
   `application/app/Services/SalaryService.php:26` returns the latest employee
   record on/before the requested date without checking the effective schedule.
   Reproduction: assign grade 5 / step 1 from a schedule paying `10000.07`, then
   query a date when a newer published schedule pays `15000.00` for the same
   category/mode/grade/step. `applicableSchedule()` selects the new schedule, but
   `salaryAsOf()` returns `10000.07` from the old one. The salary page still heads
   the result with that monthly amount and separately shows a warning.

   This snapshot behavior is deliberate in DECISIONS.md and in the existing test;
   it preserves history and avoids an unauthorized automatic raise. The missing
   functionality is a validated applicable-salary result that distinguishes a
   historical snapshot from a usable current salary and requires an authorized
   update when the source is stale. Do not fix this by rewriting history or
   silently applying new rates. The current snapshot-only contract does not fully
   establish the master prompt's effective-schedule retrieval acceptance criterion.

2. **Medium - Demonstration and real employee salary assignments share one stream.**
   `application/app/Services/SalaryService.php:26` has no data-use selector, and
   `recordSalary()` checks the previous employee record without restricting mode.
   `application/database/migrations/2026_10_25_000003_create_salary_tables.php:47`
   enforces one row per employee/date regardless of mode.

   Reproduction: create an externally authorized initial assignment, then use a
   demonstration schedule for a later `schedule_update`. The request succeeds;
   subsequent salary lookup returns the demonstration record. Attempting to
   restore the real assignment for that same effective date is rejected. Neither
   record can be edited/deleted, so this also obstructs the legitimate stream.
   The original real row remains intact, and UI demonstration labels are present;
   the defect is interference between modes, not silent alteration of that row.
   Schedule-resolution mode isolation is tested, but employee-history isolation
   is missing. Isolate these histories or prevent cross-mode assignments to an
   established employee stream, according to the agreed operational policy.

## Policy and functionality gaps

- **Promotion semantics need client validation.** At SalaryService.php:137 only
  same-grade moves are rejected. A grade 6 / `11000.09` assignment can become
  grade 5 / `10000.07` and be recorded as a promotion. Reproduced through HTTP.
  The documented prototype intentionally means "different grade"; this is a
  confirmed validation gap, not a claim that a particular statutory rule has been
  violated. Validate and test the authorized transition policy before sign-off.
- Potential eligibility is only a three-year reminder from an operator-entered
  basis, with leap-day handling. Category eligibility, exceptions, approved basis
  rules, and real LGU salary tables have not been validated.
- Retrospective corrections and cancellation/replacement of future assignments
  are deliberately unavailable. This matters operationally because an erroneous
  future record prevents insertion of earlier intervening changes. The master
  prompt does not explicitly require a correction workflow in Phase 3; this is
  a documented limitation rather than an additional acceptance defect.

## Checks executed in this audit

| Check | Result |
| --- | --- |
| `composer test` | Passed: 111 tests / 1683 assertions |
| `scripts/verify-test-isolation.ps1` | Passed: 111 tests / 1683 assertions under conflicting inherited production/MySQL settings |
| Application Pint `--test` | Passed |
| Scripts Pint `--test` | Passed |
| `php ../scripts/verify-salaries.php` against local MySQL | Passed: all three categories, effective schedules, exact decimals, recorded transitions, preserved history and rollback; no fictional fixtures retained |
| Focused ignored audit probes | 3 cases / 65 assertions reproduced the observed stale-source result, mixed modes, and downward move labeled promotion |
| `git diff --check` | Passed; Git reported normal LF/CRLF conversion notices |

The audit probes assert the observed behavior to document it; their passing does
not mean the findings satisfy acceptance. They are isolated diagnostics outside
the permanent suite, at `.local/phase3-audit/Phase3AuditTest.php`. Reproduce from
`application/`:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never --filter test_audit_ ../.local/phase3-audit/Phase3AuditTest.php
```

The project MySQL instance was initially stopped, started for the transactional
check and stopped afterward. Existing application source and tests were not
changed, and no commit was created.

## Test coverage assessment

The 35 salary feature cases exercise all three personnel classifications, role
permissions, initial/step/promotion/schedule transitions, effective-date boundaries,
exact decimals, immutable snapshots and published sources, duplicate prevention,
malformed values/UTF-8, error-session persistence, guests/CSRF, revoked actors,
and provisional review dates. All 76 earlier cases remain in the full suite.

Missing permanent regression coverage:

- A strict applicable-salary retrieval contract for stale source schedules and
  changed employee classification; the current test expects a warning plus the
  historical amount rather than a rejected/unusable current result.
- Mixed real/demo employee assignments and recovery from mode interference.
- Approved promotion direction and allowed grade/pay transition semantics.
- Real concurrent MySQL publication/assignment and simultaneous officer writes;
  SQLite feature tests and sequential MySQL checks do not establish these.
- Client-approved schedules, category-specific eligibility and authorization examples.
- Visual browser acceptance and multi-client deployment/failure recovery.

No coverage percentage is claimed. The previously observed randomized Phase 2
employee-search fixture failure did not recur in this audit's two suite runs;
it remains documented in TESTING_STATUS.md. The two confirmed Phase 2 defects
(suffix search and invalid UTF-8 persistence) remain open and were not fixed.

Phase 4 remains not started. No later-phase functionality is counted as a
missing Phase 3 feature.

## Repeat audit - 2026-10-25

Rechecked the current service, controllers, request validation, policy and salary
tests against the same Phase 3 acceptance criteria. The two findings and the
promotion-policy gap above remain reproducible; no additional defect was confirmed.

- Full PHPUnit suite rerun: passed, 111 tests / 1683 assertions.
- Existing isolated audit probes rerun: 3 cases / 65 assertions reproduced the
  stale-source result, mixed-mode interference and lower-grade promotion label.
- Application and script Pint checks: passed.
- Whitespace check: passed, with normal Git LF/CRLF notices.

The diagnostic assertions describe existing behavior rather than acceptance
success. MySQL smoke and inherited-environment isolation results above are from
the preceding audit and were not rerun in this repeat check. The project database
remained stopped. No application code, permanent tests or Phase 4 work changed;
only this audit record was updated.
