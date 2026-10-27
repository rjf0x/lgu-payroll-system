# Testing status

Updated: 2026-10-26. Phase 8 corrections pass the full isolation suite: 396 tests /
4031 assertions. Phase 2/3 audit findings and Phase 4 validation limits remain open.

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
