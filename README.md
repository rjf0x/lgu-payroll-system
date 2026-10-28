# LGU Payroll Management System

Browser-based payroll prototype with planned blockchain integrity verification.
Includes authentication, administrator-managed accounts, four roles, server
authorization, employee payroll records, and effective-dated salary schedules
with preserved employee salary history, monthly payroll drafts, employee inputs
and versioned demonstration core calculation previews, with separate contribution
and withholding reference reviews, with demonstration payroll submission,
independent approval/return and locked finalized snapshots. Finalized demonstration
payroll now supports printable registers and individual payslips, downloadable
PDFs and register CSV export.
Phase 2 has two open findings;
see [the employee-management audit](docs/PHASE_2_AUDIT.md). Phase 3 also needs
validation; see [the salary-management audit](docs/PHASE_3_AUDIT.md) for salary
applicability, real/demo history interference, and promotion-policy gaps.

## Layout

- `application/`: Laravel 13 application (Blade, Tailwind CSS, PHPUnit).
- `docs/`: master prompt, requirements, decisions, and phase checkpoints.
- `scripts/`: local development helpers.
- `.local/`: ignored local MySQL data, configuration, and credentials.

## Requirements

- PHP 8.4.1+ for the locked dependencies, with PDO MySQL, PDO SQLite (tests), BCMath, DOM, Fileinfo, Mbstring,
  OpenSSL, Session, Tokenizer, XML, and the standard Laravel extensions.
- Composer 2, Node.js 22.12+ (or a supported newer release), npm.
- MySQL 8.4. The Windows helper can discover a Laragon installation; otherwise
  pass `-MySqlBin 'C:\path\to\mysql\bin'`.

## Windows development setup

Run from this project directory:

```powershell
cd application
composer install
Copy-Item .env.example .env # first setup only; preserve an existing .env
php artisan key:generate
cd ..
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/local-mysql.ps1
cd application
php artisan migrate
npm.cmd ci
npm.cmd run build
php artisan app:check-foundation
php ../scripts/verify-mysql.php
php artisan serve --host=127.0.0.1 --port=8000
```

Open <http://127.0.0.1:8000>. Built assets are served locally; the application
does not require a CDN or internet access after dependencies are installed.
Guests are redirected to sign in. Core calculation previews are demonstration-only;
real payroll processing remains blocked pending validated rules.

The MySQL helper starts an isolated development server on `127.0.0.1:3307`,
creates `lgu_payroll`, generates local passwords, and configures `application/.env`.
It preserves an existing local database and never installs a Windows service.
Stop it when finished:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/local-mysql.ps1 -Action Stop
```

For an existing MySQL server instead, create an empty development database and a
user with access only to that database. Set the `DB_*` values in
`application/.env`, skip the local MySQL helper, and run the remaining commands.
Do not commit `.env`, database files, or local credentials.

## User access

Run inside `application/` after migrations:

```powershell
php artisan app:create-admin
```

Enter the first administrator's name, username, password, and password confirmation.
Password entry is hidden. Use at least 12 characters with uppercase, lowercase,
and numbers, at most 72 bytes, and no null characters. Some characters use more
than one byte. The command refuses reuse once an administrator exists. There is no
public registration or default account/password; `db:seed` creates no accounts.

For the optional fictional browser walkthrough, explicitly run
`php artisan db:seed --class=BrowserDemoSeeder` in `application/`. It creates four
demo-role accounts, three employees with a shared published salary schedule, and a prepared
November 2026 demonstration payroll draft. New demo accounts use `LocalDemo2026!`.
It is restricted to local/testing, preserves existing passwords/workflow on rerun,
and never approves/finalizes payroll. See [browser demo data](docs/BROWSER_DEMO.md)
for usernames, expected amounts, collision behavior and correction testing.

Sign in at <http://127.0.0.1:8000/login>. Administrators can create/edit accounts,
assign Administrator, Payroll Officer, Approver, or Viewer, reset passwords, and
activate/deactivate access under **User accounts**. Leave an edit password blank
to preserve it. Administrators cannot deactivate or demote themselves.

Sign-in and account management use a unique username instead of email. Usernames
are case-insensitive, 3–50 characters, start with a letter or number, and allow
letters, numbers, dots, underscores and hyphens. Surrounding spaces are removed;
stored usernames are lowercase. Account lists show Username; email is not collected.

For databases created before this change, run `php artisan migrate`. Existing
accounts receive usernames from their former email local part (before `@`), with
unsupported characters replaced by hyphens and duplicate names given an ID suffix.
Short/empty results use `user-ID`. Passwords, roles, activity, account IDs, existing
sessions and payroll references are preserved. Administrators can review/change
usernames under **User accounts**. New logins require the username.

The migration removes `users.email` and `email_verified_at`, renames the unused
reset-token identifier to username and discards obsolete email reset tokens.
Rolling it back creates placeholder addresses; it cannot recover removed emails.
Password resets remain administrator-managed; no email reset workflow is exposed.

If an account was created with a password longer than 72 bytes before the Phase 1
fix, an administrator should reset it to a supported password. Such full-length
passwords are now rejected; existing hashes cannot reveal their original length.

Each role has its own protected workspace and navigation. Account edits revoke
previous sessions; the current session survives a self-edit. Disabled users cannot
sign in. There is no remember-me login. Demonstration approval and finalization
use the separate Approver account described below.

## Employee records

After applying migrations, sign in as a **Payroll Officer** and open **Employees**.
Create a record with its unique employee number, first/last name, optional middle
name/suffix, position, office, personnel classification, employment start date,
and active/inactive record status. Permanent, coterminous, and part-time are
supported. Employee records are separate from login accounts.

Employee numbers are normalized to uppercase and accept letters, numbers, dots,
underscores, slashes, and hyphens, up to 50 characters, starting with a letter or
number. Search matches all entered words across number/name/position/office;
`%`, `_`, and `!` are treated literally. Results are paginated and include inactive
records. Two forms editing the same record cannot silently overwrite each other;
use **Reload saved record** after a stale-edit warning.

Payroll Officers can create/edit. Approvers and Viewers can list/search/view only.
Administrators retain account/configuration responsibilities and do not inherit
employee-record access. This prototype grants read access across the office;
client-specific organizational visibility needs validation before deployment.

Use inactive status to retain a record; no deletion or document upload is provided.
No salary amount or grade/step is assigned by employee entry. Use the separate
salary authorization/history workflow described below. Employment
start dates are not salary-effective dates. Default seeding creates no demo
records; the optional BrowserDemoSeeder above creates explicitly fictional fixtures.

## Salary schedules and employee salary history

As **Administrator**, open **Salary schedules**, create a draft covering **All
departments** or one department, with an effective date and data use
(demonstration or externally authorized). Department names come from employees'
Office / department values. Add the
approved grade/step monthly amounts individually and correct draft entries as
needed. Publishing requires a source reference and confirmation. Published
schedules and their rates are permanently locked; changes require another dated
schedule. No government salary table or rate is prefilled.

As **Payroll Officer**, open an employee's **Salary and preserved history**, then
**Record authorized change**. Choose the effective date and data use to find the
applicable published schedule for that department and date. A department-specific
schedule takes priority over an all-departments schedule, even when the latter
has a newer effective date; the latest effective version within that coverage wins.
Select a grade/step,
change type, authorized position, service-review basis date, authority reference,
and reason. Confirm external authorization (or explicitly fictional demo use).
Initial assignment, same-grade step increase, different-grade promotion, and
same-grade/step schedule update are distinct records.

New salary schedules do not restrict personnel classification. Employee
classification remains on employee/salary/payroll records for statutory review.
Previously published classification-based schedules remain locked legacy sources;
they are used only when no department-specific or all-departments schedule applies.
Existing assignments and finalized amounts are not rewritten. Apply migrations
before opening the updated forms; see [department salary schedules](docs/DEPARTMENT_SALARY_SCHEDULES.md).

Use **Salary as of** to retrieve the last authorized employee record effective on
that date. Its exact decimal amount and source are preserved. A newer schedule
never silently replaces an employee's recorded salary: record a separately
authorized schedule update. Subsequent changes must be today or later and strictly
after the latest recorded effective date; records cannot be edited or deleted.
Missing salary is shown as missing, not zero. Future position changes are stored
in salary history and do not rewrite the employee profile.

The three-year review date is a provisional reminder calculated from the explicitly
entered basis date. It does not establish eligibility, assume category-specific
rules, or change salary/steps automatically. Schedule-only updates retain the prior
basis. Obtain client validation of actual schedules, authority practices, and
eligibility rules before real use. Demonstration records remain clearly labeled
and are not production payroll inputs. Salary maintenance does not perform
statutory calculations; select covered employees through the draft workflow below.

Payroll Officers, Approvers and Viewers can read employee salary history;
Administrators maintain schedules without gaining employee-record access.
Only Administrators see draft schedules; all active roles can read published ones.

## Monthly payroll drafts

As **Payroll Officer**, open **Monthly payroll drafts**, create a payroll month
with its data use, then choose **Select employees**. Save selected employees on
each page before continuing. Reopen the batch from the draft list to inspect its
coverage, source references, exact monthly salary snapshots and preparation status.
Approvers and Viewers have read-only access; Administrators retain settings access.

There is one batch per month and data use, with one entry per employee. The month
and data use cannot be edited after creation. Repeated submissions and outdated
draft versions are rejected. Remove an entry from the draft to change coverage or
remove and re-add it to refresh changed source details; employee salary history
is retained. Reopening never silently recalculates or refreshes saved values.

The prototype uses the first day of the month as the salary reference date and
requires an active employee employed by that date, a matching latest authorized
salary record, and the applicable published schedule for the selected data use.
Missing, superseded, mismatched or mid-month-changing salary sources are blocked.
The latest record is checked across all modes before validating the batch mode;
an older real record is not substituted for a newer demonstration record.
Saved entries that become invalid or differ from current source details display
**Needs review**. Source checks cover the displayed page, not a whole-batch
approval. Client validation is required for first-day eligibility, mid-month
treatment and the one-batch-per-month convention. Whole-batch approval uses the
separate payroll review workflow below.
Employee inputs and demonstration calculation previews are maintained separately below.

## Draft earnings, deductions and attendance

From a covered employee in a monthly draft, open **Draft earnings, deductions and
attendance inputs**. Payroll Officers can add/edit allowance, bonus, loan and
adjustment lines, plus absence days and undertime minutes. Approvers/Viewers can
read current inputs and preserved revisions. JavaScript is required to add or
remove lines; existing line values and attendance fields can be edited without it.

Each employee has a separate input set. Add only authorized items; new groups are
empty and new allowances default to excluded. Select applicability explicitly.
Excluded allowances retain their entered values for review but are omitted from
the applicable allowance list and decimal-safe allowance subtotal. No entitlement
or category rule is inferred. Bonuses and loan deductions are manual amounts;
there is no loan balance, amortization or due-date scheduling.

Enter the original/reference and authorized replacement amounts for each manual
adjustment, choose earning or deduction, and document its reason. The reference
is manually entered, not a claimed calculated deduction. Every save requires an
external authorization (or fictional demonstration reference), revision reason,
and confirmation. An edit saves a complete new input revision, retaining previous
amounts, applicability, reasons, operator and the employee/salary context.

Each group supports 30 distinct descriptions and nonnegative monetary strings of
up to ten integer digits and two decimals. Absence days accept two decimal places
up to the calendar days in the month; undertime uses integer minutes bounded by
calendar minutes. These are input limits, not work schedules or deduction formulas.
Input saves do not calculate payroll. Use the separate demonstration preview below;
tax treatment and attendance deduction formulas remain unavailable.

Stale draft versions and salary/profile snapshots are rejected.
Validation retries restore values and errors only on the matching employee and
monthly draft entry; other input forms use their own saved or empty values.
Refresh coverage by removing/re-adding the employee when the source issue has been
resolved. Inputs remain attached to the employee/monthly batch and reappear after re-selection;
different source context requires a reviewed new revision. Removed employees'
input history is retained but is not active covered payroll input. Removing all
lines and saving records an empty current set without erasing earlier revisions.

## Demonstration core calculation previews

From a demonstration monthly draft, open **Core calculation previews and history**.
Save a reviewed input revision for every covered employee, with zero absence days
and zero undertime. As Payroll Officer, calculate a preview. Approvers/Viewers can
read the preserved results; Administrators do not inherit payroll-record access.

The preview uses the full monthly authorized salary reference as basic pay, adds
applicable allowances, bonuses and earning adjustments, and subtracts manually
entered loans and deduction adjustments. An adjustment's authorized replacement
is counted once; its original amount remains a reference. Excluded allowances
remain visible. All addition/subtraction uses exact two-decimal strings; invalid
precision is rejected rather than silently rounded. Negative core net is retained
and flagged for review, without an invented minimum-net rule.

GSIS, Pag-IBIG, PhilHealth, withholding and attendance formulas are **not calculated**,
not assumed zero. Core net is provisional, before statutory deductions, and is not
approved/finalized payroll. Real-mode batches and any nonzero attendance are blocked
server-side until rules are validated. Category compensation and full-month basis
still need client acceptance; the fictional preview does not establish entitlement.

Calculation never overwrites manual inputs. Repeating it for the same draft version
reopens the existing run; saving new inputs or changing coverage allows a new run.
History preserves employee, salary, input revision and line-by-line calculation
snapshots. Old results stay unchanged and show draft/source review notices.

## Contribution and withholding reference reviews (Phase 7)

From an employee in a saved core preview, open **Contribution and withholding
reference review**. Payroll Officers can save fictional scenarios; Approvers and
Viewers read preserved reviews. Explicitly review each contribution's coverage and
basis, tax procedure, taxable compensation, qualifying bonus pool and prior tax.
Unknown applicability leaves amounts and combined totals unavailable.

The source-controlled reference profile supports ordinary GSIS members, standard
Pag-IBIG employee savings and employed/shared PhilHealth premiums. Tax scenarios
support regular monthly pay, cumulative monthly averages and December annual
reconciliation. Taxable excess qualifying benefits are added to taxable compensation
once. No membership, category entitlement, tax classification or annual ledger is
inferred. Regulatory references do not establish client approval.

Reviews preserve their inputs, rules/sources, bases, results and operator without
changing core previews or manual inputs. Changed scenarios create separate
revisions; unchanged retries reuse the latest matching review. Outdated drafts or
salary/input sources prevent new reviews. Credits/refunds remain separate from
reference net and need review. All results remain demonstration-only; real
payroll is blocked. The reference review window is limited to 2026 pending review
for other years unless a reviewed maintained version covers that year.
Administrators maintain dated versions under **Payroll configuration**.

New reviews use the corrected v2 engine. Earlier-engine reviews stay unchanged
and display a notice; review and save a new revision to apply the corrections.

See [the Phase 7 handoff](docs/PHASE_7_HANDOFF.md) for sources, supported procedures,
remaining validation needs and a browser scenario with expected amounts.

## Payroll configuration versions (Phase 8)

As Administrator, open **Payroll configuration**, choose All departments or one
existing department, and independently choose All classifications or one
classification. Set the data use and first-of-month effective date, and review supported contribution
parameters and six-row tax tables. Record sources, membership scope and a change
reason. Draft revisions retain prior values in immutable change events. Copy an
existing version to prepare a separately dated change.

Activation requires the current revision, authority and reviewed-example references,
a reason and confirmation; external data use also requires confirmation of client
validation. These record operator authority rather than independently verifying
external approval. Activated versions are locked and readable by active roles;
only Administrators see drafts or change settings. Real payroll stays disabled.

New statutory reviews use department + classification first, department + all
classifications second, all departments + classification third, then both All.
Each level selects its latest activated version effective on the batch's first
day for its data use. Future/draft versions do not override earlier
months; expired review years remain incomplete. A form made stale by activation
requires reload/review. Existing payroll inputs and results stay unchanged; an
explicit new save creates a review with its selected configuration snapshot.
Where no maintained version applies, the unverified built-in reference remains.
Existing classification-only configurations retain their original all-departments
coverage. Apply migrations before using these selectors; see
[payroll configuration coverage](docs/PAYROLL_CONFIGURATION_COVERAGE.md).

Source references link only to valid HTTP(S) URLs; other references display as
escaped text. Creation retries tolerate reordered fields, including older saved
versions. Change history preserves calendar effective dates and original events.

See [the Phase 8 handoff](docs/PHASE_8_HANDOFF.md) for browser steps, limits,
file/database changes and remaining client validation needs.

## Payroll review and approval (Phase 9)

Open a monthly batch and select **Payroll review, approval and finalized snapshot**.
Save a current core preview and a complete contribution/withholding review for
every covered employee. Review combined totals and employee breakdowns, enter a
reason, confirm demonstration use and **Submit for approval** as Payroll Officer.
Unverified/missing reviews, changed sources, negative net and tax credits/refunds
block submission. Real payroll processing remains disabled.

Sign in using an independent **Approver**. Review the fixed submission, then
approve or return with a correction reason. A returned batch can be edited and
recalculated by a Payroll Officer and submitted as a new retained revision.
Submitted and approved batches reject preparation writes. A preparer cannot
review/finalize the same batch after changing role; coverage actors and historical
input/core/statutory authors are checked as well as the submitter and creator.

An Approver finalizes an approved submission using a separate confirmed action.
The same independent Approver may approve and finalize. Finalized version 1 stores
the submitted values, parameters and workflow evidence; later configuration or
employee changes leave it untouched. Before finalization, changed sources or
configuration require return and resubmission. Finalized payroll stays locked;
Phase 12-B adds separately authorized correction versions (described below).
Genuine blockchain anchoring has not been performed.

Apply `php artisan migrate` before use. See [the Phase 9 handoff](docs/PHASE_9_HANDOFF.md)
for manual verification, database changes, legacy history limits and pending client
acceptance. Rollback-only MySQL verification, from `application/`:

```powershell
php ../scripts/verify-payroll-workflow.php
```

## Finalized payroll documents (Phase 10)

After `composer install` and building assets, open a finalized payroll review and
select **Finalized payroll register and payslips**. Payroll Officers, Approvers and
Viewers can retrieve documents; Administrator retains its existing configuration/
account responsibilities and does not gain payroll-record access.

- **Register:** view or print the whole finalized batch, download an A4 landscape
  PDF, or export UTF-8 CSV with employee rows and full-batch totals.
- **Payslip:** view or print one employee's preserved earnings, deductions and net
  pay, or download an A4 portrait PDF. The employee list is paginated; registers
  include the entire batch. Employee identities come from the finalized snapshot.
- **Printing:** use the document's **Print document** button or the browser's Print
  command. Downloaded PDFs can also be printed from a PDF viewer.

Documents are regenerated on demand from the selected immutable finalization,
without reading current employee, input or rule values. No PDF is saved in public
storage. Authorized responses use private/no-store headers. Stored line/component/
batch amounts must reconcile exactly; incomplete or inconsistent snapshots return
409 without repair. Unfinalized or out-of-scope records cannot produce documents.
These consistency checks are not blockchain verification.

All pages/CSV rows identify demonstration use. The layouts are generic prototypes;
client payroll-register and payslip templates remain unprovided. Documents are
not proof of payment or approval for actual payroll processing. Existing real-mode
blocks and unresolved client rules remain unchanged.

PDF export uses locked `dompdf/dompdf` with bundled Unicode fonts; no Python or
external PDF service is required by the application. Its temporary/font cache is
under `application/storage/framework/cache/dompdf`, which must be writable by PHP.
Remote assets, embedded PHP and PDF JavaScript are disabled. CSV quoting preserves
commas/newlines; potentially active formula text is prefixed with an apostrophe in
the export only. Saved payroll values are unchanged.

See [the Phase 10 handoff](docs/PHASE_10_HANDOFF.md) for verification steps and
limits. The existing rollback-only MySQL workflow check now additionally verifies
register projection, CSV, register/payslip PDFs and retained document values:

```powershell
php ../scripts/verify-payroll-workflow.php
```

## Blockchain anchoring local interface (Phase 11)

No authorized test network is available. Phase 11 implements the master prompt's
local-interface fallback; live anchoring and independent transaction retrieval
remain incomplete. No local hash or simulated transaction is reported as anchored.

Apply `php artisan migrate`, then open a finalized demonstration payroll review
and select **Blockchain anchoring status and local commitment**. An independent
Approver can confirm the displayed version and **Prepare local commitment**.
One immutable SHA-256 commitment is retained per finalization. Payroll Officers,
Approvers and Viewers can read status; Administrator has no payroll-record access.

**Attempt submission through configured interface** records **Unavailable** with
no network or transaction reference. The application binds an unavailable adapter
with no transport or credentials. Retrying does not replace the digest. The
submission contract accepts only the digest and an opaque retry key. A future
real adapter must establish confirmation and independent retrieval; a returned
reference alone stays pending. Uncertain/interrupted sends block blind retries.

The [canonical v1 specification](docs/CANONICAL_PAYROLL_COMMITMENT_V1.md) defines
the exact saved snapshot envelope, JSON bytes and SHA-256 vector. The
[Phase 11 handoff](docs/PHASE_11_HANDOFF.md) records access, states, migration,
manual checks and remaining network work. The existing rollback-only MySQL workflow
verifier additionally checks commitment persistence and rejection of submissions
inside its rollback-only transaction. Normal standalone fallback submissions still
record unavailable attempts. Submission callers must finish any outer database
transaction before invoking the interface.
Phase 12 adds local verification/history and authorized correction versions below;
genuine network anchoring and verification remain incomplete.

## Integrity checks and retained history (Phase 12-A)

Apply `php artisan migrate`, then open **Payroll integrity checks and history**
from a finalized fictional payroll review. Payroll Officers and Approvers can
**Run integrity check**; Viewers read saved results only. Checks preserve the
snapshot, local commitment, submission attempts and earlier check results.

The page separates current local SHA-256 consistency from blockchain evidence.
No authorized network reader is configured, so ordinary checks report **Not
anchored** or **Unavailable**; a local match never establishes blockchain
verification. Confirmed-receipt comparison is tested using explicit test doubles
only. No genuine transaction has been retrieved or verified.

History records checked hashes, status/reason, actor/time and any independently
retrieved receipt metadata. Results describe their check time, not continuous
network state. Changed local records require a fresh check. Reposting the same
request key reuses its result; a fresh check retains a new history row. No network
submission, auto-retry or background polling occurs in this flow.

Phase 12 is divided as permitted by the master prompt: verification/history is
Phase 12-A; authorized correction versions spanning approval, documents and
anchoring are Phase 12-B, described below. See the
[Phase 12-A handoff](docs/PHASE_12A_HANDOFF.md) for scope, states, access, tests and
remaining network/client validation. The rollback-only MySQL workflow verifier
also checks history/key idempotency and an isolated snapshot-copy mismatch.

## Authorized correction versions (Phase 12-B)

Apply `php artisan migrate`. From a finalized demonstration payroll, open
**Correction requests and payroll versions**. A Payroll Officer enters a reason,
authority reference and review confirmation. An independent Approver reviews and
authorizes or returns the request; requesters and source preparers cannot review
their own correction, including after a role change.

Authorization opens an **empty replacement draft**, retaining the original month
and demonstration use with version 2 (then 3, etc.). Select coverage and enter
reviewed inputs again, calculate, complete statutory review, submit, approve and
finalize through the existing workflow. This is a complete replacement payroll,
not an additional payment or a difference to disburse. No disbursement is performed.

Each corrected finalization includes the source hash and authorization lineage
and has its own documents, local commitment and integrity checks. The history
page links every version's retained review, register/payslips and integrity
evidence. Prior finalized values and hashes remain unchanged. Only the latest
finalized version accepts another correction; an existing correction draft must
be completed first. Returned requests remain visible and allow a new revision.

Real payroll corrections remain blocked. Local hashes cannot establish genuine
blockchain verification without an authorized network. The rollback-only MySQL
verifier also prepares and finalizes a complete corrected version, checks JSON/
hash/document preservation and removes all its fixtures. See the
[Phase 12-B handoff](docs/PHASE_12B_HANDOFF.md) for acceptance evidence and limits.

## Interface and navigation

The workspace uses a navy sidebar, active navigation, role-specific starting
cards and a consistent blue action style. Small screens use collapsible navigation;
the login screen includes a password visibility toggle. Forms preserve existing
retry behavior and display field feedback with an error summary. Payroll previews
show aligned totals and clearer line-by-line breakdowns with their existing warnings.

The design uses local assets and system fonts. No CDN or additional frontend
dependency is required. See [the interface guide](docs/UI_UX_GUIDE.md) for design
tokens, interaction behavior and the pending visual browser checks.

## Verification

Run inside `application/`:

```powershell
composer test
php vendor/laravel/pint/builds/pint --test
npm.cmd run build
php artisan migrate:status
php artisan app:check-foundation
php ../scripts/verify-mysql.php
php ../scripts/verify-auth.php
php ../scripts/verify-auth-concurrency.php
php ../scripts/verify-employees.php
php ../scripts/verify-salaries.php
php ../scripts/verify-payroll-drafts.php
php ../scripts/verify-payroll-inputs.php
php ../scripts/verify-payroll-calculations.php
php ../scripts/verify-statutory-previews.php
php ../scripts/verify-payroll-rule-versions.php
```

PHPUnit pins in-memory SQLite in both server and environment variables. The base
test case refuses unsafe resolved configuration before destructive migrations.
Verify isolation from the project root:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify-test-isolation.ps1
```

This runs the full suite with conflicting inherited production/MySQL settings.
The ordinary MySQL smoke checks use transactions and roll back their fictional
accounts. The concurrency check temporarily commits four fictional accounts so
separate processes can access them, then deletes only the accounts it created.
It verifies reciprocal administrator deactivation/demotion without changing schema.
The employee smoke check rolls back its fictional user and employee records.
The salary smoke check likewise rolls back all of its fictional records, including
schedules, rates, employee history and users; it never runs destructive migrations.
The payroll draft check also rolls back its fictional batches, coverage and sources.
The input check rolls back its fictional revisions and verifies exclusions,
per-employee values, edits and preservation across coverage refresh.
The core calculation check verifies all three categories against prepared item and
batch totals, no input overwrite, exclusions/replacements, idempotency and history;
its fictional calculation fixtures also roll back.
The statutory preview check verifies three explicitly selected fictional category
scenarios, prepared contribution/tax/net values, taxable bonus excess, retries,
history and missing-rule states without changing core inputs. Its fictional
fixtures also roll back; an in-memory review-year override isolates their dates
from operator schedules and does not change the configured reference file.
The readiness command separately verifies the configured MySQL connection and
base tables. `/up` indicates application boot, not database readiness.

Use `php artisan serve` with built assets on Windows. For frontend editing, run
`npm.cmd run dev` in a second terminal. `composer dev` also runs the HTTP and Vite
servers together; it excludes the default Pail log process, which requires an
extension unavailable on Windows.

The three remaining setup findings in [the Phase 0 audit](docs/PHASE_0_AUDIT.md)
are still open. Use the documented manual setup sequence; preserve existing keys
and ensure no conflicting DB_URL is configured when using the local MySQL helper.

See [development progress](docs/DEVELOPMENT_PROGRESS.md),
[requirements](docs/REQUIREMENTS_STATUS.md), [decisions](docs/DECISIONS.md), and
[testing status](docs/TESTING_STATUS.md).
