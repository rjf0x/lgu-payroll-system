# Attendance & Leave Modules (Stage 4)

**Stage:** 4 of the development plan
**Status:** Implemented for the mock-defense build
**Related:** `database-implementation.md`, `authentication-authorization.md`, `database-schema.md` §6

---

## 1. Scope

| Resource | Route base | Model |
|---|---|---|
| Attendance records | `/attendance-records` | `AttendanceRecord` |
| Attendance adjustments | `/attendance-adjustments` | `AttendanceAdjustment` |
| Leave types | `/leave-types` | `LeaveType` |
| Leave balances | `/leave-balances` | `LeaveBalance` |
| Leave applications | `/leave-applications` | `LeaveApplication` |
| Leave transactions | `/leave-transactions` | `LeaveTransaction` |

Attendance is **day-based** (no time-in/time-out), with a `credited_days` value per record.

---

## 2. Authorization

Both areas require the manage permission (granted to **Admin** and **Payroll Officer**):

| Area | Permission |
|---|---|
| Attendance records & adjustments | `attendance.manage` |
| Leave types, balances, applications, transactions | `leave.manage` |

The Viewer and Approver roles cannot access these screens (`403`).

---

## 3. Business rules implemented

### Attendance records
- One record per employee per day, enforced in validation **and** by the database unique
  key `(employee_id, attendance_date)`.
- Statuses: `PRESENT`, `HALF_DAY`, `ABSENT`, `ON_LEAVE`, `HOLIDAY`, `REST_DAY`.
- The uniqueness check compares on the **date part** so it behaves identically on MySQL
  and SQLite (SQLite stores the `date` cast with a time component).

### Attendance adjustments (approval workflow)
- A Payroll Officer requests a change; the original status/days are captured as a snapshot.
- `PENDING` → an authorized user **Approve & apply** (updates the attendance record and
  records `reviewed_by`/`reviewed_at`) or **Reject**.
- An already-reviewed adjustment cannot be reviewed again.

### Leave
- **Applications** are filed as `PENDING` and approved or rejected.
- **Approving** an application, inside a DB transaction:
  1. Finds or creates the `LeaveBalance` for the employee, type, and application year.
  2. Increases `total_used` and recalculates `available_balance` (`opening + credits − used`).
  3. Writes a `LeaveTransaction` (`USED`) ledger row with the resulting balance.
  4. Marks the application `APPROVED` with `approved_by`/`approved_at`.
- **Rejecting** only records the decision (no balance change).
- A reviewed application cannot be reviewed again, and an approved application cannot be
  deleted.
- **Leave balances** can also be seeded/adjusted manually (opening balance and credits);
  `total_used` is managed by approvals.

---

## 4. Routes

```php
Route::middleware('can:attendance.manage')->group(function () {
    Route::resource('attendance-records', AttendanceRecordController::class)->except('show');
    Route::resource('attendance-adjustments', AttendanceAdjustmentController::class)
        ->except(['edit', 'update', 'destroy']);
    Route::post('attendance-adjustments/{attendance_adjustment}/approve', ...)->name('attendance-adjustments.approve');
    Route::post('attendance-adjustments/{attendance_adjustment}/reject', ...)->name('attendance-adjustments.reject');
});

Route::middleware('can:leave.manage')->group(function () {
    Route::resource('leave-types', LeaveTypeController::class)->except('show');
    Route::resource('leave-balances', LeaveBalanceController::class)->except(['show', 'destroy']);
    Route::resource('leave-applications', LeaveApplicationController::class)->except(['edit', 'update']);
    Route::post('leave-applications/{leave_application}/approve', ...)->name('leave-applications.approve');
    Route::post('leave-applications/{leave_application}/reject', ...)->name('leave-applications.reject');
    Route::resource('leave-transactions', LeaveTransactionController::class)->only('index');
});
```

---

## 5. UI

New sidebar sections **Attendance** and **Leave** appear only for users holding the
respective permission. Screens use the shared Tailwind layout and form components from
Stage 3, with index filters (employee, month, status) and inline approve/reject actions.

---

## 6. Tests

`tests/Feature/AttendanceLeaveTest.php`:

- Attendance is unique per employee and date (validation).
- Approving an adjustment applies the requested status and credited days.
- Approving a leave application updates the balance and writes a ledger transaction.
- A reviewed leave application cannot be approved twice.
- A Viewer is forbidden from the management screens.

Run with `php artisan test --compact`.
