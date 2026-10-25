# Development progress

Updated: 2026-10-25 (Asia/Manila).

## Authorized work

Phase 4 - Payroll Period Management authorized by the user's instruction to
proceed after the Phase 3 commit. Phase 5 has not been authorized.

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

## Next phase

Phase 5 - Earnings and Deduction Inputs. Do not proceed automatically.

Final status: STOPPED - Awaiting permission to continue.
