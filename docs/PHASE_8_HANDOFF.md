# Phase 8 - Payroll Configuration

Date: 2026-10-26 (Asia/Manila).

Status: Implemented but needs client validation. All three confirmed findings in
PHASE_8_AUDIT.md are corrected with permanent regressions and MySQL checks. Actual
client rules, activation policy and production payroll acceptance still require
validation. The user's earlier confirmation that no approved client examples are
available remains in effect. Phase 9 is not implemented or authorized.
PHASE_8_REAUDIT.md records the post-correction acceptance review and coverage gaps.

## Scope and behavior

- **Payroll configuration** is available from navigation and workspace cards.
  Administrators create drafts, revise parameters, copy a prior version into a
  new draft and explicitly activate/lock a reviewed version. Other active roles
  can read activated versions and their change history; draft access is restricted.
  Administrators do not gain employee or payroll-record access.
- Each version has its own identifier, personnel classification, demonstration or
  external-approval data use, first-of-month effective date and reference review
  year. Parameter forms cover the existing GSIS percentage, Pag-IBIG tier/cap,
  employed/shared PhilHealth premium, qualifying-benefit exemption and six-row
  monthly/annual tax tables. Sources and membership-scope explanations are entered
  separately. Supported values retain the existing two-decimal parameter precision;
  unfamiliar formulas, additional brackets or new methods are not inferred.
- Structural, decimal and logical bound validation reuses the existing statutory
  engine checks. Activation also revalidates required sources/bonus inputs and
  table shape. Server-built method, rounding and category metadata cannot be
  replaced by submitted activation flags or arbitrary profile fields.
- Activation requires the current draft revision, approval reference, reviewed
  example/validation reference, reason and authorization confirmation. External
  data-use versions additionally require explicit client-validation confirmation.
  These record the administrator's stated external authority; the application
  does not authenticate an external document or certify regulatory applicability.
  No new external-approved settings are seeded or enabled automatically.
- Activated versions are locked against ordinary model/HTTP edits and deletion.
  Later changes require a separately dated version. Multiple drafts may share a
  scope/date, but a database constraint allows only one activated version per
  classification, data use and effective date. New first-day versions can be
  entered for earlier or future months without changing existing saved results;
  client policy for retrospective activation still requires acceptance.
- Every creation, draft revision and activation records an immutable event with
  operator ID/username, reason, time and complete configuration snapshot. Activation
  evidence and confirmations are retained in that snapshot. Audit persistence and
  the configuration change share one transaction. This is application history,
  not privileged database tamper resistance or blockchain verification.
- Creation uses a unique form key/fingerprint; an unchanged retry reopens its
  existing version regardless of associative field order. Legacy fingerprints
  use the immutable original creation event for compatibility, even after draft
  edits; retries do not rewrite hashes or history. Edit/activation revision checks reject stale forms. Identical
  activation retries do not add duplicate events. Actor activity, role and session
  revocation are rechecked under lock during writes. Validation redirects retain
  errors but remove rejected input so names/reasons cannot prefill other workflows.
- Contribution references are linked only for valid HTTP(S) URLs; other document
  references, unsafe schemes and HTML render as escaped plain text. This applies
  to existing saved values. New change snapshots store effective dates as calendar
  dates; history also shows readable legacy dates using the application timezone
  while retaining original raw snapshots and real timestamps.

## Selection and saved payroll preservation

New statutory reference saves select the latest activated configuration whose
effective date is on/before the monthly batch's first day, for that employee's
category and batch data use. Creation/activation time is not the effective date.
Future versions and draft versions do not replace earlier selection.

If no maintained version applies, the existing unverified built-in Phase 7 profile
remains available within its original review year. This compatibility fallback
does not create an approved database version. An applicable maintained version
from an expired review year yields incomplete calculations until a new reviewed
year version is provided; it is not silently replaced with an older reference.

The form displays the selected configuration and carries its ID. A configuration
activation between opening and saving the form causes a reload/review error,
including the transition from the built-in profile to the first maintained version.
The same check runs before returning an identical statutory retry. Parameter
snapshots include version ID, effective date and activation references.

Activation does not recalculate saved core runs, input revisions or statutory
reviews, and does not change batch totals. A reviewed new statutory save creates
a separate revision using the selected configuration; previous amounts/parameters
stay available. Real-mode core/statutory processing remains blocked even when an
external-approval configuration is activated. `production_ready` remains false.

The master criterion about finalized historical payroll is supported by retaining
these existing immutable calculation snapshots. Finalized payroll does not exist
yet: the actual finalized lifecycle must be tested in Phase 9, not fabricated here.

## Manual verification

1. Run `php artisan migrate`, build assets and start the application using README.
   Sign in as Administrator and open **Payroll configuration**.
2. Create a clearly fictional **Permanent / Demonstration only** draft effective
   `2026-10-01`. Review all template parameters and source/scope descriptions.
   Give it a fictional name/reason; leave the reference values for the first test.
3. Open the draft's detail page, inspect its parameter tables/change snapshot,
   then activate with fictional approval and reviewed-example references, a reason
   and confirmation. It becomes locked. Editing should be unavailable.
4. Copy it into a new draft effective `2026-11-01`. For an explicitly fictional
   arithmetic example, change GSIS's employee fraction from `0.09` to `0.10` and
   update source/scope/reason to identify the fictional change. Activate it. Both
   versions and their events remain accessible; duplicate activation on the same
   category/data-use/date is rejected.
5. As Payroll Officer, open an eligible October demonstration core preview's
   contribution/withholding reference review. It selects the October version;
   November selects the later version. Neither selection changes old results.
6. With a fictional monthly salary/core earnings of `30000.00`, no extra core
   earnings/deductions, reviewed contribution bases `30000.00`, all three standard
   membership scenarios selected and monthly regular taxable pay `26350.00`, enter
   explicit zeros for other pay/benefits/prior tax. The October profile yields
   GSIS `2700.00`, withholding `827.55` and reference net `25522.45`. The fictional
   November profile yields GSIS `3000.00` and reference net `25222.45` using the
   same manually reviewed tax input. Taxable pay is not automatically reclassified
   when contribution settings change; actual payroll treatment needs validation.
7. Leave a statutory review form open, activate a newly applicable configuration,
   then submit the old form. It must request reload/review. Reopen and explicitly
   save to obtain a new statutory revision; old review values remain unchanged.
8. As Approver/Viewer, inspect activated versions/history and verify that drafts,
   create/edit/activation actions are unavailable. Check keyboard navigation,
   narrow-screen tables and disclosure controls manually.

Correction spot checks: use a fictional document reference or
`javascript:alert(1)` as a demonstration source and verify it displays as inert
text on the payroll reference page; an HTTPS source remains a link. Inspect
creation/revision/activation history and confirm the effective date matches the
entered month. Automated cases additionally verify reordered creation retries
and legacy record preservation. These steps are provided, not claimed as live
browser acceptance performed during correction.

The examples validate software behavior using fictional choices; they do not
establish actual employee membership, tax classification or entitlement.

## Files and database

Created: `PayrollRuleVersion` / `PayrollRuleEvent` models; configuration controller,
policy, save/activation requests and rejected-input middleware; version service;
three Blade pages; one migration; `PayrollRuleVersionTest`; rollback-only
`scripts/verify-payroll-rule-versions.php`; this handoff.

Updated: authorization registration/routes, existing navigation/cards, statutory
controller/service/request/form and shared profile validation, statutory integration
tests, README and the four tracking documents. The earlier statutory MySQL verifier
now creates its own dated fictional configurations and carries their IDs. Its
malformed-configuration checks inject faults only into those transaction-owned
fixtures, bypassing model locks for that diagnostic and restoring them before
rollback; operator configurations are never modified. No dependencies were added.

One additive migration creates `payroll_rule_versions` and `payroll_rule_events`.
It was applied to local MySQL; all twelve migrations are Ran. No production-rule
seed, data reset, payroll snapshot rewrite, commit or Phase 9 implementation.
Verification is recorded in TESTING_STATUS.md. MySQL's original stopped state is
restored after rollback-only checks.

## Remaining validation

Actual approved rule/example data, category membership/tax bases, rounding,
retrospective/first-day activation policy, second-person configuration approval
and live office use need client acceptance. The prototype authorizes activation
to Administrators; it does not invent an unapproved second-reviewer role workflow.
No new contribution formulas, automatic annual ledger, refund execution, payroll
submission/approval/finalization, documents or blockchain features were introduced.
Earlier Phase 2/3 findings are unchanged. Browser visual/accessibility acceptance,
simultaneous MySQL activation/source races, deadlock/connection-loss recovery and
office-scale performance remain unverified.

Recommended next phase: Phase 9 - Payroll Review and Approval, only after user
authorization. Stop after Phase 8.
