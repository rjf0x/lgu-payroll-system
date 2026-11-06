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
