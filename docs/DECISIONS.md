# Decisions

## 2026-10-28 - BESU-00 assessment and reuse boundary

- The separate Besu plan starts with BESU-00. Existing payroll Phases 11/12
  provide local interfaces and test-double evidence, not a completed Besu phase.
  The current next-phase authorization covers repository assessment only.
- Retain Laravel/Blade/Tailwind/MySQL, payroll calculations, independent approval,
  finalized snapshots, correction lineage and current access rules. Reuse the
  existing blockchain interfaces when their transport phases are authorized.
- Preserve historical `payroll-commitment-json-v1` digests. BESU-03 must address
  exact canonical-byte retention explicitly; changing existing JSON/amount
  representation would break commitments. A review-form hash is not an anchor.
- Future automatic anchoring must persist a durable pending intent with the
  finalization and process transport after commit. Existing unknown/pending
  reservations must be reconciled against the registry before another send.
- Do not start Docker/network setup in BESU-00. The Docker Linux engine is
  unavailable and C: has approximately 0.58 GB free; reassess storage and engine
  readiness before BESU-01. Do not clean files, install tools or change Docker
  settings as part of this assessment.
- Network/contract/writer identity, real receipts and direct registry reads must
  precede claims of anchoring or verification. Document-release outage policy
  remains unresolved. See [BESU_00_ASSESSMENT.md](BESU_00_ASSESSMENT.md).

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

## 2026-10-26 - Username sign-in requested after Phase 6

- The user explicitly requested username-based login and removal of the email
  column. This is a scoped change to existing access functionality, not Phase 7.
  Earlier email-based decisions/audits describe historical behavior.
- Use one shared ASCII username rule for login, account create/edit and initial
  administrator setup: 3–50 characters, leading letter/digit, then letters/digits,
  dots, underscores or hyphens. Normalize case and surrounding whitespace before
  validation, lookup, uniqueness and throttle keys. Preserve existing password,
  session-version, role, account-revocation and locked-write safeguards.
- Add an upgrade migration rather than rewrite applied migrations. Allocate
  lowercase usernames from the old email local part, sanitize/bound them, use
  `user-ID` for short names and append ID/attempt suffixes for collisions. Preserve
  account IDs/password hashes/roles/activity/versions/timestamps and payroll FKs.
- Remove email and email-verification columns. Clear obsolete email reset tokens
  and rename their unused identifier to username; no password-reset feature is
  added. The application continues administrator-managed password resets.
  Rollback restores placeholder addresses, not deleted original emails.
- Update account/login views, factory, existing regressions and all MySQL verifier
  fixtures to the username schema. Keep old audit documents as historical evidence.

## 2026-10-26 - UI/UX refresh requested after Phase 6

- Apply the named UI UX Pro Max skill from its installed 2.13.0 plugin directory.
  Use the verified government/navy accessibility palette and the narrower
  operational-dashboard style result; disregard the initial marketing landing
  pattern. Keep a local system-font stack for office/offline operation.
- Extend the current Laravel/Blade/Tailwind interface with shared components and
  semantic CSS tokens. Do not add another framework, remote font or dependency.
  Generic decorative SVG icons do not impersonate an official LGU seal.
- Retain existing route authorization for every navigation item and dashboard
  shortcut. Use actual existing tools; do not fabricate metrics, later-phase
  controls, payroll amounts or a production-readiness claim.
- Preserve server validation, scoped payroll retry state and saved revisions.
  Add visible field errors and a linked summary; enhance focus/ARIA descriptions
  with JavaScript only when a field match is unique. Do not highlight an arbitrary
  row when multiple forms share field names.
- Keep navigation available without JavaScript, use a mobile disclosure instead
  of a modal/focus trap, retain password-manager support and respect reduced motion.
  Input add/remove limits and business safeguards remain existing backend rules.
- Verify through the full tests, a separate fictional SQLite review database,
  served HTTP checks and measured color-token contrast. Record the unavailable
  browser connection rather than claim visual/responsive interaction acceptance.

## 2026-10-26 - Phase 7 reference contributions and withholding

- Proceed only with Phase 7 under the user's instruction. The user explicitly
  answered that no client-approved rules/examples are available. Implement a
  reviewable reference workflow; do not equate official regulatory references
  with validated LGU applicability or enable real payroll processing.
- Preserve Phase 6 core results and manual inputs. Attach immutable statutory
  review revisions to individual saved core items, retaining full rule/source
  parameters, entered bases, results and operator. Never recalculate old reviews
  from current config. One additive table is the minimal storage dependency.
- Use source-controlled reference config as a Phase 7 dependency, with explicit
  unverified entries for all three categories and a deliberately limited 2026
  reference window. Phase 8 settings maintenance, activation and effective-dated
  rule administration remain out of scope. No HTTP input can activate production.
- Require explicit coverage and monthly basis per contribution, and separately
  reviewed tax classifications, taxable compensation, qualifying bonus pool,
  elapsed monthly periods and prior withholding. Default to unknown, not zero.
  Unsupported/missing inputs or reference rules yield null affected results and
  combined totals, with explanations and `production_ready: false` in every result.
- Use official GSIS, Pag-IBIG, PhilHealth and BIR sources recorded in the handoff.
  Exclude special membership/tax cases and automatic entitlement. Support regular
  monthly-only, cumulative monthly and December annual reference procedures;
  reject regular-only processing with saved supplementary earnings. The shared
  bonus exemption is configurable and excess is included once, never hardcoded.
- Use BCMath throughout with reference half-up rounding at component totals.
  Retain eight decimal places for cumulative averages before multiplying and
  rounding liability. Client rounding approval is unresolved. Preserve credits/
  refunds separately and do not infer refund execution or a minimum-net floor.
- Reuse current actor, source/input and whole-batch core checks within a locked
  transaction before saving or reusing reviews. Reject outdated core/entry context
  and stale review revisions. Canonicalize JSON object keys for MySQL retries
  without sorting lists or coercing scalar types.
- Avoid global old-input reuse in statutory forms: only this item's saved values
  prefill its form, and rejected values must be re-entered. Keep old core screens
  explicit that their own saved totals exclude statutory amounts.
- Test literal arithmetic/boundary cases, authorization/CSRF, source/version drift,
  immutability, retries and rollback. Keep smoke fixtures transactional and isolate
  their dates with an in-memory review-year override; never rewrite operator rules.
- Stop after Phase 7 and report remaining client/browser/concurrency gaps. Do not
  implement Phase 8 or resolve unrelated earlier audit findings in this phase.

## 2026-10-26 - Phase 7 audit

- Audit current Phase 7 acceptance without application fixes or Phase 8 work.
  Retain Implemented but needs validation: passing existing tests does not negate
  reproduced cumulative rounding, cross-workflow flash-state and malformed-rule
  validation defects. Record severity, reproducible evidence and scope limits in
  PHASE_7_AUDIT.md rather than silently treating them as accepted behavior.
- Keep exact arithmetic correctness separate from unapproved client rounding
  policy. F1 contradicts the reference engine's own half-up convention; the
  production block is still required regardless of correction.
- Preserve all existing source/permanent tests and prior uncommitted work. Keep
  four reproduction probes in ignored local audit storage; they verify observed
  defects and are not corrected-behavior regressions. Do not claim a production
  approval, full statutory accuracy or browser/concurrency acceptance.

## 2026-10-26 - Phase 7 audit corrections

- Fix only confirmed current-phase findings under the user's instruction. No
  settings UI, new tax/contribution procedure, Phase 8 work or production activation.
- Evaluate cumulative selected-bracket arithmetic against exact total amounts;
  use divided averages for display only. Preserve the existing half-up convention.
  Bump the engine identifier to v2 so v1 results cannot satisfy current-engine
  retries. Retain historical reviews and identify their older engine in the UI.
- Suppress statutory `_old_input` at the outer route middleware boundary on
  validation redirects, including encoding and service failures. Keep errors and
  JSON behavior. This enforces the saved-values-only form policy without changing
  unrelated salary-form retry behavior.
- Validate list/tuple shape before tax-table numeric access and positive active
  contribution parameters with logical rate/threshold/floor/cap bounds. Invalid
  reference configurations produce explained incomplete results. No statutory
  rate or applicability rule was introduced.
- Add permanent corrected-behavior regressions and expand rollback-only MySQL
  checks. Retain the audit's original evidence as historical; permanent tests
  supersede its ignored former-defect probes. Client validation remains pending.

## 2026-10-26 - Phase 8 payroll configuration

- Implement configuration maintenance only. Preserve Phase 7 methods/rounding and
  the real-payroll block. No actual client rules are seeded or automatically approved.
- Use immutable activated versions/events with revisable drafts, unique creation
  keys, revision checks and locked current-actor authorization. Audit persistence
  is atomic. A database constraint permits one activation per category/mode/date.
- Administrators create/revise/copy/activate; other active roles read activated
  settings only. Require authority and reviewed-example references, reason and
  confirmation; external data use also needs client-validation confirmation.
  These record operator claims, not independent external verification. A second
  configuration approver policy remains for client validation.
- Scope each version to one category/data use and a first-of-month date, matching
  current monthly preparation while mid-month policy is unvalidated. Reference
  review year comes from that effective date. Reject expired-year calculations.
- Maintain existing formula families, two-decimal parameters and six-row tables.
  Validate complete schema/sources and engine bounds at save/activation; do not
  infer new formulas or operational entitlement rules.
- Select active versions by effective date rather than creation time. Retain the
  unverified built-in fallback when no maintained version applies; it is never
  automatically made an approved database version.
- Recheck the selected configuration ID before statutory persistence/retries,
  including the first maintained activation. Stale forms require reload/review.
  New explicit saves preserve full version metadata; prior results never change.
- Remove rejected configuration input from shared session flash while preserving
  errors. This prevents names/reasons prefilling other workflows.
- Keep MySQL fixtures transactional. The statutory verifier owns dated fictional
  configurations and confines malformed-data fault injection to those rows before
  restoring/rolling back; it never edits operator configurations.
- Test preservation of existing immutable snapshots. Actual finalized lifecycle
  testing belongs to Phase 9. Stop after this phase's checks/documentation.

## 2026-10-26 - Phase 8 audit

- Audit current-phase acceptance without implementation fixes or Phase 9 work.
  Change Phase 8 status to Implemented but needs validation because the source-
  reference trust boundary, creation fingerprint ordering and date-only audit
  representation have reproduced defects despite the passing existing suite.
- Distinguish confirmed unsafe href rendering from inferred browser execution;
  the diagnostic does not execute script, fetch employee records or exfiltrate
  data. Record the click/administrator prerequisites and medium severity.
- Distinguish the date serialization issue from actual effective-date selection:
  current selection/preserved monetary values are unchanged in exercised paths.
- Keep approved activation evidence as operator-recorded authority. Do not invent
  client approval, a second-person policy or finalized records to satisfy criteria.
- Preserve implementation/permanent tests and add only audit/tracking documents
  plus ignored isolated reproductions. Prior work remains uncommitted; no next phase.

## 2026-10-26 - Phase 8 audit corrections

- Correct only F1-F3 under the user's current-phase instruction. Retain the original
  audit evidence as historical and record corrected behavior separately.
- Permit document references as input; consume them as escaped text unless they
  are valid HTTP(S) URLs. Apply this at rendering so already stored references are
  covered without mutating records or rejecting legitimate document identifiers.
- Canonicalize associative keys recursively for creation fingerprints, preserving
  list order/types. Match older records against the immutable creation event and
  reason rather than the edited draft; retain original hashes/events unchanged.
- Serialize effective dates as calendar dates in new events. Show legacy dates
  using the configured application timezone while preserving raw JSON. Do not
  change actual activation timestamps, rule selection or saved payroll values.
- Add regressions and rollback-only MySQL compatibility checks. Fault injection
  is limited to transaction-owned fictional fixtures. No new schema, dependency,
  formula, actual approved rule, production switch, commit or Phase 9 feature.

## 2026-10-26 - Phase 8 post-correction re-audit

- Audit the corrected current phase only. Preserve application/permanent tests and
  the original audit evidence; add a separate current acceptance report and ignored
  isolated probes. No new defect was confirmed in exercised supported paths.
- Separate operator-recorded activation/retention acceptance from actual approved
  rules and finalized-record lifecycle acceptance. Keep client evidence unavailable
  and real processing blocked; do not fabricate later-phase records or features.
- Verify creation actor/reason identity, fail-closed legacy fallback and server
  metadata/list boundaries in additional diagnostics. Keep MySQL checks transactional
  and restore its initial stopped state. Document browser/concurrency/recovery/
  performance gaps without implying the passing sequential suite covers them.

## 2026-10-26 - Login password icons

- Replace the existing login visibility text with reusable inline eye/crossed-eye
  icons. Keep a native non-submit button, accessible action labels/tooltips and
  pressed state; reserve a 44-pixel target. Keep the control hidden until JavaScript
  attaches its behavior. No dependency, authentication rule or phase progression.

## 2026-10-27 - Phase 9 payroll review and finalization

- Reuse existing employee coverage, input revisions, core runs and statutory
  previews. Submit a fixed complete demonstration snapshot; never relabel a core-
  only subtotal or incomplete statutory scenario as approved payroll.
- Use draft/returned/submitted/approved/finalized batch states and immutable
  submissions/events/finalizations. Returned edits require explicit recalculation
  and reviewed statutory scenarios; resubmission preserves earlier revisions.
- Permit only independent Approvers to approve, return and finalize. The same
  Approver can approve and finalize using separate confirmed actions. Actual office
  authority remains a client-validation item; no additional signature role invented.
- Reject self-review after role changes using creator/submitter and historical
  input/core/statutory authors plus retained coverage-editor IDs. Backfill only
  knowable legacy creator/last-editor IDs; disclose that overwritten pre-migration
  coverage-only actors cannot be reconstructed.
- Lock the actor/batch and relevant employee/source/configuration rows during
  transitions, enforce current version/submission identity and save history/state
  atomically. A sorted local review token detects statutory changes outside the
  core batch revision; it does not implement blockchain anchoring.
- Revalidate current inputs/sources/configuration before approval/finalization.
  Permit return from approved status so changed values can be corrected. Finalized
  reads use preserved values and are independent of later configuration activation.
- Block real payroll, negative-net submission and tax credits/refunds pending
  validated policy. Retain original amounts; never silently cap, refund or recalculate.
- Finalize version 1 only. No reopen, finalized correction, document generation or
  blockchain feature in Phase 9. Refuse migration rollback if it would discard
  retained workflow records. Keep legacy Phase 2/3 findings outside this change.

## 2026-10-27 - Phase 9 acceptance audit

- Audit the current implementation only; preserve application and permanent-test
  sources. Put additional fictional diagnostics under ignored `.local/phase9-audit`.
- Distinguish the low self-review control-display defect from server-side access
  enforcement, which rejects the actions. Record F1 without implementing a fix.
- Keep demonstrated acceptance separate from actual payroll rules/authority,
  unknown legacy coverage actors, live browser, concurrency/recovery and capacity.
- Keep MySQL checks rollback-only and restore its initial stopped state. Exercise
  SQLite migration methods only in isolated memory outside the fixture transaction;
  do not infer MySQL downgrade or transactional SQLite migration acceptance.

## 2026-10-27 - Phase 9 F1 correction

- Correct only the confirmed action-visibility defect. Extract the existing
  independence determination into a side-effect-free shared service helper.
  Compute eligibility once during review rendering for role/status-eligible users.
- Keep the existing role/status policies and transactional independence guard.
  Rendering does not authorize a later write or cache a decision across requests;
  writes still lock/reload the actor/batch and re-evaluate current preparation history.
- Hide forbidden forms without changing payroll values, approval authority,
  correction/finalization behavior or data-use restrictions. Preserve independent
  Approver actions and the current real-payroll block.
- Add permanent HTTP regressions for each recorded preparation source and creator
  coverage; verify hidden controls, direct denials, positive independent actions and
  preserved snapshots. Leave the original audit/probe as historical defect evidence.
- No new feature, schema, dependency or later-phase implementation. Keep existing
  client, legacy-history, live browser, concurrency/recovery and capacity limits.

## 2026-10-27 - Phase 9 post-correction re-audit

- Preserve original audit/correction evidence and review the corrected sources
  without changing application behavior or permanent tests. Record current
  acceptance in PHASE_9_REAUDIT.md; put additional diagnostics under ignored
  `.local/phase9-reaudit`.
- Verify role/status rendering and snapshot preservation, foreign submission
  binding, and transaction-time revocation after rendering. Keep the old F1
  reproducer historical and exclude it from current corrected-behavior acceptance.
- Rerun applicable prior probes and MySQL verifiers with owned fictional fixtures
  and rollback, restoring MySQL's initial stopped state. Do not claim that passing
  sequential probes establish client, browser, concurrency, recovery or capacity
  acceptance. No new confirmed Phase 9 defect in exercised paths.

## 2026-10-27 - Phase 10 finalized payroll documents

- Generate registers and individual payslips only from Phase 9's immutable
  finalization snapshot. Keep current payroll read roles, scope every finalization
  to its batch, and resolve employee IDs from preserved rows rather than live
  Employee route binding. Never recalculate statutory rules or refresh identity
  from current profiles during document retrieval.
- Use on-demand authenticated HTML/print, PDF exports and register CSV. Stable
  batch/finalization/employee URLs and preserved source values support retrieval
  without another table or public payroll files. PDF bytes may contain renderer
  metadata; no byte-stability or archival signing promise is made.
- Add `dompdf/dompdf` (^3.1, locked 3.1.6) directly, plus its four locked parser/font
  dependencies. No framework wrapper, PDF service or application Python dependency.
  Follow the [official project usage](https://github.com/dompdf/dompdf/tree/v3.1.6).
  Keep remote resources, embedded PHP and PDF JavaScript disabled, restrict local
  resources to bundled fonts and use ignored application storage for renderer cache.
- Check snapshot schemas, scope/data-use flags, canonical money, unique snapshot
  identities, included line subtotals, saved component deductions/net and aggregate
  totals before issuing any document. Compare stored amounts using BCMath only;
  do not repair inconsistent snapshots or treat these checks as blockchain integrity.
- Keep UTF-8 CSV quoting explicit and neutralize leading formula/control text in
  textual cells with an export-only apostrophe. Keep saved values untouched.
  Use private/no-store responses and filenames built from period/version/numeric
  identifiers rather than names or operator-entered text.
- Use clearly marked generic A4 landscape register and A4 portrait payslip layouts
  while actual templates remain unprovided. Do not invent an LGU seal, legal form,
  signatory certification, payment-release workflow or production calculation rule.
- Retain PDF page headings, warning, period/finalization/version references and
  individual payslip identity on continuation pages. Use separate table rows for
  long payslips, avoiding non-pageable giant table cells. Verify generated PDFs
  visually and by extracted values/page bounds using fictional ignored QA fixtures.
- No new schema, document uploads, finalized corrections, blockchain feature,
  Git commit or Phase 11 implementation in this phase.

## 2026-10-27 - Phase 10 audit boundary

- Audit only the current document-generation implementation against its own
  criteria. Report client-template and operational validation gaps separately
  from confirmed bugs. Record the low-severity extreme-pagination HTTP 500 as F1;
  do not fix it during an audit or infer production approval from generic PDFs.
- Retain existing application/permanent tests. Add isolated ignored probes for
  extra malformed snapshots and document layout boundaries; count their ten
  acceptance cases / 161 assertions and the separate F1 reproducer / 12 assertions
  apart from the 479-case permanent suite. The reproducer asserts the current bug.
- Keep detailed PDF extraction/visual QA distinct from permanent binary-generation
  checks. Passing sequential SQLite/MySQL checks does not establish concurrent
  rendering, browser/print/CSV acceptance, resource failure recovery or capacity.
- Record the evidence in PHASE_10_AUDIT.md and the required tracking documents.
  Do not fix unrelated earlier findings, commit changes or begin Phase 11.

## 2026-10-27 - Phase 10 pagination correction

- Under the user's current-phase-only correction authorization, fix F1 by
  rejecting document-index pages beyond the preserved row count with HTTP 404
  before offset multiplication. Keep valid page links, snapshot totals, read
  authorization and exports unchanged.
- Add a permanent regression for PHP_INT_MAX, the first overflowing page offset
  and an ordinary unavailable page. Verify subsequent normal retrieval and
  unchanged finalized batch/snapshot/workflow events. Retain the existing
  multi-page identity/full-register test.
- Preserve the original audit and its ignored HTTP 500 reproducer as historical
  evidence. Current acceptance uses the new corrected-behavior regression.
- No new feature, schema, dependency, official template, payroll-rule change,
  Git commit or Phase 11 implementation.

## 2026-10-27 - Phase 10 re-audit scope

- Re-audit Phase 10 without changing application behavior or permanent tests.
  Verify F1 against corrected-behavior tests; retain the original HTTP 500
  reproducer as historical evidence and exclude it from current acceptance.
- Add ignored in-memory probes for exact page boundaries, malformed queries,
  authorization ordering and inconsistent snapshots. Keep those separate from
  permanent coverage and the original applicable audit probes.
- Record missing client templates and office acceptance as validation blocks,
  and PDF regression/printing/concurrency/recovery/capacity gaps as unverified.
  Do not infer production approval or implement Phase 11 from passing tests.

## 2026-10-27 - Phase 10 commit scope

- Follow the user's authorization to review and create one Conventional Commit
  for Phase 10, including its correction, tests and audit records. Do not push,
  rewrite history or start Phase 11.
- Explicitly stage the reviewed 22-file scope. Keep local environment/credentials,
  database files, dependency directories, builds, renderer caches, PDF/PNG QA and
  ignored diagnostics excluded. No unrelated source changes during commit review.
- Retain the demonstration-only boundary and unresolved client/operational
  validation limitations. A clean commit does not approve real payroll or certify
  official client forms.

## 2026-10-27 - Phase 11 local-interface fallback

- Follow the user's explicit no-test-network answer and the master prompt's
  fallback. Implement the local interface only; do not install a network,
  introduce signer credentials, manufacture transactions or claim anchoring.
- Hash an explicit versioned envelope over the entire saved finalization
  snapshot and batch/finalization/version IDs. Define application-specific
  canonical JSON rather than claiming RFC 8785: recursively sorted map keys,
  ordered lists, exact strings/integers/booleans/null, no floats, unescaped Unicode
  and slashes, no Unicode normalization/BOM/trailing newline. Preserve the
  existing decoded empty-array convention and document it. Reuse frozen-value
  document validation without recalculating or repairing source payroll.
- Keep one immutable commitment per finalization, enforced by a unique FK, with
  a separate opaque UUID retry key. Retain attempts separately from payroll
  status; never modify finalized snapshots, existing workflow events or inputs.
- Preserve existing payroll read roles. Permit local preparation and explicit
  submission only for active independent Approvers, with transaction-time actor,
  session, scope, digest, demonstration confirmation and historical preparer
  checks. A preparer changed to Approver still cannot anchor their own batch.
- Bind the submission contract to an unavailable adapter with no transport.
  Pass only digest and opaque idempotency key across that boundary. Return real
  references as pending only; no confirmed/verified state exists in this fallback.
- Reserve an attempt before calling the interface outside the service's database
  transaction; never automatically retry a side effect in a transaction callback.
  Unavailable/definite rejection permit explicit safe retry using the same
  digest/key. Pending, unknown and interrupted sends block blind resubmission.
  Suppress raw provider exceptions, which may contain credentials. A future real
  adapter must enforce durable reservation outside ambient caller transactions,
  network idempotency/reconciliation, receipt identity and confirmation policy.
- Extend the rollback-only MySQL verifier only with the unavailable binding,
  checking JSON persistence and retained finalization values without broadcasting.
  Refuse schema rollback that would discard saved commitments.
- Keep live anchoring and independent retrieval incomplete. Do not implement
  Phase 12 chain verification/correction workflows or change unresolved payroll,
  client-template, deployment and earlier audit findings in this phase.

## 2026-10-27 - Phase 11 audit boundary

- Audit the user-authorized no-network fallback without fixing application code,
  adding permanent tests, running migrations, committing or beginning Phase 12.
  Keep real anchoring/retrieval explicitly incomplete; missing network acceptance
  is not evidence of a successful deployment.
- Record the reproduced extreme attempt-history pagination HTTP 500 as F1, low
  severity. Preserve its original failing diagnostic output and a separate
  current-behavior reproducer; passing that reproducer does not fix the bug.
- Record outer-transaction loss of a test-double submission record as conditional
  hazard H1. Distinguish this from a current live-broadcast defect: the production
  adapter remains unavailable, and the HTTP controller adds no outer transaction.
- Keep exploratory probes ignored and isolated/rolled back. Report permanent
  coverage separately, and compare application/test/verifier hashes to establish
  the audit-only change boundary. No concurrency or real-chain claim from
  sequential/reentrant test doubles.

## 2026-10-27 - Phase 11 review corrections

- Correct F1 and H1 within the existing local-interface scope; do not add a
  network, confirmation/reconciliation commands or Phase 12 functionality.
- Validate the resolved page before SQL offset multiplication using the maximum
  safe integer page for 20 rows. Preserve ordinary empty out-of-range responses,
  malformed-query fallback, existing role checks and snapshot-validation order.
- Reject every submission inside an outer caller transaction before recording
  an attempt. Do not special-case unavailable/test adapters or defer a network
  side effect into a transaction that a caller may still roll back. Permit local
  preparation in transactions; a standalone submission commits its own reservation
  before invoking the interface with the same digest/key.
- Test the boundary using fresh in-memory SQLite databases without the usual
  wrapping test transaction. The base test case's destructive-migration safety
  check stays intact, with no environment bypass or shared database access.
- Adapt the rollback-only MySQL verifier to expect 409 and no attempt, rather
  than weaken the service guard to allow smoke-test submission inside a caller
  transaction. Continue verifying local commitment persistence and idempotency.
- Preserve original audit reproducers and logs as historical evidence. Execute
  permanent corrected-behavior regressions and applicable independent probes;
  do not rewrite bug reproducers to hide their original results.

## 2026-10-27 - Phase 11 re-audit boundary

- Re-audit corrected Phase 11 only. Preserve original F1/H1 diagnostics, exclude
  their assertions of former buggy behavior, and rerun current permanent tests
  plus applicable independent probes without modifying application/test sources.
- Separate successful local-fallback tests from unfulfilled genuine anchoring and
  independent-transaction retrieval acceptance. No new confirmed supported-path
  defect was found; no real network or confirmed transaction is implied.
- Inject result-persistence failure only in an isolated test double. Retention
  and blocked duplicate sends demonstrate a local safety property, not real chain
  reconciliation, live-network durability or full process-crash recovery.
- Keep new probes ignored, preserve all fixture isolation/rollback and original
  MySQL state, and compare source hashes. No application fix, permanent test,
  migration, new dependency, commit, publication or Phase 12 implementation.

## 2026-10-27 - Phase 11 commit scope

- Follow the user's explicit instruction to review, validate and create one
  Conventional Commit for Phase 11's local anchoring interface, corrections,
  permanent tests and audit records. Do not amend, rewrite history, push or start
  Phase 12. Use the existing configured Git identity.
- Explicitly stage the reviewed 27-file scope. Exclude local environment and
  credentials, database files, dependencies, generated builds/caches and ignored
  diagnostic probes/logs. Keep application behavior unchanged during commit review.
- Identify the implementation as a local interface in the commit message. Real
  blockchain anchoring, confirmation and independent retrieval remain incomplete
  under the user's authorized no-network fallback.

## 2026-10-27 - Phase 12-A verification/history scope

- Use the master prompt's subphase rule to separate verification/history (12-A)
  from authorized finalized corrections (12-B). Corrections require coordinated
  changes to approval/preparation locks, finalization uniqueness, document scope
  and anchoring identity. Do not implement that lifecycle or Phase 13 here.
- Continue the user-confirmed unavailable-network boundary. Bind a receipt reader
  with no transport; no runtime simulator or manually entered confirmation.
  Confirmed comparisons in tests use explicit doubles and do not satisfy genuine
  blockchain acceptance.
- Retain Phase 11's exact v1 envelope bytes in a shared encoder helper. Hash changed
  snapshots before arithmetic validation so isolated inconsistent tampering still
  yields a mismatch; reject invalid/unserializable values and never repair sources.
- Keep local consistency and network evidence separate. Require an identity-matched
  confirmed receipt with valid SHA-256, block and positive confirmations before a
  verified outcome. A future real adapter must enforce authorized chain identity,
  exact successful payload and confirmation/reorganization policy, with bounded
  timeouts; current read-only lookups occur under the local consistency lock.
- Permit active Payroll Officers and Approvers to run comparisons, without granting
  approval/finalization authority. Viewers read results only; Administrator's
  existing no-payroll-record scope remains. Recheck actor/session/policy and
  finalization scope under locks; apply CSRF, throttle and private response headers.
- Retain append-only verification evidence with scoped UUID request idempotency,
  comparison hashes, safe reason codes, receipt metadata and actor/time. Receipt
  retrieval never submits/retries transactions or changes anchor attempts. Display
  saved checks as historical; no continuous verification or automatic polling.
- Reuse the unchanged finalized fixture in a shared test trait, keeping Phase 11's
  permanent tests and base in-memory safety guard intact. Isolate test tampering
  and extend rollback-only MySQL verification without real chain traffic.
- Stamp new verification evidence using the application clock explicitly. Database
  defaults can use another timezone and do not follow an isolated test clock.
  Keep a frozen-clock regression for stored/displayed check time; do not alter
  timestamps or records from previous phases.

## 2026-10-27 - Phase 12-A audit boundary

- Audit current verification/history only, without changing application behavior,
  permanent tests, schema, dependencies or implementing Phase 12-B/13.
- Map all four parent acceptance criteria honestly: comparison/mismatch/history
  paths pass tested local/test-double cases, while genuine verification and the
  correction-version lifecycle remain incomplete. No new confirmed supported-path
  defect found; do not equate fake-receipt success with a real network commitment.
- Keep diagnostics isolated/ignored and source hashes unchanged. Treat injected
  unique-key/storage failures as fault probes, not proof of actual simultaneous
  writers, process-crash recovery or network/disk failure handling.
- Preserve MySQL fixture rollback and its original stopped state. Retain earlier
  limitations; no application fix, commit, push or next-subphase implementation.

## 2026-10-27 - Phase 12-A commit scope

- Follow the user's explicit instruction to review, validate and create one
  Conventional Commit for Phase 12-A's local verification/check history, its
  tests, shared fixture, migration and audit records. Use the configured Git
  identity. Do not amend, rewrite history, push or start Phase 12-B/13.
- Explicitly stage the reviewed 25-file scope. Exclude local credentials and
  environment, databases, dependencies, generated builds/caches and ignored
  diagnostic probes/logs. Do not change application behavior during commit review.
- Describe the implementation as local integrity checks/history. Genuine receipt
  retrieval/verification remains incomplete without an authorized test network;
  correction versions remain Phase 12-B and are not part of this commit.

## 2026-10-27 - Phase 12-B correction boundary

- Implement only the authorized next subphase after Phase 12-A commit `5ba1bd4`.
  Preserve the no-network fallback; do not implement Phase 13, commit or publish.
- Represent a correction as a new monthly batch linked to its finalized source
  and family root. Never reopen or overwrite a finalized batch/submission/document/
  commitment/check. Use consecutive version numbers and database uniqueness to
  prevent forks while retaining one normal version-1 root per month/data use.
- Require a Payroll Officer's immutable reason/authority request and an independent
  Approver's immutable authorization/return. Check original preparation history
  and requester identity even after role changes. The authorizing Approver may
  later approve/finalize the fully prepared replacement if still independent.
- Start the authorized draft empty. Operators must review current coverage,
  entitlements and inputs, then use all normal calculation/statutory/approval
  gates. A correction replaces the whole version; it is not a payment or delta.
  Do not infer changed amounts or auto-copy old attendance, loans or coverage.
- Bind requests and authorization to a freshly recalculated source hash, including
  a saved commitment consistency check when present. Reject altered sources,
  mismatched lineage and missing authorization. Returning an invalid-source
  request remains possible without repairing or replacing integrity evidence.
- Freeze reason, authority, actor, time and source/version lineage into corrected
  submission snapshots, which the existing canonical encoder hashes. Leave legacy
  version-1 snapshot shape and canonicalization unchanged. Keep records local;
  external adapters receive only the existing hash payload.
- Serialize requests/decisions with family locks. Scope exact UUID retries to
  source/actor/reviewed content, retain immutable decisions and reject conflicting
  retries. Transactions roll back draft creation if evidence insertion fails.
- Use UTF-8 sanitation, CSRF, throttling and private/no-store history responses.
  FK retention and model guards preserve lineage/history; migration rollback
  refuses retained corrections. Short explicit constraint names support MySQL.
- Authority references are operator attestations, not verified legal instruments.
  Real payroll, office authority/replacement policy, live browser/concurrency/
  recovery and genuine blockchain acceptance still require validation.

## 2026-10-27 - Phase 12-B audit boundary

- Audit current correction-version behavior without changing application code,
  permanent tests, schema, dependencies or implementing Phase 13. Preserve source
  hashes and keep diagnostic fixtures/probes/logs isolated and ignored.
- Report F1 as a low-severity stale-source display defect with a reproduced
  server-side 409/no-draft protection. Do not equate a passing defect-reproduction
  probe with successful acceptance or silently fix it in an audit-only turn.
- Separate tested local behavior and explicit test-double corrected receipts from
  genuine network acceptance, which remains blocked by the no-network constraint.
  Treat draft cancellation/payment/reopen exclusions as design limits, not new
  acceptance requirements. Retain office/concurrency/recovery/browser validation gaps.
- Restore initially stopped MySQL after rollback-only verification. Update only
  audit/tracking documentation; do not commit, push or start the next phase.

## 2026-10-27 - Phase 12-B F1 fix scope

- Follow the user's current-phase review/fix instruction. Resolve the confirmed
  F1 view inconsistency without adding correction features or implementing Phase 13.
- Offer authorization only when the current valid source digest matches that
  request's captured hash. Explain pending source mismatches to readers; preserve
  independent review permissions and the return path. Leave the locked POST
  source comparison and all persisted payroll/authorization evidence unchanged.
- Add a permanent failing-before/passing-after regression covering the exact
  no-commitment case, valid controls, Viewer/Approver behavior, read-only history,
  rejection of forged authorization POST and safe return with retained evidence.
- Preserve audit-time observations as historical evidence and append the fix
  resolution. Live network, office policy, real payroll, concurrency/recovery and
  live browser acceptance remain outstanding; no new feature or commit is implied.

## 2026-10-27 - Phase 12-B re-audit scope

- Re-audit the current phase after F1's fix without application, permanent-test,
  schema, dependency or business-rule changes. Do not implement Phase 13 or commit.
- Recheck F1 with corrected expectations; the original defect-reproduction probe
  remains historical. Exercise corrected return/recalculate/resubmit separately
  in an ignored test to assess a known permanent-coverage gap without changing code.
- Report no new confirmed local defect only within reviewed/executed limits. Keep
  genuine-network, client policy, operational concurrency/recovery/capacity and
  browser acceptance separate from local/test-double success.
- Preserve source hashes, roll back MySQL fixtures and restore its initially
  stopped state. Update only re-audit/tracking documents and ignored diagnostics.

## 2026-10-27 - Phase 12-B commit scope

- Follow the user's explicit instruction to review/validate and create one
  Conventional Commit for Phase 12-B demonstration correction versions, the F1
  fix/permanent regression, migration/verifier and related audit/handoff records.
  Use the configured Git identity; do not amend, rewrite history or push.
- Explicitly stage only the reviewed 24-file phase scope. Exclude environment/
  credentials, database/configuration, dependencies, generated build/cache files,
  logs/PDFs and ignored diagnostic probes. No application behavior change during
  commit review and no next-phase feature or new test implementation.
- Describe the result as authorized demonstration correction versions. Genuine
  anchoring/verification and office/client/operational acceptance remain incomplete;
  local and test-double results do not establish genuine network acceptance.

## 2026-10-28 - Explicit browser-demo seeder

- Fulfill the user's request for the fictional browser walkthrough's seeder as a
  setup convenience; do not begin Phase 13 or integrate Besu. Keep DatabaseSeeder
  empty so ordinary/default seeding does not add public demo credentials.
- Restrict BrowserDemoSeeder to local/testing even with --force. Create the four
  named demo accounts and three fictional salary/employee fixtures, then prepare
  the November 2026 draft through existing services. Submission, independent
  authorization, approval, finalization and blockchain operations remain manual.
- Preserve existing passwords and owned payroll workflow/history on repeat runs.
  Reject conflicting identifiers/source metadata and roll back the entire seed;
  never truncate, overwrite records, reset accounts or infer changes to payroll.
- Respect existing maintained statutory profiles instead of activating/replacing
  rules. Expected walkthrough totals assume the shipped reference profile. Test
  using isolated SQLite only; let the user explicitly populate their local database.
- Stabilize only the existing employee-search test's random control first names
  after a randomly generated Maria caused a valid search to fail the test. Keep
  application search behavior and employee factories unchanged.

## 2026-10-28 - Department salary schedule coverage

- Follow the user's explicit request to replace new salary schedule classification
  selection with All departments or one department. Use existing employee Office /
  department names plus retained schedule names; add no department CRUD module.
- Follow the user's explicit precedence choice: a published department-specific
  schedule effective by the date wins over a newer applicable global; newest
  source within that scope wins. Globals are fallback, then legacy classification.
- Preserve old published classification authorizations rather than silently
  expanding them to all departments or discarding them. Add nullable department/
  coverage key, retain old classification values and uniqueness for legacy data,
  and clear classification only on newly saved department coverage headers.
- Derive coverage keys server-side using trimmed/case-normalized names; normalize
  choice lists in PHP using the same rule so accent-insensitive DB collations do
  not hide a distinct department. Reject unknown scopes, overposted classification
  and duplicate coverage/date/data-use. Keep published coverage permanently locked.
- Reuse coverage resolution in employee assignment and whole-month payroll source
  checks. Block genuine midmonth winning-source changes, not irrelevant other-
  department or lower-priority global updates. Leave statutory category reviews
  and immutable employee/finalized payroll snapshots unchanged.
- Update fresh demo setup to one shared All departments salary schedule while
  preserving existing legacy demo sources. Retain legacy fixture regressions and
  add new department-specific acceptance tests. No phase advance or blockchain work.

## 2026-10-28 - Independent payroll configuration coverage

- Resume only the interrupted user request: All/one department and All/one
  classification are independent choices. Departments reuse recorded Office /
  department values and retained schedule/configuration values; no department
  management module is introduced.
- The user explicitly selected department priority over classification priority.
  Resolve department + classification, department + all, global + classification,
  then global + all; choose the latest effective activated version within a tier
  and matching demonstration/external mode. Keep built-in fallback unchanged.
- Use normalized department/classification SHA-256 keys for selection and database
  uniqueness, retaining one activation per exact scope/date/mode. Preserve accent
  distinctions and case-insensitive department identity consistently across databases.
- Backfill only new keys for legacy global/classification versions. Preserve old
  fingerprints, event JSON, parameters and saved statutory profile metadata.
  Old submissions omitting department retain global scope. New forms require the
  explicit selector. Rollback refuses to discard newly supported coverage.
- Use the calculation's saved employee office/classification for review selection;
  activation requires explicit new reference reviews before workflow can proceed.
  All classifications remain unverified individually; broad coverage does not
  confer statutory eligibility, authorize real payroll or recalculate history.
