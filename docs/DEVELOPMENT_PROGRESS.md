# Development progress

Updated: 2026-10-26 (Asia/Manila).

## Authorized work

Phase 7 - Contributions and Withholding Tax is now authorized by the user's
instruction to proceed to the next phase, implement only that phase, test and stop.
The user confirmed no client-approved rules/examples are available; production
statutory processing remains blocked. Phase 8 has not been authorized.
Phase 6 - Core Payroll Calculation was authorized after the Phase 5 commit.
After Phase 6 commit `8217a68`, the user authorized replacing email-based login
with usernames and removing email columns. This change does not begin Phase 7.
The user subsequently requested a UI/UX refresh using UI UX Pro Max. That work
updated existing interfaces only; Phase 7 was not authorized at that time.

## Initial inspection

- The workspace contained `docs/master-prompt.md` and a local `Microsoft/`
  directory, with no existing application or Git repository.
- No working application functionality needed migration or replacement.
- Available tools: PHP 8.5.10, Composer 2.10.3, Node.js 24.19.0, npm 11.17.0,
  MySQL Community Server 8.4.3 (Laragon installation).

## Phase 0

Status: Implemented and tested.

- Laravel 13 application created in `application/`.
- Blade startup page, local Tailwind/Vite assets, and Asia/Manila configuration.
- Standard Laravel directory conventions and base migrations retained.
- Isolated local MySQL helper, generated credentials, and database readiness command.
- PHPUnit coverage for startup, health, persistence, and readiness failures.
- Setup instructions and the four required project tracking documents created.

Verification is recorded in [TESTING_STATUS.md](TESTING_STATUS.md). No payroll,
authentication, approval, reporting, or blockchain feature is implemented in this phase.

Acceptance evidence:

- Live startup page, `/up`, compiled CSS, and compiled JavaScript returned HTTP 200.
- The configured MySQL instance has all three base migrations applied; readiness
  and transactional write/read/rollback checks passed.
- PHPUnit: 6 tests passed, 14 assertions. PHP formatting, Composer validation,
  platform requirements, and Vite production build passed.
- Repeated MySQL start and clean stop/restart preserved readiness.
- Local app is running at `http://127.0.0.1:8000`; MySQL is running on loopback port 3307.

Final status: STOPPED - Awaiting permission to continue.

## Audit follow-up

The local acceptance checks pass, but [PHASE_0_AUDIT.md](PHASE_0_AUDIT.md) records
four unresolved Phase 0 findings: test database isolation, repeated setup key
rotation, incomplete Composer database bootstrap, and conflicting DB_URL handling.
The commit preserves the foundation with these limitations explicitly recorded;
it does not claim the findings are fixed.

## Phase 1

Status: Implemented and tested.

- Session login/logout with session rotation, CSRF protection, generic credential
  errors, and throttling; inactive accounts cannot sign in.
- Four exclusive roles: Administrator, Payroll Officer, Approver, Viewer.
  Server gates protect workspaces and every account-management route/action.
- Administrator account listing, creation, editing, role assignment,
  activation/deactivation, and password resets. Emails are normalized and unique;
  new passwords require 12 characters with upper/lowercase and numbers, a maximum
  of 72 bytes, and no null characters.
- Account changes revoke earlier sessions using an authentication version.
  An administrator cannot deactivate or demote their own account.
- Interactive initial-administrator command with hidden password input; no public
  registration or shared seeded credentials.
- Role-specific navigation and access landing pages; later-phase payroll modules
  remain unimplemented.
- Phase 0 test-isolation finding resolved as a prerequisite: both environment and
  server variable sources are pinned, with a guard before destructive migrations.
- Access-fields migration applied to local MySQL. Existing accounts default to
  Viewer without changing their existing credentials.

Acceptance: all four roles authenticate and unauthorized role/account actions are
denied. PHPUnit passed 45 tests / 611 assertions, including existing foundation
coverage and permanent regression tests for all five audit findings. MySQL
role/authentication checks rolled back all fictional accounts. A separate two-process
MySQL check passed for reciprocal deactivation and demotion; its fictional accounts
were removed afterward.
See [TESTING_STATUS.md](TESTING_STATUS.md) for commands and verification boundaries.

The initial real administrator is intentionally left for the operator to create
with `php artisan app:create-admin`; no credentials are committed or printed.
The remaining three Phase 0 setup findings are unchanged.

Phase 1 audit follow-up: all five findings in [PHASE_1_AUDIT.md](PHASE_1_AUDIT.md)
were fixed under the user's current-phase-only authorization. Account writes lock
and recheck the actor and target before changing access, duplicate email conflicts
return validation errors with rollback, password input is compatible with bcrypt,
and forms safely render rejected input. Initial administrator setup also uses a
shared lock and rechecks after interactive input. Password-looking hashes are
treated as literal credentials. No schema change or later-phase feature was added.
Browser layout/accessibility acceptance and full production deployment remain
unverified; the unrelated Phase 0 setup findings remain open.

Post-correction audit: [PHASE_1_REAUDIT.md](PHASE_1_REAUDIT.md) confirms the stated
acceptance criteria, with no new confirmed bug or missing Phase 1 task area.
The 45-test suite and MySQL guard/concurrency checks pass again. Two independent
audit probes pass 78 assertions for additional throttle, PATCH, escaping, and
password-whitespace checks. Browser/deployment and broader coverage gaps remain
explicitly documented. Application code and Phase 2 scope were unchanged.

## Phase 2

Status: Implemented but needs validation.

- Employee create, paginated list/search, detail, and edit screens using the
  existing Laravel/Blade structure and session protections.
- Unique normalized employee number; structured names; position and office;
  permanent/coterminous/part-time classification; employment start date; and
  active/inactive record status. Login accounts remain independent.
- EmployeePolicy grants Payroll Officers writes and Approvers/Viewers reads;
  Administrator retains its Phase 1 responsibilities without employee access.
- Typed validation, safe rejected-input rendering, bound literal-word search,
  database uniqueness with controlled conflict errors, and versioned stale-edit
  rejection. Writes reauthorize the current locked user after revocation.
- Employee table migration applied to local MySQL. No salary schedule, payroll
  input/calculation, approval, upload, or later-phase feature was introduced.
- Fictional employee factory and a rollback-only MySQL employee workflow check.

Acceptance evidence: the full suite passed 76 tests / 1040 assertions (31 new
employee cases); all earlier tests remain. MySQL create/read/edit for all three
classifications, literal search, role policy, stale-edit rejection and rollback
passed. Production assets build and strict Composer validation passed. See
TESTING_STATUS.md for the full execution record and verification limits.

Client numbering/visibility conventions, full browser/office-network deployment,
and the earlier unrelated Phase 0 setup findings remain unresolved or unverified.
No demonstration employee or user was retained by the smoke check.

Audit follow-up: [PHASE_2_AUDIT.md](PHASE_2_AUDIT.md) records two confirmed defects:
full-name search omits suffixes, and invalid UTF-8 text bypasses validation then
produces MySQL/HTTP 500 errors. The existing 76-test suite still passes. Isolated
diagnostics reproduce both gaps; failed MySQL writes roll back. No application
fixes or Phase 3 work were performed during the audit. Resolve these findings
before accepting Phase 2 without qualification.

## Phase 3

Status: Implemented but needs validation.

- Administrator draft schedule maintenance, individual grade/step rates, explicit
  category/effective-date/data-use coverage, and externally referenced publication.
  Published schedules/rates are locked and remain available as earlier versions.
- Payroll Officer salary assignment with external authority and reason. Initial
  assignments, step increments, promotions and schedule updates are distinct.
  Salary records preserve source, exact decimal amount, category, position,
  service-review basis, operator, and effective date without editing prior history.
- Applicable schedule resolution by category/date/demo mode; employee monthly
  salary retrieval from the last effective authorized snapshot. New schedules do
  not silently recalculate existing employee history, and missing salary is not zero.
- Provisional three-year review reminders from an explicitly entered basis date.
  No eligibility decision or salary/step change is automatic; actual category rules
  remain blocked by client validation.
- Server policies, transactional actor rechecks/locks, duplicate prevention,
  source/date checks, immutable model guards, safe rejected salary text/error
  sessions, and role-specific navigation. Approver/Viewer history access is read-only.
- Salary migration applied to MySQL (three new tables); no production rate seeded.
  Existing Phase 2 findings remain open and no Phase 4 work was introduced.

Acceptance evidence: full suite passed 111 tests / 1683 assertions (35 salary cases
plus all 76 previous cases). MySQL verified all three categories, effective schedule
resolution, exact decimals, initial/step/promotion/schedule changes, preserved history
and rollback. Vite build and strict Composer validation passed. See TESTING_STATUS.md.

Real LGU schedules/eligibility/authority practices, browser acceptance, and broad
concurrency/deployment validation remain unverified. Retrospective correction and
cancellation of recorded future changes are not provided; new records append forward.
A repeated full-suite run also exposed an intermittent, unchanged Phase 2 search
test failure caused by non-isolated random fixture names; see TESTING_STATUS.md.

Audit follow-up: [PHASE_3_AUDIT.md](PHASE_3_AUDIT.md) records a missing strict
applicable-salary guard and mixed real/demo employee histories, reproduced by
isolated HTTP/service diagnostics. Lower-grade/lower-pay moves accepted as
promotions also need client policy validation. Both full-suite runs during the
audit passed 111 tests / 1683 assertions; MySQL, isolation, and Pint checks passed.
No application fixes or Phase 4 work were performed during the audit.

## Phase 4

Status: Implemented and tested.

- Monthly draft create/list/reopen, with fixed month/data use and paginated
  employee coverage. Payroll Officers write; Approvers/Viewers read. Administrators
  do not inherit payroll record access.
- Server-selected salary/employee snapshots retain exact decimal amounts, source,
  grade/step, authorized position, category, identity and office. Duplicate month/
  mode batches and duplicate employee coverage are prevented by database constraints.
- First-day reference, employment/source applicability checks, mode matching,
  superseded-source and mid-month-change rejection. The latest source is checked
  across all modes so a contaminated salary stream cannot supply an older fallback.
- Draft entries preserve saved values on reopen and show Needs review when sources
  or profile details change. Removing/re-adding refreshes a draft entry explicitly
  without changing salary history. Status remains Draft/Not calculated.
- Atomic writes, locked current actor/employee/salary sources, batch version checks,
  stale authority protection and safe validation. Selection adds coverage page by
  page rather than replacing previously saved employees.
- Two-table migration applied to local MySQL; rollback-only MySQL verification
  covers both modes, all categories, exact snapshots, reopening and duplicates.

See TESTING_STATUS.md for executed tests and the intermittent existing Phase 2
random-fixture failure. The earlier Phase 2/3 audit findings remain open; Phase 4
adds consumer guards rather than changing the earlier salary workflow. Client
grouping/first-day/mid-month policies, live concurrent MySQL writes and browser
acceptance remain unverified. No calculations, input amounts, approvals or Phase 5
features were introduced.

Acceptance evidence: the final full isolation run passed 148 tests / 1997
assertions, including 37 new Phase 4 cases and all 111 prior cases. MySQL draft
create/reopen, both modes, all categories, exact salary snapshots, source checks,
duplicate rejection and rollback passed. Pint, production build, strict Composer
validation, migration status and readiness checks passed.

Audit follow-up: [PHASE_4_AUDIT.md](PHASE_4_AUDIT.md) confirms both acceptance
criteria for supported prototype conventions; no new Phase 4 application defect
was reproduced. Both full-suite runs passed 148 tests / 1997 assertions; MySQL
and three additional workflow/boundary probes passed. Client policy, historical
employment handling, live races, browser acceptance and performance remain
unverified. No application fixes or Phase 5 work were performed during the audit.

## Phase 5

Status: Implemented and tested.

- Employee-specific allowance, bonus/additional earning, manual loan deduction,
  attendance and adjustment input screens on existing monthly draft entries.
  Payroll Officers write; Approvers/Viewers read current and historical inputs.
- Explicit allowance applicability, excluded-line retention and exact applicable
  allowance subtotal. New lines do not infer entitlement or common employee rates.
- Manual absence days/undertime minutes, original/reference and authorized
  replacement adjustment amounts, earning/deduction direction and required reasons.
  No pay, absence, tax or contribution formulas are activated.
- Safe typed/bounded input validation and add/remove-line UI. Source/profile
  rechecks, current locked actor/batch/entry/employee authorization, versioned
  stale-save/duplicate rejection and transactionally saved revisions.
- Immutable input revisions preserve full input sets, source context, operator,
  authority and reasons. Empty sets clear current lines while retaining originals.
  Employee/batch identity retains history through coverage removal/re-addition;
  context changes require a reviewed new input revision before later processing.
- One revision-table migration applied to local MySQL. Rollback-only MySQL checks
  verify all personnel categories and both modes, independent employee values,
  exclusions, exact decimals, edited history and coverage refresh.

See TESTING_STATUS.md for executed evidence and a form-validation regression
found and corrected during implementation. Existing Phase 2/3 audit findings and
Phase 4 client-policy limits remain open. Eligibility, authorization practice,
attendance/adjustment semantics, browser acceptance, live races and deployment
remain unverified. No loan scheduling, uploads, approval workflow or Phase 6
calculation engine was introduced.

Acceptance evidence: the final full isolation regression passed 182 tests / 2387
assertions, including 34 new input cases and all 148 earlier cases. MySQL input
and prior draft workflows passed with fixture rollback. Pint, Node syntax check,
production asset build, strict Composer validation, eight-migration status and
database readiness checks passed. Browser interaction/visual acceptance and
simultaneous writer tests were not performed.

Audit follow-up: [PHASE_5_AUDIT.md](PHASE_5_AUDIT.md) records one reproduced
medium defect: global rejected form values can prefill a different employee's
input form before the original redirect consumes the flash. Database state changes
only after an authorized resubmission; prior revisions remain preserved. Both full
suite runs passed 182 tests / 2387 assertions, and MySQL checks passed. Additional
diagnostics verified rollback/history pagination and reproduced the form-state gap.
No application fixes or Phase 6 work were performed during the audit.

Correction follow-up: the rejected-input defect is fixed. Validation redirects
carry server-derived batch/entry/employee identity; only the matching form restores
old groups, attendance, authority, reasons and errors. Other forms retain their own
saved/default values. The obsolete client form-type marker was removed. Seven
permanent regression cases cover employee/month isolation, unsaved recipients,
forged identity, matching-form retries, UTF-8 preprocessing, JSON errors and
service validation. The full suite passed 189 tests / 2472 assertions (41 Phase 5
cases), and MySQL input checks passed with rollback. No schema changes, new
features or Phase 6 work were introduced by this correction.

## Phase 6

Status: Implemented and tested for the supported demonstration scope.

- Exact-decimal core engine using full-month authorized salary references,
  applicable allowances, bonuses, manual loan deductions and authorized adjustment
  replacements. Originals remain audit references and are not counted twice.
- Transparent per-employee line breakdown, excluded allowances, aggregate totals
  and negative-core-net review flags. No implicit rounding, capping or input edits.
- Immutable versioned runs/items snapshot source context and input revisions.
  Only currently covered employees and their latest reviewed inputs participate.
  Repeated calculations for one draft/engine version are idempotent; later draft
  versions preserve earlier results. Changed/withdrawn sources are flagged on view.
- Current actor/version/source checks and atomic persistence reuse existing draft
  authorization and locks. Any invalid member prevents a partial batch preview.
- Two new MySQL tables applied. Rollback-only MySQL checks verify all categories,
  independently prepared item/batch values, preservation, history and real-mode blocking.

Acceptance evidence: `composer test` passed 220 tests / 2737 assertions, including
31 new Phase 6 cases and all 189 earlier cases. The focused suite first caught a
Blade rendering defect, corrected before the full passing run. MySQL checks,
Pint, production build and strict Composer validation passed; see TESTING_STATUS.md.

Real-mode processing and nonzero attendance remain blocked. The demo's full-month
basis is provisional; category rules, attendance formulas, statutory contributions,
withholding and any minimum-net policy require validation. Missing statutory and
attendance amounts are null/not calculated, not zero. No approval, finalization,
rule configuration or Phase 7 features were added. Existing Phase 2/3 findings,
browser acceptance, live concurrent writer and performance gaps remain open.

Audit follow-up (2026-10-26): [PHASE_6_AUDIT.md](PHASE_6_AUDIT.md) confirms all
three acceptance criteria for supported demonstration inputs; no new defect or
missing required demo workflow was confirmed. Full isolation regression passed
220 tests / 2737 assertions; three additional probes passed 114 assertions for
zero replacements, detail/history pagination and changed salary sources. MySQL
and Pint checks passed. Real processing/client rules, browser acceptance, live
concurrency/recovery and performance remain unverified. Audit/tracking documents
and ignored diagnostics only; no application fixes or Phase 7 work.

## Username sign-in change after Phase 6

The user requested replacing email login with username and removing the email
column. Login, account lists/forms, initial administrator provisioning, factory,
existing regression fixtures and MySQL verifiers now use usernames. A shared rule
enforces normalized unique usernames while retaining password/role/session checks.

The upgrade migration derives existing usernames, resolves collisions and removes
email/verification columns; obsolete reset tokens are cleared and their unused
identifier renamed. Original account IDs, credentials, roles and payroll references
are preserved. Deleted emails are not recoverable by rollback (placeholder addresses
are used). The local MySQL account migrated to username `admin`; its password and
other preserved account attributes were unchanged. Verification is recorded in
TESTING_STATUS.md. No statutory/payroll functionality or Phase 7 work was added.

Status: Implemented and tested. Thirteen new username/migration cases pass; the
final full isolation run passed 233 tests / 2902 assertions. Existing access,
MySQL authentication/concurrency and all workflow verifiers passed. Pint and
production build passed. The first full run hit the previously documented random
employee-search fixture failure; its search logic/fixtures were left unchanged.

## UI/UX refresh after Phase 6

Status: Implemented but needs validation (visual/browser acceptance).

- Applied the requested UI UX Pro Max skill's verified government palette,
  accessible styling, operational dashboard and Laravel feedback guidance.
- Shared navy/blue interface, desktop/sidebar and mobile disclosure navigation,
  active links, breadcrumbs, role-specific dashboard/workspace cards and split login.
- Consistent fields, tables, status badges, draft/schedule lists, empty states,
  calculation totals and line breakdowns. Existing prototype warnings remain visible.
- Inline errors, focusable linked summaries, skip link, keyboard scroll regions,
  password visibility, reduced-motion rules, pending submit feedback and contextual
  input-line limit/focus feedback. No payroll values or permissions were changed.
- Six new interface tests pass, and the full suite passes 239 tests / 2974
  assertions. Build, Pint, JavaScript syntax, 36 served-page/asset checks and six
  token contrast checks pass. See TESTING_STATUS.md and UI_UX_GUIDE.md.

The local review used a separate ignored SQLite database with fictional accounts
and payroll data; the office MySQL database was not changed. No connected browser
was available, so screenshots, actual viewport layout, screen-reader behavior and
live JavaScript interactions remain unverified. No backend/rule/schema changes,
dependencies, Phase 7 work or Git commit were introduced.

## Phase 7 - Contributions and Withholding Tax

Status: Implemented but needs validation. The supported reference engine/workflow
is implemented and tested; client-approved real processing is blocked.

- Separate per-employee contribution/withholding reviews from preserved core
  previews, using explicit fictional coverage and reviewed bases for each category.
- Source-controlled regulatory reference parameters, with ordinary GSIS,
  tiered/capped Pag-IBIG and shared employed PhilHealth employee calculations.
  All category applicability remains unverified; real calculations stay disabled.
- Monthly regular-only, cumulative monthly average and December annual tax
  scenarios. Configurable shared bonus exemption; taxable excess included once in
  taxable compensation; previous withholding and credits/refunds recorded.
- Explainable exact-decimal calculations with explicit reference rounding;
  incomplete inputs/rules produce issues and null totals. No statutory zero is
  inferred from missing data. Manual tax/YTD inputs are not a verified ledger.
- Immutable statutory revisions preserve complete inputs, parameters/sources,
  results, operator and time. Source/version/actor checks, stale-save rejection,
  idempotent retries and core/input preservation reuse existing authorization.
- One new table, routes, linked review screen, calculator/workflow regressions and
  rollback-only MySQL verifier. No dependencies, Phase 8 settings/activation UI or
  approval/finalization features were added. See PHASE_7_HANDOFF.md for files,
  browser scenarios, sources and limits; TESTING_STATUS.md records executed checks.

Acceptance evidence: final full isolation regression passed 304 tests / 3319
assertions, including 65 new Phase 7 cases and all 239 existing cases. New statutory
and existing MySQL workflow verifiers passed with fixture rollback. Production
asset build, Pint, strict Composer validation and database readiness passed.
MySQL testing caught and corrected JSON object-key ordering in idempotent retries;
a permanent regression covers the correction. One new table was applied, along
with the previously pending username migration; all eleven migrations are Ran.

The user confirmed no approved client rules are available. Category coverage,
taxable classifications, office procedure selection/rounding, special cases,
refund/shortfall execution, browser acceptance, live concurrency/recovery and
performance remain unverified. Existing Phase 2/3 findings are unchanged.

## Phase 7 audit and correction

Phase 7 audit (2026-10-26): [PHASE_7_AUDIT.md](PHASE_7_AUDIT.md) records two
medium findings (one-cent cumulative half-cent underrounding and rejected statutory
reason prefill in another employee's salary form) and one low source-rule validation
finding (malformed brackets/limits). Full isolation regression still passes 304
tests / 3319 assertions; four additional diagnostics reproduce the findings and
MySQL statutory/core verifiers pass with rollback. Status remains Implemented but
needs validation; production isolation holds in exercised paths. No fixes or
Phase 8 work were performed. Correct the current-phase findings before treating
the reference workflow as accepted.

Correction follow-up: the user authorized current-phase fixes only. F1-F3 are
corrected: exact cumulative liability before rounding, suppressed rejected input
across statutory request/encoding/service failures, and validated bracket shapes
and contribution parameter ranges. Engine v2 saves new reviews rather than reuse
v1 results; older immutable reviews remain readable and marked for review.

Focused Phase 7 tests pass 99 cases / 528 assertions. The full isolation suite
passes 338 tests / 3502 assertions, retaining all 304 previous cases and adding 34
regressions. Expanded MySQL statutory, core and input checks pass with rollback;
build, Pint, Composer validation, readiness and whitespace checks pass. No policy
rate, schema, dependency, new payroll feature or Phase 8 work was introduced.
Client rules, browser acceptance, concurrency/recovery and performance remain
unverified. See PHASE_7_AUDIT.md and TESTING_STATUS.md for details.

## Next phase

Phase 8 - Payroll Configuration. Do not proceed automatically.

Final status: STOPPED - Awaiting permission to continue.
