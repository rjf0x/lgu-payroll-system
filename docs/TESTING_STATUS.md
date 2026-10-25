# Testing status

Updated: 2026-10-25. Phase 3 passes 111 tests / 1683 assertions. The two Phase 2
defects in PHASE_2_AUDIT.md remain open and were not changed in Phase 3.

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
backups/recovery, and all Phase 4-13 workflows. Payroll formulas and regulatory
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

## Phase 2 employee management

`EmployeeManagementTest.php` adds 31 cases covering:

- Create/retrieve/edit for permanent, coterminous, and part-time records, including
  normalized identifiers, nullable names, active status, and exclusion of injected
  salary/role/version fields.
- All four roles' route/action permissions and navigation, guest restrictions,
  disabled users, stale sessions, and in-flight writer revocation.
- Invalid required fields, lengths, dates, classification/status values, malformed
  arrays, and redirect-following rendering of rejected input with session cookies.
- Duplicate/repeated identifiers and database uniqueness races on create/edit,
  with full rollback; stale/missing versions cannot overwrite saved records.
- Full-name and other field search, literal wildcard escaping, bound injection
  attempts, empty results, stable pagination, inactive records, invalid search
  parameters, CSRF enforcement, missing records, and absence of deletion.

| Check | Result |
| --- | --- |
| `composer test` | Passed: 76 tests / 1040 assertions, including all 45 previous cases |
| `scripts/verify-test-isolation.ps1` | Passed: 76 tests / 1040 assertions with inherited production/MySQL settings |
| `php vendor/laravel/pint/builds/pint` for application and scripts | Passed; no formatting changes required |
| Pint `--test`, employee route listing, and `git diff --check` | Passed; six employee routes registered, no delete route |
| `npm.cmd run build` | Passed |
| `composer validate --strict` | Passed |
| `php artisan migrate --force` and `migrate:status` | Passed: employee migration applied on MySQL; all five migrations Ran |
| `php artisan app:check-foundation` | Passed against MySQL |
| `php ../scripts/verify-employees.php` | Passed: three classifications, create/read/edit, role policy, literal multiword search, stale-edit rejection, and rollback; no fictional records retained |

The first full run passed 75 cases and failed a new test helper that assumed login
always redirects to the dashboard. Laravel correctly preserved the originally
requested employee URL; the helper now asserts the actual intended destination.
The subsequent full run passed. No existing authentication test was changed.

HTTP feature tests use guarded in-memory SQLite and array sessions. The MySQL
employee smoke check uses the real validator, controller, policies, and SQL inside
an outer rollback transaction, not a live authenticated browser. No coverage
percentage is claimed. Visual/browser acceptance, actual simultaneous employee
writes, client-specific numbering/record visibility, and production deployment
remain unverified. Compensation rules and salary history are not Phase 2 features.

## Phase 2 audit follow-up

See [PHASE_2_AUDIT.md](PHASE_2_AUDIT.md) for the acceptance assessment, findings,
reproduction commands, and missing coverage. The existing suite was rerun and
passed 76 tests / 1040 assertions; Pint also passed for application and scripts.
The inherited production/MySQL isolation regression passed the same 76 tests /
1040 assertions.
MySQL migration status/readiness and the normal employee workflow smoke check
passed. Two isolated audit tests passed 12 assertions: one reproduces the omitted
suffix search, and one positively checks valid Unicode/HTML escaping. Seven MySQL
invalid-encoding paths were reproduced, with validation passing followed by error
1366 and HTTP 500; the failed edit remained unchanged and all fixtures rolled back.
These diagnostics confirm defects and are separate from the normal test suite.
No application code was changed during the audit. MySQL was started temporarily
and stopped afterward to restore its initial state.

## Phase 3 salary management

`SalaryManagementTest.php` adds 35 cases covering:

- Draft schedule/rate creation and editing, publication authority/confirmation,
  duplicate dates/grade-step pairs, decimal boundaries and invalid formats.
- Initial assignments, step increases, different-grade promotions and explicit
  schedule updates for all three classifications; exact amount/date/source lookup,
  demo/real-mode isolation, missing/future/wrong-category/unpublished sources.
- Unchanged historical snapshots, immutable published headers/rates and salary
  records, preserved service basis on schedule updates, explicit category changes,
  no future position rewrite, and no automatic salary replacement.
- All four roles' schedule and employee-salary permissions; guests, CSRF, inactive
  employees, revoked in-flight administrators/officers, and invalid query dates.
- Three-year provisional review (including leap day), safe malformed inputs,
  external reference/reason validation, and encrypted JSON error-session persistence.

| Check | Result |
| --- | --- |
| Salary-focused PHPUnit suite before final two cases | Passed: 33 cases / 607 assertions |
| `composer test` after final changes | Passed: 111 tests / 1683 assertions; all earlier tests retained |
| `scripts/verify-test-isolation.ps1` | Passed: 111 tests / 1683 assertions with conflicting inherited production/MySQL settings; repeated run encountered the existing employee-search fixture failure described below |
| Pint application formatter and script `--test` | Passed; application formatting applied |
| `npm.cmd run build` | Passed |
| `composer validate --strict` | Passed |
| MySQL `migrate --force` and `migrate:status` | Passed: salary migration applied; all six migrations Ran |
| `php ../scripts/verify-salaries.php` | Passed: all categories, effective schedules, exact decimals, initial/step/promotion/schedule changes, immutable history and rollback; no fictional records retained |
| `git diff --check` | Passed |

One additional test initially tried to decode encrypted session storage directly
as JSON. It now decrypts through the actual session encrypter before asserting
valid JSON. No production session setting was changed. Rejected text is not saved
as salary data. The tests exercise HTTP controllers with isolated SQLite; the
MySQL smoke check exercises real validators, service transactions and persistence
inside an outer rollback transaction, not a live browser.

A repeated isolation run returned 111 tests / 1662 assertions with one failure
in the unchanged Phase 2 employee-search test (line 229): it expected only the
target employee but also received a randomly generated employee. The fixture
leaves other employees' first names random, which can match the `maria` query.
This earlier-phase fixture instability is not corrected in Phase 3. All salary
cases passed in that run. The final rerun after the salary source-lock refresh
passed all 111 tests / 1683 assertions. The existing project MySQL instance was stopped after
the checks to restore its initial state.

Still unverified: real client schedule/rule/authorization examples, visual and
multi-client browser acceptance, simultaneous schedule publication/assignment races,
exhaustive database failure/recovery, and deployment. No coverage percentage or
official category eligibility is claimed. No payroll batch/calculation work was
introduced, and earlier Phase 0/2 findings remain documented.

## Phase 3 audit follow-up

Both `composer test` and `scripts/verify-test-isolation.ps1` passed 111 tests /
1683 assertions. Application and script Pint checks passed. The rollback-only
MySQL salary workflow passed with no fictional records retained; the temporarily
started project database was stopped to restore its initial state.

Three ignored diagnostic cases (65 assertions) reproduced a superseded schedule
returned by salary lookup, demonstration assignments interfering with an existing
real history, and a lower-grade/lower-pay move recorded as a promotion. These are
observational probes, not successful acceptance tests for the affected behavior.
PHASE_3_AUDIT.md records evidence, severity, policy gaps and missing permanent
coverage. No application fixes or Phase 4 functionality were implemented.

## Phase 3 commit verification - 2026-10-25

- `composer test`: passed, 111 tests / 1683 assertions.
- Application and script Pint `--test`: passed.
- `composer validate --strict` and `npm.cmd run build`: passed.
- MySQL salary smoke check and migration status: passed; all six migrations Ran,
  all fictional fixtures rolled back, and the temporarily started project database
  was stopped afterward.
- Reviewed the phase files for sensitive paths, credential patterns and debugging
  markers; none found. Environment files, local credentials/diagnostics, generated
  build assets and installed dependencies remain ignored and excluded.

The existing Phase 3 audit findings remain open; successful automated checks do
not establish full acceptance. No application fixes or later-phase work were
introduced during commit preparation.
