# Phase 1 audit - Authentication and User Roles

Date: 2026-10-25. Scope: Phase 1 only. The original audit made no application
fixes. A subsequent authorized Phase 1 follow-up corrected the findings below.

## Current follow-up status

The post-correction re-audit is recorded in [PHASE_1_REAUDIT.md](PHASE_1_REAUDIT.md).
It found no new confirmed defect and revalidated the Phase 1 acceptance checks.

Status: Implemented and tested. All five confirmed findings are resolved within
Phase 1. No Phase 2 features or new migrations were introduced.

| Finding | Correction | Verification |
| --- | --- | --- |
| Reciprocal administrator lockout | Lock actor and target in ID order, recheck current role/active status/session version within the write transaction | Permanent stale-request regressions; separate MySQL processes prove a second writer waits and is denied after deactivation/demotion |
| Password suffix truncation | Reject passwords above 72 bytes on login/create/edit/bootstrap | ASCII and UTF-8 boundary tests; full 72-byte credential round trips; legacy suffix login rejected |
| Null-byte password crash | Shared input rule rejects null bytes before hashing | Login/create/edit/bootstrap validation and unchanged-record assertions |
| Error-form crashes | Type-check flashed input before rendering; safe role/status defaults | Redirect-following tests preserve actual session cookies and display validation errors on login/create/edit forms |
| Unique-write crashes | Catch unique-constraint exceptions outside the transaction and return email errors | Create/update conflict tests verify renderable errors and complete rollback, including password/authentication version |

The review also protected overlapping initial-administrator commands using a
shared lock and an existence recheck after prompts, and ensured hash-shaped
password text is hashed as a literal credential.

`composer test` passed 45 tests / 611 assertions. MySQL migration status, readiness,
role/authentication smoke checks, and reciprocal deactivation/demotion across two
processes passed. See TESTING_STATUS.md for the full execution record. The old
ignored diagnostic file below documents pre-fix behavior and is not the current
regression suite; use `AccessRegressionTest.php` for current expected behavior.

Remaining verification: authenticated multi-client browser acceptance, layout and
accessibility, IP route-throttle edge cases, broad load testing, and production
deployment. The unrelated three Phase 0 setup findings remain open. Previously
accepted overlong passwords need an administrator reset; their original length
cannot be recovered from bcrypt hashes.

## Acceptance result (original audit)

The normal Phase 1 workflows pass the existing acceptance tests, but five defects
remain. Overall status: Implemented but needs validation. Resolve the defects and
add regression coverage before treating access management as fully accepted.

| Criterion / task | Evidence | Assessment |
| --- | --- | --- |
| Each of the four roles can log in | Four-role HTTP feature data set and MySQL guard smoke check | Pass for ordinary valid credentials; password edge cases fail |
| Restricted actions reject unauthorized users | Workspace gate matrix; guest/non-admin account action denials; stale/inactive session checks | Pass for new requests; in-flight revocation/administrator availability has a concurrency defect |
| Authorization tests pass | `composer test`: 26 tests, 350 assertions | Pass within current coverage |
| Login/logout and account management | Session rotation, logout, create/edit, password reset, activation/deactivation | Implemented; input/error handling and concurrent writes need correction |
| Four roles and role-specific navigation | Role enum, database constraint, server gates, dashboard/workspaces | Implemented and covered |

No Phase 1 module is wholly missing. Password recovery by email, employee records,
payroll preparation/approval, and reports are not Phase 1 acceptance requirements.
The role workspace landing pages correctly defer those later-phase operations.

## Findings

1. **High - Concurrent administrator edits can remove all active administrators.**
   `application/app/Http/Controllers/UserController.php:39` locks only the target
   account and does not preserve an active-administrator invariant or recheck the
   actor inside the transaction. `SaveUserRequest.php:39` prevents self-deactivation
   but not reciprocal requests that already passed authorization. A deterministic
   interleaving validates A's request to deactivate B, lets B deactivate A, then
   resumes A's controller write. Both administrator accounts become inactive.
   The bootstrap command (`CreateAdministrator.php:21`) refuses recovery because
   an administrator still exists. This requires overlapping authorized requests;
   it is not a demonstrated unauthenticated escalation. Reproduced using isolated
   SQLite and the real request validator/controller, not a simultaneous MySQL load
   test. Missing behavior: atomic actor validation and preservation of at least
   one usable administrator across access changes.

2. **Medium - Accepted long passwords are silently truncated by bcrypt.**
   `SaveUserRequest.php:33` and `CreateAdministrator.php:36` allow 1,024 characters;
   the installed default bcrypt hasher only uses the first 72 bytes. A strong
   password made from a 72-byte prefix plus `FIRST` creates an account; login with
   the same prefix plus `SECOND` succeeds. Reproduced through account creation and
   login HTTP feature requests. Multibyte passwords reach the byte boundary sooner.
   Missing behavior: a password policy compatible with the configured hasher and
   explicit validation instead of silently ignoring part of the credential.

3. **Medium - Null-byte passwords pass validation and crash account creation.**
   `SaveUserRequest.php:33` accepts a confirmed mixed-case numeric password
   containing a null byte. The hashed model cast calls bcrypt, which rejects it;
   POST `/users` returns HTTP 500. No account is inserted. Reproduced in an HTTP
   feature probe. Initial provisioning uses the same unrestricted rule, but its
   null-byte failure was not separately executed. Missing behavior: controlled
   validation of input unsupported by the hasher.

4. **Medium - Malformed input crashes forms after validation redirects.**
   `resources/views/auth/login.blade.php:12` prints `old('email')` as a scalar.
   Posting `email[]=bad` correctly produces a validation error, but GET `/login`
   then returns HTTP 500 because the flashed value is an array. The account form
   has the same issue for `name` (`users/form.blade.php:9`); an array `is_active`
   also crashes its string casts at line 26. All three were reproduced. The
   existing hostile-input tests stop at the validation response and miss the next
   GET. Missing behavior: safe rendering of rejected input and usable error forms.

5. **Medium - Duplicate submissions after validation produce an unhandled error.**
   `UserController.php:27` saves after a separate unique-email validation query.
   If another submission inserts the same email between validation and save, the
   unique constraint prevents duplicate storage, but the response is HTTP 500.
   Reproduced by inserting the competing account in the model's creating event,
   which deterministically simulates the window; no parallel MySQL run was used.
   Missing behavior: translate the constraint conflict into a validation/conflict
   response. Update collisions have the same code-review risk and were not
   separately executed.

## Tests executed during this audit

| Check | Observed result |
| --- | --- |
| `composer test` | Passed: 26 tests, 350 assertions |
| `scripts/verify-test-isolation.ps1` | Passed: 26 tests, 350 assertions with inherited production/MySQL settings |
| `php vendor/laravel/pint/builds/pint --test` | Passed |
| `php artisan migrate:status` after starting existing local MySQL | Passed: all four migrations Ran; no migration executed |
| `php artisan app:check-foundation` after starting MySQL | Passed |
| `php ../scripts/verify-auth.php` after starting MySQL | Passed: four-role authentication/authorization, inactive-account rejection, rollback; no test accounts retained |
| Seven isolated diagnostic probes | 7 passed, 46 assertions **confirming the defects above**, not accepting those behaviors as correct |

The first MySQL checks failed because loopback port 3307 refused connections. The
existing server was started with its existing configuration; no credentials or
configuration were changed. Checks were rerun successfully. The server was stopped
afterward to restore its initial state. This was an environment prerequisite, not
evidence of an application authentication defect.

Diagnostic probes are retained locally in ignored
`.local/phase1-audit/Phase1AuditTest.php`. Reproduce from `application/`:

```powershell
php vendor/phpunit/phpunit/phpunit -c phpunit.xml ../.local/phase1-audit/Phase1AuditTest.php --colors=never --testdox
```

These probes inherit the application's guarded in-memory SQLite configuration and
do not modify the configured MySQL data. The initial probe run had a test-harness
route-binding error; it was corrected before the successful diagnostic run.

## Coverage gaps recorded at the original audit

Existing coverage includes all four roles, direct route/action denials, basic
account lifecycle, validation, hashing, logout, session rotation/revocation,
email uniqueness/normalization, five-attempt throttling/recovery, CSRF, bootstrap
validation, and absence of shared seeded credentials. Six tests retain foundation
coverage. No coverage percentage was measured.

Missing permanent regression coverage:

- The five findings above, including redirect-following error-form assertions,
  password byte boundaries/multibyte cases, and conflicting concurrent writes.
- Concurrent first-administrator bootstrap; its existence check precedes user
  prompts and is not atomic (code-review risk, not separately reproduced).
- The independent 20-per-minute route/IP throttle and multi-user shared-IP behavior.
- Complete authenticated browser workflows backed by persistent MySQL sessions,
  including two genuinely separate clients during reset/deactivation/self-edit.
- MySQL concurrency, enum/unique-conflict behavior, migration upgrade/downgrade,
  paginated account lists, and browser layout/accessibility acceptance.

The MySQL smoke test checks the guard and gates directly; it does not prove the
full HTTP middleware/session pipeline against MySQL. Existing session tests use
the array driver and sometimes simulate another request with `Auth::forgetGuards`.
The SQLite suite therefore does not establish browser cookie persistence.

The original recommendation was to correct and regression-test Phase 1 before
proceeding. That follow-up is now complete as recorded above. The three unresolved
Phase 0 setup findings remain in PHASE_0_AUDIT.md; unvalidated payroll rules and
all Phase 2+ modules remain unchanged.
