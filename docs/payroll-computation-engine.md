# Payroll Computation Engine (Stage 6)

**Stage:** 6 of the development plan
**Status:** Implemented for the mock-defense build
**Related:** `database-implementation.md`, `earnings-deductions-modules.md`, `attendance-leave-modules.md`, `payroll-computation-design.md`

---

## 1. Scope

| Resource | Route base | Model |
|---|---|---|
| Payroll periods | `/payroll-periods` | `PayrollPeriod` |
| Payroll runs | `/payroll-runs` | `PayrollRun` |
| Payroll details (per employee) | `/payroll-runs/{run}/details/{detail}` | `PayrollDetail` |

A `PayrollComputationService` builds `payroll_details` (with snapshots) plus
`payroll_earnings`, `payroll_deductions`, and `payroll_employer_contributions`.

**Authorization:** reads `payroll.view`; write/compute `payroll.prepare`
(Payroll Officer + Admin). The Viewer can open runs read-only.

---

## 2. Lifecycle

1. Create a **payroll period** (monthly coverage + pay date).
2. Create a **payroll run** for a period (status `DRAFT`, `run_number` auto-increments per period).
3. **Compute** the run: `PayrollComputationService::computeRun()` rebuilds every active
   employee's `PayrollDetail`. `processed_at` is set; the run stays editable.
4. **Edit** a detail to enter manual allowances/deductions and the LBP amount; saving
   recomputes only that detail.
5. Later stages add submit → approve → finalize (Stage 7) on top of the same records.

A run is editable while `DRAFT`, `RETURNED`, or `REJECTED` (`PayrollRun::isEditable()`).

---

## 3. Computation steps

For each active employment record with an effective compensation profile:

1. **Basic pay**
   - Grade-and-step: the schedule's `monthly_salary`. Direct rate: `basic_rate`.
   - Monthly / proportional: `rate × service_fraction`, then reduced for unpaid days using
     the CSC leave-without-pay factor `(22 − days_without_pay) / 22`.
   - Daily rate: `rate × credited paid days`.
   - If the period has **no attendance records**, a complete month is assumed (a
     demo convenience documented in the engine).
2. **Earnings** — from effective `employee_earning_assignments` (amount or % of basic pay).
3. **Gross pay (Total Compensation)** = basic pay + earnings.
4. **Statutory employee shares** (effective-dated `contribution_rates`), each clamped to
   the rate's compensation floor/ceiling:
   - GSIS, PhilHealth, Pag-IBIG → `payroll_deductions` rows.
5. **Withholding tax** — annual graduated projection: annualize taxable gross less the
   pre-tax employee contributions, find the effective `tax_brackets` row, then
   `(base_tax + rate × excess) / 12`.
6. **Manual deductions** — non-statutory rows entered by the officer (loans, GABE, dues).
7. **Total deductions** = tax + statutory shares + manual deductions.
8. **NTHP before LBP** = gross pay − total deductions.
9. **LBP deduction** (separate, excluded from total deductions) → **Net pay**.

**Employer shares** are stored in `payroll_employer_contributions` and never reduce net pay.

All monetary values are rounded to **2 decimals** at each step.

---

## 4. Documented example (verified by test)

Monthly salary ₱45,000, complete month, no allowances/loans:

| Line | Amount |
|---|---:|
| Gross / Total Compensation | ₱45,000.00 |
| GSIS employee share (9%) | ₱4,050.00 |
| PhilHealth employee share (2.5%) | ₱1,125.00 |
| Pag-IBIG employee share (2% of ₱10k) | ₱200.00 |
| Withholding tax (annual projection ÷ 12) | ₱3,133.33 |
| **Total Deductions** | **₱8,508.33** |
| **NTHP Before LBP** | **₱36,491.67** |
| **Net Take-Home Pay** | **₱36,491.67** |
| Employer contributions (12% + 2.5% + 2%) | ₱6,725.00 |

`tests/Feature/PayrollComputationTest.php` asserts these figures exactly.

---

## 5. Routes

```php
Route::middleware('can:payroll.view')->group(function () {
    Route::get('payroll-periods', ...)->name('payroll-periods.index');
    Route::get('payroll-runs', ...)->name('payroll-runs.index');
    Route::get('payroll-runs/{payroll_run}', ...)->whereNumber('payroll_run')->name('payroll-runs.show');
});

Route::middleware('can:payroll.prepare')->group(function () {
    // period + run create/store/edit/update/destroy
    Route::post('payroll-runs/{payroll_run}/compute', ...)->whereNumber('payroll_run')->name('payroll-runs.compute');
    Route::get('payroll-runs/{payroll_run}/details/{payroll_detail}/edit', ...)->scopeBindings()->name('payroll-details.edit');
    Route::put('payroll-runs/{payroll_run}/details/{payroll_detail}', ...)->scopeBindings()->name('payroll-details.update');
});
```

`whereNumber('payroll_run')` keeps `payroll-runs/create` from matching the `{payroll_run}` show route.

---

## 6. UI

- **Payroll Periods** — list + create/edit.
- **Monthly Payroll** — list of runs; open a run to see status, totals (gross, deductions,
  net, employer contributions), and the per-employee details table. **Compute/Recompute**
  and **Delete** act on editable runs.
- **Detail entry** — a per-employee screen with amount inputs for every active earning type
  and non-statutory deduction type plus the LBP field; saving recomputes the detail.

---

## 7. Tests

- `tests/Feature/PayrollComputationTest.php` — documented example totals; employer shares;
  leave-without-pay reduction; daily-rate pay.
- `tests/Feature/PayrollRunFlowTest.php` — create period/run, compute via HTTP; manual
  earning + LBP update flow; Viewer cannot prepare payroll.

---

## 8. Caveats (initial engine)

- Auto-withholding uses the **annual projection ÷ 12** method for the prototype; the
  BIR-compliant periodic table is a later enhancement.
- Leave-without-pay is driven by `ABSENT` attendance; unpaid leave types are not yet
  distinguished.
- `HOURLY` pay basis is treated as a monthly base (no hours field exists in the day-based
  design).
- Attendance absence defaults to a full month when no records exist — noted for demos.
- All seeded rates/amounts remain illustrative placeholders.
