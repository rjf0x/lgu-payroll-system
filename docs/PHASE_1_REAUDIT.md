# Phase 1 re-audit - after corrections

Date: 2026-10-25. Scope: Authentication and User Roles only.
Application code, schema, and the permanent test suite were unchanged during this
audit. No Phase 2 work was performed.

## Acceptance assessment

Phase 1 meets its stated acceptance criteria within the verified development
environment. No missing Phase 1 module or new reproducible application defect was
identified. This finding is limited to the code reviewed and checks executed; it
does not establish production deployment or exhaustive browser acceptance.

| Master-prompt criterion | Evidence | Result |
| --- | --- | --- |
| Each role can log in | Four-role HTTP feature dataset plus MySQL session-guard authentication smoke check | Passed |
| Restricted actions reject unauthorized users | Guest and non-admin account-action denials, workspace matrix, CSRF checks, stale-session and in-flight actor checks | Passed |
| Authorization tests pass | Full suite: 45 tests, 611 assertions | Passed |

All task areas are present: login/logout, account management, four roles, server
route/action authorization, and role-specific navigation. Account deletion is
handled through deactivation by the recorded design. Payroll screens, employee
management, approval actions, and reports belong to later phases; their absence is
not a missing Phase 1 feature.

## Review of previously reported defects

- Reciprocal administrator edits lock actor and target in ID order and check the
  actor's current role, active state, and authentication version inside the write
  transaction. Separate MySQL processes again demonstrated the second writer
  waiting and then being denied after deactivation and demotion. One active
  administrator was preserved in each scenario.
- Password inputs above 72 bytes or containing null characters are rejected
  before hashing on login, account writes, and bootstrap. Boundary, UTF-8,
  hash-shaped-literal, and legacy-suffix regressions pass.
- Rejected arrays are type-checked before rendering login/account forms. Permanent
  tests follow the redirect with a session cookie and assert visible errors.
- Unique-email conflicts are caught outside the database transaction and become
  validation errors. Create/update regressions confirm rollback, including password
  and authentication-version preservation.
- Initial administrator provisioning uses a shared cache lock and rechecks
  existence after prompts. Its contention and recheck cases pass. The normal
  configured database cache is shared across processes; alternative deployment
  cache configuration must retain shared locking.

No new confirmed bug was found. Existing accounts originally created with
passwords above 72 bytes still need administrator resets; hashes cannot identify
those original lengths. The three unrelated Phase 0 setup findings remain open
in PHASE_0_AUDIT.md.

## Checks executed

| Command/check | Result |
| --- | --- |
| `composer test` | Passed: 45 tests / 611 assertions |
| `scripts/verify-test-isolation.ps1` | Passed: 45 tests / 611 assertions with inherited production/MySQL settings |
| Pint for application and scripts | Passed |
| MySQL migration status | Passed: same four migrations Ran; no migrations executed |
| MySQL readiness command | Passed |
| `scripts/verify-auth.php` | Passed: four-role guard/gate checks, inactive-account rejection, rollback |
| `scripts/verify-auth-concurrency.php` | Passed: reciprocal deactivation and demotion across separate MySQL processes; temporary accounts removed |
| Two independent isolated audit probes | Passed: 2 tests / 78 assertions |

The independent probes verify the 20-per-minute route limit across distinct emails
on one IP and recovery after timeout, PATCH authorization, literal leading/trailing
password whitespace, and HTML escaping of an account name. They are retained in
ignored `.local/phase1-reaudit/IndependentChecksTest.php`, not counted among the
45 permanent tests. Reproduce from `application/`:

```powershell
php vendor/phpunit/phpunit/phpunit -c phpunit.xml ../.local/phase1-reaudit/IndependentChecksTest.php --colors=never --testdox
```

MySQL and the HTTP application were initially stopped. Only the existing MySQL
instance was started for checks with its existing configuration; it was stopped
afterward to restore the initial state. No HTTP server or browser was started.

## Remaining test coverage and validation gaps

- Full authenticated browser flows with multiple cookie jars and persistent MySQL
  sessions, including password reset, self-edit, deactivation/reactivation, and
  role changes across truly separate clients.
- Concurrent duplicate-email creates/edits against MySQL. Current permanent
  conflict regressions use deterministic SQLite events; the MySQL process test
  targets reciprocal access changes rather than uniqueness races.
- Actual concurrent CLI bootstrap processes and cache-lock expiry/failure paths.
  The permanent suite covers contention and an administrator appearing after
  prompts, using isolated test storage.
- Pagination, session expiry, broad concurrent load, and migration downgrade or
  failure/recovery scenarios.
- Visual layout/accessibility, office-network operation, production HTTPS/session
  settings, backups/recovery, and deployment acceptance.
- The independent route-limit/PATCH/escaping/whitespace probes are not integrated
  into the normal suite. No coverage percentage was measured.

These are remaining verification limits, not demonstrated missing functionality.
Phase 1 remains Implemented and tested. No authorization to start Phase 2 is
inferred from this audit.
