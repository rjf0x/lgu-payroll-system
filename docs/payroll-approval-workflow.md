# Payroll Approval Workflow & Separation of Duties (Stage 7)

**Stage:** 7 of the development plan
**Status:** Implemented for the mock-defense build
**Related:** `payroll-computation-engine.md`, `authentication-authorization.md`, `database-schema.md` §5

---

## 1. Lifecycle

```
DRAFT ──compute──► DRAFT ──submit──► PENDING_APPROVAL
                                        │
                     ┌──────────────────┼───────────────────┐
                     ▼                  ▼                   ▼
                 APPROVED            REJECTED           RETURNED
                     │                                     │
              finalize│                          edit / compute / submit
                     ▼                                     └──► PENDING_APPROVAL
                 FINALIZED
```

| Action | From | To |
|---|---|---|
| Submit | `DRAFT` / `RETURNED` / `REJECTED` (editable) | `PENDING_APPROVAL` |
| Approve | `PENDING_APPROVAL` | `APPROVED` |
| Reject | `PENDING_APPROVAL` | `REJECTED` |
| Return for correction | `PENDING_APPROVAL` | `RETURNED` |
| Finalize | `APPROVED` | `FINALIZED` |

A run is editable (`PayrollRun::isEditable()`) only while `DRAFT`, `RETURNED`, or
`REJECTED`. Compute/edit/delete are blocked once it enters review.

---

## 2. Permissions per action

| Action | Permission |
|---|---|
| Submit | `payroll.submit` |
| Approve | `payroll.approve` |
| Reject | `payroll.reject` |
| Return | `payroll.review` |
| Finalize | `payroll.finalize` |

The **Admin** role deliberately holds none of these (only `payroll.view`), so
administrative privilege cannot bypass the workflow.

---

## 3. Separation of duties

`PayrollApprovalService::isIndependentReviewer()` requires the reviewer to be
**neither** `payroll_runs.created_by` **nor** `payroll_runs.submitted_by`. The check is
enforced in the service (returning `403`), not just hidden in the UI. A user holding both
the Payroll Officer and Approver roles still cannot approve a run they prepared or
submitted.

Each transition is recorded in `payroll_approvals` (`action`, `acted_by`, `remarks`,
`acted_at`) and mirrored to `audit_logs` (`PAYROLL_SUBMITTED` / `APPROVED` / `REJECTED` /
`RETURNED` / `FINALIZED`).

Only an **approved** run can be finalized (`422` otherwise).

---

## 4. Routes

```php
Route::post('payroll-runs/{payroll_run}/submit',   ...)->middleware('can:payroll.submit')->name('payroll-runs.submit');
Route::post('payroll-runs/{payroll_run}/approve',  ...)->middleware('can:payroll.approve')->name('payroll-runs.approve');
Route::post('payroll-runs/{payroll_run}/reject',   ...)->middleware('can:payroll.reject')->name('payroll-runs.reject');
Route::post('payroll-runs/{payroll_run}/return',   ...)->middleware('can:payroll.review')->name('payroll-runs.return');
Route::post('payroll-runs/{payroll_run}/finalize', ...)->middleware('can:payroll.finalize')->name('payroll-runs.finalize');
```

All are `whereNumber('payroll_run')` so they never collide with `payroll-runs/create`.

---

## 5. UI

- **Run page** shows status, submitter, finalizer, totals, the details table, an
  **Approval History** table, and context-aware actions:
  - compute / submit / delete for editable runs;
  - approve / return / reject for an independent reviewer while pending;
  - finalize once approved.
- **Monthly Payroll** list has a **status filter**, and Approvers get a **Pending
  Approvals** shortcut (`?status=PENDING_APPROVAL`).

---

## 6. Tests

`tests/Feature/PayrollApprovalTest.php`:

- Officer submits a computed run (`SUBMITTED` recorded, `submitted_by` set).
- An independent approver approves.
- A preparer holding approve permission is still blocked (`403`).
- Only an approved run can be finalized (pending → `422`).
- Returned runs are editable and can be recomputed/resubmitted (history preserved).
- Rejection is recorded.
- Admin cannot approve without the permission.

---

## 7. Next

Blockchain anchoring and verification of finalized payroll are implemented in Stage 9,
building on the `FINALIZED` state.

## F04: compensation uniqueness and non-regular payroll

Regular compensation is checked by employee identity across employment records,
run numbers, and inclusive payroll-period dates. Basic salary and each earning
type are separate components; changing an amount does not create a new entitlement.
Processing, pending, approved, and finalized runs reserve their compensation.
Editable drafts, returned/rejected runs, and cancelled runs do not reserve it,
but repeated components and overlapping details within the same run are invalid.
Regular coverage uses the full run period: the system has no approved policy for
splitting one regular entitlement between multiple appointments in that period.

Preparation/computation, submission, approval, and finalization repeat these
checks. Financial transitions acquire the singleton `payroll_processing_locks`
database row before locking a run, and retain it through validation and commit.
This serializes cross-run decisions even when no conflicting detail existed
before the first request. Existing unique run-number and run/employment indexes
remain in force. Blocked financial transitions are audited after rollback.

Pending or approved runs require the existing independent return/rejection
workflow before edits. A regular run returned/rejected by an independent reviewer
can be replaced in the same period with a reason. Replacement retains the old
run as cancelled, copies provisional inputs, clears confirmation/fingerprints,
and requires fresh computation and approval. Finalized runs cannot be replaced,
returned, edited, or deleted through payroll preparation routes.

Supplemental and adjustment runs do not carry forward finalized payroll or
automatically include the employee population. The officer explicitly selects
employees and records coverage, an entitlement reference, justification, and
accounting authorization. Supplemental basic salary is always zero; earnings
are entered explicitly. Adjustments record only the positive salary difference
and/or explicit earning differences and reference a finalized original detail
for the same employee and coverage. Original details and registry proofs remain
unchanged; the correction receives its own run, approval history, and registry
capture on finalization when registry integration is enabled.

An overlapping earning component may represent a separate authorized entitlement.
It requires a distinct supporting reference and overlap justification, visible to
the independent reviewer and recorded in the audit trail. The same normalized
reference, component, employee, and overlapping coverage is always rejected,
even when amounts differ. A linked adjustment is distinct from its original;
another correction of that component still needs its own entitlement evidence.
References identify the business entitlement, not a newly generated payment ID.

### Accounting decisions still requiring Municipal Accounting Office confirmation

- Whether the existing monthly contribution bases, minimums, fixed amounts,
  and annual-projection withholding calculation apply to each non-regular run
  type and its supported entitlements; whether aggregation with prior payments
  or another calculation method is required.
- What documents distinguish separate entitlements, installments, and corrections
  of the same component, and who verifies those supporting authorizations.
- Treatment of recoveries, negative adjustments, refunds of deductions, and
  transactions that would produce negative net pay. Automatic recoveries and
  negative-net-pay exceptions are unsupported; F03 remains in force.

Until confirmed, non-regular amounts are provisional and details show
`POLICY_REQUIRED`; submission, approval, and finalization fail with an accounting
policy message. Missing configuration is never interpreted as an approved zero
tax/contribution treatment. Draft gross/net totals exclude unestablished statutory
amounts and must not be treated as final financial liabilities.

Only if MAO explicitly confirms that the existing calculation applies to the
supported transaction type, configure its `PAYROLL_SUPPLEMENTAL_POLICY_REFERENCE`
or `PAYROLL_ADJUSTMENT_POLICY_REFERENCE` and corresponding
`*_CALCULATION_METHOD=existing_monthly`, then rebuild the configuration cache and
recompute the draft. These variables are unset by default. Unknown methods remain
blocked; a different treatment needs a separately reviewed implementation.
Confirmation does not waive F03: compensation profiles, effective contribution
rates, deduction types, tax coverage, reconciled totals, calculation fingerprints,
and nonnegative net pay must still pass validation. Policy changes invalidate
non-regular computation fingerprints. Approval and finalization revalidate them.
