# Phase 6 audit - Core Payroll Calculation

Date: 2026-10-26 (Asia/Manila). Scope: the current uncommitted Phase 6 implementation
after Phase 5 commit `f55d53a`. No application fixes or Phase 7 implementation.

## Acceptance assessment

Status retained: Implemented and tested for the documented demonstration scope.
No new Phase 6 application defect was confirmed. This is not acceptance of real
payroll processing or unvalidated compensation rules.

| Master-prompt acceptance criterion | Assessment | Evidence |
| --- | --- | --- |
| Totals match independently prepared test cases | Passed for supported inputs | Four literal expected-result vectors cover full inputs/exclusions/replacements, basic-only, negative net and maximum line values; category/item/batch cases also pass on SQLite and MySQL. |
| Monetary calculations are consistent | Passed for supported addition/subtraction | Canonical two-decimal strings, BCMath totals, deterministic repeats, invalid precision/type rejection, aggregate-width boundary and negative-net preservation. No rate division or rounding formula is active. |
| No silent overwriting of manual inputs | Passed for exercised workflows | Input/entry/batch attribute equality, repeated calculation, separate later-version runs, preserved old snapshots, whole-batch rejection and injected persistence rollback. |

All seven task areas are present within that scope: basic pay from the authorized
monthly salary reference; applicable earnings; manual loan deductions; adjustment
replacement amounts; core deductions; core net; transparent employee/aggregate
breakdowns. No additional required Phase 6 demo workflow was found absent.

## Reviewed behavior and source evidence

- `application/app/Services/PayrollCalculator.php:13` rejects noncanonical money;
  line 26 requires normalized zero attendance. Lines 31-64 preserve included and
  excluded lines and apply replacement amounts once. Lines 66-75 calculate core
  totals and retain unavailable statutory/attendance components as null.
- `application/app/Services/PayrollCalculationService.php:18` starts an atomic
  transaction, locks/rechecks the current actor and batch, verifies the draft
  version and blocks real mode. Lines 31-68 use only current coverage, revalidate
  salary context and require the latest matching input revision. Lines 69-82
  revalidate even repeated runs and preserve one run per batch/engine version.
- Run/item models reject ordinary update/delete operations. Their migration uses
  stable batch/employee/input/salary foreign keys, without a reference to a
  removable draft entry. This is application preservation, not privileged database
  tamper resistance or blockchain verification.
- `application/app/Http/Controllers/PayrollCalculationController.php:31` checks
  read authorization and batch/run ownership before showing saved snapshots.
  History and details paginate at 10 runs / 20 employees. The detail view labels
  provisional core net and missing deductions, flags negative results and explains
  that source-review notices cover the displayed page.

## Missing or blocked functionality

These are explicit scope/validation limits, not newly reproduced bugs:

- Real-mode calculation is disabled server-side. Fictional previews use the full
  authorized monthly salary as basic pay for each category. Actual category basis,
  eligibility, full-month treatment and the existing first-day/monthly grouping
  conventions require client-approved examples before real processing.
- Nonzero absence or undertime is rejected, including when arbitrary adjustments
  are supplied. No approved attendance deduction/proration formula or linkage from
  an adjustment to a calculated attendance original exists. The master prompt
  prohibits inventing that rule; manually entered originals remain references.
- Adjustment originals are not added to replacement amounts or treated as deltas.
  External authority references and confirmations record operator authorization;
  they do not independently verify the external authority or client policy.
- Contributions, withholding and taxable compensation are Phase 7, not missing
  Phase 6 features. Their absence is visible and not represented as zero. Approval,
  finalization, reporting and blockchain likewise remain later phases.
- Negative core net is preserved and flagged. Any minimum-net constraint remains
  unvalidated; no floor or deduction priority is silently invented.

Earlier Phase 2/3 audit findings and Phase 4 policy limits remain open. Phase 6
uses the existing stricter preparation source guards instead of fixing those
earlier workflows during this audit.

## Checks executed in this audit

| Check | Result |
| --- | --- |
| `scripts/verify-test-isolation.ps1` | Passed: full suite, 220 tests / 2737 assertions, 71.280 seconds, under conflicting inherited production/MySQL configuration; tests remained isolated in-memory SQLite |
| Additional ignored HTTP/database audit probes | Passed: 3 tests / 114 assertions |
| `php ../scripts/verify-payroll-calculations.php` | Passed on MySQL: three categories, prepared item/batch totals, exclusions, replacements, input preservation, idempotency, new-run history, real-mode rejection and fixture rollback |
| `php artisan migrate:status` | All nine migrations already Ran; this audit ran no migration |
| Application and script Pint `--test` | Passed |
| `git diff --check` | Passed; ordinary LF/CRLF conversion notices |

Additional probes at `.local/phase6-audit/Phase6AuditTest.php` exercise:

1. Zero earning/deduction replacements preserve nonzero original references without
   counting them: earnings 12945.75, deductions 200.02, core net 12745.73; inputs unchanged.
2. Twenty-one employee details across two pages and eleven saved runs across two
   history pages. Full aggregate remains 266378.91 on both employee pages; ten later
   input edits produce 266368.91 in a new run and preserve the original result.
3. Employee deactivation and a newly published mid-month schedule both block a
   repeat calculation and show source-review notices while saved values/inputs remain unchanged.

Run those diagnostics from `application/`:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never --filter test_audit_ ../.local/phase6-audit/Phase6AuditTest.php
```

The probes use the existing test fixtures and in-memory test database. MySQL was
initially stopped, temporarily started for rollback-only verification and stopped
afterward. No real employee fixture, credential or database dump was added.

## Coverage assessment and remaining evidence

Permanent Phase 6 coverage comprises 31 cases: 12 calculator cases and 19
integration cases. All 189 earlier cases pass. Tests cover exact prepared totals,
excluded allowances, original/replacement semantics, malformed money, negative
net, current coverage/latest revisions, unchanged inputs, idempotent history,
stale versions/profile context, missing/real/nonzero-attendance blocking, atomic
rejection, injected rollback, authorization/CSRF/revoked actors, cross-batch scope
and ordinary model immutability. No coverage percentage is claimed.

Remaining gaps:

- The extra pagination/zero-replacement/source probes are ignored diagnostics,
  not permanent regressions. The permanent source-drift test changes office;
  full source/category/supersession boundaries mostly rely on prior preparation tests.
- No simultaneous MySQL calculation retries, input/coverage edits, source
  publication, deadlock/unique-constraint race or connection-loss recovery tests.
  Sequential retries and SQLite injected rollback do not establish those properties.
- No live browser navigation, visual/accessibility acceptance or representative
  office-scale performance test. Twenty-one diagnostic employees are a pagination
  boundary check, not a capacity measurement.
- No client-approved actual payroll examples for category basis, attendance,
  authorization, deductions/minimum net or statutory totals. Full payroll accuracy
  cannot be signed off from fictional core cases alone.

Only audit/tracking documents and ignored diagnostics were added during this
audit. No application or permanent-test changes, fixes, schema changes, commits
or Phase 7 work were performed.
