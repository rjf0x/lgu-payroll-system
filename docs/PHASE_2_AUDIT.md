# Phase 2 audit - Employee Management

Date: 2026-10-25. Scope: Phase 2 only. No application fixes or Phase 3 work.

## Acceptance assessment

Status: Implemented but needs validation. Normal employee workflows and role
restrictions pass the existing tests, but two confirmed defects need correction.

| Master-prompt acceptance criterion | Evidence | Assessment |
| --- | --- | --- |
| Employee records can be created, edited, and retrieved | Three-classification HTTP feature datasets and transactional MySQL create/read/edit checks | Passed for supported normal inputs |
| Input validation and access restrictions work | Four-role policy/action matrix, CSRF, guest/inactive/stale-session restrictions, invalid-input cases, uniqueness and stale-edit checks | Access checks pass; malformed text encoding bypasses validation and produces HTTP 500 |

All Phase 2 task areas are present: create, details, authorized editing, position,
classification, search, and basic employment/payroll information. Search is
incomplete for names containing a suffix. Salary grades, salary history, payroll
calculations and approval are later-phase requirements and are not counted as
missing Phase 2 functionality. No authorization bypass was found in this audit.

## Findings

1. **Medium - Full-name searches containing a suffix miss the employee.**
   `application/app/Http/Controllers/EmployeeController.php:29` searches employee
   number, first/middle/last name, position, and office, but omits `suffix`.
   Every search word must match one of these columns. A record displayed as
   `Fictional Maria Santos Jr.` is retrieved by `Fictional Maria Santos`, but a
   search for the exact displayed full name returns zero results when `Jr.` is
   absent from the other searchable fields. Reproduced through the HTTP feature
   pipeline with an authorized Viewer. Missing behavior: include suffix-bearing
   displayed names in the existing employee name search and cover that case.

2. **Medium - Invalid UTF-8 text passes validation and returns HTTP 500 on MySQL.**
   `application/app/Http/Requests/SaveEmployeeRequest.php:30` checks string type
   and length without checking encoding for names, suffix, position, or office.
   A string containing an invalid byte, for example PHP `"Invalid\xFFName"`, passes
   the actual form validator. MySQL rejects the insert/update with error 1366.
   The controller catches only unique-constraint exceptions, so the exception
   renderer returns HTTP 500 instead of a field validation error.
   Reproduced for creation through all six text fields (first/middle/last name,
   suffix, position, office) and for a first-name edit. Failed writes rolled back
   correctly; the existing employee remained unchanged. These were direct real
   MySQL validator/controller/exception-renderer checks, not browser requests.
   Missing behavior: reject unsupported encoding before persistence and add
   regression coverage. Ordinary valid accented names and escaped HTML text were
   separately confirmed to render correctly on SQLite; this is not a general
   rejection of valid Unicode names.

## Tests executed

| Check | Observed result |
| --- | --- |
| `composer test` | Passed: 76 tests / 1040 assertions, including 31 employee cases and all prior tests |
| `scripts/verify-test-isolation.ps1` | Passed: 76 tests / 1040 assertions with inherited production/MySQL settings |
| Pint `--test` for application and scripts | Passed |
| `php artisan migrate:status` | Passed: all five migrations Ran; no migration executed during audit |
| `php artisan app:check-foundation` | Passed against MySQL |
| `scripts/verify-employees.php` | Passed: three classifications, create/read/edit, role policy, literal multiword search, stale-edit rejection and rollback |
| Two isolated audit tests | Passed: 2 tests / 12 assertions; one confirms the suffix-search defect, one positively checks identifier normalization, valid Unicode storage and HTML escaping |
| MySQL encoding diagnostics | Seven failure paths reproduced: six create fields and one edit; validation accepted input, MySQL error 1366, rendered HTTP 500; update unchanged and all fictional records rolled back |

Passing diagnostic assertions confirm the reported defects; they are not successful
acceptance of the erroneous behavior. Diagnostic files remain in ignored local
storage, outside the permanent suite. Reproduce from `application/`:

```powershell
php vendor/phpunit/phpunit/phpunit -c phpunit.xml ../.local/phase2-audit/Phase2AuditTest.php --colors=never --testdox
php ../.local/phase2-audit/verify-encoding.php
```

The encoding probe reuses the existing MySQL smoke check and then runs additional
rollback-only diagnostics. The existing MySQL instance was initially stopped; it
was started with its unchanged configuration for these checks and stopped afterward
to restore the initial state. No HTTP server or browser was started. No real
employee/user data was used, and no fictional records were retained.

## Coverage and remaining verification

Current permanent coverage includes create/retrieve/edit for all classifications,
role navigation/action denials, guest/inactive/stale sessions, in-flight actor
revocation, malformed input shapes, length/date/enum/status validation, safe error
forms, duplicate submissions and deterministic unique conflicts, stale edits,
pagination, inactive employee retrieval, literal wildcard/injection search cases,
CSRF, missing records, and absence of deletion. No coverage percentage was measured.

Missing permanent coverage and verification:

- Suffix-bearing full-name search and invalid UTF-8 create/update validation.
- Real simultaneous MySQL employee edits/unique-number collisions across separate
  users/connections. Existing uniqueness tests use deterministic SQLite events;
  the MySQL smoke check rejects a stale edit sequentially, not concurrently.
- Live browser flows with persistent MySQL sessions, including stale-form reload,
  authorization changes across clients, mobile layout, accessibility, and error UX.
- Complete accepted-length/date boundary cases and accented/case-insensitive name
  search on MySQL. The new positive Unicode probe is separate from the normal suite.
- Migration downgrade/failure recovery, broad load, office-network operation,
  deployment, and backups/recovery.

Client validation remains pending for whole-office employee visibility and the
prototype employee-number conventions. The current documented policy gives Payroll
Officers writes, Approvers/Viewers reads, and no implicit Administrator access.
That policy is enforced, but it does not establish client-approved record scoping.
Three prior Phase 0 setup findings remain open. Recommended next action: correct
the two Phase 2 findings and add regression tests; do not start Phase 3 automatically.
