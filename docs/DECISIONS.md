# Decisions

## 2026-10-25 - Phase 0 architecture

- Adopt the master prompt's provisional Laravel / Blade / Tailwind CSS / MySQL
  stack because no application exists. Laravel 13 supports the installed PHP;
  exact resolved dependencies are captured in Composer and npm lockfiles.
- Keep the conventional Laravel tree in `application/`, requirements/checkpoints
  in `docs/`, and development helpers in `scripts/`. Preserve the master prompt.
- Use Asia/Manila as the application timezone. Currency calculations and rounding
  are deferred to the authorized payroll calculation phase.
- Compile Tailwind/Vite assets locally and use system fonts. Normal operation
  must not depend on a public CDN; dependency installation requires network access.
- Use an isolated MySQL 8.4 instance at 127.0.0.1:3307 for this Windows development
  environment. Keep its data and generated credentials in ignored `.local/`.
  The application database account has privileges only on `lgu_payroll`.
  No global database or Windows service is changed. This is a development helper,
  not the production deployment strategy.
- Retain Laravel's base users/session, cache, and queue migrations. The user model
  is scaffolding; authentication and roles are not Phase 0 features.
- Use PHPUnit with RefreshDatabase and in-memory SQLite for isolated automated
  checks, then separately validate migrations/readiness and persistence on MySQL.
- Keep `composer dev` compatible with Windows by starting only HTTP and Vite
  processes. Exclude the default Pail process, whose PCNTL requirement is not
  supported by this PHP installation, and defer the unused queue worker.
- Laravel's minimum PHP requirement is 8.3, but this resolved dependency lock uses
  Symfony 8 and requires PHP 8.4+. The verified runtime is PHP 8.5.10.
- Keep database readiness in the CLI `app:check-foundation` command; Laravel's
  `/up` endpoint indicates application boot only. Failure output must not disclose
  connection credentials or internal database exception details.
- Keep `.env`, build output, installed dependencies, database files, and local
  runtime state out of version control. `.env.example` contains no credentials.
- Do not infer approved payroll formulas from preliminary reports. Track the
  unresolved business and deployment rules explicitly in REQUIREMENTS_STATUS.md.

References: [Laravel 13 upgrade guide](https://laravel.com/framework/docs/13.x/upgrade),
[Laravel database testing](https://laravel.com/framework/docs/13.x/database-testing),
[MySQL data-directory initialization](https://dev.mysql.com/doc/refman/8.4/en/windows-initialize-data-directory.html).

## 2026-10-25 - Phase 1 access control

- Use Laravel's existing session guard, password hashing, validation, CSRF middleware,
  and gates; no authentication framework or public registration is needed.
- Assign one backed-enum role per user, constrained by the database. Existing and
  newly constructed accounts default to Viewer. Administrator does not implicitly
  inherit payroll preparation or approval privileges; detailed payroll actions
  remain for their authorized phases.
- Restrict account management to active administrators. Retain accounts through
  deactivation rather than deletion. Prevent changing one's own administrator role
  or active status. Other administrators can reset passwords and manage access.
- Increment an authentication version atomically on every account edit. Protected
  requests reject inactive accounts or stale versions. A self-edit keeps the
  current, rotated session; all other sessions require a new login. Do not enable
  persistent remember-me login in this phase.
- Normalize emails to lowercase; enforce database uniqueness and input validation.
  Require 12-character passwords with uppercase, lowercase, and numbers for new
  accounts/resets. Preserve an existing password when the edit field is blank.
- Apply five failed login attempts per email/IP per minute, plus a route limit of
  20 submissions per IP per minute. Use generic credential errors for missing,
  incorrect, or inactive accounts.
- Provision the first administrator interactively with hidden password entry.
  The command refuses reuse when any administrator exists. Remove Laravel's
  scaffold account seeding to avoid enabling known credentials.
- Resolve the audited test-isolation issue before adding authentication database
  tests: pin both `$_SERVER` and environment values and reject unsafe resolved
  configuration before RefreshDatabase. MySQL smoke tests use rollback rather than
  destructive migrations. Other Phase 0 setup findings remain outside this phase.

## 2026-10-25 - Phase 1 audit

- Reclassify Phase 1 as Implemented but needs validation: passing normal-path tests
  does not resolve the five defects recorded in PHASE_1_AUDIT.md.
- Keep the audit separate from implementation. Reproduction probes stay in ignored
  local storage; no authentication fixes or Phase 2 features were applied.
- Preserve the existing phase sequence; recommend resolving Phase 1 findings and
  adding permanent regression coverage before employee-management work.

## 2026-10-25 - Phase 1 corrections

- Resolve only Phase 1 findings. Keep schema, roles, modules, and phase sequence
  unchanged; no employee-management features are authorized.
- Lock the actor and target user rows in ID order for writes, then authorize the
  fresh actor and check its authentication version inside the transaction. An
  actor cannot revoke itself under the existing validated rules; reciprocal edits
  serialize and preserve an active administrator. Account creation also locks
  and rechecks the actor. Retry database deadlocks up to three attempts.
- Catch unique-constraint conflicts outside the transaction and report an email
  validation error. Failed edits roll back passwords and authentication versions;
  session rotation happens only after a successful commit.
- Keep bcrypt and apply a shared input rule to login, account edits/creation, and
  initial provisioning: at most 72 bytes and no null characters. Do not truncate.
  Hash literal user input explicitly at account-write boundaries, including text
  that resembles an encoded hash. Existing overlong credentials need an admin
  reset; stored hashes do not expose their original length.
- Render only scalar/string rejected inputs in form attributes, with safe role
  and status fallbacks. Preserve validation errors across real session cookies.
- Serialize first-administrator provisioning using the configured shared cache
  lock and recheck existence after interactive input. The normal database cache
  is shared across local processes; array cache remains test-only.
- Add permanent HTTP regression coverage and a MySQL two-process concurrency
  verification script. No destructive migrations are used for MySQL checks; only
  randomly named fictional accounts created by the script are removed.

## 2026-10-25 - Phase 2 employee management

- Keep login accounts separate from employee payroll records. Store a unique
  normalized employee number, structured name, position, office, classification,
  employment start date, active flag, timestamps, and record version. Do not add
  personal/bank/contribution identifiers, uploads, salary values, or HR workflows.
- Support permanent, coterminous, and part-time as a shared backed enum/database
  constraint. Selecting a category activates no compensation rule or formula.
  Employment start date is not an authorized salary-effective date. Dates use the
  database-compatible 1000-01-01 through 9999-12-31 range; future dates are allowed.
- Assign employee create/edit actions only to active Payroll Officers. Grant
  Payroll Officers, Approvers, and Viewers read access across this prototype's
  office. Preserve Phase 1's exclusive roles; Administrator has no implicit
  employee access. Confirm client-specific record/office visibility separately.
- Use explicit EmployeePolicy checks for routes/actions and navigation. Recheck
  the locked current actor and authentication version inside write transactions
  so in-flight requests cannot write after role/session revocation.
- Reject duplicate numbers in validation and at the database constraint, converting
  concurrent constraint conflicts into field errors. Use row locking and a server
  record version to reject stale employee edits instead of overwriting them.
- Treat the employee-number character set and 50-character limit as prototype
  input conventions, not verified LGU numbering rules. Names may contain Unicode
  and punctuation; no universal name pattern is invented.
- Search all words across employee number, name parts, position, and office with
  bound LIKE parameters and explicit escaping of wildcard characters. Limit input
  to 100 UTF-8 characters and paginate 20 records with a stable order. Retain
  inactive records in searches; do not implement deletion.
- Extend the established Blade/Tailwind views and local assets. Fixtures are
  explicitly fictional and are not seeded into the office database. The MySQL
  employee verification script rolls back all of its records.

## 2026-10-25 - Phase 2 audit

- Reclassify Phase 2 as Implemented but needs validation after two reproduced
  defects: omitted suffixes in name search and malformed UTF-8 accepted before
  MySQL persistence. See PHASE_2_AUDIT.md for evidence and coverage boundaries.
- Keep audit diagnostics in ignored local storage. No application fixes, policy
  changes, migrations, or later-phase features were applied during the audit.

## 2026-10-25 - Phase 3 salary management

- Use separate schedules, grade/step rates, and append-only employee salary records.
  A schedule covers one classification, effective date and data-use mode. Do not
  assume permanent, coterminous and part-time personnel share compensation rules.
  No statutory table/rate is supplied or activated automatically.
- Administrators maintain drafts and publish against an external authorization
  reference/confirmation. Publication locks the header and every rate. Existing
  schedules remain; the latest published effective date on/before the requested
  date selects the applicable schedule within the classification and mode.
- Payroll Officers record employee assignments with their own external reference,
  reason and confirmation. Approver/Viewer access remains read-only, and schedule
  maintenance does not grant Administrator access to employee salary records.
  Internal payroll approval/finalization remains Phase 9, not part of this workflow.
- Preserve each selected rate and decimal monthly amount in a salary snapshot.
  Publishing a new schedule alone does not change employee salary; a separate
  authorized schedule-update record is required. The date lookup returns the last
  effective employee snapshot, including its source/mode metadata. Future payroll
  consumers must reject missing/demo/inapplicable records rather than infer readiness.
- Distinguish initial assignment, higher step in the same grade, promotion to a
  different grade, and same-grade/step schedule replacement. These are prototype
  record types, not a verified statutory promotion/eligibility policy. Each source
  rate must match the employee's current category and the applicable schedule date.
- Only the first employee record may establish an older starting salary. Later
  records append strictly after the last effective date and cannot predate today;
  this avoids changing previously retrieved historical intervals. No record edit,
  deletion, retrospective correction, or cancellation flow is provided in this phase.
- Store authorized position/category as historical snapshots. Do not rewrite the
  employee profile for future-dated salary changes. Warn when the present employee
  category differs from the selected historical record.
- Use DECIMAL(12,2), decimal strings, and BCMath normalization. Grades/steps accept
  positive unsigned-small-integer values as storage bounds, not invented universal
  LGU grade/step limits. No floats, prorating, tax or contribution formula is used.
- Three years from the explicitly authorized service-basis date provides a
  provisional review date, with leap-day anniversaries clamped to February 28.
  Operators enter the basis for initial/step/promotion records; schedule-only
  updates retain it. Applicability remains unverified for every category, and the
  reminder never grants eligibility or mutates salary/steps.
- Reauthorize locked current actors inside write transactions; lock employee and
  schedule rows and reject stale authority/source/date choices. Guard published
  sources/history against ordinary model edits and retain FK references. This is
  application-level preservation, not blockchain tamper verification.
- Reject malformed salary UTF-8 before validation/persistence and remove rejected
  bytes from flashed inputs so encrypted JSON sessions remain valid. Apply this
  only to Phase 3 routes; the two known Phase 2 audit findings are unchanged.
- Use clearly fictional tests and rollback-only MySQL checks. Real schedules,
  category eligibility and external authorization practice require client validation.

## 2026-10-25 - Phase 3 audit

- Reclassify Phase 3 as Implemented but needs validation. PHASE_3_AUDIT.md
  distinguishes preserved history from the missing strict applicable-salary
  lookup contract, and records real/demo stream interference and unvalidated
  promotion semantics. Existing snapshot behavior is not treated as permission
  to recalculate salaries or rewrite history automatically.
- Keep audit probes in ignored local storage. Update audit/tracking documents
  only; do not fix application code or start Phase 4 during this audit.

## 2026-10-25 - Phase 4 payroll period management

- One fixed monthly batch per data-use mode; one covered entry per employee in a
  batch. These are provisional office-grouping conventions, not an assertion of
  statutory batch limits. No deletion or period/mode editing endpoint is supplied.
- Payroll Officers prepare drafts, Approvers/Viewers read them, and Administrators
  retain configuration responsibilities. Preparation status is Draft, with coverage
  count and per-entry source checks/Needs review; no approval readiness is claimed.
- Store the authorized salary reference and exact monthly salary as a draft
  snapshot, separate from calculated pay. Reopen saved snapshots unchanged. A
  deliberate removal/re-add refreshes an entry; source salary history is untouched.
- Use first-day salary and currently active employment as a conservative prototype
  convention. Block employees joining mid-month and salaries/schedules changing
  within the month until the client supplies approved treatment. Do not prorate,
  infer attendance, calculate pay or introduce Phase 5 inputs.
- Resolve the latest employee record across all modes, then require the batch's
  mode and employee category, a matching effective published schedule and equal
  stored/source decimal amounts. Do not fall back across modes or use a superseded
  source. These guards apply to the Phase 4 consumer; the Phase 3 audit stays open.
- Add selected coverage atomically without discarding previous pages. Limit each
  request to 100 distinct employees and paginate visible lists at 20. A selected
  source is rechecked at write time under locks, not trusted from submitted amounts.
- Reauthorize the current actor, lock batch and employee/source rows, and compare
  draft versions for every coverage mutation. Database unique constraints back
  duplicate guards. Deadlock retries do not substitute for live race validation.
- Reuse Phase 3's text-encoding middleware on payroll draft mutations to keep
  rejected malformed inputs out of JSON sessions. No credentials or fixtures seeded.
- Current employee activity is not effective-dated; historical employment status
  cannot be inferred. Real grouping and eligibility require client validation.

## 2026-10-25 - Phase 4 audit

- Retain Implemented and tested for the supported Phase 4 prototype workflows;
  no new confirmed application defect was found. PHASE_4_AUDIT.md separates
  tested acceptance behavior from unvalidated policies and missing evidence.
- Historical activity reconstruction, mid-month treatment and grouping require
  client validation; do not invent rules or expand this audit into later phases.
- Keep additional integration/boundary probes in ignored local storage. No
  application or permanent test changes, fixes, migrations or Phase 5 work.

## 2026-10-25 - Phase 5 earnings and deduction inputs

- Add an immutable full input revision per employee/monthly batch save. Key
  revisions by stable batch/employee identity, not a removable draft-entry ID.
  Retain the salary-record FK and employee/salary snapshot for each revision.
  Removing draft coverage does not destroy input history; re-selection restores
  it and changed source context must be reviewed and saved as a new revision.
- Reuse batch role policy, shared text-encoding middleware and salary applicability
  checks. Lock the actor, batch, entry and employee, revalidate the current source,
  compare batch versions and increment the version in the revision transaction.
  An input save invalidates other open forms for the same batch conservatively.
- Support up to 30 manual lines per allowance/bonus/loan/adjustment group. Labels
  are distinct within a group, ignoring case. Validate allowed row keys; loan
  balances, dates, schedules and unknown fields are not part of input rows.
- Allowance applicability is an explicitly authorized operator input, defaulting
  to excluded in a new UI line. Preserve excluded entered amounts, but expose a
  filtered applicable list and exact subtotal. Do not infer category entitlement
  or treat the subtotal as a payroll calculation-engine implementation.
- Normalize nonnegative money to decimal strings with BCMath and two decimals;
  store JSON money as strings. The input maximum is ten integer digits plus two
  decimals; multiple-line allowance subtotals can exceed a single-line maximum
  and remain decimal strings. No monetary float arithmetic is introduced.
- Attendance inputs are decimal absence days and integer undertime minutes.
  Calendar-month bounds prevent impossible magnitudes without asserting working
  days or daily paid hours. No deduction formula or implied proration is active.
- Adjustments explicitly retain manually entered original/reference and authorized
  replacement amounts, direction and reason. These are not computed originals or
  automatically applied deltas. Save-level external authority, reason and explicit
  confirmation apply to the complete input set. Internal approval remains Phase 9.
- Empty groups mean none entered; a new empty set replaces current lines while
  preserving past versions. Restore rejected groups only for a server-matched
  batch/entry/employee context; absent groups on that retry remain empty.
- Future processing must start with currently covered entries, use the latest
  employee/batch revision only after source/context checks, and exclude inapplicable
  allowances. Do not sum historical revisions, withdrawn employee inputs or both
  original and replacement adjustment amounts. Tax treatment remains Phase 7.
- No schedules, uploads, statutory calculations, payroll totals, net pay or
  approval actions were added. Browser/office acceptance and actual input rules
  remain subject to validation; preserve earlier Phase 2/3/4 findings and limits.

## 2026-10-25 - Phase 5 audit

- Reclassify Phase 5 as Implemented but needs validation after reproducing
  rejected input being restored on another employee's form. PHASE_5_AUDIT.md
  records the conditional same-session sequence, unchanged database until an
  authorized resubmission, preserved history and missing regression coverage.
- Keep audit probes in ignored local storage. No application/permanent-test
  changes, fixes, migrations, commits or Phase 6 work during this audit.

## 2026-10-25 - Phase 5 correction

- Correct only the audited form-state defect. A route-specific middleware records
  bound batch/entry/employee identity on validation redirect responses, before the
  session is saved. Laravel's routing pipeline handles exceptions internally;
  inspect the response's ValidationException instead of expecting an outer catch.
- Put this middleware before the shared encoding validator so malformed-input
  retries receive the same identity protection. JSON validation errors do not
  flash input and must not establish a restore context.
- Ignore all old input and unrelated error bags on other employee/batch forms.
  Matching forms restore groups, attendance and authorization metadata, including
  empty groups. Remove the generic client-side form marker; posted identity
  fields cannot select the trusted restore context.
- Preserve ordinary session-flash lifetime. This fixes wrong-form restoration,
  not multi-tab draft caching or indefinite retention of rejected input.
- Add seven permanent regression cases and retain all earlier Phase 5 behavior.
  No salary/input persistence rules, schema, earlier-phase workflows or Phase 6
  features change. Client-rule, browser and live-race limitations still apply.

## 2026-10-25 - Phase 6 core payroll calculation

- Keep the initial engine demonstration-only. Real-mode requests fail server-side;
  no unvalidated category, statutory or attendance rule is activated. Full-month
  authorized salary supplies the fictional basic-pay basis, with zero absence and
  undertime required. Arbitrary manual adjustment labels cannot establish that
  attendance was correctly deducted; nonzero attendance fails closed.
- Use BCMath addition/subtraction on canonical two-decimal strings. Reject invalid
  precision/types rather than round saved source data. There is no division or
  fractional rate in this phase; do not invent a proration/rounding rule.
- Sum only applicable allowance lines, bonuses, earning replacements, manual loans
  and deduction replacements. Count adjustment replacement amounts once, preserving
  originals/reasons as audit references. Never mutate input revisions or salary
  sources. Preserve negative net and flag it instead of inventing a floor/limit.
- Statutory and attendance deduction components remain null/not calculated, not
  numeric zero. Label results provisional core net before statutory deductions,
  not approved/finalized payroll or real-payroll readiness. Tax treatment is Phase 7.
- Start with current batch coverage and latest employee/batch input revisions.
  Reuse preparation/input context guards under actor, batch, entry, employee and
  salary-source locks. Reject stale versions, changed source context and incomplete
  members atomically before creating any run. Recheck sources even on repeated runs.
- Preserve immutable run/item snapshots with FKs to stable batch/employee/input/
  salary identities, not removable draft entries. One run per batch version and
  engine version makes retries idempotent; changed inputs/coverage increment the
  draft version through existing workflows and permit a separate preserved run.
- Display old results from saved snapshots, with version/source/withdrawn-coverage
  review notices. Application model immutability is not database administrator
  tamper resistance or blockchain verification. No new dependency, approval,
  finalization, statutory configuration or later-phase feature is introduced.

## 2026-10-26 - Phase 6 audit

- Retain Implemented and tested for supported demonstration core previews after
  acceptance review, full isolation regression, MySQL checks and additional
  pagination/replacement/source diagnostics. No new application defect confirmed.
  PHASE_6_AUDIT.md separates that evidence from actual payroll/client-rule readiness.
- Keep real processing and nonzero attendance inactive; no guessed formula or
  statutory zero is introduced to remove a documented validation blocker.
- Keep diagnostic probes in ignored local storage and update audit/tracking records
  only. Do not fix earlier phases, alter permanent tests/schema or begin Phase 7.
