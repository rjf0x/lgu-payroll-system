# Testing status

Updated: 2026-10-28. BESU-00 repository assessment passes the current full
in-memory SQLite suite: **615 tests / 7568 assertions**, 3m25.741s, 102 MB.
Composer strict/platform checks and read-only MySQL readiness/migration checks
pass. No real Besu network/contract/transaction was tested; Docker engine is
unavailable. See [BESU_00_ASSESSMENT.md](BESU_00_ASSESSMENT.md).

Earlier Phase 12-A verification/history passed full isolation:
537 tests / 6539 assertions, including all 505 earlier cases. Its audit found no
new confirmed supported-path bug. Genuine blockchain
verification remains incomplete without an authorized network; correction versions
are deferred to Phase 12-B. Earlier audit/client/operational limits remain.

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
backups/recovery, live simultaneous calculation/source writes, large-batch performance,
real payroll calculations, nonzero attendance and all Phase 7-13 workflows. Payroll formulas and regulatory
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

## Phase 4 payroll period management

`PayrollPreparationTest.php` adds 37 cases, including datasets, covering:

- Create/list/reopen an empty or covered monthly draft; all three categories and
  both data-use modes; exact decimal amounts and selected salary record IDs.
- Four-role read/write matrix, guests/CSRF, revoked role and expired actor checks.
- Duplicate month/mode and employee coverage rejection, repeated submissions,
  stale draft versions, cross-batch removal, and atomic rollback on invalid members.
- Invalid months, data-use values and selection shapes; safe old inputs and
  malformed UTF-8 rejection; supported year storage boundaries.
- First-day employment/reference conventions; missing/future/inactive/mismatched
  category/mode/amount sources, corrected employment dates and superseded schedules.
- Newest-record selection without cross-mode fallback, first-day change inclusion,
  mid-month salary/schedule rejection and leap-day/month-end boundaries.
- Saved-value preservation, stale source/profile warnings, explicit remove/re-add
  refresh without changing salary history, and paginated coverage preservation.

| Check | Result |
| --- | --- |
| Initial Phase 4 focused suite | Passed: 31 cases / 272 assertions before final additions |
| `composer test` before final source guards/boundary cases | Passed: 143 tests / 1966 assertions |
| Final `scripts/verify-test-isolation.ps1` | Passed: 148 tests / 1997 assertions with inherited production/MySQL settings |
| Application and scripts Pint `--test` | Passed; formatting applied to new code |
| `npm.cmd run build` and `composer validate --strict` | Passed |
| MySQL `migrate --force` and `migrate:status` | Passed: two new payroll draft tables; all seven migrations Ran |
| `php ../scripts/verify-payroll-drafts.php` | Passed twice, including after final service change: both modes, all categories, create/reopen, exact snapshots/source checks, duplicate protection and fixture rollback |
| `php artisan app:check-foundation` | Passed against local MySQL |
| Route inventory | Seven new payroll batch/coverage routes present; no calculation or approval endpoints |
| `git diff --check` | Passed; normal Git LF/CRLF notices |

An earlier isolation run returned 148 tests / 1976 assertions with one failure in
the unchanged Phase 2 random employee-search fixture (EmployeeManagementTest.php:229).
It expected the target alone but received another generated employee. This is the
same instability documented during Phase 3; all Phase 4 cases passed. The final
isolation rerun passed all 148 cases; the earlier failure is not concealed or fixed
as part of Phase 4.

HTTP/Blade feature cases use isolated in-memory SQLite. The MySQL smoke check uses
fictional model fixtures and validated payroll requests/service transactions within
an outer rollback transaction. It is not a browser or simultaneous-writer test.
The project MySQL instance was started temporarily, the salary/draft migrations
were retained, all smoke fixtures rolled back, and the server stopped afterward.

Unverified: client batch grouping and first-day/month-changing salary conventions,
actual salary/eligibility/authority examples, browser visual acceptance, concurrent
MySQL publication/assignment/coverage races, large-office performance, deployment
and backup/recovery. Per-entry source checks cover the visible page; no whole-batch
approval/readiness or calculated pay is claimed. Phase 2/3 findings remain open and
Phase 5 inputs were not implemented.

## Phase 4 audit follow-up

The full PHPUnit suite and inherited-environment isolation regression each passed
148 tests / 1997 assertions. Application/script Pint and whitespace checks passed.
MySQL verified create/reopen, both modes, all categories, exact source snapshots,
duplicates and rollback; all seven migrations already showed Ran. The project
database was started temporarily and stopped to restore its initial state.

Three ignored probes (3 cases / 36 assertions) passed actual employee/salary
authorization HTTP integration into a real-mode draft with distinct rates,
unpublished-schedule handling, and employee-selection pagination/read-only actions.
These extend diagnostic evidence rather than permanent suite coverage.

No new Phase 4 defect was reproduced. PHASE_4_AUDIT.md records the qualified
acceptance result, missing real/client/concurrent/browser/performance evidence and
the historical-employment/mid-month limitations. Earlier Phase 2/3 findings remain
open. No application fixes or Phase 5 implementation were performed.

## Phase 4 commit verification - 2026-10-25

- `composer test`: passed, 148 tests / 1997 assertions.
- Application and scripts Pint `--test`: passed.
- `composer validate --strict` and `npm.cmd run build`: passed.
- MySQL payroll draft workflow and migration status: passed; all seven migrations
  Ran. All fictional fixtures rolled back; the temporarily started database was
  stopped to restore its initial state.
- Candidate files were reviewed for sensitive paths, secret/debug markers and
  exact local application/database credential values; none matched. Environment
  files, local credentials/diagnostics, dependencies and generated assets remain
  excluded. The Phase 4 audit's client-policy and verification limits still apply.

No application fixes or Phase 5 functionality were introduced during preparation.

## Phase 5 earnings and deduction inputs

`PayrollInputTest.php` adds 34 cases, including datasets, covering:

- Independent employee allowance/bonus/loan/adjustment/attendance persistence for
  all three classifications, with real-mode and demonstration examples.
- Excluded allowance retention and filtered applicability, multiple-line exact
  subtotals, zero/maximum monetary values, normalization, invalid money formats,
  and ignored forged salary/status/total fields.
- Complete revision edits, original/replacement earning and deduction adjustments,
  required reasons/authority/confirmation, old revision read-only display, model
  update/delete guards, and empty-current-set saves with history preservation.
- Allowed row keys, shapes/counts, case-insensitive distinct descriptions, safe
  rejected inputs, valid UTF-8/HTML escaping, and cleared rows after a validation
  error in an unrelated field.
- Calendar-month/leap bounds on absence days and integer undertime minutes;
  no calculation-formula claim is tested or made.
- All four roles, guests/CSRF, fresh actor role/activity/auth-version checks,
  stale/duplicate saves, cross-batch entry/revision scoping and bad revision queries.
- Stale employee/source rejection, retained revisions across coverage removal/
  re-selection, changed-context warnings and explicit reviewed replacement revisions.

| Check | Result |
| --- | --- |
| Initial focused PHPUnit suite | Passed: 32 cases / 372 assertions |
| Focused suite after cleared-row regression correction | Passed: 33 cases / 382 assertions |
| Final `scripts/verify-test-isolation.ps1` | Passed: 182 tests / 2387 assertions with inherited production/MySQL settings; 34 input cases plus 148 earlier cases |
| Application and scripts Pint | Passed; new code formatted, final application/script `--test` checks passed |
| `node --check resources/js/payroll-inputs.js` | Passed syntax check; not browser interaction evidence |
| `npm.cmd run build` | Passed; 59 modules, compiled CSS and JavaScript |
| `composer validate --strict` | Passed |
| MySQL `migrate --force` / `migrate:status` | Passed: input revision migration applied; all eight migrations Ran |
| `php ../scripts/verify-payroll-inputs.php` | Passed twice: all categories/modes, employee-specific values, exclusions, exact JSON decimal strings, two revisions with original values, stable history through coverage refresh and rollback |
| `php ../scripts/verify-payroll-drafts.php` | Passed after adding input schema/workflow |
| `php artisan app:check-foundation` | Passed against MySQL |
| `git diff --check` | Passed; ordinary LF/CRLF conversion notices |

An intermediate `composer test` run returned 180 passed / 1 failed (2375
assertions). The new cleared-row regression exposed that normalizing a FormRequest
alone did not preserve empty groups in the original request's flashed input.
The form now includes an inert state marker so a validation retry distinguishes
an intentionally cleared group from the initial saved defaults. The regression
was corrected and passed, followed by the final full 182-case pass. No production
session setting or earlier-phase application logic was changed.

HTTP/Blade tests use in-memory SQLite. The MySQL input script uses fictional
model fixtures, actual FormRequests and input/draft service transactions inside
an outer rollback transaction. All fictional data was rolled back. The project
MySQL server was started temporarily and stopped afterward to restore its initial
state; the new migration remains applied.

Unverified: browser add/remove-line interaction and visual acceptance, concurrent
input/coverage/source writers and failure/recovery behavior, office-scale load,
client allowance/attendance/adjustment authorization rules, and deployment.
Adjustment originals are entered reference values, not computed deductions.
Current-input history for removed coverage is retained but not active payroll
input. No loan scheduling, uploads, statutory formulas, payroll calculation totals,
tax treatment, net pay or internal approval workflow were implemented. No coverage
percentage or full real-office acceptance is claimed.

## Phase 5 audit follow-up

The full PHPUnit suite and inherited-environment isolation regression each passed
182 tests / 2387 assertions. Application/script Pint, Node syntax and whitespace
checks passed. MySQL input checks passed with fixture rollback; all eight existing
migrations showed Ran. The temporarily started database was stopped afterward.

Three ignored audit cases (38 assertions) reproduced cross-employee restoration
of rejected old input before the originating redirect consumes the flash, verified
atomic rollback on an injected post-insert batch-update failure, and checked
11-revision history pagination. The defect probe passes by asserting the observed
wrong-form values, not by asserting acceptance success. The rollback injection
used SQLite, not a real MySQL disconnection.

PHASE_5_AUDIT.md records the medium form-state defect, acceptance qualifications,
missing permanent/multi-tab/browser/concurrency evidence and client policy limits.
No application fixes or Phase 6 implementation were performed during the audit.

## Phase 5 correction verification

- Added seven permanent input cases: other employee and unsaved-recipient forms,
  same employee in another month, forged posted context, matching-form retries,
  encoding rejection, JSON errors and service validation (dataset cases included).
  Phase 5 now has 41 cases; all 148 earlier cases remain.
- The three boundary regressions first failed against the original implementation,
  reproducing cross-form values/errors. The first middleware attempt caught thrown
  exceptions, while Laravel's routing pipeline had already rendered them; three
  matching-form checks caught that mistake. Handling validation redirect responses
  corrected it. The focused suite then passed 40 cases / 466 assertions, before
  the final service-validation case was added.
- Final `composer test`: passed, 189 tests / 2472 assertions.
- MySQL `verify-payroll-inputs.php`: passed; fictional fixtures rolled back. All
  eight migrations remain Ran; no new migration was introduced for this fix.
- Pint application formatting/check, scripts Pint, strict Composer validation,
  production asset build, Node syntax and whitespace checks passed.

No live browser race or interaction test was performed. Client-policy and earlier
phase findings remain unchanged. No new features or Phase 6 work were introduced.

## Phase 5 post-correction re-audit

Full PHPUnit rerun passed 189 tests / 2472 assertions. The corrected diagnostic
suite passed 3 cases / 38 assertions. Application/script Pint, Node syntax and
whitespace checks passed. The former cross-form restoration defect no longer
reproduces; no additional confirmed Phase 5 defect or missing required task was
found. See PHASE_5_AUDIT.md for the qualified acceptance result and remaining
client/browser/concurrency/recovery coverage gaps. MySQL, build and isolation
checks were not rerun in this re-audit; the database remained stopped. No
application or Phase 6 work was performed.

## Phase 5 commit verification - 2026-10-25

- `composer test`: passed, 189 tests / 2472 assertions.
- Application and scripts Pint `--test`, JavaScript syntax check, strict Composer
  validation and production asset build: passed.
- MySQL input workflow and migration status: passed; all eight migrations Ran.
  Fictional fixtures rolled back, and the temporarily started project database
  was stopped afterward to restore its initial state.
- Reviewed all 20 phase files for sensitive paths, secret/debug markers and exact
  local application/database credential values; no matches found. Environment
  files, local credentials/diagnostics, installed dependencies and generated assets
  remain excluded. No new dependency or unrelated functionality change is included.

The audit's client-rule/browser/concurrency/recovery limitations remain documented.
No Phase 6 work or application changes were introduced during commit preparation.

## Phase 6 core payroll calculation

Permanent coverage in `PayrollCalculatorTest.php` and `PayrollCalculationTest.php`
adds 31 cases (12 calculator cases and 19 integration cases). Expected amounts
are literal independently prepared values, not generated by the engine under test.

| Prepared calculator case | Earnings | Core deductions | Core net |
| --- | --- | --- | --- |
| Basic 12345.67 + applicable allowances 100.07 + 200.08 + bonuses 500.01 + 0.02 + earning replacement 50.09; loans 200.02 + 30.03 and deduction replacement 111.11 | 13195.94 | 341.16 | 12854.78 |
| Basic salary only 1000.00 | 1000.00 | 0.00 | 1000.00 |
| Basic 100.00, loan 160.00 | 100.00 | 160.00 | -60.00 |
| Basic and 30-line groups at 9999999999.99, adjustments split 15 earning / 15 deduction | 759999999999.24 | 449999999999.55 | 309999999999.69 |

The first vector excludes an entered allowance of 999.99 and retains adjustment
originals 999.99/300.03 without counting them. Repeated results are deterministic;
payload equality verifies no source mutation. Seven invalid amount cases cover
excess precision, exponent, negative, leading zero, float, array and overflow;
unknown applicability and nonzero attendance are rejected.

Integration coverage verifies all three classifications against prepared core
earnings 12995.84, deductions 311.13 and net 12684.71. Tests compare unchanged input,
entry and batch attributes, idempotent repeats, latest input use, selected coverage,
preserved old results, source/input-context drift, missing/empty/real-mode rejection,
stale draft versions, whole-batch atomicity and injected persistence-failure rollback.
Negative net -87115.26 is visible without capping the original 99999.99 loan.
Role matrices, cross-batch scope, guests, CSRF, revoked role/auth-version and
ordinary model update/delete immutability are covered. GET pages read snapshots.

| Executed check | Result |
| --- | --- |
| Initial focused calculator/integration suite | 30 tests / 243 assertions, three failures exposed a Blade directive rendering defect in the history list; fixed within Phase 6 |
| Corrected focused suite | Passed, 30 tests / 255 assertions, before adding the negative-net UI regression |
| Final `composer test` | Passed, 220 tests / 2737 assertions (31 Phase 6 + 189 prior cases), 37.13 seconds |
| `php ../scripts/verify-payroll-calculations.php` | Passed on MySQL: all categories, exact per-item and aggregate prepared values, excluded allowances, adjustment replacements, unchanged inputs, idempotent repeats, new-run history, real-mode block and fixture rollback |
| MySQL prepared aggregate | Three employees: earnings 38987.52, deductions 933.39, core net 38054.13; increasing one loan by 100.00 creates net 37954.13 while the old run remains 38054.13 |
| MySQL migration/status | Calculation run/item migration applied; all nine migrations report Ran |
| Pint application and scripts | Formatting applied and checks passed |
| `composer validate --strict` | Passed |
| `npm.cmd run build` | Passed, 59 modules transformed |

The MySQL script validates existing HTTP input request rules then calls the
calculation service in an outer rollback transaction; it does not substitute for
browser testing or simultaneous process races. No private credentials/real employee
data are printed or seeded. The temporarily started MySQL instance is stopped
after verification to restore its initial state.

Acceptance is demonstrated for exact-decimal supported core previews and unchanged
manual inputs. Real processing, nonzero attendance formulas, category/full-month
basis, minimum-net limits and statutory rules remain inactive pending validation.
Statutory/attendance fields are null/not calculated, not assumed zero. Browser
interaction/visual acceptance, simultaneous writers/source changes and large-batch
performance were not tested. Earlier Phase 2/3 audit findings remain open. No
coverage percentage, statutory accuracy, approval/finalization, blockchain or
Phase 7 implementation is claimed.

## Phase 6 audit - 2026-10-26

[PHASE_6_AUDIT.md](PHASE_6_AUDIT.md) assesses all three acceptance criteria as
passed for supported demonstration core previews. No new application defect or
missing required demo workflow was confirmed; real payroll readiness is not claimed.

- Full `scripts/verify-test-isolation.ps1`: passed, 220 tests / 2737 assertions,
  71.280 seconds, with conflicting inherited production/MySQL settings.
- Ignored additional audit probes: passed, 3 tests / 114 assertions. Zero
  adjustment replacements do not count originals; 21 employee items / 11 runs
  paginate with correct preserved aggregates; deactivation and new mid-month
  sources block retries while preserving snapshots and inputs.
- MySQL calculation verifier: passed with fixture rollback; all nine migrations
  already Ran. No migration executed. Temporary database stopped after checks.
- Application and script Pint `--test`, whitespace checks: passed.

Permanent Phase 6 coverage remains 31 cases (12 calculator / 19 integration),
plus 189 prior cases. Extra diagnostics are not permanent regressions or performance
measurements. Browser/visual acceptance, simultaneous MySQL writes, real failure/
recovery and approved category/attendance/authorization/minimum-net/statutory
examples remain missing evidence. No application/permanent-test changes, fixes,
schema changes, commits or Phase 7 work were performed during this audit.

## Phase 6 commit verification - 2026-10-26

- `composer test`: passed, 220 tests / 2737 assertions, 35.64 seconds.
- Application/script Pint `--test`, strict Composer validation and production
  asset build: passed. No dependency/lockfile changes or generated assets included.
- MySQL core calculation verifier: passed with fixture rollback; all nine
  migrations already Ran. No migration executed during commit preparation.
- Reviewed all 20 phase files, including audit/tracking records, for sensitive
  paths, private-key/token markers, exact local application/database credential
  values and debugging code. No sensitive or unrelated files found. Environment,
  database, installed dependencies, generated assets and ignored audit diagnostics
  remain excluded. Whitespace checks passed with normal Git LF/CRLF notices.

Existing client-rule, browser, concurrency/recovery and performance limitations
remain documented in PHASE_6_AUDIT.md. No application changes or Phase 7 work
were introduced during commit preparation. The temporarily started project
database was stopped afterward to restore its original state.

## Username sign-in change after Phase 6 - 2026-10-26

This explicitly requested change replaces account email with username; it does not
implement Phase 7. Historical audit/test records above retain their original field
names. Existing tests and all MySQL verifier fixtures now use the current schema.

`UsernameAuthenticationTest.php` adds 13 permanent cases / 165 assertions:

- Username/password login, whitespace/case normalization and rejection of the old
  email-only login payload; login/account UI no longer exposes email fields.
- Account creation and renaming, case-insensitive duplicate rejection, unchanged
  password hashes on blank-password edits and session-version increment.
- Valid 3/50-character bounds and allowed punctuation; invalid short/long names,
  embedded spaces, email-shaped names, leading underscore, Unicode and array values.
- Upgrade from legacy account rows, duplicate/truncated local-part collisions,
  fallback names, ID/password/role/activity/version/timestamp preservation,
  obsolete reset-token clearing, email-column removal and migrated login.

| Executed check | Result |
| --- | --- |
| Existing access/foundation focused suite | Passed, 45 tests / 611 assertions |
| New username suite | Passed, 13 tests / 165 assertions |
| First `composer test` full run | 232 passed / 1 failed, 2881 assertions; the previously documented randomized employee-search fixture matched an additional employee at EmployeeManagementTest.php:229. No username test failed; unrelated search fixture/logic left unchanged |
| Final `scripts/verify-test-isolation.ps1` full run | Passed, 233 tests / 2902 assertions, 32.798 seconds, with conflicting inherited production/MySQL settings |
| Local MySQL upgrade migration | Applied; all ten migrations Ran. A before/after preservation fingerprint confirmed the one existing account's ID/name/password hash/remember token/role/activity/auth version/timestamps were unchanged; removed email columns and valid unique usernames checked |
| MySQL auth and concurrency verifiers | Passed: username authentication, four roles, inactive rejection, rollback, reciprocal admin deactivate/demote locking and revocation; fictional accounts removed |
| MySQL foundation, employee, salary, draft, input and core-calculation verifiers | All passed with fictional fixture rollback after converting their account identifiers to usernames |
| Application/script Pint `--test`, production build, database readiness and whitespace checks | Passed |

The local existing administrator's username is `admin`; its password was not reset
or printed. The ignored preservation diagnostic stores only a fingerprint/count,
not credential values. MySQL was temporarily started and stopped afterward.
The migration removes original email addresses; rollback restores unique placeholder
addresses rather than recovering deleted values. Email recovery remains unavailable;
password resets continue through administrators. No new reset flow, public
registration, payroll formula or Phase 7 feature was added.

Live browser interaction and office acceptance remain unverified. Earlier Phase
2/3 findings and the existing intermittent employee-search fixture issue remain
open. No Git commit was created for this change.

## User-requested UI/UX refresh after Phase 6 - 2026-10-26

The named UI UX Pro Max 2.13.0 skill was read from the local installed plugin.
The verified government/navy palette, accessible style, data-dense dashboard
result and Laravel/error-summary guidance were applied to existing screens only.
No backend rule, route permission, database schema or Phase 7 feature changed.

| Executed check | Result |
| --- | --- |
| Initial selected auth/account/calculation suite | Passed, 52 tests / 732 assertions |
| First full run with interface regressions | 237 passed / 2 failed, 2967 assertions. Both new validation tests omitted the session cookie on the redirect follow-up; corrected to carry the actual cookie, matching the existing regression helpers |
| Corrected interface suite | Passed, 6 tests / 72 assertions |
| Final `composer test` | Passed, 239 tests / 2974 assertions, 39.63 seconds (all 233 existing cases retained) |
| Pint `--test` | Passed |
| `node --check resources/js/workspace.js` and `payroll-inputs.js` | Both passed syntax checks; no browser-execution claim |
| `npm.cmd run build` | Passed, 60 modules; final CSS 79.31 kB / gzip 16.62 kB and JS 55.37 kB / gzip 20.88 kB |
| Served HTTP/asset checks on isolated review server | Passed, 36 page/asset checks across all four roles, plus username login and dashboard landmarks. Covered accounts, employees, salary lists/forms, monthly drafts, inputs and calculation history/detail as authorized |
| Measured palette contrast | Passed: text/white 10.35:1; muted/white 6.31:1; primary button 5.93:1; sidebar link 11.34:1; input border/white 3.24:1; error text 6.36:1 |
| Whitespace check | Passed |
| Browser visual review | Unavailable: browser inventory empty; creating an in-app tab returned `Browser is not available: iab` |

`WorkspaceInterfaceTest.php` covers navigation/shortcuts for all four roles, active
and controlled navigation landmarks, linked/inline login errors, exclusion of
rejected passwords, employee field errors and preservation of rejected form values.
Existing input-isolation, permissions, calculation exactness and historical
preservation regressions all still pass.

The ignored `.local/ui-review.sqlite` database was migrated/seeded solely for
served-HTTP review with explicitly fictional users, employees and monthly values.
It is separate from configured MySQL; no real account or employee was altered.
Review fixtures/build outputs are not included as application data or source assets.
The temporary review server was stopped after checks; the original MySQL runtime
state was left unchanged.

No rendered screenshots or viewport measurements were obtained. Browser menu
open/close/Escape, password toggle, field-error focus, add/remove/30-line cap,
submit pending/back-forward feedback, live screen-reader announcements, reduced
motion, 200% zoom and 375/768/1024/1440px layouts remain manual verification gaps.
Contrast values above measure specified token pairs, not every rendered state.
No accessibility certification, measured CLS, office acceptance or performance
capacity claim is made. See UI_UX_GUIDE.md for the manual review checklist.

## Phase 7 - Contributions and Withholding Tax - 2026-10-26

Scope: regulatory-reference scenarios only. The user confirmed no client-approved
rules/examples are available. Real payroll remains blocked; passing arithmetic
tests do not validate client applicability or production payroll.

| Executed check | Result |
| --- | --- |
| Existing calculator regression before integration | Passed: 12 tests / 36 assertions |
| Initial Phase 7 calculator/workflow suite | Passed: 62 tests / 330 assertions |
| Full isolation regression before MySQL retry correction | Passed: 301 tests / 3304 assertions |
| Focused Phase 7 suite after JSON-key-order correction | Passed: 63 tests / 340 assertions |
| Final full isolation regression, including cumulative-rounding/supplementary guards | Passed: 304 tests / 3319 assertions, 39.649 seconds, 70 MB; all 239 existing tests retained. Ran with conflicting inherited production/MySQL/DB_URL values and stayed on in-memory SQLite |
| New `php ../scripts/verify-statutory-previews.php` | Passed on MySQL: explicit scenarios for all three categories, literal contributions/tax/net, shared-pool bonus excess, previous withholding, retries, preserved inputs/core/rule history, null unavailable amounts, real blocking and fixture rollback |
| Existing MySQL core-calculation and payroll-input verifiers | Passed with fictional fixture rollback |
| Existing MySQL foundation/auth/concurrency/employee/salary/draft verifiers | Passed; transactional fixtures rolled back and the concurrency verifier's temporary accounts removed |
| `php artisan migrate --force`, `migrate:status`, `app:check-foundation` | Applied the previously pending username migration plus one new statutory-preview table migration. All eleven migrations Ran; database readiness passed |
| Application Pint `--test` and new verifier Pint `--test` | Passed |
| `npm.cmd run build` | Passed: 60 modules, CSS 79.38 kB / gzip 16.65 kB, JS 55.37 kB / gzip 20.88 kB |
| `composer validate --strict` | Passed |
| `git diff --check` | Passed; ordinary LF/CRLF conversion notices |

The final suite adds 65 cases / 345 assertions: 48 calculator cases and 17
workflow cases. Literal vectors cover all monthly/annual tax brackets, contribution
thresholds/floors/caps, half-cent rounding, bonus pool changes, cumulative averages
without premature monthly rounding, previous withholding and separate credits.
Missing coverage/bases/rules, unsupported years/categories, supplementary regular-
only rejection and unavailable combined totals are covered. Integration tests
exercise all roles, guest/CSRF/revocation, route ownership, source/version drift,
stale reviews, idempotency, history pagination, immutable models, persistence
failure rollback, encoding rejection and absence of cross-employee rejected values.

The first MySQL run reproduced a real retry bug: strict PHP array comparison
treated MySQL's reordered JSON object keys as changed inputs/parameters. The
service now recursively canonicalizes object keys, preserving list order and
scalar types; a permanent regression simulates reordering and rejects a changed
rate type. A subsequent preservation check initially compared an unrefreshed
newly inserted core model with its database serialization. Refreshing the smoke
baseline corrected that verifier mismatch; the passing run confirms no core/input
overwrites. Both failed attempts rolled back their fictional records.

MySQL was initially stopped and temporarily started for additive migrations and
verification. Its original stopped state was restored after checks. The new smoke
check uses a vacant far-future fictional year and an in-memory reference-year
override solely to avoid operator schedules; no configured rule file is modified.
No real employee fixtures, passwords or database dumps were added to source.

HTTP integration tests render the new form/history for authorized roles and assert
literal values, warnings and links. No live browser visual/JavaScript/viewport
acceptance was performed. No simultaneous MySQL statutory-save/source-publication
race, recovery/deadlock fault test or office-scale performance measurement was
performed. No client-approved statutory cases are claimed. See
PHASE_7_HANDOFF.md for source references, unsupported cases and browser checks.

## Phase 7 acceptance audit - 2026-10-26

- Full isolation suite rerun: passed **304 tests / 3319 assertions**, 39.778
  seconds, 68 MB. All tests stayed on in-memory SQLite under conflicting inherited
  production/MySQL/DB_URL values. No application or permanent-test changes.
- Four ignored diagnostics passed **36 assertions reproducing observed defects**:
  cumulative liability `50625.15` instead of the exact half-up `50625.16`;
  rejected statutory reason in another employee's salary form using the actual
  session cookie; associative bracket HTTP 500 with no saved review; and a zero
  savings ceiling accepted with `0.00` deduction and no issues. These are successful
  reproductions, not tests establishing correct behavior.
- MySQL statutory-preview and core-calculation verifiers passed with rollback.
  All eleven migrations were already Ran; no migration executed during this audit.
  MySQL was temporarily started and its original stopped state restored afterward.
- Application and new verifier Pint `--test` passed; whitespace check passed.
- No browser visual/interaction, statutory concurrent-writer/recovery or capacity
  tests performed. No client-approved regulatory payroll cases are available.

See PHASE_7_AUDIT.md for acceptance assessment, the two medium/one low findings,
missing/blocked functionality, test limitations and the diagnostic command. The
audit updated documentation and ignored probes only; no fixes or Phase 8 work.

## Phase 7 audit corrections - 2026-10-26

F1-F3 are corrected under the user's current-phase-only instruction. No new
payroll feature, schema change, dependency or Phase 8 work.

| Executed check | Result |
| --- | --- |
| Selected regressions before fixes | Expected failures reproduced: 12 failed / 2 passed, 52 assertions; cumulative cent boundaries, persistent-session flash and malformed bracket HTTP behavior |
| Focused statutory suite after fixes | Passed: 99 tests / 528 assertions |
| Final `scripts/verify-test-isolation.ps1` | Passed: 338 tests / 3502 assertions, 43.551 seconds, 70 MB; all 304 previous cases retained. Inherited production/MySQL/DB_URL conflicts stayed isolated in-memory |
| Expanded MySQL statutory verifier | Passed: original scenarios plus exact `50625.16` liability, negative reference net, retries, invalid zero cap and associative-bracket incomplete states; three explicit fictional categories and rollback |
| MySQL core/input verifiers | Both passed with fixture rollback and input/history preservation |
| Application/verifier Pint `--test` | Passed |
| Production build | Passed: 60 modules; CSS 79.38 kB / gzip 16.65 kB, JS 55.37 kB / gzip 20.88 kB |
| Strict Composer validation | Passed |
| Readiness/migration status | Passed; eleven migrations already Ran. No migration executed |
| Whitespace check | Passed; ordinary LF/CRLF conversion notices |

Thirty-four permanent cases were added: ten literal cumulative cent-boundary
vectors, six malformed bracket shapes, twelve invalid contribution ranges and six
workflow cases (three validation paths, persisted half-cent output, malformed-rule
incomplete output and old-engine history/retry preservation). Total Phase 7
coverage is 99 cases / 528 assertions: 76 calculator and 23 workflow cases. The
existing statutory-form isolation test now explicitly carries a session cookie.
Cross-workflow tests also check suppression of unvalidated shared fields.

Engine v2 preserves exact liability through final half-up rounding; eight-decimal
averages are display values. Legacy v1 snapshots remain unchanged; the regression
verifies a reviewed v2 revision and idempotent retries. No historical data rewrite
or reference rate change. Middleware affects statutory validation redirects only.

MySQL was initially stopped, temporarily started for rollback-only checks and
restored afterward. No real fixture, credential export, database dump or commit
was added. Historical ignored audit probes assert former defects and are no longer
the current regression command. Client-approved cases/category applicability and
rounding acceptance, live browser, statutory concurrency/recovery and office-scale
performance remain unverified. Real payroll remains disabled.

## Phase 8 - Payroll Configuration - 2026-10-26

| Executed check | Result |
| --- | --- |
| Existing statutory/interface suite after initial integration | Passed: 105 tests / 600 assertions |
| Initial new suite | 32 passed / 2 failed, 279 assertions; a Blade form loop used reserved `$component`, causing render errors. Renamed the variable |
| Corrected initial configuration suite | Passed: 34 tests / 303 assertions |
| Expanded configuration suite | Passed: 40 tests / 347 assertions before final configuration identity guard; later full run includes the activation-confirmation assertion |
| Configuration/statutory workflow suite with identity guard | Passed: 64 tests / 676 assertions |
| Final `scripts/verify-test-isolation.ps1` | Passed: 379 tests / 3866 assertions, 50.696 seconds, 72 MB; all 338 prior cases retained. Conflicting inherited production/MySQL/DB_URL values stayed isolated in-memory |
| New MySQL configuration verifier | Passed: six scopes, draft revision/snapshots, creation/activation retries, authorization, external confirmation, effective dates, duplicate prevention, preserved versions, prepared preview arithmetic and rollback |
| Updated MySQL statutory verifier | Passed with dated transaction-owned configuration fixtures, selected IDs, exact rounding, snapshots/retries, unavailable fault states, core preservation and rollback |
| Existing MySQL core/input verifiers | Both passed with rollback |
| Migration/readiness | One additive migration applied, two new tables; all twelve migrations Ran and readiness passed |
| Application and configuration/statutory verifier Pint | Passed |
| Production build | Passed: 60 modules; CSS 79.38 kB / gzip 16.65 kB, JS 55.37 kB / gzip 20.88 kB |
| Strict Composer validation / whitespace | Passed; ordinary LF/CRLF notices |

Phase 8 adds 41 permanent cases / 364 assertions: 38 configuration cases and three
statutory integration cases. Coverage includes four roles/six scopes, validation,
draft visibility, stale edits, repeat creation/activation, date boundaries/future/
reverse-insertion selection, expired years, approval evidence/confirmation, forged
activation fields, HTTP/model immutability, history pagination, audit failure
rollback for all three mutation types, encoding/cross-form flash, guest/CSRF/
role/session/inactivity checks, saved confirmations, selected-version identity
changes, unchanged payroll snapshots and real processing rejection despite active
external settings and forged readiness input. No coverage percentage is claimed.

MySQL checks use fictional far-future versions and rollback. The statutory verifier
owns its dated configuration fixtures instead of depending on runtime defaults
when operator versions exist. Malformed-rule diagnostics bypass model locks only
on those transaction-owned rows, restore them and roll back; operator settings
are untouched. MySQL's original stopped state was restored after checks. No real
rule seed, credential export, database reset, finalized-record fabrication or commit.

HTTP tests render the new pages, but live browser visual/keyboard/responsive
acceptance is unverified. No simultaneous MySQL activation/source race, deadlock/
connection-loss recovery or capacity test. Client rules, rounding and activation
policy require acceptance. Actual finalized-history lifecycle awaits Phase 9;
existing immutable core/statutory snapshot preservation is tested. No Phase 9 work.

## Phase 8 acceptance audit - 2026-10-26

- Reran full isolation suite: **379 tests / 3866 assertions passed**, 49.735
  seconds, 74 MB. Inherited production/MySQL/DB_URL values did not escape SQLite.
- Three ignored diagnostics passed **21 assertions reproducing defects**:
  administrator-entered `javascript:` source saved/activated and rendered in a
  payroll user's href without a CSP response header; same creation payload/key
  rejected after associative field reordering; and `2026-10-01` effective date
  rendered in the event snapshot as `2026-09-30T16:00:00.000000Z`. These assert
  observed defects, not corrected behavior. No browser script was executed.
- MySQL configuration, statutory-preview and core-calculation checks passed with
  fixture rollback. All twelve migrations already Ran; no migration executed.
- Application and both configuration/statutory verifier Pint checks passed;
  whitespace check passed. MySQL's original stopped state was restored.
- No live browser, simultaneous configuration/statutory writer, recovery or capacity
  test. Actual client-approved cases/finalized lifecycle remain unavailable.

See PHASE_8_AUDIT.md for severities, source evidence, acceptance limits and the
diagnostic command. Only documentation and ignored probes changed in this audit;
no application fix, permanent regression change, commit or Phase 9 implementation.

## Phase 8 audit corrections - 2026-10-26

Corrected the three confirmed current-phase findings only. No new payroll feature,
schema change, dependency, production activation, Git commit or Phase 9 work.

| Check executed | Result |
| --- | --- |
| New regression selection before fixes | Reproduced defects: 15 failed, 2 passed / 127 assertions; confirms tests exercise the old behavior |
| Same selection after fixes | Passed: 17 cases / 165 assertions |
| `php artisan test --compact` | Passed: 396 tests / 4031 assertions, 56.60 seconds |
| `scripts/verify-test-isolation.ps1` | Passed: 396 tests / 4031 assertions, 55.423 seconds, 74 MB; conflicting inherited production/MySQL/DB_URL values stayed isolated in-memory |
| `php ../scripts/verify-payroll-rule-versions.php` | Passed on MySQL: all six scopes, canonical/legacy retries after edits, unchanged hashes/drafts/events, calendar dates, authorization, activation, dated selection, arithmetic, real blocking and rollback |
| `php ../scripts/verify-statutory-previews.php` | Passed on MySQL with fictional owned configuration fixtures and rollback |
| `php ../scripts/verify-payroll-calculations.php` | Passed on MySQL with rollback |
| `php ../scripts/verify-payroll-inputs.php` | Passed on MySQL with rollback |
| Application Pint `--test` and configuration/statutory verifier Pint `--test` | Passed after formatting changed files |
| `npm run build` | Passed: 60 modules; generated assets remain ignored |
| `composer validate --strict` | Passed |
| `php artisan app:check` / `php artisan migrate:status` | Ready; all twelve migrations already Ran; no migration executed |
| `git diff --check` | Passed; ordinary LF/CRLF notices |

New permanent coverage:

- Twelve saved-source rendering cases exercise JavaScript/mixed-case/control
  character schemes, data/file/protocol-relative references, plain document IDs,
  HTML input, valid HTTP(S), and Payroll Officer/Approver/Viewer consumption.
  References remain stored unchanged, and unsafe values are escaped plain text.
- Equivalent creation retries reorder nested associative fields and reject actual
  changed values without modifying the existing version/event. Legacy fingerprint
  and UTC-snapshot fixtures remain unchanged through reordered retries after edits;
  the original creation is compared instead of the latest draft. MySQL checks
  repeat these paths across all six scopes, exercising its JSON object ordering.
- Three timezone cases cover creation/revision/activation snapshots in Asia/Manila,
  UTC and Pacific/Auckland. Effective dates stay `Y-m-d`, actual activation times
  remain ISO UTC instants, and history shows the correct effective date. Legacy raw
  JSON is preserved with its date interpreted in the application timezone.

Phase 8 now contributes 58 cases / 529 assertions (43 configuration and 15 statutory
integration cases); all 338 prior cases remain. No coverage percentage is claimed.
The ignored original audit diagnostics assert former defects and are superseded
by permanent corrected-behavior tests.

MySQL was initially stopped, temporarily started for rollback-only checks and
stopped again afterward. Legacy fault injection touched only owned fictional
transaction fixtures; no operator data, stored history, credential, dump or actual
configuration was rewritten or retained. Client-approved calculations, actual
finalized payroll, browser visual/accessibility acceptance, simultaneous writers,
connection-loss/deadlock recovery and office-scale capacity remain unverified.

## Phase 8 post-correction re-audit - 2026-10-26

- `scripts/verify-test-isolation.ps1`: passed **396 tests / 4031 assertions**, 54.873
  seconds, 72 MB, with conflicting inherited production/MySQL/DB_URL settings.
- Ignored additional probes: passed **3 tests / 38 assertions**. Creation keys remain
  bound to their original actor/reason; missing/invalid legacy creation history
  rejects fallback without mutation/server error; nested server metadata cannot be
  forged; tax list reversal is rejected. These are audit-only cases, not permanent
  suite additions.
- MySQL configuration/statutory/core verifiers passed with owned fictional fixtures
  and rollback. Twelve migrations already Ran; none executed. MySQL was started
  temporarily and restored to its initial stopped state.
- Application and configuration/statutory verifier Pint `--test` passed; whitespace
  check passed with ordinary LF/CRLF notices. Asset build was not rerun because no
  application code changed; its preceding result remains recorded above.

PHASE_8_REAUDIT.md confirms no new supported-path Phase 8 defect and verifies F1-F3
remain corrected. Permanent Phase 8 coverage remains 58 cases / 529 assertions;
all 338 prior cases remain. Client examples/approval policy, actual finalized-record
acceptance, live browser/accessibility/mobile checks, simultaneous writers, recovery
and office capacity remain unverified. Only audit/tracking documents and ignored
diagnostics changed; no application fix, permanent-test change, migration, commit
or Phase 9 work.

## Phase 8 commit validation - 2026-10-26

- Full `scripts/verify-test-isolation.ps1`: passed 396 tests / 4031 assertions,
  58.762 seconds, 74 MB. All 338 earlier cases remain.
- MySQL configuration, statutory, core and input verifiers passed with fixture
  rollback; twelve migrations already Ran and `app:check` passed. No migration
  executed. MySQL's initial stopped state was restored.
- Application/verifier Pint `--test`, Composer strict validation and asset build
  passed. The working-tree build includes the separate login icon update; generated
  assets are ignored. Reviewed Phase 8 sources are independent of that UI change.
- Commit scope excludes the four login-icon source files and their entries in the
  four shared tracking documents. No application behavior changed during review.
  Actual client rules and browser/concurrency/recovery/capacity acceptance remain
  pending; real processing stays blocked. No Phase 9 work or push.

## Login password visibility icons - 2026-10-26

- `php artisan test --compact --filter='AuthenticationTest|UsernameAuthenticationTest|WorkspaceInterfaceTest'`:
  passed 29 tests / 416 assertions, 7.08 seconds.
- JavaScript toggle smoke check using the actual password-toggle handler: passed
  reveal/conceal input types, both icon states, action labels/tooltips/pressed state,
  initial activation and unchanged input value. This uses DOM stubs, not a browser.
- `node --check resources/js/workspace.js` passed; final `npm run build` passed
  after the icon attribute toggle change. Generated assets remain ignored.
- No schema, authentication behavior, dependencies, permanent tests or later-phase
  features were added. Live browser visual/assistive-technology checks were not run.

## Phase 9 - Payroll Review and Approval - 2026-10-27

| Check executed | Result |
| --- | --- |
| `php vendor/phpunit/phpunit/phpunit --colors=never --filter=PayrollWorkflowTest` | Passed: 42 tests / 569 assertions, 20.566 seconds; new permanent workflow coverage |
| `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify-test-isolation.ps1` | Passed: 438 tests / 4600 assertions, 89.770 seconds, 74 MB; all 396 earlier cases retained; inherited production/MySQL/DB_URL settings remained isolated in-memory |
| `php vendor/phpunit/phpunit/phpunit --colors=never --filter=PayrollPreparationTest` | Passed: 37 tests / 314 assertions; existing draft behavior after status/schema/model changes |
| `php artisan test --compact --filter=PayrollPreparationTest` | Passed: 37 tests / 314 assertions; Artisan runner also verified |
| `php ../scripts/verify-payroll-workflow.php` | Passed on MySQL: three-category combined totals, JSON tokens, role-change independence, return/correction/resubmission, version/event retention, locked/duplicate finalization, later configuration preservation, real block and rollback |
| Existing MySQL core/statutory/configuration verifiers | Passed: `verify-payroll-calculations.php`, `verify-statutory-previews.php`, `verify-payroll-rule-versions.php`; all fictional fixtures rolled back |
| `php artisan migrate --force` / `migrate:status` / `app:check` | Phase 9 migration applied successfully; all thirteen migrations Ran; database/base tables ready |
| Application and workflow-verifier Pint `--test` | Passed |
| `composer validate --strict` | Passed |
| `npm.cmd run build` | Passed: 60 modules. Sandbox initially denied Vite access to parent directories; rerun with approved build escalation passed. Generated assets remain ignored |
| `git diff --check` | Passed |

Workflow assertions cover the three personnel categories and all four roles;
current actor/session/CSRF/guest restrictions; self-review after role changes;
historical preparation/coverage editors; every preparation write in submitted,
approved and finalized states; stale/repeated transitions and statutory review
tokens; wrong submission identity; required reasons/confirmation; UTF-8/escaping
and rejected-input isolation; missing/incomplete/stale/real scenarios; negative net
and tax credits; exact combined employee/batch totals; original values/history;
actual configuration activation before/after finalization; five injected persistence
rollback paths; and immutable models including a stale batch object.

The first MySQL workflow fixture omitted the statutory demonstration confirmation
and was rejected before submission, with its transaction rolled back. The fixture
was corrected and the full MySQL workflow then printed its success result. No
production rule or operator data was used. MySQL was initially stopped, temporarily
started for migration/checks and stopped afterward; no fictional fixtures retained.

No coverage percentage, live browser/visual/accessibility/mobile acceptance,
simultaneous MySQL decision/source/configuration writes, deadlock/connection-loss
recovery, MySQL migration rollback or office-scale capacity test performed. Actual
approved payroll cases and authority policy remain client-validation items. New
code preserves existing real-processing blocks and older Phase 2/3 findings.
No Phase 10 feature, Git commit or publication.

Final review: locked source results are explicitly compared against the saved
entry using the freshly locked employee row before relying on historical preview
checks. After that guard and the shared payroll navigation update, the focused
`PayrollWorkflowTest|WorkspaceInterfaceTest` selection passed 48 tests / 641
assertions (17.717 seconds). The MySQL workflow passed again against the final
application sources and MySQL was stopped afterward. The final full isolation
rerun passed 438 tests / 4600 assertions (76.908 seconds, 74 MB). Concurrent MySQL
race/recovery behavior remains unverified.

## Phase 9 acceptance audit - 2026-10-27

- Full `scripts/verify-test-isolation.ps1` passed **438 tests / 4600 assertions**,
  73.636 seconds, 74 MB. Conflicting inherited production/MySQL/DB_URL settings
  remained isolated in-memory. Permanent Phase 9 remains 42 cases / 569 assertions.
- Ignored diagnostics passed **10 tests / 156 assertions**, 6.473 seconds. One
  probe reproduces F1: former preparers see approve/return/finalize forms after a
  role change, but valid submissions return 403 and create no finalization. Other
  probes verify same-batch stale submission IDs, return-event rollback, three
  finalizer revocation modes, six-revision history pagination, returned coverage
  refresh after an office change, migration-method legacy backfill/input preservation
  and rollback refusal for retained finalization history.
- The initial migration-method probe errored when SQLite could not disable foreign
  keys within RefreshDatabase's outer transaction. The audit harness was adjusted
  to run those methods outside that transaction, still strictly in-memory; all ten
  probes then passed. No application migration/source was changed. This does not
  verify populated transactional SQLite migration or MySQL downgrade/recovery.
- MySQL workflow/configuration/core/statutory verifiers passed with fixture rollback.
  `migrate:status`: thirteen already Ran; `app:check`: ready. No migration executed.
  MySQL was started temporarily and restored to its original stopped state.
- Application and workflow-verifier Pint `--test` and `git diff --check` passed.
  Build not rerun: no application/asset source changed; preceding successful build
  evidence remains above.

PHASE_9_AUDIT.md records the low UI finding and acceptance/coverage limits. No
high/medium workflow defect or additional missing supported task area confirmed.
Actual rules/authority, legacy overwritten coverage actors, live browser/assistive
technology, simultaneous MySQL writers, recovery and office-scale capacity remain
blocked/unverified. Only audit/tracking documents and ignored diagnostics changed;
no application fix, permanent-test change, migration, Git commit or Phase 10 work.

## Phase 9 audit correction - 2026-10-27

Corrected F1 only: read-time action visibility and the locked write-time guard now
reuse the same preparation-independence determination. No approval policy change,
new workflow, calculation, schema, dependency, Git commit or Phase 10 feature.

| Check executed | Result |
| --- | --- |
| Five new rendered-control cases before the fix | Failed as expected: 5 cases / 57 assertions; each preparation source exposed a forbidden approval action |
| Focused new cases plus enhanced existing creator test after correction | Passed: 6 tests / 179 assertions, 2.427 seconds |
| `scripts/verify-test-isolation.ps1` | Passed: 443 tests / 4768 assertions, 79.250 seconds, 74 MB; all 438 earlier cases retained; inherited production/MySQL/DB_URL settings stayed isolated in-memory |
| `php ../scripts/verify-payroll-workflow.php` | Passed on MySQL: existing three-category totals, role-change independence, return/resubmission, preserved snapshots/history, duplicate/finalization locks, later configuration retention, real block and fixture rollback |
| `php artisan migrate:status` / `app:check` | Thirteen migrations already Ran; database/base tables ready. No migration executed |
| Application Pint `--test` and `composer validate --strict` | Passed |
| `git diff --check` | Passed |

Five new cases isolate submitter-only, input-author, core-author, statutory-reviewer
and coverage-editor identities; the enhanced existing creator test covers that
path as well. Tests inspect submitted/approved pages, reject direct approve/return/
finalize requests by former preparers, retain immutable submission values, and
confirm independent Approvers still see and complete their existing actions.
The new snapshot-preservation assertion was corrected to compare cast arrays
rather than raw stored JSON before the final focused passing run.

Permanent Phase 9 coverage is now 47 cases / 737 assertions. No coverage percentage
measured. The old ignored F1 audit reproducer asserts the former visible-control
behavior and is superseded by these permanent corrected-behavior cases; it was not
rerun as a current acceptance test. Original audit evidence remains unchanged.

MySQL was initially stopped, temporarily started for rollback-only checks and
stopped again afterward; no fictional fixture or operator payroll value retained
or changed. Build not rerun: CSS/JavaScript and utility classes did not change.
Live browser/visual/keyboard/assistive-technology checks were not performed. Client
rules/authority, legacy overwritten coverage actors, simultaneous writes, recovery,
MySQL downgrade and office-scale performance remain limited/unverified as before.

## Phase 9 post-correction re-audit - 2026-10-27

- Full `scripts/verify-test-isolation.ps1` passed **443 tests / 4768 assertions**,
  76.617 seconds, 74 MB. Inherited production/MySQL/DB_URL values stayed isolated
  in-memory. Permanent Phase 9 remains 47 cases / 737 assertions.
- New ignored re-audit diagnostics passed **6 tests / 127 assertions**, 3.927
  seconds: all four roles across submitted/approved/finalized markup, no read-time
  snapshot mutation, role/session revocation after rendering, independence helper
  submission binding and cross-batch decision rejection.
- Applicable prior ignored audit probes passed **9 tests / 140 assertions**, 5.770
  seconds: same-batch old submission, return-event rollback, finalizer revocation,
  six-revision pagination, returned-source refresh and isolated migration-method
  preservation. The old F1 visible-control reproducer was excluded intentionally;
  permanent corrected-behavior tests supersede it. No audit history rewritten.
- MySQL workflow/configuration/core/statutory verifiers passed with fixture rollback.
  `migrate:status`: all thirteen already Ran; `app:check`: ready. No migration
  executed. MySQL's initial stopped state was restored after checks.
- Application and workflow-verifier Pint `--test` and `git diff --check` passed.
  Build/Composer validation not rerun because asset/dependency sources were unchanged;
  their latest passing results remain recorded above.

PHASE_9_REAUDIT.md confirms F1 remains corrected and no new Phase 9 defect or
missing supported task area was confirmed. Fifteen applicable extra audit cases
pass 267 assertions, separately from the permanent suite; no coverage percentage
measured. Client/authority examples, legacy overwritten coverage actors, live
browser/accessibility/mobile behavior, simultaneous MySQL writers, failure/migration
recovery, MySQL downgrade and office capacity remain limited/unverified.
Only re-audit/tracking documents and ignored diagnostics changed; no application
fix, permanent-test change, migration, new feature, Git commit or Phase 10 work.

## Phase 9 commit validation - 2026-10-27

- Full `scripts/verify-test-isolation.ps1` passed **443 tests / 4768 assertions**,
  78.061 seconds, 72 MB. All 396 prior-phase cases and 47 Phase 9 cases remain.
- MySQL workflow/configuration/core/statutory verifiers passed with fixture rollback.
  Thirteen migrations already Ran; `app:check` passed. No migration executed.
  MySQL's initial stopped state was restored after checks.
- Application and workflow-verifier Pint `--test`, `composer validate --strict`,
  `npm.cmd run build` (60 modules) and `git diff --check` passed. Build used the
  previously approved sandbox escalation; generated output remains ignored.
- Reviewed tracked diffs, the initially empty staged diff, new Phase 9 files and
  the intended 28-file commit scope. Candidate-path checks and private-key/provider-
  key/hardcoded-credential/debug-marker scans returned no findings. No environment,
  credential, dump, dependency, generated build or ignored diagnostic file belongs
  in the commit. Local ignore checks confirm those exclusions. Git's inaccessible
  user-global ignore warning is environmental; repository ignore rules were checked.
- No application behavior changed during commit review. Client rules/authority,
  legacy overwritten coverage actors, browser, concurrency/recovery, MySQL downgrade
  and capacity acceptance remain pending. Real payroll remains blocked. No Phase 10
  implementation or push.

## Phase 10 - Payroll Document Generation - 2026-10-27

| Check executed | Result |
| --- | --- |
| Initial focused `PayrollDocumentTest` run | Passed: 33 cases / 754 assertions, 61.028 seconds; authorization, exact HTML/CSV values, PDF generation, preserved identities/amounts, invalid snapshots and CSV/XSS cases |
| Initial full isolation regression | Passed: 477 tests / 5542 assertions; included the added snapshot-index pagination/full-register case |
| Intermediate full isolation after strict ID case/layout update | Passed: 478 tests / 5557 assertions |
| Footer regression before/after fix | Reproduced wrong individual identifier in register footer: 1 failed / 11 assertions; corrected targeted test passed 1 case / 13 assertions |
| Final `scripts/verify-test-isolation.ps1` | Passed: 479 tests / 5570 assertions, 130.533 seconds, 98 MB; all 443 earlier cases remain; hostile inherited production/MySQL/DB_URL settings stayed isolated in-memory |
| Ignored PDF render fixture and inspection | Passed: 1 test / 10 assertions; final 28-employee long-identity register generated 8 pages, long payslip 6 pages. Extracted Unicode/net values, last employee/continued line, every repeated warning/page/identity reference and all text bounds passed; PNG pages/contact sheets visually inspected |
| `php ../scripts/verify-payroll-workflow.php` | Passed on MySQL, including new register projection/CSV/register PDF/payslip PDF checks, preserved documents after configuration activation, exact existing workflow values and fixture rollback; rerun after final footer fix passed |
| Existing MySQL configuration/core/statutory verifiers | Passed with owned fictional fixtures and rollback |
| `php artisan migrate:status` / `app:check` | Thirteen migrations already Ran; database/base tables ready. No migration executed |
| Application Pint and workflow-verifier Pint `--test` | Passed |
| `node --check resources/js/workspace.js` | Passed |
| `composer validate --strict` / `composer check-platform-reqs` | Passed |
| `npm.cmd run build` | Passed: 60 modules; generated assets remain ignored |
| `git diff --check` | Passed; ordinary LF/CRLF notice on the verifier |

Phase 10 contributes **36 permanent cases / 802 assertions**. No coverage
percentage measured. Cases exercise three categories, all four roles/all six
document endpoints, frozen identities and multiple employees, exact register/
payslip/CSV arithmetic, index pagination versus full-batch export, multi-page PDF
generation, missing/unfinalized/cross-batch/uncovered records, guests/inactive/
expired sessions, later profile/configuration activation, no history mutation,
eleven malformed snapshot scenarios, eight CSV text scenarios and escaped stored
HTML. A specific footer test prevents the register from inheriting the final loop
employee while keeping the individual identifier on payslip continuation pages.

Visual QA found crowded register headings/excessive spacing and a shared Blade
row-variable footer leak. Header spacing/labels were refined, then the leak was
reproduced by the permanent test and corrected. The final re-rendered PDFs and
full suite passed afterward. QA adds no operator data or permanent payroll record.
Ignored diagnostic sources/output are under `.local/phase10-qa`; temporary Python
PDF inspection packages are under `.local/pdf-tools`. They are not runtime
application dependencies and are not staged or publicly served.

The interrupted setup initially failed downloads under the old sandbox. After
network access was available, Composer installed Dompdf 3.1.6 plus four new locked
font/parser dependencies, with zero existing upgrades and no reported security
advisories. The temporary unbounded require constraint resolved to `^3.1` when
Composer finished; final strict validation passed. PyMuPDF was installed only for
ignored visual/extraction QA; it is not required by the PHP application.

MySQL was initially stopped, started temporarily for rollback-only verification
and stopped again afterward. No fictional fixture, actual payroll/configuration,
credential export, database reset or migration change retained by diagnostics.
No real payroll activation, supporting-document upload, finalized correction,
blockchain behavior, Git commit or Phase 11 work.

Remaining unverified: actual LGU templates/headings/signatories, client payroll
rules/authority and confidential distribution policy; native browser/physical
printing, keyboard/mobile/screen-reader behavior; CSV viewer differences; large
office capacity, concurrent rendering/font cache use, disk/memory/time failures,
interrupted downloads and office deployment. Earlier Phase 2/3 and legacy-history
limits remain. Generic PDF QA does not certify an official payroll form or payment.

## Phase 10 audit - 2026-10-27

- Full `scripts/verify-test-isolation.ps1` rerun passed **479 tests / 5570
  assertions**, 148.538 seconds, 98 MB. The recorded run completed successfully
  under conflicting inherited production/MySQL settings with in-memory SQLite.
- Extra ignored audit probes passed **10 tests / 161 assertions**, 80.149 seconds,
  90 MB. Nine snapshot/data-use faults return 409 across all six routes without
  mutation; a synthetic 120-line projection with maximum-length employee identity
  fields renders HTML and PDFs successfully. These are not permanent test additions.
- F1 ignored fault reproducer passed **1 test / 12 assertions**, 2.815 seconds,
  64 MB, by observing the existing HTTP 500 and array_slice offset TypeError at
  `?page=9223372036854775807`. This confirms a bug, not corrected behavior.
- MySQL workflow/document verifier passed with fictional fixture rollback,
  preserved values after configuration activation and register/CSV/payslip PDFs.
  MySQL was initially stopped and stopped again afterward. No migration executed.
- Re-inspection of existing fictional PDFs passed: eight-page register, six-page
  payslip, Unicode/net/last-line values, repeated warnings/references/page numbers
  and page bounds. Contact sheets visually reviewed.
- New boundary PDF inspection passed: one-page register, eleven-page payslip,
  full 350-character name, 50-character employee number, 150-character office and
  position, all 114 added 100-character zero-value lines, preserved net and repeated
  references/warnings/page numbers within page bounds. First/last pages reviewed.
- Application/workflow-verifier Pint `--test`, Composer strict/platform checks,
  JavaScript syntax and whitespace checks passed. The existing implementation
  production-build result above was not rerun; this audit changes no asset source.

PHASE_10_AUDIT.md records preserved values/ordinary retrieval passing for exercised
demonstrations, plus one low-severity confirmed extreme-pagination defect (F1).
Permanent phase coverage remains
36 cases / 802 assertions; no line/branch percentage measured. Extra sources,
regression log, boundary PDFs and inspection scripts are ignored under
`.local/phase10-audit`; existing QA and inspection packages remain ignored.

Remaining coverage gaps include a permanent extreme-page regression for F1,
detailed PDF text/layout assertions outside the
permanent suite, native browser/physical printing, keyboard/mobile/screen-reader
behavior, actual spreadsheet imports/precision, office templates/distribution,
concurrent cache/rendering, disk/memory/time failures, interrupted downloads and
office-scale memory/time capacity. Earlier validation/history findings remain.
No application fix, permanent-test change, commit, migration or Phase 11 work.

## Phase 10 correction - 2026-10-27

- New permanent F1 regression first failed **1 test / 9 assertions** with the
  expected original HTTP 500 and array_slice offset TypeError. After the guard,
  it and the existing multi-page identity/full-register test passed **2 tests /
  36 assertions**, 3.343 seconds, 86 MB.
- Full `scripts/verify-test-isolation.ps1` passed **480 tests / 5586 assertions**,
  137.762 seconds, 98 MB. Hostile inherited production/MySQL configuration stayed
  isolated to in-memory SQLite. All 443 prior cases remain; Phase 10 now has
  **37 permanent cases / 818 assertions**. No coverage percentage measured.
- The new case verifies 404 for PHP_INT_MAX, the first overflowing offset boundary
  and an ordinary out-of-range page, then successful normal retrieval and unchanged
  finalized batch/snapshot/workflow events. The original ignored F1 reproducer
  asserts the former HTTP 500 and was intentionally not rerun as current acceptance.
- Application Pint `--test` and `git diff --check` passed. Earlier MySQL, build,
  dependency and visual/extracted PDF checks were not rerun because this follow-up
  changes only the controller's index guard, its regression and phase records.
  The full suite still exercises all document generation and authorization cases.

The permanent extreme-page coverage gap is closed. Other PDF text/layout,
browser/print/accessibility, spreadsheet, concurrency/resource-failure, capacity
and client-validation gaps from PHASE_10_AUDIT.md remain. Red/green diagnostic
logs are ignored under `.local/phase10-audit`. No schema, dependency, new feature,
Git commit or Phase 11 work.

## Phase 10 re-audit - 2026-10-27

- Full `scripts/verify-test-isolation.ps1` rerun passed **480 tests / 5586
  assertions**, 151.140 seconds, 98 MB. The permanent F1 regression passes under
  hostile inherited production/MySQL settings with in-memory SQLite.
- New ignored probes passed **4 tests / 91 assertions**, 21.437 seconds, 68 MB:
  20/40/41-row page boundaries, unavailable/extreme pages, malformed query fallback,
  unchanged snapshots, and authorization/invalid-snapshot checks before pagination.
- Applicable earlier ignored probes passed **10 tests / 161 assertions**, 80.978
  seconds, 90 MB. The historical F1 HTTP 500 reproducer was excluded deliberately;
  the permanent corrected-behavior test supersedes it.
- MySQL workflow/document verifier passed with owned fictional fixtures and
  rollback, including PDF/CSV/register values retained after configuration changes.
  MySQL's initial stopped state was restored; no migration executed.
- PDF extraction/bounds checks passed for existing eight-page Unicode register/
  six-page payslip and regenerated one-page boundary register/eleven-page payslip.
  Long identities, all 114 added lines, totals, repeated references/warnings/page
  numbers remain intact. Boundary register and final payslip page visually reviewed.
- Application/workflow-verifier Pint, Composer strict validation, JavaScript syntax
  and whitespace checks passed. No new build or dependency installation. SHA-256
  comparisons confirm all thirteen reviewed application paths stayed unchanged.

PHASE_10_REAUDIT.md records all three criteria passing for tested demonstrations,
F1 still corrected and no new confirmed Phase 10 defect/missing supported task
area. Permanent phase coverage remains **37 cases / 818 assertions**; fourteen
extra ignored diagnostic cases pass 252 assertions separately. No line/branch
percentage measured. New probes/log are ignored under `.local/phase10-reaudit`.

Client forms/distribution, real payroll rules, native browser/physical printing,
accessibility/mobile/spreadsheet acceptance, concurrent rendering, resource failure
recovery and office capacity remain unverified. Detailed PDF text/layout assertions
remain outside the permanent suite. Earlier Phase 2/3 and legacy-history limits
remain. Only audit/tracking documents and ignored diagnostics changed; no
application fix, permanent-test change, migration, commit or Phase 11 work.

## Phase 10 commit validation - 2026-10-27

- Full `scripts/verify-test-isolation.ps1` passed **480 tests / 5586 assertions**,
  141.059 seconds, 98 MB. Includes 37 Phase 10 cases / 818 assertions and all
  443 prior cases under isolated in-memory SQLite despite hostile inherited settings.
- MySQL workflow/document verification passed with owned fictional fixture rollback,
  preserved documents after configuration activation and PDF/CSV outputs. MySQL's
  original stopped state restored. No migration executed.
- Application/workflow-verifier Pint, Composer strict/platform checks,
  `composer audit --locked --no-interaction`, JavaScript syntax, production build
  (60 modules) and `git diff --check` passed. Composer reported no vulnerability
  advisories. Build outputs remain ignored.
- Reviewed modified and new Phase 10 files and the initially empty staged diff.
  All 22 candidate paths match the intended phase scope, with no deleted/unrelated
  file or sensitive/generated path. Private-key/provider-key/hardcoded-credential/
  debug scans returned no findings; local ignore checks confirm environment,
  dependency, renderer-cache, build and diagnostic exclusions.
- No application/permanent-test change during commit review. The earlier ignored
  probes and visual QA were not rerun; their evidence remains in PHASE_10_REAUDIT.md.
  Remaining client, browser/print/spreadsheet, concurrency/recovery and capacity
  limitations are unchanged. No production activation, push or Phase 11 work.

## Phase 11 local-interface fallback - 2026-10-27

Authorized scope: Phase 11 only. User confirmed no authorized test network is
available and selected the master prompt's local-interface fallback. No genuine
network submission, confirmation, independently retrieved transaction, simulated
anchoring success or Phase 12 verification is claimed.

| Executed check | Result |
| --- | --- |
| Focused `PayrollAnchoringTest` | **22 tests / 363 assertions passed**, 14.354 seconds, 70 MB |
| `scripts/verify-test-isolation.ps1` | **502 tests / 5949 assertions passed**, 172.995 seconds, 100 MB; includes all 480 prior tests and 22 new cases. Hostile inherited production/MySQL configuration remains pinned to in-memory SQLite |
| Local MySQL migration | Applied only `2026_10_27_000002_create_payroll_commitment_tables`; adds commitments and attempts. All 14 migrations Ran afterward |
| `php ../scripts/verify-payroll-workflow.php` | Passed exact three-category workflow/document checks plus unique local preparation, digest JSON round-trip, unavailable/no-transaction attempts and retained finalization values. All fictional business fixtures rolled back. The verifier refuses a transport-capable binding |
| Application Pint `--test` | Passed; new source/test/migration files included |
| MySQL verifier Pint | Passed after formatting; no logic change from formatting |
| `npm.cmd run build` | Passed, 60 modules; generated assets remain ignored |
| `git diff --check` | Passed; existing verifier LF/CRLF normalization notice only |

The first focused run passed 21 cases and encountered one test-fixture error:
the cross-batch fixture attempted to recreate an existing unique salary schedule.
The fixture now reuses that schedule; the corrected focused run and full suite
pass. No existing payroll functionality was changed to bypass this constraint.

New permanent tests cover the independently reproducible canonical byte/hash
vector, recursive maps, numeric-key map identity, list order, Unicode/slashes,
exact monetary strings and float rejection. End-to-end fixtures cover all three
personnel categories, GET without writes, idempotent preparation, unchanged batch/
snapshot/workflow values, unchanged digest after live profile changes, stale hash,
invalid confirmation, missing preparation, cross-batch and metadata/data-use
rejection, roles, guest/inactive/revoked sessions, CSRF and preparer role changes.

The production binding is asserted unavailable. Attempts retain no fabricated
network/reference, never send an HTTP request and do not change payroll status.
Injected test doubles independently exercise hash/key-only arguments, a pending
reference without confirmation, definite rejection and safe retry with the same
key, suppressed provider details, ambiguous acceptance and interrupted sends with
blocked retry. These test-double references are not blockchain transactions.
Model-level mutation/deletion rejection and private status headers also pass.

MySQL began stopped and was restored to stopped after verification. The schema
migration is retained; fictional users/payroll/commitments/attempts were rolled
back. Full-suite execution output is ignored at `.local/phase11-isolation.log`.
No dependency installation, network adapter, signer credential or commit.

Remaining unverified: genuine anchoring, chain/receipt identity, independent
retrieval, confirmation/reorganization policy, transport idempotency, network
outage/recovery, simultaneous submissions/process interruption and operational
capacity. A future live adapter must not broadcast inside an ambient caller
transaction. No live browser/accessibility acceptance or line/branch coverage
percentage was measured. Client/statutory/attendance/document/deployment and
earlier audit limitations remain as previously recorded.

## Phase 11 audit - 2026-10-27

Audit-only scope; no application fix, permanent-test change, migration, dependency
installation, commit or Phase 12 work. PHASE_11_AUDIT.md records acceptance,
findings, reproduction details and coverage gaps.

- Full hostile-environment isolation passes **502 tests / 5949 assertions**,
  167.302 seconds, 100 MB. All 480 earlier tests and 22 Phase 11 cases remain.
- **11 ignored audit cases / 181 assertions pass**, 25.869 seconds, 66 MB.
  These include an intentional current-behavior reproducer for F1's HTTP 500 and
  a conditional H1 test-double reproduction, not corrected-behavior acceptance.
- F1 first appeared as an expected-200 failure on `PHP_INT_MAX` history pagination.
  A separate probe confirms both that value and the first overflowing-offset
  boundary return 500, while normal retrieval afterward succeeds and saved
  commitment/attempt/finalization attributes remain unchanged. No fix made.
- The first diagnostic run also had three ignored-harness errors from supplying
  a session manager to a synthetic request. Supplying the session store corrected
  the harness only. The original log is retained; current probe execution passes.
- Other audit probes pass recursive full-snapshot key reorder, hidden float and
  schema/metadata/amount rejection, post-request actor revocation, invalid provider
  result, saved format/algorithm disagreement, reentrant reservation visibility,
  ordinary/malformed pagination, ten-request throttling and retained-record
  rollback protection. They are separate from permanent tests.
- MySQL workflow/document/local-commitment verification passes with fictional
  fixture rollback. All 14 migrations already Ran; none executed. The original
  stopped MySQL state was restored. No transport-capable adapter was used.
- Application Pint, JavaScript syntax and whitespace pass. SHA-256 checks across
  **165 application/config/route/view/migration/test/verifier files** show no
  changes during the audit. Prior asset build evidence was not rerun.
- Permanent Phase 11 coverage remains **22 cases / 363 assertions**. No permanent
  extreme-history-page regression, ambient-transaction safety regression, real
  network, multi-process submission, process-crash/post-send persistence recovery,
  capacity or live browser/accessibility coverage. No measured coverage percentage.

Ignored probes, original/current diagnostic logs, full-suite output and the source
hash manifest are under `.local/phase11-audit`. No real transaction was created;
live anchoring and independent retrieval remain incomplete under the fallback.

## Phase 11 correction - 2026-10-27

User-authorized scope: review/fix Phase 11 only, with no new features or next-phase
work. F1 and H1 are corrected. The original audit and reproducer logs remain
historical evidence; they were not rewritten to assert corrected behavior.

| Executed check | Result |
| --- | --- |
| New pre-fix regressions | Original code failed the offset boundary and outer-transaction cases. The separate populated-history test reproduced HTTP 500 instead of expected 404 (1 failed / 14 assertions); initial three-case run reported 2 failures / 53 assertions |
| Corrected focused `PayrollAnchoringTest` | **25 tests / 458 assertions passed**, 16.831 seconds, 70 MB |
| Full `scripts/verify-test-isolation.ps1` | **505 tests / 6044 assertions passed**, 169.778 seconds, 100 MB. Includes all 480 pre-Phase-11 tests and 25 Phase 11 cases; hostile inherited production/MySQL settings remain pinned to in-memory SQLite |
| Applicable ignored audit probes | **9 tests / 161 assertions passed**, 28.063 seconds, 64 MB. Original F1/H1 bug reproducers excluded, not modified |
| MySQL workflow/document/commitment verifier | Passed with fictional fixture rollback. Local preparation is idempotent and JSON digest preserved; submission inside the verifier's outer transaction returns 409 with no attempt. All 14 migrations already Ran; none executed. Initial stopped MySQL state restored |
| Application and verifier Pint `--test` | Passed |
| JavaScript syntax and `git diff --check` | Passed; ordinary verifier LF/CRLF notice only |

Three new permanent cases verify:

1. `PHP_INT_MAX` and the first unrepresentable page offset return 404, the largest
   representable offset remains safe, normal retrieval works afterward, and
   batch/snapshot/commitment/attempt/workflow records remain unchanged.
2. Exactly 20 first-page attempts and one second-page attempt remain correctly
   scoped; existing empty out-of-range behavior, malformed-query fallback and
   Administrator denial still work.
3. Submissions inside committed or rolled-back outer transactions return 409
   without an attempt or interface call. A subsequent standalone call uses the
   original hash/key, enters the test-double interface at transaction level zero
   with a retained `submitting` reservation, records pending, and is not resent.

The Phase 11 test class now migrates its fresh disposable in-memory database
without a wrapping test transaction. This allows the ordinary production
transaction guard to run without adapter or testing-environment exemptions. The
base TestCase still rejects unsafe resolved database configuration before any
destructive migration; the hostile-environment full suite passes.

Correction code changes are confined to the anchoring controller/service,
permanent Phase 11 tests and the existing MySQL verifier. No schema, dependency,
asset-source, canonical format, new feature or Phase 12 change. No build rerun was
needed for backend guards; the earlier asset build remains historical evidence.
Execution logs are ignored under `.local/phase11-correction`.

No real transaction was created. Genuine network submission, confirmation,
independent retrieval, transport idempotency/reconciliation, actual multi-process
concurrency, process-crash/post-send persistence recovery, capacity and live
browser/accessibility acceptance remain unverified. The fixes do not resolve
earlier payroll/client/operational limitations or authorize real payroll.

## Phase 11 re-audit - 2026-10-27

Scope: audit current Phase 11 after correction; no application fix, permanent-test
change, migration, dependency, commit or Phase 12 work. See PHASE_11_REAUDIT.md.

- Full hostile-environment isolation passes **505 tests / 6044 assertions**,
  180.758 seconds, 100 MB; all 480 earlier cases remain. Permanent Phase 11
  coverage is unchanged at **25 cases / 458 assertions**, including F1/H1 guards.
- Five new ignored re-audit cases pass **92 assertions**, 5.751 seconds, 64 MB.
  They cover empty/exact-40 attempt histories and offset boundaries, private
  responses, auth/snapshot-error ordering, actual unavailable-adapter rejection
  inside outer transactions, subsequent standalone unavailable submission,
  injected post-interface result-storage failure with retained reservation and
  blocked resend, and the database's unique-finalization commitment constraint.
- Nine applicable earlier ignored audit cases pass **161 assertions**, 33.066
  seconds, 64 MB. The original F1/H1 bug reproducers remain historical/excluded.
  Fourteen applicable ignored cases therefore pass **253 assertions**, separately
  from the permanent suite; no coverage percentage was measured.
- MySQL workflow/document/local-commitment verification passes with fixture
  rollback and nested-submission rejection before any attempt. All 14 migrations
  already Ran; none executed. MySQL began stopped and was restored to stopped.
- Application/verifier Pint, JavaScript syntax and whitespace pass. Hash checks
  across **165 source/config/route/view/migration/permanent-test/verifier files**
  confirm no change during the re-audit. No new build/dependency install required.
- No new confirmed supported-path defect was found. Genuine anchoring and
  independent transaction retrieval remain incomplete under the authorized
  fallback. Tests do not establish live transport idempotency/confirmation,
  chain identity/reorganization handling, network reconciliation, actual parallel
  writers/process kills, all persistence failures, full crash recovery, capacity
  or live browser/accessibility/client acceptance.

Ignored new probes, full/current/prior execution logs and source hash manifest
are under `.local/phase11-reaudit`. No real transaction was created. Only the
re-audit report, four tracking documents and ignored diagnostics changed.

## Phase 11 commit validation - 2026-10-27

- Reran full hostile-environment isolation: **505 tests / 6044 assertions pass**,
  170.067 seconds, 100 MB. This includes all 480 earlier tests and the 25 Phase 11
  cases / 458 assertions. Execution log: ignored `.local/phase11-commit/isolation.log`.
- Reran MySQL workflow/document/commitment verification: passed local JSON digest
  retention/idempotency and outer-transaction rejection without an attempt, plus
  existing three-category workflow and document checks. Fictional fixtures rolled
  back; all 14 migrations already Ran, none executed; MySQL's stopped state restored.
- Application/verifier Pint `--test`, `node --check resources/js/app.js`,
  `composer validate --strict`, `composer check-platform-reqs`, production asset
  build (60 modules) and `git diff --check` all pass. Build outputs remain ignored.
- Reviewed the initially empty index and full 27-file Phase 11 source/test/
  migration/UI/documentation scope. No deleted/unrelated file, sensitive/generated
  candidate path, private key, cloud/provider token, credential assignment,
  debug statement or incomplete marker found by the executed review/scans.
  Ignore checks cover environment, dependencies, database/config, build and logs.
- SHA-256 comparisons across 165 source/config/route/view/migration/test/verifier
  files show no application change during this commit review. No dependency or
  lockfile change; no installation or new security-advisory audit was needed.
- Earlier ignored diagnostic probes were not rerun for this commit review; their
  execution evidence remains in PHASE_11_REAUDIT.md. Live network acceptance and
  existing client/concurrency/recovery/browser/capacity limits remain unchanged.
  One local-interface commit authorized; no push or Phase 12 implementation.

## Phase 12-A verification and check history - 2026-10-27

Authorized scope: next-phase implementation, tests and stop. The master prompt
permits reviewable subphases; Phase 12-A covers verification/history and Phase 12-B
will cover authorized correction versions across the locked payroll lifecycle.
No Phase 12-B or Phase 13 feature is implemented. No genuine blockchain result
is claimed: the default receipt reader is unavailable, with no transport.

| Executed check | Result |
| --- | --- |
| Final focused `PayrollVerificationTest` | **32 tests / 495 assertions passed**, 19.749 seconds, 70 MB; includes the stored/displayed application-clock regression |
| Prior combined anchoring/verification focused run | **55 tests / 922 assertions passed**, 28.747 seconds, 70 MB; before the final additional actor-revocation case |
| Final full `scripts/verify-test-isolation.ps1` | **537 tests / 6539 assertions passed**, 182.547 seconds, 100 MB, including all 505 earlier cases; hostile inherited production/MySQL values remained pinned to in-memory SQLite |
| Local MySQL migration | Applied only `2026_10_27_000003_create_payroll_verifications_table`; all 15 migrations Ran afterward. Existing finalized/commitment tables unchanged |
| MySQL workflow/document/commitment/check-history verifier | Passed and reran successfully after the check-time correction: exact three-category workflow/document checks plus saved hash/history JSON persistence, UUID idempotency and isolated snapshot-copy mismatch. No original snapshot modification. All fictional business fixtures rolled back; initial stopped MySQL state restored |
| Application/verifier Pint, JS syntax | Passed |
| Composer strict validation/platform requirements | Passed; no dependency/lockfile change or installation |
| Production asset build | Passed, 60 modules; generated assets remain ignored |
| `git diff --check` | Passed; ordinary existing verifier LF/CRLF notice only |

The initial focused run had 51 cases / 850 assertions and two failures: newly
inserted pending-attempt fixtures had not loaded database-default creation times
before strict attribute comparison. Refreshing the fixture before capturing its
attributes corrected the tests. The application did not alter those attempts;
the preservation checks remain strict. Corrected focused/full execution passes.

Final review also caught a check-time defect: relying on the database's default
timestamp can disagree with the application's timezone/clock. A new frozen-clock
regression first failed (1 case / 11 assertions), then passed after both new-record
paths explicitly stamp `created_at` from the application clock. No older payroll
or anchoring timestamps were changed. The final focused/full counts above include
this regression. The earlier pre-correction full run also passed 536 tests / 6526
assertions; it did not yet contain the timestamp regression.

New permanent tests cover all three categories, unchanged Phase 11 envelope bytes,
GET without events, local unanchored/match states, unavailable defaults and no
network submissions, confirmed matching test receipts, isolated amount tampering
even with invalid arithmetic, confirmed network-hash disagreement, pending/failed
receipts, wrong receipt identity, suppressed provider exceptions and required
confirmed hash/block/positive confirmations. They retain previous history across
explicit new checks and reuse the same actor/finalization request UUID on retry.

Role controls, hidden/visible Run buttons, guest/inactive/revoked sessions, CSRF,
cross-batch scope, cross-actor/key misuse, unfinalized/real batches, changed actor
state after request construction, malformed snapshots/format/references, history
pagination/overflow, model retention and migration rollback protection also pass.
Stored and displayed check time matches the frozen application time.
The server accepts only a request UUID, never operator-provided receipt or hash
confirmation fields. Test doubles are not runtime proof of real blockchain use.

The original Phase 11 fixture methods moved unchanged to a shared test trait;
all 25 anchoring tests and earlier regression cases remain. No percentage line/
branch coverage was measured. Logs are ignored under `.local/phase12a`.

Remaining unverified: genuine network reader/chain/receipt identity and authorized
confirmation/reorganization policy, independent real retrieval, bounded adapter
timeouts and lock latency, concurrent writers/request-key races, process-crash/
storage/network failures, office-scale capacity and live browser/accessibility
acceptance. There is no confirmation poller, transaction reconciliation/retry or
correction-version workflow. Earlier payroll/client/document/deployment limits
remain unchanged. No new commit, push or publication.

## Phase 12-A audit - 2026-10-27

Audit-only scope: no application fix, permanent-test change, migration, dependency
or Phase 12-B/13 implementation. See PHASE_12A_AUDIT.md for criteria and limits.

- Full hostile-environment isolation passes **537 tests / 6539 assertions**,
  172.426 seconds, 100 MB. All 505 earlier cases remain; permanent Phase 12-A
  coverage is still **32 cases / 495 assertions**, with no measured coverage percentage.
- New ignored audit probes pass **9 cases / 155 assertions**, 3.092 seconds,
  62 MB: separate network/transaction identity rejection; invalid-reference clock
  and UUID retention; workflow/null/float tampering; escaped receipt metadata;
  injected key conflict returning 409; injected history insert failure with rollback
  and read-only retry; SQL FK retention; Viewer/stale GET with no lookup/write;
  and refusal of client-provided success/hash/confirmation/actor/time overrides.
- MySQL workflow/document/history/JSON/idempotency/isolated-copy mismatch checks
  pass with fictional fixture rollback. All 15 migrations already Ran; none
  executed. Initial stopped MySQL state restored. No real chain calls were made.
- Application/verifier Pint, JS syntax and whitespace pass. SHA-256 comparisons
  across **175 source/config/route/view/migration/permanent-test/verifier files**
  confirm no changes during this audit. No asset build/dependency install needed;
  their earlier implementation evidence remains historical.
- No new confirmed Phase 12-A bug found. Genuine chain verification/retrieval,
  confirmation/reorganizations and authorized correction versions remain
  incomplete under the current network constraint and documented subphase split.
  Fault injection does not establish actual multi-process concurrency, physical
  persistence/network failure handling or full crash recovery. Live browser/
  accessibility, capacity, client acceptance and earlier payroll limits remain.

Probes, source manifest and execution logs are ignored under
`.local/phase12a-audit`. Only the audit report, four tracking documents and ignored
diagnostics changed. No application fix, permanent test, commit or next phase.

## Phase 12-A commit validation - 2026-10-27

- Reran full hostile-environment isolation: **537 tests / 6539 assertions pass**,
  182.287 seconds, 100 MB. Includes all 505 earlier cases and 32 Phase 12-A
  cases / 495 assertions. Log is ignored at `.local/phase12a-commit/isolation.log`.
- MySQL workflow/document/anchoring/check-history verification passes with
  fictional fixture rollback: saved JSON digest/history, scoped UUID idempotency,
  original-value preservation and isolated snapshot-copy mismatch. All 15
  migrations already Ran; none executed. Original stopped MySQL state restored.
- Application/verifier Pint `--test`, JS syntax, Composer strict validation and
  platform checks, production build (60 modules) and whitespace checks pass.
  No dependency/lockfile change, new package or generated output included.
- Reviewed the initially empty index and intended 25-file source/test/migration/
  UI/documentation scope. No deleted/unrelated file, secret/database/generated/
  diagnostic path or private/cloud/provider key, credential assignment, debugging
  statement or incomplete marker found by the executed review/scans. Local ignore
  checks cover environment, dependencies, database/config, builds and logs.
- SHA-256 comparisons across 175 source/config/route/view/migration/test/verifier
  files confirm no application change during commit review. Earlier ignored
  audit probes were not rerun; their evidence remains in PHASE_12A_AUDIT.md.
- Genuine network confirmation/retrieval, live concurrency/recovery/browser/
  capacity acceptance and authorized correction versions remain incomplete or
  deferred. One local-integrity/history commit authorized; no push or Phase 12-B/13.

## Phase 12-B - Authorized Correction Versions - 2026-10-27

Scope: current next-phase instruction after Phase 12-A commit `5ba1bd4`. Only the
authorized correction subphase is implemented. PHASE_12B_HANDOFF.md maps parent
acceptance and records manual verification and remaining limits.

- `php vendor/phpunit/phpunit/phpunit --colors=never --filter=PayrollCorrectionTest`
  in application/: **28 cases / 540 assertions pass**, 25.894 seconds, 70 MB.
  Covers an independently authorized empty draft and full three-version lifecycle,
  separate commitments/source lineage, changed replacement amounts, old document/
  hash/check preservation, four roles and role-change independence, return/revision,
  duplicate/fork/conflicting-retry prevention, hash/route/input/UTF-8 failures,
  source tampering before/after authorization, missing/wrong context, authorization
  storage-fault rollback, stale/inactive session/requester checks, model/FK retention,
  guarded and empty migration rollback, application timestamps, pagination/escaping,
  CSRF and demonstration-only/finalized-only gates. No coverage percentage measured.
- `php vendor/phpunit/phpunit/phpunit --colors=never --filter='Payroll(Workflow|Preparation)Test'`:
  **84 tests / 1051 assertions pass**, 22.968 seconds, 68 MB.
- `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify-test-isolation.ps1`:
  **565 tests / 7079 assertions pass**, 203.311 seconds, 100 MB. This includes all
  537 earlier cases plus the 28 correction cases, under conflicting inherited
  production/MySQL configuration. The base safety guard retains in-memory SQLite;
  no test migrates the configured application database. Log:
  `.local/phase12b/isolation.log` (ignored).
- Started the initially stopped local MySQL helper. Applied only
  `2026_10_27_000004_create_payroll_correction_versions`; forward migration succeeds,
  all **16 migrations Ran**. The explicit short decision-request FK/unique names
  avoid MySQL's identifier-length limit. Empty rollback/reapply is exercised in
  isolated SQLite; a MySQL down/up with retained application data was not run.
- `php scripts/verify-payroll-workflow.php` passes: three categories, exact original
  totals, JSON review tokens, independent approval, return/resubmit, fixed original
  documents/PDFs, configuration preservation, commitment/check retries and isolated
  mismatch, then authorized empty correction/retries, full replacement coverage/
  input/calculation/statutory/approval/finalization and separate JSON/hash/documents/
  local check with original preservation. All fictional records rolled back;
  explicit cleanup checks include correction requests/decisions and replacement
  batch/finalization/commitment/check. Initial stopped MySQL state restored.
- Application `php vendor/laravel/pint/builds/pint --test` and root
  `php application/vendor/laravel/pint/builds/pint --test scripts/verify-payroll-workflow.php`
  pass. `node --check resources/js/app.js`, `composer validate --strict`,
  `composer check-platform-reqs`, `npm.cmd run build` (60 modules) and
  `git diff --check` pass. No dependency/lock change or generated artifact tracked.
- The first correction run caught an incorrect test URL missing the document
  finalization parameter; corrected the test and reran successfully. No failing
  test remains. Injected storage faults and source changes are isolated tests;
  they do not establish real concurrent-writer or process-crash recovery behavior.

No authorized test network is available, no genuine transaction/confirmed receipt
was produced, and no payroll/authority information was sent externally. Local
correction tests do not complete genuine Phase 11/12 blockchain acceptance.
Office authority/replacement policy, real payroll rules/examples/templates,
actual concurrency/recovery, capacity and live browser/accessibility/printing
acceptance remain unvalidated. No Phase 13, commit, push or publication.

## Phase 12-B audit - 2026-10-27

Audit-only scope. See PHASE_12B_AUDIT.md for criterion mapping, open F1 and limits.
No application fix, permanent-test change, migration, dependency or Phase 13 work.

- Full hostile-environment isolation rerun passes **565 tests / 7079 assertions**,
  209.217 seconds, 100 MB. Includes 28 permanent correction cases / 540 assertions
  and all 537 earlier cases. No measured coverage percentage.
- Seven new ignored audit probes pass **7 cases / 177 assertions**, 11.653 seconds,
  88 MB: F1 no-commitment changed-source display/409 reproduction; corrected PDF/
  original/lineage-time/hash preservation and isolated authorization-hash mismatch;
  ignored actor/version/action/time overrides and actor-bound/uppercase retry keys;
  corrected hash-only submission/own test-double receipt; corrected unavailable
  default with no invented transaction; invalid source shape; and HTTP rejection
  of submit/approve when correction authorization context is removed.
- F1 is reproduced, not fixed: a valid current digest without a local commitment
  still causes the view to offer authorize despite the request's earlier hash.
  POST rejects with 409, writes no draft/decision, and return remains available.
  The passing probe asserts this observed undesirable display behavior explicitly.
- MySQL workflow verifier passes full corrected/original JSON/hash/document/check
  preservation with all fictional fixtures rolled back. All 16 migrations already
  Ran; none executed. Initially stopped helper stopped again. No genuine network
  call or receipt; corrected receipt success uses explicit test doubles only.
- Application/verifier Pint, JS syntax and whitespace checks pass. All **190**
  compared source/config/route/view/migration/permanent-test/verifier files retain
  pre-audit SHA-256 values. Build/Composer were not rerun; no source/dependency
  changes justified them and their implementation evidence remains historical.
- Probes/logs/manifests/generated PDFs remain ignored at `.local/phase12b-audit`.
  PDF response/signature checks do not establish visual/printed layout or extracted
  text acceptance. No additional permanent regression or concurrency test added.

Permanent gaps: F1's exact no-commitment case, correction-specific PDF/receipt
coverage and dedicated corrected return/recalculate/resubmit (common workflow
return/resubmit already covered). Genuine blockchain, client policy/real payroll,
actual concurrent races/recovery/capacity and live browser acceptance remain
incomplete. F1 remains open. No fix, commit, push, publication or Phase 13.

## Phase 12-B F1 fix validation - 2026-10-27

User-authorized current-phase review/fix only; no new correction capability or
next-phase work. F1 is resolved in the view and its exact regression is permanent.

- Added `test_changed_source_without_commitment_displays_warning_and_only_allows_return`.
  Ran it against the old view first: **1 case, 17 assertions, 1 expected failure**
  because the integrity warning was missing. This establishes defect reproduction;
  the original failure log remains ignored under `.local/phase12b-fix`.
- After the fix, `php vendor/phpunit/phpunit/phpunit --colors=never --filter=PayrollCorrectionTest`
  passes **29 cases / 576 assertions**, 23.988 seconds, 70 MB. New coverage verifies
  the valid-source forms, pending mismatched-source warning without commitment,
  Approver return-only and Viewer read-only controls, no GET history writes,
  locked POST 409/no draft, return and preserved source/request evidence.
- `powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify-test-isolation.ps1`
  passes **566 tests / 7115 assertions**, 203.385 seconds, 102 MB, including all
  565 earlier cases under conflicting inherited production/MySQL configuration.
  Log: `.local/phase12b-fix/isolation.log` (ignored). No measured coverage percentage.
- Application Pint `--test`, `node --check resources/js/app.js`, production
  `npm.cmd run build` (60 modules) and `git diff --check` pass. The initial sandbox
  build could not read a parent directory/configuration; the approved rerun outside
  the sandbox succeeded. No package/configuration change was needed.
- SHA-256 comparison across 190 source/config/route/view/migration/permanent-test/
  verifier files confirms only the correction Blade view and test changed.
  Other edits this turn are phase audit/handoff/tracking records. No service,
  controller, schema, dependency or formula change; generated assets/logs remain
  ignored, and the staged diff remains empty.
- No migration or MySQL helper/verifier operation this turn; MySQL remains stopped.
  The earlier implementation/audit MySQL results are historical, not new execution
  evidence. Composer and ignored audit probes were not rerun. The old F1 audit
  probe intentionally expects the defect and is retained only as historical evidence.

Genuine network anchoring/verification, real payroll/client policy, actual
concurrency/recovery/capacity and live browser acceptance remain unvalidated.
Current-phase F1 is fixed; no new feature, Phase 13, commit, push or publication.

## Phase 12-B re-audit - 2026-10-27

Audit-only review following F1's fix; PHASE_12B_REAUDIT.md maps acceptance and
coverage limits. No new confirmed defect or required local task area wholly
missing. Genuine verification remains incomplete under the no-network fallback.

- `scripts/verify-test-isolation.ps1`: **566 tests / 7115 assertions pass**,
  214.552 seconds, 102 MB. Includes **29 correction cases / 576 assertions**, F1's
  permanent regression and all earlier cases under conflicting inherited
  production/MySQL configuration. No measured coverage percentage.
- Eight ignored re-audit probes: **8 cases / 221 assertions pass**, 14.176 seconds,
  88 MB. Updated the historical F1 probe to expect the corrected warning/no-authorize
  behavior and added a corrected return/recalculate/resubmit/finalize probe.
  Other checks cover original/corrected PDF responses and frozen lineage times,
  isolated authority-hash mismatch, ignored actor/version/action/time overrides,
  actor-bound UUID retries, corrected hash-only submit/own test-double receipt,
  unavailable default, malformed source and missing-context HTTP blocking.
- The corrected return cycle rejects stale submission input, recalculates changed
  loan inputs, preserves the first submission and frozen correction authority
  lineage in both revisions, finalizes the second submission as payroll version 2,
  retains five events and leaves the original finalized payroll unchanged.
- MySQL `scripts/verify-payroll-workflow.php` passes full replacement/original JSON/
  hash/document/check preservation with all fictional fixtures rolled back. All
  **16 migrations already Ran**; none executed. Initially stopped MySQL restored
  to stopped. No genuine network operation, transaction or receipt was produced;
  successful corrected confirmation uses explicit test doubles only.
- Application/verifier Pint `--test`, JavaScript syntax and whitespace pass.
  SHA-256 comparisons across **190** source/config/route/view/migration/permanent-
  test/verifier files confirm no changes. Build/Composer were not rerun; previous
  evidence remains historical because no source/dependency change required them.
- Probes/logs/PDFs/manifests remain ignored at `.local/phase12b-reaudit`. No
  permanent-test change or new regression was added. PDF checks establish HTTP
  success/signature, not extracted text, visual layout or printed acceptance.

F1 remains resolved. Permanent correction-specific PDF/receipt and dedicated
return-cycle coverage remains absent, despite their ignored-probe execution.
Real-network verification, client policy/real payroll, simultaneous writers,
crash/deadlock recovery, capacity and live browser acceptance remain unverified.
Only re-audit/tracking documents and ignored diagnostics changed. No fix, feature,
schema/dependency change, Phase 13, commit, push or publication.

## Phase 12-B commit validation - 2026-10-27

- Reran `scripts/verify-test-isolation.ps1`: **566 tests / 7115 assertions pass**,
  217.549 seconds, 102 MB, including all 29 correction cases / 576 assertions and
  F1's permanent regression. Log: `.local/phase12b-commit/isolation.log` (ignored).
- Reran MySQL workflow verifier: complete replacement preparation/approval/
  finalization, original and corrected JSON/hash/document/check preservation,
  retry semantics, real-payroll block and fixture rollback pass. No migration
  executed; original stopped helper state restored. No genuine network operation.
- Application/verifier Pint `--test`, JS syntax, Composer strict validation and
  platform requirements, production asset build (60 modules) and whitespace pass.
  No new dependency, lock change or generated build/database artifact included.
- Reviewed the initially empty staged diff and all 24 phase-related modified/new
  files: no deleted/unrelated path and no sensitive/database/generated/diagnostic
  path, private/provider key, credential assignment, debug or incomplete marker
  found by the executed checks. Environment/configuration, credentials, dependencies,
  generated build/cache files and ignored probe/log/PDF files remain excluded.
- SHA-256 comparison across 190 source/config/route/view/migration/permanent-test/
  verifier files confirms no application change during commit review. Ignored
  re-audit probes were not rerun; their prior eight-case/221-assertion evidence
  remains in PHASE_12B_REAUDIT.md. No coverage percentage was measured.
- Genuine chain verification, client policy/real payroll, actual concurrency/
  recovery/capacity/browser acceptance and permanent correction-specific PDF/
  receipt/return-cycle coverage remain incomplete/unverified. One reviewed
  demonstration-correction commit is authorized; no push or Phase 13 work.

## User-requested browser-demo seeder - 2026-10-28

Scope: opt-in fictional browser fixtures for the existing prototype. No Phase 13,
Besu integration, application database population, schema or dependency change.

- New BrowserDemoSeederTest passes **10 cases / 86 assertions**, 2.751 seconds,
  66 MB: four working login accounts/roles, three salary/employee fixtures,
  prepared November draft and exact combined reference totals, no automatic
  submissions/finalizations/anchors/configuration activation, idempotent reruns,
  changed-password retention, finalized snapshot retention, identity collisions,
  late-conflict transaction rollback, empty default seed and production/staging
  rejection even through `db:seed --force`.
- Initial new-test execution revealed two test harness assumptions: raw attributes
  of a just-created user differ in scalar types/order from a fresh database read,
  and production Artisan seeding asks for confirmation before invoking the class.
  Compare fresh raw records and invoke --force in the guard test to verify that
  the class itself still refuses nonlocal environments. All 10 cases then pass.
- The first full isolation attempt reached **576 tests / 7180 assertions** with
  one failure in the existing employee literal-search test. Its 21 random control
  employees can include another Maria, legitimately matching the search that
  expects only its named target. Made only those control first names deterministic;
  employee search code/factory remain unchanged. Combined focused rerun passes
  **11 cases / 113 assertions**, 6.770 seconds, 74 MB.
- Application Pint --test and whitespace checks pass. No frontend/dependency
  changes require a new asset build or Composer run. All test fixtures use
  isolated SQLite; no MySQL helper or persistent seeding operation was executed.
  The documented seed command remains an explicit user step for their local DB.
- The pre-existing untracked docs/HYPERLEGER_BESU_INTEGRATION.md is untouched.
  Logs remain ignored under `.local/browser-demo-seeder`. No commit or push.
- Final hostile-environment isolation rerun passes **576 tests / 7201 assertions**,
  212.913 seconds, 102 MB, including all 566 earlier cases and 10 new seeder cases.
  No cases skipped; no remaining failure. Evidence:
  `.local/browser-demo-seeder/isolation-final.log` (ignored).

## User-requested department salary schedules - 2026-10-28

Scope: replace new salary schedule classification selection with All departments
or one existing department. The user explicitly chose specific-department priority.
No statutory formula/activation, finalized snapshot, phase or blockchain change.

- New DepartmentSalaryScheduleTest passes **15 cases / 158 assertions**, 3.162
  seconds, 64 MB: dropdown/escaped/accent-distinct labels, known scope validation,
  dates/modes/publication, specific-over-newer-global priority, all personnel
  classifications, wrong-department rates, transfer source invalidation, relevant
  and irrelevant midmonth changes, normalized duplicates, forged headers, valid
  draft edits/locked publication, case-only name edits, legacy schema round-trip
  preservation and retained department rollback refusal.
- Focused salary/preparation/demo/department run passes **97 cases / 1115
  assertions**, 16.505 seconds, 70 MB. Legacy classification fixture sources remain
  tested through direct migration-era model fixtures; header HTTP CRUD now uses
  department coverage. Demo setup now uses a shared All departments source while
  preserving already-seeded legacy sources and all manual payroll workflow state.
- Applied `2026_10_28_000001_add_department_coverage_to_salary_schedules` to local
  MySQL successfully; all **17 migrations Ran**. Before/after SHA-256 values over
  existing columns match for salary schedules/rates/employee history and finalized
  payroll/commitment/check tables. No existing source or snapshot was rewritten.
- MySQL salary verifier (now department-scoped) and full payroll workflow verifier
  pass with fictional fixtures rolled back. An ignored MySQL probe confirms both
  Nino/NiÃ±o choices survive DB collation and upper-case/space-varied selection
  validates using the same normalization; its fixtures also roll back. Original
  source/history hashes still match afterward. Local MySQL is left available for
  browser use; no demo fixture population performed by these checks.
- Application/verifier Pint --test, JavaScript syntax and production asset build
  (60 modules) pass. Sandbox esbuild initially could not read parent/config paths;
  the approved out-of-sandbox build succeeds. No dependency/lock change.
- New legacy round-trip test initially compared unsynchronized new-model raw
  attributes with freshly read rows; compare fresh database attributes on both
  sides to verify exact retained values. No application-data repair was involved.
- Additional review fixed capitalization changes defaulting an edit form to All
  when its saved name differed from deduplicated option casing. Selected options
  and server validation now use normalized scope identities; a permanent case-only
  regression passes. Both original full checks passed (590 tests / 7262 then 7264
  assertions); the final suite additionally includes this regression.

No live browser screenshot/printing, office authority/department convention or
real concurrent writer acceptance is claimed. Real payroll remains blocked.
The existing untracked Besu document and earlier seeder work remain intact.
No commit, push or Phase 13 implementation.

Final full hostile-environment isolation passes **591 tests / 7273 assertions**,
220.230 seconds, 102 MB, including all prior 576 cases and 15 department cases.
Log: `.local/department-schedules/isolation-verified.log` (ignored). No failing or
skipped tests remain; no coverage percentage measured. Whitespace passes.

## Payroll configuration coverage - 2026-10-28

- Full hostile-environment isolation via `scripts/verify-test-isolation.ps1`:
  **615 tests / 7568 assertions**, 206.147 seconds, 100 MB. All pass, none skipped.
  Includes all prior 591 cases and 24 new coverage cases. Ignored log:
  `.local/payroll-configuration-coverage/isolation.log`.
- `PayrollConfigurationCoverageTest`: **24 tests / 295 assertions**, passing.
  Covers all four scope combinations, existing/escaped/accent-distinct department
  options, invalid values, draft edits/copy, immutable coverage events, normalized
  creation retries, activation uniqueness including direct database enforcement,
  scope precedence, latest effective activation and data-mode filtering.
- Legacy migration round-trip preserves every original configuration column and
  event; rollback refuses new shared/department coverage. Existing configuration
  regression tests retain canonical/legacy creation and timezone compatibility.
- Browser-route workflow regression seeds three classifications, selects the
  shared global profile, then activates a department-wide profile ahead of a newer
  global classification profile. Old review submissions fail, explicitly saved
  reviews use department-specific GSIS amounts, and independent submission,
  approval/finalization succeed. Later activation preserves all saved reviews and
  the finalized snapshot; documents remain accessible.
- Local MySQL migration applied successfully: all 18 migrations Ran. Original
  columns in 11 source/history tables have unchanged SHA-256 digests before/after
  migration and smoke checks; new scope columns and migration metadata are excluded
  from that comparison. Existing configuration/event tables were empty locally;
  populated legacy backfill is covered by the isolated migration regression.
- MySQL `verify-payroll-rule-versions.php`, `verify-statutory-previews.php` and
  `verify-payroll-workflow.php` pass with fixture rollback. New checks cover shared
  scopes in both data modes, exact/department/global priority and normalized
  unique-index enforcement. Two original preview/workflow probes first failed
  because a retained global salary schedule superseded legacy fixture sources;
  fixtures now use isolated departments and the reruns pass. No operator data repair.
- Full application Pint, targeted changed PHP/script Pint, JavaScript syntax,
  production asset build (60 modules) and `git diff --check` pass. Generated build
  files and local verification artifacts remain ignored. No dependency changes.

No live browser visual, actual concurrent writer, statutory/client approval or
genuine blockchain validation is claimed. Real payroll remains blocked. No
Phase 13, commit or push; unrelated existing documentation is retained.

## 2026-10-28 - BESU-00 repository assessment

Assessment only; existing tests reused with no new executable behavior. Commands
below were executed from `application/` unless otherwise specified.

| Command | Actual result |
| --- | --- |
| `php vendor/phpunit/phpunit/phpunit --colors=never` | PASS: 615 tests, 7568 assertions; 03:25.741; 102 MB; exit 0. Includes calculation, workflow, frozen snapshots/documents, canonical hashes, local anchoring failure states, verification test doubles, correction history and access regressions. |
| `composer validate --strict` | PASS: composer.json valid. |
| `composer check-platform-reqs` | PASS: installed dependencies satisfy platform requirements. |
| `php artisan app:check-foundation` | PASS: configured local database connection and base tables ready. |
| `php artisan migrate:status --no-ansi` | PASS: all 18 application migrations Ran; read-only inspection, no migrations applied. |
| `php artisan route:list --path=payroll-batches --no-ansi` | PASS: 29 payroll routes, including workflow, anchoring, integrity and correction endpoints. Route enumeration alone is not authorization evidence; access checks are covered by PHPUnit. |
| `php artisan --version` | Laravel Framework 13.38.0. |
| `php --version`, `php -m` | PHP 8.5.10; BCMath, PDO MySQL/SQLite, cURL and OpenSSL available. Sodium absent; not required by current installed dependencies. |
| `composer --version`, `node --version`, `npm.cmd --version` | Composer 2.10.3; Node.js 24.19.0; npm 11.17.0. |
| `docker version` (root) | FAIL for engine connectivity: CLI 29.7.2 available, Docker Linux engine named pipe absent. No container/network acceptance established. |
| `docker compose version` (root) | Compose v5.4.0 installed; engine execution untested. |
| `docker context show` (root) | `desktop-linux`. |
| `wsl --status`, `wsl --list --verbose`, `wsl --version` (root) | Ubuntu and docker-desktop stopped, both WSL2; WSL 2.7.12.0, kernel 6.18.33.2-2. Status also reports WSL1 unsupported. |
| `java -version` (root) | Microsoft OpenJDK 17.0.20.1 available; native Besu runtime compatibility not established. |
| `Get-Command besu,solc,forge,anvil,curl.exe -ErrorAction SilentlyContinue` | Only curl.exe found among these commands. |
| `Get-CimInstance Win32_OperatingSystem`, `Get-PSDrive C` (root; selected resource fields only) | Windows 11 Pro; 15.87 GB usable RAM, 5.97 GB free RAM, approximately 0.58 GB free on C: at inspection. |
| `Get-NetTCPConnection -State Listen` (root; filtered to 8545, 8546, 30303) | No listeners on these candidate Besu TCP ports. Other ports/remote hosts were not scanned. |
| `git diff --check` (root) | PASS: no whitespace errors in the working-tree diff. |

Tests are pinned to `APP_ENV=testing`, SQLite `:memory:` and empty DB_URL by
`phpunit.xml`; `tests/TestCase.php` refuses unsafe database configuration before
destructive test migrations. This run did not rerun the separate conflicting-env
isolation helper or live MySQL payroll write verifiers. MySQL checks above were
read-only. No seeding, live payroll edits, schema/dependency changes or key creation.

No blockchain RPC/transaction test was possible or appropriate in BESU-00.
Confirmed-receipt unit tests use explicit doubles and do not demonstrate real
blockchain acceptance. No new build/formatting run is needed for documentation-only
changes. The official Besu Quickstart and QBFT documentation were consulted for
the assessment; no version-dependent network configuration was implemented.

Final status: STOPPED - Awaiting authorization for BESU-01.

## 2026-10-30 - BESU-01 private blockchain network

Scope: BESU-01 only, following the completed assessment. No application source,
configuration, database, payroll or dependency changes. Full evidence and initial
failure corrections: [BESU_01_HANDOFF.md](BESU_01_HANDOFF.md).

- `docker desktop start`: engine started; `docker pull hyperledger/besu:26.10.2`
  completed successfully. CLI 29.9.2, Compose 5.6.1.
- `docker compose -f blockchain/besu/compose.yaml config --quiet`: passed.
- `powershell -NoProfile -File blockchain/besu/Test-Configuration.ps1`: passed
  topology/version, loopback publication, identity isolation, internal network,
  persistent volumes, genesis and PowerShell syntax assertions.
- `powershell -NoProfile -File blockchain/besu/Initialize-Network.ps1`: passed
  fresh initialization after direct-entrypoint fix. Reinitialization deliberately
  rejected without changing existing identities; active and diagnostic key paths
  confirmed ignored with `git check-ignore`.
- `docker compose -f blockchain/besu/compose.yaml up -d`: five real nodes started.
- `powershell -NoProfile -File blockchain/besu/Test-Network.ps1`: passed against
  real Besu 26.10.2, chain ID 20261030, four peers on all five nodes, generated
  four-validator membership, zero gas/base fee and advancing height 9 -> 17.
- Per-validator `docker compose ... logs --no-color` checks: every validator
  produced blocks.
- `docker compose ... down`, `up -d`, then network test: passed; height 26 -> 36.
  `eth_getBlockByNumber` at fixed block 5 retained its hash across recreation:
  `0x02a796fd5b237820bab99689ddea3a025d34be1962f5a486ceee154a9d422e6d`.
- `git diff --check`: passed. PHP suite not rerun: application unchanged.

Initial entrypoint double-generation, empty CORS configuration, occupied port
8545 and premature startup-readiness failures are corrected and documented.
Use host RPC `127.0.0.1:18545`; existing `besu-integrity-lab` remains untouched.
The tested network remains running. No registry, signer, payroll transaction,
application connectivity or simulated blockchain success is claimed.

Final status: STOPPED - Awaiting authorization for BESU-02.

## 2026-10-30 - BESU-01 audit

Audit scope and findings: [BESU_01_AUDIT.md](BESU_01_AUDIT.md). All six network
acceptance criteria pass for the documented single-host development boundary.
No implementation or permanent-test fixes, next phase, commit or push.

- `powershell -NoProfile -File blockchain/besu/Test-Configuration.ps1`: passed.
- `powershell -NoProfile -File blockchain/besu/Test-Network.ps1`: passed, height
  542 -> 550, correct chain/client/validator membership and four peers per node.
- `powershell -NoProfile -File .local/besu-01-audit.ps1`: independent read-only
  probes passed. Every validator proposed 25 of the latest 100 blocks; all five
  nodes agree at fixed block 0x261 and retain the earlier block-5 history.
  ADMIN/DEBUG/PERM methods disabled (-32604), personal method absent (-32601),
  invalid Host and untrusted Origin rejected (403); zero restarts/nonprivileged
  containers confirmed. Early audit-harness assumptions were corrected locally.
- Temporary fresh-identity Besu node on the same internal network remained at
  zero peers/block 0; original five nodes retained four peers each. Probe container
  removed after testing, without changing node identities or permission files.
- From application/: `php vendor/phpunit/phpunit/phpunit --colors=never --filter 'Payroll(Anchoring|Verification|Correction|Workflow)Test'`:
  133 tests / 2266 assertions passed, 02:09.866, 72 MB.
- F1 confirmed in an ignored isolated configuration copy: delete the copied
  genesis.config.qbft object, run the copied Test-Configuration.ps1, observe exit
  0/PASS. Real genesis and implementation files unchanged.
- H1: Get-Acl on state/rpc/key confirms inherited Authenticated Users Modify and
  Users ReadAndExecute. Existing trusted-host prototype limitation; not fixed.
- `git diff --name-only -- application`: empty. Active keys/scratch probes remain
  Git-ignored. `git diff --check`: passed, with line-ending notices only.

No fresh shutdown/restart, backup restore, validator-outage or production-load
exercise was run. Historical restart evidence remains valid against saved block
5. No payroll transaction or application-to-Besu integration was claimed.

Final status: STOPPED - BESU-02 not implemented.

## 2026-10-30 - BESU-02 smart contract development

Scope: BESU-02 only after committed BESU-01 c52281f. Full acceptance evidence,
addresses, exact operations and limitations: [BESU_02_HANDOFF.md](BESU_02_HANDOFF.md).
No Laravel code/dependencies/database, payroll calculations or access rules changed.

From blockchain/contracts/:

- `npm install --ignore-scripts`: installed pinned solc 0.8.38 and ethers 6.17.0.
  Initial audit found solc's transitive tmp 0.2.6 advisory. Explicit tmp 0.2.7
  override and lockfile fix it; no compiler downgrade was needed.
- `npm run compile`: warning-free compilation, optimizer 200 runs, explicit
  Shanghai EVM; public ABI emitted, full bytecode artifact ignored.
- `npm run init:signers`: separate encrypted administrator/writer/outsider keys
  created. Windows ACL inspection confirms operator and SYSTEM only. Password
  files, keystores, node_modules and full artifacts confirmed Git-ignored.
- Initial compilation failed on reserved identifier reference and then warned on
  shadowing the sha256 built-in; both corrected before successful compilation.
  One npm command failed ENOENT from repository root; rerun from workspace passed.
- Initial `npm test`: 9/10 passed. Identical retry values were correct; comparing
  ethers Result proxies failed deep equality. Test now compares plain value arrays.
- Next `npm test`: 10/10 passed, 47.958 seconds, all contract tests on real Besu.
- `npm run deploy`: receipt status 1 at block 1062, registry
  0xDE556481A1DbF7a9bB1C152Ed574f122706dfFB6. Deployment transaction
  0x433615052af8b0cae4e2cd76f6c50f1677c21ae68c833367a27c8caf13ec19a3.
- `npm run verify`: passed canonical receipt, matching compiled creation input/
  constructor, runtime hash, chain/genesis, administrator and writer permission.
- Second `npm run deploy`: verified existing deployment; no transaction sent.
- Final `npm ci --ignore-scripts`: clean lockfile installation succeeded.
- Final `npm audit --audit-level=low`: zero vulnerabilities.
- Final `npm test`: **12 tests passed, 0 failed/skipped**, 49.426 seconds. Ten
  real-Besu contract tests plus endpoint safety and published ABI/compiler checks.
- `npm run verify` after the final suite: passed against the retained deployment.

Contract cases cover exact SHA-256 bytes/event/metadata, absence, administrator/
writer separation, actual status-0 unauthorized and conflicting transactions,
identical idempotent retry by a second writer, grants/revocations with retained
history, independent correction references and zero inputs/constructor addresses.
Every test transaction uses random opaque identifiers and fictional hashes. Test
registry fixtures are separate from the recorded deployment. No simulated success
or application payroll anchoring is claimed.

From repository root:

- `powershell -NoProfile -File blockchain/besu/Test-Configuration.ps1`: passed.
- `powershell -NoProfile -File blockchain/besu/Test-Network.ps1`: passed, chain
  20261030, five real nodes, four peers each, zero fees, height 1072 -> 1079.
- `git diff --name-only -- application`: empty. PHP suite not rerun because
  application behavior/dependencies/configuration were unchanged in this phase.
- `git check-ignore` verifies new signer credentials/artifacts/dependencies ignored.
- `git diff --check`: passed, with pre-existing line-ending notices only.

The immutable administrator and colocated operator-only password files are local
prototype custody limitations. Existing BESU-01 F1/H1 findings remain; no unrelated
network fixes or next-phase code was introduced. Contract/library security audit,
production governance, restoration and application integration remain pending.

Final status: STOPPED - Awaiting authorization for BESU-03. No commit or push.

## 2026-10-30 - BESU-02 audit

Audit only; no implementation fixes, permanent-test changes or BESU-03 work.
[BESU_02_AUDIT.md](BESU_02_AUDIT.md) records all seven passing phase criteria,
security review and two reproduced deployment-tooling findings.

From blockchain/contracts/:

- `npm test`: 12 passed, 0 failed/skipped, 49.647 seconds. Actual successful and
  failed contract transactions on real Besu using separate synthetic fixtures.
- `npm run verify`: successful canonical deployment at block 1062 verified,
  source/constructor/chain/genesis/runtime/initial authorization checks passed.
- `npm audit --audit-level=low`: zero reported vulnerabilities.
- `npm run deploy`: verified/reused recorded address, no transaction sent.

From root:

- `node .local/besu-02-audit.mjs`: F1 reproduced using a local HTTP 307 redirect
  from accepted 127.0.0.1 to directly rejected 127.0.0.2. Relay forwarded only
  allowlisted read calls to real Besu; no public host or signed payload involved.
  F2 reproduced with an in-memory manifest copy containing blockNumber=-1 and a
  zero blockHash; verifyDeployment still passed. On-disk deployment record stayed
  unchanged. Altered genesisHash/runtimeCodeHash were correctly rejected.
  Temporary audit HTTP servers closed; scratch probe remains Git-ignored.
- `powershell -NoProfile -File blockchain/besu/Test-Configuration.ps1`: passed.
- `powershell -NoProfile -File blockchain/besu/Test-Network.ps1`: passed five-node
  connectivity/chain/fees/consensus checks, height 1269 -> 1277.
- Six new signer secret files confirmed operator/SYSTEM-only ACLs; active secrets,
  generated artifacts and scratch probes confirmed ignored by Git.
- `git diff --name-only -- application`: empty.
- `git diff --check`: passed, with existing LF/CRLF notices only.

From application/:

`php vendor/phpunit/phpunit/phpunit --colors=never --filter 'Payroll(Anchoring|Verification|Correction|Workflow)Test'`

133 tests / 2266 assertions passed, 01:04.780, 72 MB. Application adapters remain
unavailable; this compatibility evidence is not application-to-Besu anchoring.

Current phase status: Implemented but needs validation. Medium F1 and low F2 are
reported without fixes. Existing documented custody/governance/network limitations
remain. No restoration/interruption/stress/public-network experiment was run.
No Git commit or push. STOPPED before BESU-03.

## 2026-10-30 - BESU-03 payroll snapshot and hashing

Only BESU-03 implemented after committed BESU-02 53222c0. Exact canonical v1
bytes/digest are archived atomically at finalization; existing legacy hashes,
amount strings, manual preparation and correction versions remain compatible.
[BESU_03_HANDOFF.md](BESU_03_HANDOFF.md) records phase acceptance and limits.

From application/:

- `php vendor/phpunit/phpunit/phpunit --colors=never --filter 'Payroll(Snapshot|Anchoring|Verification|Correction|Workflow)Test'`:
  147 tests / 2507 assertions passed, 01:14.869, 74 MB.
- `php vendor/phpunit/phpunit/phpunit --colors=never`: **629 tests / 7809 assertions
  passed**, 03:26.497, 102 MB. TestCase/phpunit.xml enforce in-memory SQLite before
  any destructive test migrations; the configured MySQL data is not reset.
- Fourteen new snapshot cases cover all three classifications, exact archived
  bytes/hash, 32-byte digest decoding, atomic rollback on injected archive failure,
  JSON map-order/live-profile independence, independent known encoder vector,
  amount/list/version changes, three-employee deterministic creation, Unicode and
  line-ending retention, tampered archive/snapshot rejection, legacy dry-run/apply/
  idempotent capture, prior digest mismatch, no-digest capture and validation,
  model immutability, uniqueness, restricted parent deletion and rollback guards.
- Existing correction-version tests now explicitly retain original archive bytes
  across versions 1/2/3 and assert the original archive warning survives request
  return. New tests disallow submitter/reader calls and stray HTTP.
- First regression run: 132/133 passed; the archive now supplies a stronger
  correction-source warning, so those assertions were updated. First new-suite
  run: 13/14 passed; fixture baseline was refreshed to include database-default
  created_at/attribute ordering. Both issues resolved before final passing runs.
- `php vendor/bin/pint --dirty`, then scoped `php vendor/bin/pint --test` on all
  changed/new application PHP files: passed (initial test-fixture formatting fixed).
- `composer validate --strict`, `composer check-platform-reqs`: passed.
- `php artisan migrate:status --no-ansi`: exactly 18 existing migrations Ran; only
  2026_10_30_000001_create_payroll_snapshot_archives pending before application.
- `php artisan migrate --force --no-ansi`: new migration applied successfully.
  Subsequent status reports all 19 Ran.
- `php artisan payroll:archive-snapshots` and `php artisan payroll:archive-snapshots --apply`:
  both completed, Validated: 0; blocked: 0. No legacy finalized application records
  existed; no historical JSON/hash/key was rewritten or populated.
- `php artisan payroll:archive-snapshots --help --no-ansi`: dry-run/apply and
  finalization selection options registered. `php artisan app:check-foundation`:
  configured local MySQL ready.

From repository root:

- `php -l scripts/verify-payroll-workflow.php`: passed.
- `php scripts/verify-payroll-workflow.php`: passed existing three-classification
  workflow/documents/correction checks plus exact archive byte/hash idempotency,
  independent correction archives, original archive retention, >64 KB UTF-8/escaped
  line-ending text round-trip, tamper detection/restoration and complete rollback
  of fictional fixtures. Only the schema migration remains in MySQL.
- Node crypto independently calculated the fictional encoder vector SHA-256:
  b59b444791271872119426f8ffa81071b2d4c41cf047b765d779f2a1f9bde575.
- `git diff --check`: passed, with existing LF/CRLF notices only. No contract,
  network, signer, dependency-manifest or transport-binding change.

No Besu transaction, new RPC/signing implementation, payroll formula change or
permission weakening. Full preserved bytes remain confidential in the application
DB; only a fictional vector is checked in. Legacy capture without an earlier
hash is a current baseline, not historical authenticity proof. Application guards
cannot stop privileged SQL rewrites/deletion of all local evidence. Concurrency,
restoration, production capacity and existing BESU-01/02 findings remain pending.

Final status: STOPPED - Awaiting authorization for BESU-04. No commit or push.

## 2026-10-30 - BESU-03 audit

Audit only. All five explicit hashing/retention criteria pass in exercised paths,
but two current integration findings remain. Full report: BESU_03_AUDIT.md.
No implementation or permanent-test fixes, BESU-04, commit or push.

From application/:

- `php vendor/phpunit/phpunit/phpunit --colors=never --filter 'Payroll(Snapshot|Anchoring|Verification|Correction|Workflow)Test'`:
  147 tests / 2507 assertions passed, 01:13.036, 74 MB.
- `php vendor/phpunit/phpunit/phpunit --colors=never --filter PayrollDocumentTest`:
  37 tests / 818 assertions passed, 01:01.509, 102 MB.
- `php vendor/phpunit/phpunit/phpunit --colors=never --filter test_audit ../.local/Besu03AuditTest.php`:
  2 isolated probes / 37 assertions passed, 00:04.825, 66 MB. They confirm F1/F2
  without changing implementation: a structurally valid changed employee name in
  finalized JSON is exported in a 200 CSV despite intact original archive and
  invalid_snapshot verification; corrupted archived bytes leave the Prepare form
  visible on anchoring GET, while its POST correctly rejects with 409. No manual
  commitments/attempts are created, and original archive remains intact in F1.
- Scoped `php vendor/bin/pint --test` on changed/new phase PHP sources, tests and
  migration: passed.
- `composer validate --strict`, `composer check-platform-reqs`: passed.
- `php artisan payroll:archive-snapshots`: dry-run Validated: 0; blocked: 0;
  no retained records written.
- `php artisan migrate:status --no-ansi`: all 19 Ran; no migration applied in audit.

From root:

- `php scripts/verify-payroll-workflow.php`: passed actual MySQL full workflow,
  exact byte/hash idempotency, original/correction archive retention, >64 KB
  UTF-8/escaped-line-ending round-trip, tamper rejection/restoration and complete
  rollback of fictional fixtures.
- `git check-ignore .local/Besu03AuditTest.php`: excluded. Audit fixture databases
  are in-memory SQLite; reader/submitter calls and stray HTTP are prohibited.
- `git diff --check`: passed, with existing LF/CRLF normalization notices only.
- No contract/network/provider/serializer/dependency changes or chain submissions.

Medium F1 is an integrity-boundary omission in document issuance after isolated
saved-JSON tampering, not a normal-user edit/authorization bypass. Low F2 is action
availability mismatch; server-side checks remain effective. Both need regression
coverage and fixes in their own authorized work. Prior full 629 / 7809 run is
retained evidence and was not rerun in this audit. Concurrency/crash/restore,
production capacity and visual browser acceptance remain unvalidated.

Status: Implemented but needs validation. STOPPED before BESU-04.

## 2026-10-30 - BESU-03 F1/F2 bug fixes

Authorized scope: existing current-phase bugs and failing tests only. No future
phase, dependency, calculation, permission, schema or transport change.

- F1 fixed: PayrollDocumentService::prepare now requires archived-byte consistency
  before issuance. Frozen schema/arithmetic validation is separated into
  validatedProjection; diagnostic currentBytes uses that path without recursion
  or concealing a changed snapshot behind archived data.
- F2 fixed: anchoring GET checks archive consistency, displays the blocked-source
  warning and suppresses confirmation/action controls. POST remains guarded.
- Five permanent cases added: source/archive corruption before/after preparation
  across all six document endpoints and direct service, consistent GET/POST action
  blocking, retained bytes/commitments with no attempts, and valid read-only legacy
  handling. Existing 21-row pagination fixture explicitly simulates an unarchived
  legacy finalization instead of rewriting retained archive bytes.

Actual validation:

- Before fixes, the new four-case regression returned HTTP 200 instead of 409 in
  all four cases, confirming the defect. After fixes, the broader affected-suite
  run passed 185/189; four new no-marker assertions saw Laravel's debug-rendered
  test source, although all requests correctly returned 409. The new regression
  now explicitly uses production-style error rendering; app configuration is not
  changed. No implementation guard was weakened to satisfy the assertions.
- From application/: `php vendor/phpunit/phpunit/phpunit --colors=never --filter PayrollSnapshotTest`:
  **19 tests / 382 assertions passed**, 00:08.886, 68 MB.
- From application/: `php vendor/phpunit/phpunit/phpunit --colors=never`:
  **634 tests / 7953 assertions passed**, 03:16.533, 106 MB.
- From root: `php scripts/verify-payroll-workflow.php`: passed actual MySQL
  workflow/correction/archive checks plus document rejection for corrupt archive
  and changed source JSON with intact archive. Restoring synthetic data restores
  the exact original CSV. All fictional writes rolled back.
- `php -l scripts/verify-payroll-workflow.php`: passed.
- Scoped `php vendor/bin/pint --test` on bug-fix source/tests: passed.
- `composer validate --strict`, `composer check-platform-reqs`: passed.
- `php artisan payroll:archive-snapshots`: dry-run Validated: 0; blocked: 0;
  no existing data changed. No migration was applied in this follow-up.
- `git diff --check`: passed with existing line-ending notices only. Contract,
  network, canonical serializer, dependency and provider binding diffs are empty.

F1/F2 corrected; status Implemented and tested for the documented prototype.
Historical ignored audit probes retain pre-fix assertions; permanent regressions
now cover corrected behavior. Earlier-phase findings, privileged SQL limits,
production policy/custody and restoration/capacity limitations remain unchanged.
No blockchain submission, Git commit, push or BESU-04 feature. STOPPED.

## BESU-04 — Backend Blockchain Connection (2026-10-30)

Implemented only this phase; see BESU_04_HANDOFF.md. Live `php artisan besu:check --sign` passed against running Besu 26.10.2, chain 20261030 and the reviewed registry. Registry lookup succeeded and a synthetic transaction was signed offline. No transaction broadcast, payroll data modification or automatic anchoring.

Validation results:
- Full application PHPUnit run: **641 tests / 7983 assertions passed**, 3m26s. This run began with the first seven connection tests.
- Final expanded connection feature file: **11 tests / 40 assertions passed** separately, including four additional safe-failure cases and the changed-block case added while the full run was running. Thus the existing 634-test regression suite and all final connection cases passed.
- Offline Node bridge: **3 tests passed**, covering ABI compatibility, safe rejection, encrypted ephemeral signing, recovered fields and wrong writer.
- Pint dirty-file check, composer validate --strict, composer check-platform-reqs and git diff --check passed.
- npm audit --audit-level=low: **0 vulnerabilities**.
- Five network services running. Development signer ACLs contain only operator and SYSTEM; environment and credential files remain Git-ignored. No raw secrets added to tracked files.

During development, the first live check exposed PCRE bounded-repeat compilation limits; validation now uses byte-length guards plus simple hex patterns. Initial test failures were corrected in test fake reset/output assertions. All final checks above pass. Previous phase documentation remains present and uncommitted. No commit or push. Stop before BESU-05.

## BESU-05 - Automatic Payroll Anchoring (2026-10-30)

See BESU_05_HANDOFF.md. Only this integration phase was implemented after 0dd090f; no UI/future-phase work, commit or push.

Actual results:
- Full PHP regression run: 661 cases / 8122 assertions, zero failures/errors, one skipped opt-in live test, 05:04.243. Run started before the final two nonce/JSON-identity tests were added.
- Final expanded focused run: 29 cases / 209 assertions, zero failures/errors, one skipped opt-in live test. It covers all final Besu connection, anchoring and receipt-verifier cases, including the two later additions. Existing 634 payroll regressions are included in the full run.
- Explicit real-Besu integration: 1 test / 18 assertions passed, 00:22.952. Actual fictional authorized-finalization workflow, durable transaction, successful receipt, matching registry hash, repeated processing with one signed/confirmed event and writer nonce increase exactly one.
- Public transaction proof: 0x8668ae90aa3afeb097a2ba3c17cae180feed73d7160cf571bf18ca3b5b9980ed; block 4881, hash 0x89a7dad2b82a3c87c0569c76b55b8619090426ae89a324500adf13d06b9ce4e0. No payroll content was transmitted.
- Node offline bridge: 3 tests passed, including signed transaction/event compatibility and rejection of wrong chain/status/hash/event/digest.
- Existing real MySQL workflow script passed with automatic anchoring disabled for that process; fictional writes rolled back.
- Composer validate --strict and platform requirements passed; npm audit --audit-level=low reported zero vulnerabilities; Pint and git diff --check passed.
- Existing Docker Desktop/Besu and Laragon MySQL were stopped initially and started with their existing configurations. First live test failed due to RPC unavailable; reruns passed. The first ambiguous-send test had a duplicate Mockery sign expectation; corrected test passes without weakening journal assertions.
- Only migration 2026_10_30_000002_create_besu_anchor_outbox was pending and applied to configured MySQL. The new automatic flag was appended to ignored local .env; other environment settings preserved. Empty outbox command and scheduled minute registration passed. No persistent scheduler process installed.

MySQL JSON object ordering is normalized for identity comparison and tested. Broadcasting inside an outer database transaction is rejected. Signed transaction data is encrypted before durable commit and never logged. Incomplete sends retain the original signed bytes/nonce and do not become successful confirmations. Operator must run Laravel's scheduler for ongoing automatic processing. Production custody, failover/load/crash stress and BESU-06 UI remain outside this phase.

## BESU-05 F1/F2 bug fixes - 2026-10-30

Scope: current phase bugs only. Permanent regressions first reproduced the pending-head starvation and writer-revocation read failures. The processor now rotates by attempt timestamp, including incomplete processing. Historical reads/receipts retain all chain/contract checks but do not require current signing authority; new signing and broadcasts remain authorization-gated.

- Full PHPUnit run: 666 cases / 8209 assertions, zero failures/errors, one skipped opt-in live case, 03:37.163.
- Final expanded focused Besu run: 33 cases / 276 assertions, zero failures/errors, one skipped opt-in live case, 00:06.279. Includes the late broadcast-authorization guard and its regression, added after the full run started.
- Node bridge: 3 tests passed.
- MySQL workflow script passed with automatic anchoring disabled only for that legacy check process; all fictional writes rolled back.
- Pint and git diff --check passed.
- No migrations, environment/dependency changes, payroll rules, UI, commit, push or next-phase code in this bug-fix turn.
- Final explicit live Besu integration after the broadcast guard: 1 test / 18 assertions passed, 00:17.062. Exactly one transaction, 0x6eb81d20d217e5c4a6efa2a8e584e41e3db1c79dadde7091d3d17b9b837fed88, confirmed in block 6786; repeated processing preserved its signature/confirmation and the frozen snapshot.

## BESU-06 - Integrity Verification UI

Implementation only through BESU-06 after committed BESU-05 (2cf4fb8). See BESU_06_HANDOFF.md.

Validation performed:
- Full application PHPUnit run: 679 cases / 8433 assertions, zero failures/errors, two opt-in live cases skipped, 03:50.933.
- Expanded UI/legacy verification/finalization tests: 47 cases / 782 assertions, zero failures/errors, one skipped opt-in live UI case, 00:28.727. This validates the final wording after clearing a stale compiled-view cache.
- Final UI feature file after the failed-status staleness refinement: 12 cases / 205 assertions, zero failures/errors, one skipped opt-in live UI case, 00:04.711. The last message/staleness assertions were added after the full run began; they passed separately.
- Controlled-response visual probe: 1 case / 14 assertions passed; actual Blade response rendered in headless Edge at 1440px and visually reviewed with the existing styles. HTML/screenshot/probe/browser profile remain ignored under .local. No live blockchain confirmation is implied by this visual fixture.
- npm run build passed (Vite 7.3.7); generated build files remain ignored. Composer validate --strict and check-platform-reqs passed; Pint and whitespace checks passed.
- Existing MySQL workflow verification passed with automatic anchoring disabled only in that process, across three classifications with all fictional writes rolled back.
- Only migration 2026_10_30_000003_link_besu_verification_history was pending; applied successfully to configured MySQL. All 21 migrations now show Ran. No existing verification records were rewritten.

Explicit live UI integration failed at its prerequisite: its synthetic anchoring transaction remained pending after polling (1 failed case / 10 assertions). RPC remained reachable, but QBFT could not mine: node logs rejected proposed block 8820 because its timestamp was about 53815 seconds earlier than its parent; latest head was 8819. No clock, genesis, data, signer or nonce reset was attempted. A successful new real-Besu UI confirmation is not claimed. The retained opt-in live test covers Verified, actual endpoint unavailability, isolated snapshot tampering, unchanged outbox and unchanged writer nonce once the network clock recovers.

Initial development failures: an inline Blade PHP expression caused a compiled template parse error, fixed using block syntax; three old workflow assertions expected the obsolete unconditional no-anchor message, now updated to assert accurate status; a compiled-view cache retained old local-status wording until view:clear; viewer text was adjusted so restricted action guidance is not shown as an available operation. No authorization guard was weakened.

No signing/broadcast from verification, payroll formula/role/environment/dependency changes, Git commit, push or BESU-07 implementation. Full live validation remains blocked by the external validator-clock condition; controlled-response acceptance cases pass.

## BESU-06 F1 current-phase bug fix

- Corrected stale Verified summaries after stored/configured identity or batch-reference changes by retaining and comparing the checked context fingerprint. New nullable column applied through migration 2026_10_30_000004_preserve_besu_verification_context; all 22 migrations show Ran. Existing history is not backfilled or rewritten.
- Before the fix, three permanent changed-identity regression cases failed. After the fix, full PHPUnit run: 685 cases / 8550 assertions, zero failures/errors, two opt-in live cases skipped, 03:34.291.
- Final focused UI/legacy verification run: 50 cases / 817 assertions, zero failures/errors, one opt-in live case skipped, 00:24.206. This includes the final queued-context refinement and assertions, added after the full run started: an absent signing identity is stable for a queued check, not perpetually stale. JSON key-order changes remain equivalent.
- Tests also cover simultaneous config/journal changes, changed reference, older linked history without a context fingerprint, fresh check replacement, unchanged original records and migration rollback protection.
- Existing real MySQL payroll workflow script passed with all fictional writes rolled back; automatic anchoring was disabled only for that legacy-check process. Pint and git diff --check passed.
- Read-only network inspection still finds head 8819 and rejected proposals for 8820 due to backwards timestamps (approximately -51831 seconds). The explicit live integration was not rerun while this known condition persists, avoiding another pending synthetic transaction. No new successful live confirmation is claimed.
- No clock, chain, genesis, credential, environment, calculation, authorization, UI redesign, dependency, Git commit/push or BESU-07 changes. The old ignored audit probe retains pre-fix expectations as historical evidence; permanent regressions cover corrected behavior.

## BESU-07 - Outages, Corrections, and History (2026-10-30)

- Final full application suite: 701 cases / 8764 assertions, zero failures/errors, three opt-in live tests skipped; 03:56.653, PHP 8.5.12 / PHPUnit 12.5.40, isolated in-memory SQLite.
- Explicit live read-only recovery: 1 test / 4 assertions passed (10.950 seconds), existing fictional commitment, nonexistent signing paths and unchanged writer pending nonce; recovered the canonical transaction from block 6786 without signing/broadcasting.
- Node offline bridge tests: 3 passed, including event topics, exact signed-transaction reconstruction and modified-input rejection. No deployed contract changes or new live transactions.
- Existing MySQL rollback-only payroll workflow script passed with automatic anchoring disabled only for that process. Migration 000005 applied successfully; journal backfill/retention tested in isolated SQLite.
- Vite production build, Composer manifest/platform requirements, Pint and whitespace checks passed. Generated assets remain ignored; no dependencies added.
- Initial validation found SQLite index-drop ordering in the upgrade fixture/rollback, now fixed. A stale compiled Blade view caused an old-page assertion failure; clearing the view cache resolved it. Final full results include both fixes and separate corrected-version transaction journal assertions.
- Fresh live mining/corrected-version confirmation remains unvalidated because of the existing QBFT clock regression/stalled head. Controlled transport tests do not establish a new real-chain confirmation. See BESU_07_HANDOFF.md for operator use and limits. Stop before BESU-08.
