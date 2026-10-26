# Phase 7 - Contributions and Withholding Tax

Date: 2026-10-26 (Asia/Manila).

Status: Implemented but needs validation. The reference engine and preserved review
workflow are implemented; approved LGU rules and production statutory processing
remain blocked by client validation. The user confirmed that no client-approved
rules or examples are available. Phase 8 is not implemented or authorized.

## Scope and behavior

- Each employee in a preserved core preview has a **Contribution and withholding
  reference review**. Payroll Officers save scenarios; Approvers/Viewers read;
  Administrators retain their existing configuration/account role without gaining
  employee access. Guests, disabled/revoked sessions and CSRF failures are rejected.
- GSIS ordinary-member percentage, Pag-IBIG tiered/capped employee savings and
  PhilHealth employed-member shared premiums use reference parameters in
  `application/config/payroll_statutory.php`. Coverage and the relevant monthly
  basis must be reviewed separately for each employee/component. All three
  personnel categories explicitly remain unverified; classification grants no
  entitlement or membership. No employer-share payroll deduction is added.
- Supported reference tax procedures: regular monthly compensation only,
  cumulative monthly average and December annual reconciliation. Tax classifications,
  previous-employer pay, amounts paid through the current payroll and previous tax
  withheld are explicit reviewed inputs, not reconstructed or verified annual
  ledgers. Regular taxable pay must already exclude reviewed non-taxable earnings
  and mandatory contributions. Loans are not implicitly tax deductions.
- Qualifying 13th-month/other benefits share one configurable exemption pool.
  `max(pool - exemption, 0)` is added once to regular and other taxable
  supplementary compensation. Other fully taxable bonuses belong in the separate
  supplementary input. No worksheet example excess is hardcoded. Regular-only
  processing rejects saved bonuses/earning adjustments or supplementary/YTD tax inputs.
- Corrected cumulative liability uses exact bracket arithmetic over the total
  taxable basis before final half-up rounding. Eight-decimal averages are retained
  for display only. Individual employee contribution
  totals and tax liability round half-up to cents. These are explicit reference
  rounding conventions, not claimed client-approved rounding rules.
- Additional withholding is nonnegative. Overwithholding is preserved separately
  as a credit/refund requiring review; it is not silently added to net pay. The
  displayed reference net includes core earnings/loans/adjustments and calculated
  employee deductions, before any separately reviewed refund. Negative results
  remain visible; minimum-net/collection-shortfall policies are unresolved.
- Missing coverage, basis, rule/table, review year/category or procedure produces
  issues and null affected results/combined totals. Explicit fictional
  nonapplicability is distinguishable from an unknown contribution. Every result
  has `production_ready: false`; real batches are blocked server-side.
- Current source/input and whole-batch version checks reuse the existing locked
  core calculation service. Old/outdated core previews remain readable but cannot
  acquire new reviews. Sources are revalidated even for identical retries.
- `statutory_previews` retains immutable per-core-item revisions, reviewed inputs,
  complete rule/source parameters, tax brackets/bases/results, operator and time.
  Sequential identical retries reuse the latest matching review. Stale edits are
  rejected. JSON comparison preserves types/list order while ignoring object-key
  order, including MySQL's JSON serialization order. Core runs/items/manual inputs
  are never overwritten by statutory review.
- The form uses only this employee/core item's saved values; rejected submissions
  do not refill any statutory form. Rejected statutory input is removed from session
  flash so other workflows cannot consume shared keys. Validation errors remain;
  the user must re-enter corrections.

The Phase 7 audit's F1-F3 findings have been corrected. New calculations use
`statutory-reference-v2`; existing v1 results remain unchanged and are marked as
earlier-engine results. Review and save a new scenario to obtain a corrected
revision. Historical v1 values are not retrospectively corrected. See
PHASE_7_AUDIT.md for verification. No schema, policy rate, client approval or
next-phase functionality changed in this follow-up.

## Reference sources and validation boundary

Sources inspected on 2026-10-26. Regulatory references do not establish that an LGU
employee belongs to the standard membership/tax scenario.

| Reference | Use and limitation |
| --- | --- |
| [GSIS contributions](https://www.gsis.gov.ph/active-members/contributions/) | Ordinary member employee rate; special membership cases excluded. Indexed official text was available; direct opening was rejected by the site's access filter. |
| [Pag-IBIG Circular 460, official ONAR-filed copy](https://naro.law.upd.edu.ph/storage/01JQWKTTA92H57M9EWV31W8F1D.pdf) | Employee savings tiers and maximum fund salary; not a universal flat deduction. |
| [PhilHealth Circular 2020-0005](https://www.philhealth.gov.ph/circulars/2020/circ2020-0005.pdf), [Advisory 2025-0002](https://www.philhealth.gov.ph/advisories/2025/PA2025-0002.pdf), [2026 government announcement](https://pia.gov.ph/news/philhealth-sets-5-premium-contribution-rate-for-2026/) | Standard employed direct-contributor reference; self-paying/special cases and LGU applicability remain unresolved. |
| [BIR RR 11-2018](https://bir-cdn.bir.gov.ph/local/pdf/RR%20No.%2011-2018.pdf), [official digest](https://bir-cdn.bir.gov.ph/local/pdf/Digest%20RR%2011-2018.pdf), [Annex E](https://bir-cdn.bir.gov.ph/local/pdf/Annex%20E%20RR%2011-2018.pdf) | Reference monthly/annual brackets and procedures; taxable classifications, benefit qualification and the office's procedure selection still require client acceptance. |

The shipped reference review window is deliberately limited to calendar year 2026;
this is an application validation boundary, not a claim that statutes expire then.
Other years remain incomplete until references are reviewed. Configuration is a
source-controlled minimal Phase 7 dependency; there is no settings-maintenance,
rule activation, approval or effective-dated configuration UI from Phase 8.

## Browser verification

1. Run migrations, build assets and start the application using the root README.
2. Prepare a demonstration core preview with one fictional employee, monthly
   salary `30000.00`, no extra earnings/loans/adjustments and saved zero attendance.
   Existing schedules are locked: use the normal authorized schedule/assignment
   workflow and a suitable draft month rather than editing published records.
3. Open the employee's **Contribution and withholding reference review** link.
4. Select the standard reference scenario for each contribution and enter
   `30000.00` as each reviewed basis. Choose monthly regular compensation only.
   Enter regular taxable compensation `26350.00`; enter explicit zeros for other
   supplementary pay, qualifying benefits and prior tax. Leave elapsed periods
   empty. Supply a fictional reference/reason and tick the confirmation.
5. Expect employee contributions `2700.00`, `200.00`, `750.00`; withholding
   `827.55`; combined deductions `4477.55`; reference net `25522.45`.
6. Re-submit the same reference/reason and inputs: the existing review is reused.
   Save a changed basis using the latest form: a new revision retains the old
   result and parameters. An already-open stale form must not overwrite it.
7. Set GSIS coverage to Unknown and save: the GSIS amount and combined totals
   become unavailable, with an explanation. Previous reviews stay unchanged.
8. For a December draft, choose annual reconciliation. Use regular taxable pay
   `500000.00`, other taxable supplementary pay `10000.00`, qualifying benefits
   `135000.00`, and prior withholding `50000.00`. Expect bonus excess `45000.00`,
   annual taxable compensation `555000.00`, annual liability `53500.00`, and
   additional withholding `3500.00`. Changing qualifying benefits changes the
   excess; it is not a fixed example value.
9. For cumulative October, use 10 elapsed periods, regular taxable compensation
   `300000.00`, other supplementary pay `5000.00`, qualifying benefits `100000.00`
   and prior withholding `15000.00`. Expect taxable total `315000.00`, cumulative
   liability `16000.50` and additional withholding `1000.50`.
10. Check Approver/Viewer read-only access, core/source drift notices, narrow-screen
    tables, keyboard access to disclosures and field errors. Real-mode calculation
    remains unavailable regardless of form fields or reference parameters.

These figures validate software arithmetic on explicit scenarios, not an actual
employee's tax position or a client-approved payroll example.

## Files and database

Created: the reference config; `StatutoryCalculator` and `StatutoryPreviewService`;
`PreviewStatutoryRequest`; `StatutoryPreviewController`; `StatutoryPreview` model;
`statutory-previews/show.blade.php`; one additive migration; calculator/workflow
test classes; `scripts/verify-statutory-previews.php`; this handoff document.

Modified: `routes/web.php`, the existing core-preview detail view, root README,
and the four required project tracking documents. No dependencies were added.

The additive migration creates one table. During local verification, Laravel also
applied the previously pending username migration from the earlier user-requested
change; no earlier migration source was edited. All eleven migrations are now Ran.
MySQL was initially stopped, temporarily started for checks and restored to its
initial stopped state afterward. Smoke data was transactionally rolled back.

## Remaining limitations

Actual client acceptance examples, category coverage, approved bases/procedures,
rounding, special member/tax cases, allowance tax classification, automatic YTD
ledger reconciliation, refund execution, withholding shortfall settlement and
minimum-net policy remain unverified or unsupported. No real payroll activation,
batch statutory approval, finalization, document generation or blockchain work is
included. Existing Phase 2/3 audit findings remain open. Visual/live-browser,
concurrent MySQL race/recovery and office-scale performance acceptance are not
claimed. Test results are recorded in TESTING_STATUS.md.

Recommended next phase: Phase 8 - Payroll Configuration, only after authorization.
