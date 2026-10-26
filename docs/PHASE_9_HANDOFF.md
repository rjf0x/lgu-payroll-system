# Phase 9 - Payroll Review and Approval

Date: 2026-10-27 (Asia/Manila). Only Phase 9 is implemented. Stop before Phase 10.

Status: Implemented but needs validation. Automated and MySQL checks establish
supported demonstration behavior; real payroll rules and authority remain unvalidated.

## Behavior and acceptance

| Requirement | Implemented behavior |
| --- | --- |
| Review and submission | Combined employee/core/statutory totals, expandable calculation/authority details, fixed submission revisions and paginated workflow/history. Payroll Officer submits only a complete current demonstration preview. |
| Separation of duties | Active Approver reviews/finalizes. Creator, submitter, retained coverage editors and any historical input/core/statutory author cannot review/finalize their own batch, even after a role change. Administrator and Viewer cannot perform transitions. |
| Return and correction | Submitted or approved payroll can be returned with a required reason. Returned coverage/inputs are editable using existing endpoints. Explicit recalculation and statutory review precede a new submission. Earlier inputs, calculations, submissions and events remain available. |
| Finalization lock | Only approved payroll with a retained approval event can finalize. A separate confirmed action stores finalized version 1 with submitted values and workflow actor/reason/time evidence. Submitted/approved/finalized batches reject all preparation writes. |
| Preservation | Model guards reject ordinary mutations of submissions, events, finalizations and finalized batch/coverage. Source/configuration changes do not recalculate finalized values. This is application-level retention, not protection against privileged database modification. |

The same independent Approver may approve and finalize; no third-signatory policy
is invented. An independent Approver can return an approved batch if sources change
before finalization. Once finalized, no reopen/correction command is provided.
Authorized finalized correction versions belong to Phase 12.

## Validation and transactions

- Every mutation locks/reloads the actor and batch, rechecks active role/session,
  validates the current record version and enforces allowed status transitions.
  Decisions carry the exact current submission ID. Stale/repeated actions reject
  without duplicate history or finalized snapshots; they are not silently replayed.
- Submission requires the current core engine/run, matching employee coverage,
  current reviewed inputs/salary/profile sources and complete statutory scenarios
  for every employee using the currently selected configuration/engine. Component
  deductions and combined totals retain exact two-decimal arithmetic.
- A locally generated review token detects changed statutory revisions/amounts
  between viewing and submitting, even though statutory saves do not increment
  the core batch version. It is a concurrency check, not blockchain verification
  or the Phase 11 canonical on-chain representation.
- Approval and finalization recheck sources and configuration against submitted
  values. Any change requires return/correction/resubmission. Return remains
  available when stale values would prevent approval or finalization.
- Reasons and explicit demonstration/review confirmation are required. UTF-8,
  length, CSRF, guest and safe escaped rendering checks apply. Rejected workflow
  values are not flashed into other workflows. Submitted amounts/status cannot
  be supplied by the client.
- State, immutable events and submission/final snapshot writes share a transaction.
  Unique indexes protect submission revisions/batch versions, event batch versions
  and one finalized version per batch/submission. Injected storage failures roll
  back the whole transition. Live overlapping writers remain unverified.

Real-mode submission/approval/finalization remain blocked. Demonstration results
retain `production_ready=false`, with no production-rule activation switch. Negative
net or an unexecuted tax credit/refund blocks submission pending a handling policy;
neither is capped, refunded or silently excluded. Existing zero-attendance/full-
month and statutory reference limitations continue to apply.

## Files and database

New: `PayrollWorkflowService`, `PayrollReviewController`, `PayrollWorkflowRequest`,
three immutable workflow models, two review Blade files, one migration,
`PayrollWorkflowTest`, `scripts/verify-payroll-workflow.php`, and this handoff.

Changed: `PayrollBatch`, `PayrollEntry`, batch policy/preparation service/routes,
batch list/detail and core result navigation, README and all four tracking documents.
No dependency added. No document export, payslip, blockchain or Phase 10 feature.

Migration `2026_10_27_000001_create_payroll_review_tables.php`:

- Expands batch statuses to draft/returned/submitted/approved/finalized.
- Adds retained coverage-preparer IDs; backfills known existing creator/last editor.
- Creates `payroll_submissions`, `payroll_workflow_events`, `payroll_finalizations`
  with restrictive foreign keys and uniqueness constraints.
- Refuses rollback once submissions/workflow states must be retained.

Applied to local MySQL; all thirteen migrations are Ran. No payroll inputs or
calculated values rewritten. Legacy coverage-only editors overwritten before this
migration cannot be reconstructed. Creator/last-editor and historical saved
input/calculation/statutory authors are checked; all new coverage editors are
retained. Do not treat this as a reconstructed pre-Phase-9 audit trail.

## Verification executed

- Focused workflow suite: 42 tests / 569 assertions passed.
- Final workflow/navigation selection: 48 tests / 641 assertions passed.
- Full hostile-environment isolation suite: 438 tests / 4600 assertions passed,
  including all 396 earlier cases. No coverage percentage measured.
- Rollback-only MySQL workflow: three categories in one batch, exact employee and
  batch values, MySQL JSON token compatibility, role-change self-review rejection,
  return/correction/resubmission, prior history, finalized locking/duplicate
  rejection, later real configuration activation and real-mode block passed.
- Existing MySQL core/statutory/configuration verifiers passed with rollback.
- Application/verifier Pint, strict Composer validation, production build, database
  readiness and whitespace checks passed. MySQL's initial stopped state restored.

SQLite coverage additionally checks four roles, every preparation endpoint in
three locked states, wrong submission IDs, stale versions/tokens, incomplete
reviews, changed employee/configuration sources, actual configuration activation
before/after finalization, exact multi-employee totals, invalid text/escaping,
guest/CSRF/current actor revocation, five injected persistence failures and ordinary
model immutability including a stale pre-finalization batch object.

## Manual verification

1. Apply migrations, build assets and start the app per README. Use fictional
   employees/schedules and separate Payroll Officer/Approver accounts. Prepare a
   demonstration month, explicit zero attendance, current core preview and complete
   statutory reference reviews for every employee.
2. As Payroll Officer, open **Payroll review, approval and finalized snapshot**.
   Inspect totals/employee details, enter a fictional reason, confirm and submit.
   Coverage, inputs, core and statutory writes must now be unavailable.
3. As independent Approver, return with a reason. As Payroll Officer, correct one
   loan, recalculate, save new statutory reviews and resubmit. Inspect both retained
   submissions and the correction reason.
4. As Approver, approve then finalize with separate confirmed actions. Reopen as
   Viewer and verify the fixed version/totals and history. Attempt preparation
   writes as Payroll Officer; they must fail. No blockchain success is displayed.
5. As Administrator, activate a separately dated applicable fictional configuration.
   Reopen finalized payroll; stored totals/parameters must stay unchanged. For a
   still-submitted batch, an applicable change must require return and resubmission.

For a literal one-employee example, fictional monthly salary `30000.00`, no extra
earnings/loans, all three reference memberships with entered bases `30000.00`,
monthly regular taxable pay `26350.00` and other tax fields explicitly zero yield
earnings `30000.00`, deductions `4477.55`, net `25522.45` under the original
reference parameters. A corrected loan of `100.01` yields net `25422.44`. These
examples test arithmetic; they do not establish employee entitlement or tax bases.

## Remaining validation

Client-approved category/contribution/withholding/attendance rules, finalization
authority, evidence practices, minimum-net/refund policy and office deployment
remain pending. Live browser/visual/keyboard/mobile/screen-reader acceptance,
simultaneous MySQL submission/decision/source/configuration races, deadlock and
connection-loss recovery, migration rollback on MySQL and office-scale performance
are unverified. Earlier Phase 2/3 audit findings are unchanged.

Recommended next phase: Phase 10 - Payroll Document Generation, only after the
user authorizes it. No Phase 10 work, Git commit or publication performed.

## Audit correction follow-up - 2026-10-27

The subsequent Phase 9 audit confirmed one low F1 defect: former preparers promoted
to Approver saw self-review/finalization forms that the server correctly rejected.
F1 is now corrected under the user's current-phase-only fix authorization.

`PayrollWorkflowService::isIndependent()` shares the existing determination between
the review controller and transactional guard. The controller computes it once
for eligible Approvers, and the view hides forbidden approve/return/finalize forms.
Independent Approvers retain all existing actions. Locked actor/batch rechecks,
server denials, preparation rules, payroll snapshots and amounts are preserved.

Changed for this correction only: workflow service/controller/review view,
`PayrollWorkflowTest` and relevant tracking/audit/handoff records. No schema or
dependency change. Five new preparation-source cases plus enhanced creator coverage
pass; focused selection is 6 tests / 179 assertions. Full isolation passes
443 tests / 4768 assertions; Phase 9 contributes 47 cases / 737 assertions. MySQL
workflow, readiness/status, Pint, Composer validation and whitespace checks pass.
MySQL's original stopped state was restored. Full details are in TESTING_STATUS.md.

Manual spot check: submit a fictional batch as Payroll Officer; change that account
to Approver using Administrator and sign in again. Its own payroll must show no
approve/return/finalize forms. An independent Approver must still see approve and
return while submitted, then finalize and return after approval. Confirm retained
totals and history remain readable. These browser steps are provided, not claimed
as live browser acceptance performed during correction.

The original audit/probe remains historical evidence of the pre-fix behavior.
Client rules/authority, legacy overwritten coverage actors, browser, concurrency/
recovery, MySQL downgrade and capacity validation remain pending. Stop at Phase 9;
no new feature or Phase 10 work was implemented.
