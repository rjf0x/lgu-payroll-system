# Earnings & Deductions Configuration (Stage 5)

**Stage:** 5 of the development plan
**Status:** Implemented for the mock-defense build
**Related:** `database-implementation.md`, `master-data-modules.md`, `payroll-computation-design.md`

---

## 1. Scope

Configuration screens that feed the payroll computation engine (Stage 6):

| Resource | Route base | Model |
|---|---|---|
| Earning types | `/earning-types` | `EarningType` |
| Employee earning assignments | `/employee-earning-assignments` | `EmployeeEarningAssignment` |
| Deduction types | `/deduction-types` | `DeductionType` |
| Employee deduction assignments | `/employee-deduction-assignments` | `EmployeeDeductionAssignment` |
| Tax brackets | `/tax-brackets` | `TaxBracket` |
| Contribution rates | `/contribution-rates` | `ContributionRate` |

---

## 2. Authorization

- Reads require `employees.view`.
- Writes require `compensation.manage` (Admin + Payroll Officer).
- Viewer can browse but gets `403` on create/edit; Approver cannot access these screens.

---

## 3. Earning types

Definitions for allowances/earnings. Seeded with the client register allowances
(PERA, Hazard Pay, Clothing, Subsistence, Laundry, Medical — all `MANUAL`, non-taxable
placeholders until confirmed).

Fields: `earning_name`, `earning_category`, `computation_type`
(`MANUAL` / `FIXED` / `PERCENTAGE`), `is_taxable`, `is_recurring`, `status`.

## 4. Deduction types

Definitions for loans, contributions, GABE, and other deductions. Seeded from the client
register, including the computed statutory entries (`Withholding Tax`, `GSIS Contribution`,
`PhilHealth Contribution`, `Pag-IBIG Contribution`) and the manual entries
(`GSIS_LOAN`, `PAGIBIG_LOAN`, `GABE`, `PLI_DUES`, and the separate `LBP` deduction).

Fields: `deduction_name`, `deduction_category`, `computation_type`
(`MANUAL` / `FIXED` / `PERCENTAGE` / `COMPUTED`), `is_statutory`, `is_pre_tax`, `status`.

## 5. Employee assignments

Attach an earning or deduction to an **employment record** with an effective period.

| Field | Notes |
|---|---|
| `employment_id` | The employment record the assignment applies to. |
| `earning_type_id` / `deduction_type_id` | The configured type. |
| `amount` / `percentage` | **At least one is required** (validated). |
| `frequency` (earnings) | `MONTHLY` only for automatic assignments of active, recurring earning types. Annual and one-time amounts require explicit payroll-period input. |
| `reference_number` (deductions) | Optional lender/authority reference. |
| `effective_from` / `effective_to` | `effective_to` must not precede `effective_from`. |
| `status` (deductions) | `ACTIVE` / `INACTIVE`. |

Automatic computation skips inactive earning types, inactive deduction assignments and inactive deduction types. Earnings have no separate assignment-status field. Assignment dates are inclusive at the compensation date (period end, or employment end when earlier). Amount takes precedence when both amount and percentage are supplied; percentages apply to basic pay. Statutory deductions and the separate LBP amount retain their dedicated calculation/input paths.

Effective legacy `ANNUAL`/`ONE_TIME` assignments, or automatic assignments using a nonrecurring active earning type, block regular computation and submission with an employee-specific correction message. They are not silently repeated or discarded. Correct or remove the assignment, then enter exceptional earnings explicitly in the applicable period. Inactive or out-of-period assignments do not trigger that restriction. Existing unsupported edit values remain visible and labeled for correction; new forms offer supported choices only.

Next-period carry-forward excludes inactive types, nonrecurring earnings and assignment-backed rows without an eligible effective monthly earning or active deduction assignment. Active recurring manual earnings and active manual deductions without assignments may remain provisional inputs. Same-period authorized replacements retain their explicit inputs. Every copied detail still requires current confirmation and recomputation. This does not introduce annual entitlement scheduling or one-time payment tracking.

## 6. Statutory tables (effective-dated)

### Tax brackets (`/tax-brackets`)
`tax_type`, `pay_frequency`, `minimum_income`, `maximum_income`, `base_tax`,
`marginal_tax_rate` (0–1), `effective_from`, `effective_to`.
Seeded with the illustrative annual TRAIN brackets.

### Contribution rates (`/contribution-rates`)
`contribution_type`, `minimum_compensation`, `maximum_compensation`,
`employee_rate`/`employer_rate` (0–1), optional fixed amounts, and effective dates.
Seeded with illustrative GSIS (9% / 12%), PhilHealth (2.5% / 2.5%, ₱10k–₱100k), and
Pag-IBIG (2% / 2%, max ₱10k).

> All seeded amounts and rates remain **illustrative placeholders** for the mock defense.

---

## 7. Routes

```php
$configResources = [
    ['earning-types', EarningTypeController::class],
    ['employee-earning-assignments', EmployeeEarningAssignmentController::class],
    ['deduction-types', DeductionTypeController::class],
    ['employee-deduction-assignments', EmployeeDeductionAssignmentController::class],
    ['tax-brackets', TaxBracketController::class],
    ['contribution-rates', ContributionRateController::class],
];

foreach ($configResources as [$uri, $controller]) {
    Route::resource($uri, $controller)
        ->except('show')
        ->middlewareFor('index', 'can:employees.view')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:compensation.manage');
}
```

---

## 8. Tests

`tests/Feature/EarningsDeductionsTest.php`:

- Officer creates an earning type.
- Earning/deduction assignment requires an amount or percentage.
- Earning assignment with an amount is created.
- Contribution rate validates rate bounds (0–1) and `maximum >= minimum`.
- Tax bracket is created and effective-dated.
- Viewer can list but not create/configure.
- Deduction type can be created.

Run with `php artisan test --compact`.

`tests/Feature/PayrollAssignmentPolicyTest.php` covers active/effective automatic sources, unsupported assignment and hourly-profile rejection, computation/submission rollback, stale calculations after inactivation, supported pay formulas, explicit exceptional earnings and next-period carry-forward filtering.
