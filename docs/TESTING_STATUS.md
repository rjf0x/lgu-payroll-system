# Testing status

Updated: 2026-10-25. Phase 1 audit findings fixed and regression-tested;
45 tests / 611 assertions pass. See PHASE_1_AUDIT.md for current status and history.

## Automated checks

`application/tests/Feature/FoundationTest.php` covers:

1. Home page redirects unauthenticated users to login (updated in Phase 1).
2. Application boot health endpoint responds.
3. Base migrations support persistence of a fictional factory record.
4. Readiness succeeds with the base schema.
5. Readiness fails when required base tables are missing.
6. Readiness fails cleanly when the database is unavailable.

These checks use in-memory SQLite with RefreshDatabase. The home-page feature test
disables Vite lookup; a separate built-asset HTTP check must verify real assets.

## Execution log

| Check | Result |
| --- | --- |
| Composer dependency installation and platform compatibility | Passed: Laravel 13.38.0 installed; `composer check-platform-reqs` succeeded |
| Composer manifest and lock validation | Passed: `composer validate --strict` |
| PHPUnit suite (`composer test`) | Passed: 6 tests, 14 assertions; final run 1.43 seconds |
| PHP formatting (`php vendor/laravel/pint/builds/pint --test`) | Passed for application and, separately, `../scripts` |
| npm dependency installation and production asset build | Passed: 87 packages; `npm.cmd run build` with Vite 7.3.7 |
| MySQL migrations and migration status | Passed: users, cache, and jobs migrations all reported Ran |
| MySQL readiness command | Passed before and after database restart |
| MySQL persistence smoke check (`php ../scripts/verify-mysql.php`) | Passed: fictional account write/read and rollback; no account retained |
| HTTP startup, health, and compiled local assets | Passed: `/`, `/up`, compiled CSS and JavaScript all returned HTTP 200 |
| External/development asset references on live page | Passed: no Google/Bunny font CDN, Tailwind CDN, or Vite dev client references |
| Local MySQL helper repeat start and stop/restart | Passed: existing instance recognized, clean shutdown, restart, readiness preserved |

The initial sandboxed npm attempt was interrupted after failing to complete;
the installation succeeded outside the sandbox. npm reported an unapproved
optional esbuild install script; the production build succeeded without enabling
additional package lifecycle scripts.

The scaffold initially ran its automatic base migration step. A subsequent
explicit migration check found no pending migrations on the configured MySQL
database. The smoke script explicitly requires the MySQL driver, so SQLite
coverage is not being presented as MySQL validation.

No test is reported as passed before execution. There were no existing application
tests or features at initial inspection.

## Not yet verified

Production deployment, full offline office-network operation, visual browser review,
backups/recovery, and all Phase 2-13 workflows. Payroll formulas and regulatory
procedures require validation before their implementation and activation.

## Audit and commit review

See [PHASE_0_AUDIT.md](PHASE_0_AUDIT.md) for the unresolved findings and missing
coverage. A configuration-only probe confirmed inherited database variables can
override PHPUnit's defaults. For commit validation, the test command explicitly
pins APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, and an empty
DB_URL in its own process; this precaution does not fix the committed test setup.

The pre-commit run passed: 6 tests and 14 assertions (1.34 seconds), MySQL
readiness and transactional persistence smoke check, Pint for application and
scripts, Vite production build, strict Composer validation, and platform checks.
The candidate-file scan found no private-key/token/debugging patterns or matches
for the generated local database passwords and application key. Git ignore checks
exclude local credentials, database files, installed dependencies, and built assets.
The first staged whitespace check flagged three blank lines in the unused Laravel
starter view; trailing spaces were removed and the subsequent check passed.

## Phase 1 verification

Commands run from `application/` unless specified:

| Check | Result |
| --- | --- |
| `composer test` | Passed: 26 tests, 350 assertions, including six foundation tests |
| `php vendor/phpunit/phpunit/phpunit --colors=never` with inherited MySQL DB_CONNECTION/DB_DATABASE/DB_URL | Passed: 26 tests, 350 assertions; SQLite isolation enforced |
| `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify-test-isolation.ps1` from project root | Passed: 26 tests, 350 assertions, also overriding inherited APP_ENV=production |
| `php vendor/laravel/pint/builds/pint --test` and `php vendor/laravel/pint/builds/pint ../scripts --test` | Passed |
| `composer validate --strict` | Passed |
| `npm.cmd run build` | Passed: local Vite production assets generated |
| `php artisan migrate --force` and `php artisan migrate:status` | Passed: access-fields migration applied to local MySQL; all four migrations Ran |
| `php artisan app:check-foundation` | Passed against configured MySQL |
| `php ../scripts/verify-mysql.php` | Passed: foundation persistence and rollback |
| `php ../scripts/verify-auth.php` | Passed: MySQL login, four-role gate matrix, inactive-account rejection and rollback; no fictional accounts retained |
| Live HTTP checks at 127.0.0.1:8000 | Login and health HTTP 200, CSRF field present, missing-token login HTTP 419, four compiled asset references HTTP 200 |
| `php artisan route:list --except-vendor` and `git diff --check` | Passed; protected account and role routes present; no whitespace errors |

`AuthenticationTest` covers login for every role, the workspace access/navigation
matrix, guest restrictions, invalid/inactive credentials, hostile input shapes,
throttling and recovery, session rotation/logout, stale/inactive session rejection,
and CSRF on login, account creation/editing, and logout. The CSRF test explicitly
enables enforcement normally bypassed by Laravel's test environment.

`UserManagementTest` covers administrator account creation for every role,
password hashing, sensitive-field exclusion, email normalization/uniqueness,
invalid/weak input and duplicate submissions, account edits/deactivation/reset,
blank-password preservation, session revocation, immediate role changes, self
deactivation/demotion prevention, non-admin direct-action denials, initial-admin
command validation/reuse prevention, and the absence of seeded shared accounts.

Initial runs found a time-sensitive throttle assertion and a test guard that did
not simulate fresh HTTP authentication state; both tests were corrected. The
inherited-variable run then proved forced `env` settings alone insufficient because
Laravel reads `$_SERVER` first. Adding pinned `server` settings resolved this;
the configuration guard safely refused to migrate during the failing probe.

Phase 1 does not establish real office deployment, visual/accessibility acceptance,
live authenticated browser operation, or client payroll/approval rules. Real
administrator bootstrap remains an operator action. The MySQL smoke check exercises
the session guard and gates directly; HTTP feature workflows use isolated SQLite.

## Phase 1 audit follow-up

See [PHASE_1_AUDIT.md](PHASE_1_AUDIT.md) for the acceptance matrix, five defects,
reproduction details, and missing coverage. The existing suite was rerun and
passed 26 tests / 350 assertions; the inherited-environment regression also passed.
Seven ignored diagnostic probes passed 46 assertions confirming erroneous
password handling, HTTP 500 error paths, and a deterministic concurrent-request
administrator lockout. These are defect reproductions, not successful acceptance
tests. They have not been added to the normal suite or used to change application
code. MySQL was initially stopped; after starting the existing instance, migration
status, readiness, and the authentication/authorization rollback smoke check passed.
The instance was stopped after the audit to restore its prior state.

## Phase 1 corrections - final verification

All five confirmed findings are fixed in the current Phase 1 implementation.
`tests/Feature/AccessRegressionTest.php` adds 19 permanent regression cases for
unsupported password inputs across create/edit/login/bootstrap, ASCII/UTF-8 byte
boundaries, legacy suffix rejection, literal hash-shaped passwords, redirect-safe
rejected input, create/update uniqueness conflicts with full rollback, reciprocal
edits and creation by revoked in-flight actors, and bootstrap locking/rechecks.

| Check | Result |
| --- | --- |
| `composer test` | Passed: 45 tests / 611 assertions; all original tests retained |
| `scripts/verify-test-isolation.ps1` | Passed: 45 tests / 611 assertions with conflicting inherited production/MySQL settings |
| Pint for application and scripts | Passed |
| `npm.cmd run build` | Passed |
| `composer validate --strict` | Passed |
| MySQL `migrate:status` and `app:check-foundation` | Passed: same four migrations Ran; no new migration or schema changes |
| `scripts/verify-auth.php` | Passed: four-role authentication/authorization, inactive rejection, rollback |
| `scripts/verify-auth-concurrency.php` | Passed: separate MySQL processes for reciprocal deactivation and demotion; second writer observed waiting on locks and denied after revocation; one active administrator preserved |

The concurrency script temporarily commits four randomly named fictional accounts
to permit separate connections, then removes only those allocated IDs. It does
not migrate or reset the database. Existing accounts and configuration are retained.
The existing MySQL instance was started for checks and stopped afterward to restore
its prior state.

The first new-test run exposed fixture comparison differences (fresh database
booleans/attribute ordering) and missing session-cookie forwarding in the test
client. Assertions now compare fresh records and follow redirects with the actual
cookie, exercising persisted JSON flash errors and their rendered messages. No
assertions were removed to accept the original defects.

Still unverified: complete multi-client authenticated browser acceptance, visual
layout/accessibility, the route/IP throttle edge cases, broad concurrent load,
and production deployment. MySQL duplicate-conflict error handling is covered by
the shared exception path and isolated SQLite HTTP regressions; the MySQL process
check specifically exercises reciprocal account access changes. The three unrelated
Phase 0 setup findings remain open.

## Phase 1 post-correction re-audit

See [PHASE_1_REAUDIT.md](PHASE_1_REAUDIT.md) for the current acceptance matrix and
remaining coverage gaps. No new confirmed defect or missing Phase 1 task area was
identified. Application code and the permanent test suite were unchanged.

- `composer test`: passed again, 45 tests / 611 assertions.
- Inherited-environment isolation regression: passed, 45 tests / 611 assertions.
- Pint for application and scripts: passed.
- MySQL migration status/readiness and four-role guard/gate smoke checks: passed.
- MySQL two-process reciprocal deactivation/demotion: passed; test accounts removed.
- Two independent ignored audit probes: passed, 2 tests / 78 assertions. These
  exercise the shared-IP route limit/recovery, PATCH authorization, HTML escaping,
  and preservation of password whitespace; they are separate from the 45-test suite.

The existing MySQL instance was started for checks and stopped to restore its
initial state. No browser/HTTP server was started; live browser sessions, broader
MySQL uniqueness races, bootstrap process/lock-failure cases, pagination, session
expiry, accessibility and deployment remain unverified. No coverage percentage is
claimed. The unrelated Phase 0 setup findings remain unresolved.
