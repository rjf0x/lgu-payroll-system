# Payroll Register, Payslips & Reports (Stage 8)

**Stage:** 8 of the development plan
**Status:** Implemented for the mock-defense build
**Related:** `payroll-computation-engine.md`, `payroll-approval-workflow.md`, `payroll-computation-design.md` §9

---

## 1. Scope

| Feature | Route | Model |
|---|---|---|
| Payroll register (view) | `GET /payroll-runs/{run}/register` | `PayrollDetail` |
| Register CSV export | `GET /payroll-runs/{run}/register/export` | `GeneratedReport` |
| Payslips list | `GET /payroll-runs/{run}/payslips` | `Payslip` |
| Generate payslips | `POST /payroll-runs/{run}/payslips/generate` | `Payslip` |
| Payslip view (printable) | `GET /payslips/{payslip}` | `Payslip` |
| Publish payslip | `POST /payslips/{payslip}/publish` | `Payslip` |
| Reports hub | `GET /reports` | `GeneratedReport` |
| Payroll summary | `GET /payroll-runs/{run}/summary` | `PayrollDetail` |

---

## 2. Payroll register

The register reproduces the client's column layout, built dynamically from the run's
details:

`Office · Employee Number · Name · Position · Gross Salary · <each active earning type> ·
Total Compensation · Withholding Tax · GSIS Contrib. · PhilHealth Contrib. ·
Pag ibig Contrib. · <each active non-statutory deduction type> · TOTAL DEDUCTIONS ·
NTHP Before LBP · LBP Loan Deduction · Net Take Home Pay`

- Earning amounts are grouped by snapshot name; deduction amounts by **deduction type id**
  (so same-named types such as the two `MPL` entries stay distinct, labelled with their
  category).
- A totals row sums every monetary column.
- **Export CSV** streams the same rows and records a `generated_reports` row
  (`PAYROLL_REGISTER`) with the run id in `filters`.

---

## 3. Payslips

- **Generated only for `APPROVED` or `FINALIZED` runs.** Generation creates one `Payslip`
  per detail that lacks one (`GENERATED` status), numbered
  `PS-<YYYYMM>-<employee_number>`.
- The **payslip view** is a printable document (separate `layouts/print`) showing earnings,
  deductions, NTHP, LBP, net pay, and employer contributions.
- **Publish** sets `publication_status = PUBLISHED` and `published_at`.

---

## 4. Reports

- **Reports hub** lists generated reports and recent runs with quick links to their
  register and summary.
- **Payroll summary** aggregates gross / deductions / net by department, with a grand-total
  footer and a link to export the register.
- Every register export writes to `generated_reports` (`report_type`, `generated_by`,
  `filters`, `generated_at`).

---

## 5. Authorization

| Action | Permission |
|---|---|
| View register, payslips, reports, summary | `payroll.view` |
| Export register, generate/publish payslips | `reports.generate` |

---

## 6. UI

- The run page has **Register**, **Payslips**, and **Summary** links once details exist.
- The **Reports** link appears in the Payroll sidebar section.
- The payslip page is print-optimized (`window.print()` button; sidebar-free layout).

---

## 7. Tests

`tests/Feature/PayrollOutputTest.php`:

- Register renders employee rows.
- Register CSV export returns `text/csv` and records a `generated_reports` row.
- Payslips cannot be generated for a draft run.
- Payslips generate for an approved run, the payslip renders, and publish sets `PUBLISHED`.
- A user without `payroll.view` cannot open the register.
