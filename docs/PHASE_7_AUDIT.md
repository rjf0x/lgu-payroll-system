# Phase 7 audit - Contributions and Withholding Tax

Date: 2026-10-26 (Asia/Manila). Scope: the current uncommitted Phase 7 changes,
including reference calculation, review persistence, authorization and tests.
No application fixes, permanent-test changes, migrations or Phase 8 implementation.

**Correction follow-up:** the user subsequently authorized current-phase fixes.
F1, F2 and F3 are now corrected with permanent regressions and passing full/MySQL
checks. Original audit evidence below is retained as history; see the correction
section at the end for current results. Client validation remains outstanding and
real payroll remains blocked.

## Original audit acceptance assessment

Status remains **Implemented but needs validation**. Phase 7 is not ready for
acceptance as accurate real payroll. Two medium findings affect the supported
reference workflow; a low finding affects malformed source configuration. The
production block remains effective in the exercised paths.

| Master-prompt acceptance criterion | Assessment | Evidence |
| --- | --- | --- |
| Verified test cases pass | Partial | All 304 existing tests pass, but an additional exact half-cent cumulative case produces a one-cent error. No client-approved cases exist; the user confirmed that none are available. |
| Unverified rules are clearly marked and cannot silently produce production-ready results | Passed for production isolation; reference validation needs work | Real batches are rejected, all results have `production_ready: false`, and the UI marks unverified category applicability. However, malformed bracket shape can cause HTTP 500 and an invalid zero savings ceiling can produce a complete-looking demonstration result. |
| Contribution and tax breakdowns are explainable | Passed for inspected reference paths, subject to calculation accuracy | Saved reviews retain inputs, rules/sources, adjusted contribution bases/rates, tax bracket/average, bonus excess, liability, previous withholding, credits and combined totals. F1 means explainability does not establish numerical correctness. |

The implementation covers reference scenarios for GSIS, Pag-IBIG and PhilHealth,
regular monthly/cumulative/December annual withholding, varying taxable bonus
excess and preserved calculation evidence. Approved LGU contribution rules and
validated operational withholding procedures are still absent; reference scenarios
do not fulfill those real-processing tasks.

## Confirmed findings

### F1 - Medium: cumulative half-cent liability rounds down by one cent

Location: `application/app/Services/StatutoryCalculator.php:202` and `:209`,
with intermediate multiplication at `:94`.

Reproduction uses the supported cumulative procedure, three elapsed monthly
periods, taxable regular compensation `300000.02`, explicit zero other taxable
pay/qualifying benefits/previous tax, and the shipped reference profile. All
contribution coverage/bases are otherwise complete. The HTTP save succeeds and
the result is persisted as `demonstration`.

For the selected bracket, exact arithmetic is:

```text
3 * 8541.80 + 0.25 * (300000.02 - 3 * 66667.00)
= 50625.155
Half-up at the liability total = 50625.16
Saved/displayed liability     = 50625.15
```

The eight-decimal division and intermediate multiplication truncate a repeating
average below the half-cent boundary before the final half-up step. Additional
withholding is understated and the reference net overstated by one cent in this
case. This is a defect against the application's stated component-total half-up
convention; it is not a claim about a client-approved rounding policy.

The existing cumulative rounding regression uses `100000.00 / 3`, which does not
exercise this exact half-cent boundary. Recommended correction: preserve the
exact selected-bracket algebra through final rounding and add the counterexample
as a permanent regression. No correction was made in this audit.

### F2 - Medium: rejected statutory reason prefills another employee's salary form

Relevant locations: statutory POST at `application/routes/web.php:36`;
`application/app/Http/Requests/PreviewStatutoryRequest.php:14`;
`application/resources/views/employee-salaries/form.blade.php:3` and `:38`.

Reproduction:

1. Submit employee A's statutory review with invalid `gsis_basis = 1.001` and
   reason `UNRELATED-STATUTORY-REASON`.
2. Before following the validation redirect, open employee B's authorized salary
   change form in the same session, carrying the actual session cookie.
3. Employee B's salary Reason textarea contains A's rejected statutory reason.

Laravel's validation response globally flashes request input. Statutory forms
ignore `old()` values themselves, but that does not prevent an existing form
with the shared `reason` key from consuming them. The affected salary form reads
global `old('reason')`. No salary record changes until an authorized user submits
that form; the defect is incorrect cross-employee/workflow prefill rather than
unauthorized persistence or cross-user data disclosure.

The existing isolation test only opens another statutory form, and its follow-up
does not explicitly carry the response session cookie. It does not cover this
cross-workflow recipient. Recommended correction: scope or suppress statutory
validation flash input and test the salary-form recipient with a persisted session.
No application correction was made.

### F3 - Low: malformed source rules do not consistently become explained missing-rule states

Locations: `application/app/Services/StatutoryCalculator.php:38`, `:59`, `:62`,
`:78` and `:86`.

Two in-memory configuration probes reproduced incomplete validation:

- Replacing a numeric bracket tuple with an associative row containing valid
  `lower`, `fixed` and `rate` strings passes the count/value checks. Numeric
  access to `$row[0]` then raises an undefined-key exception. The HTTP request
  returns **500**, instead of an explained incomplete preview. No review is saved.
- Setting the standard Pag-IBIG ceiling to the string `0.00`, leaving the other
  standard tier parameters unchanged, passes validation and computes employee
  savings `0.00` for a positive `30000.00` basis. There are no issues and the
  status is `demonstration`, although this is an invalid standard tier/cap setup.

Both require a source/configuration change; normal HTTP users cannot submit rule
parameters. The shipped configuration is not shown to contain these malformed
values, and neither probe enables production. Severity is therefore low. The
problem is robustness of the configurable reference-rule boundary, not a request
authorization bypass. Validate list/tuple shape and the logical parameter ranges
before arithmetic; keep invalid reference amounts unavailable. No fix was made.

## Missing or blocked functionality

These are declared scope/validation limits, separate from the defects above:

- No client-approved contribution applicability, category compensation basis,
  withholding examples or office rounding acceptance. All categories explicitly
  remain unverified and real processing is disabled.
- Reviewed contribution and taxable/YTD amounts are manual scenario inputs. They
  are not reconciled automatically with prior payroll, previous-employer records,
  current core earnings or a verified annual ledger. An entered taxable amount
  can therefore be internally inconsistent with the saved payroll without a
  reconciliation warning. Treat this as a validation limitation, not proof of
  a correctly classified employee tax position.
- Termination-before-December annualization, special membership, minimum-wage and
  fringe-benefit tax scenarios are unsupported. Credits/refunds are recorded but
  not executed; shortfall settlement and minimum-net treatment remain unresolved.
- Results are individual preserved reference reviews. Core batch aggregates still
  exclude statutory components; there is no consolidated statutory batch result
  or approved annual ledger. This is not a hidden zero deduction.
- Settings maintenance, effective-dated activation and configuration approval are
  Phase 8; their absence is not counted as a new Phase 7 defect. Payroll approval,
  finalization, documents and blockchain also remain later phases.

The official [BIR digest](https://bir-cdn.bir.gov.ph/local/pdf/Digest%20RR%2011-2018.pdf)
and [Annex E](https://bir-cdn.bir.gov.ph/local/pdf/Annex%20E%20RR%2011-2018.pdf)
were re-opened to check the referenced cumulative/annual procedures and table
parameters. That check does not establish LGU employee applicability or client
approval. The contribution source review remains as recorded in PHASE_7_HANDOFF.md;
this audit does not claim independent regulatory certification of every rate.

## Executed checks

| Check | Result |
| --- | --- |
| `scripts/verify-test-isolation.ps1` | Passed: 304 tests / 3319 assertions, 39.778 seconds, 68 MB; conflicting inherited production/MySQL/DB_URL settings did not escape in-memory SQLite |
| Additional ignored audit diagnostics | Passed as reproductions: 4 tests / 36 assertions. They assert the observed defects, not desired corrected behavior |
| `php ../scripts/verify-statutory-previews.php` | Passed on MySQL; three explicit fictional category scenarios, literal arithmetic, persisted JSON/retries/history, missing coverage, core/input preservation, real-mode blocking and fixture rollback |
| `php ../scripts/verify-payroll-calculations.php` | Passed on MySQL with fixture rollback |
| `php artisan migrate:status` | All eleven migrations already Ran; this audit ran no migration |
| Application and statutory-verifier Pint `--test` | Passed |
| Whitespace check | Passed; ordinary LF/CRLF conversion notices |

MySQL was initially stopped, temporarily started for rollback-only verification,
and restored to its initial stopped state. No real payroll fixtures, credentials
or database dumps were created by the audit.

The diagnostic file is ignored local material at
`.local/phase7-audit/Phase7AuditTest.php`. Run from `application/`:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never --filter test_audit_ ../.local/phase7-audit/Phase7AuditTest.php
```

## Test coverage assessment

Permanent Phase 7 coverage remains 65 cases / 345 assertions: 48 calculator and
17 workflow cases, plus all 239 earlier tests. It covers literal bracket vectors,
contribution floors/caps/tiers, ordinary half-cent contribution rounding, bonus
excess variants, prior withholding/credits, unavailable input states, stale
versions/sources, route ownership, four roles, guest/CSRF/revocation, snapshot
preservation, JSON key-order retries, history pagination, model immutability and
injected persistence failure. No coverage percentage is claimed.

Gaps exposed by this audit:

- No permanent regression for exact half-cent cumulative liabilities with repeating
  averages, nor a broader comparison against exact bracket algebra.
- No persistent-session cross-workflow rejected-input test for salary recipients.
- No associative/sparse bracket-shape or logically invalid contribution-limit tests.
  Existing malformed-rule tests cover one nonnumeric rate, not full schema validity.
- The in-flight actor probe exercises role/session-version revocation; simultaneous
  account inactivity, source publication and statutory writes are not demonstrated.
- No simultaneous MySQL statutory retries/edits, source-rule changes, deadlock or
  connection-loss recovery test. Existing concurrent account checks do not prove
  statutory concurrency behavior.
- No live browser visual/accessibility/responsive acceptance, office-scale load
  test or client-approved payroll examples. No automatic YTD reconciliation tests
  can establish a ledger that is not implemented.

Recommended order: correct and add permanent regressions for F1/F2, harden the
reference-rule validator for F3, rerun the suite/MySQL checks, then retain the real
processing block until client rules are accepted. Do not begin Phase 8 as part of
this audit. Only audit/tracking documents and ignored diagnostics were changed.

## Correction follow-up - 2026-10-26

All three findings are corrected under the user's current-phase-only instruction.
No new payroll feature, Phase 8 work, schema change or dependency was introduced.

| Finding | Correction and regression evidence |
| --- | --- |
| F1 | Engine `statutory-reference-v2` selects the bracket against the total taxable basis scaled by elapsed periods. It evaluates `periods * fixed + rate * (basis - periods * lower)` exactly before final half-up rounding. Eight-decimal averages are display values only. Ten literal cent-boundary vectors pass; the HTTP case persists/displays `50625.16` and net `-24275.16`. |
| F2 | `PreventStatutoryInputFlash` removes rejected `_old_input` on statutory validation redirects while retaining errors. It wraps encoding middleware, request validation and service validation. Persistent-session regressions cover numeric, UTF-8 and stale-version failures followed by another employee's salary form, including suppression of unvalidated shared fields. |
| F3 | Tables and three-value rows must be lists before numeric access. Active contribution parameters must be positive, rates/shares at most one, threshold below cap, low rate no greater than high rate, and floor no greater than cap. Six malformed-shape and twelve invalid-range cases leave affected amounts/totals unavailable. The associative-row HTTP case now saves an explained incomplete review instead of returning 500. |

Existing v1 reviews are immutable historical records. The engine version change
prevents unchanged-input retries from treating an old v1 result as current. The
UI identifies saved engines and marks older results for review; a reviewed save
creates a v2 revision while retaining the original. A permanent regression covers
history preservation and idempotent v2 retries. No existing saved result was rewritten.

Executed checks:

- Before fixes, selected corrected-behavior regressions reproduced the defects:
  12 failed / 2 passed, 52 assertions.
- After fixes, focused Phase 7 tests passed **99 tests / 528 assertions**.
- Full isolation suite passed **338 tests / 3502 assertions**, 43.551 seconds,
  70 MB. All 304 previous tests and 34 new cases passed under conflicting inherited
  production/MySQL/DB_URL settings while isolated in-memory.
- Expanded MySQL statutory checks passed the exact half-cent result, invalid-cap
  and malformed-table incomplete states across three explicit fictional categories,
  with retries, history/core-input preservation and rollback. Existing core and
  input verifiers also passed with rollback.
- Build, application/verifier Pint, strict Composer validation, database readiness,
  eleven-migration status and whitespace checks passed. No migration executed.
- MySQL was temporarily started and restored to its initial stopped state.

The historical ignored audit diagnostic file asserts former defects and is no
longer a current verification command. Permanent `Statutory*Test` classes supersede
those probes. Status remains **Implemented but needs validation** because client
rules/examples, category applicability and rounding acceptance are absent. F1-F3
are resolved; the documented ledger/special-case, browser, concurrency/recovery
and performance limitations remain. No real payroll acceptance is claimed.
