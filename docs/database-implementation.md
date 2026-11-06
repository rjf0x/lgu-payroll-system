# Database & Model Implementation

**Stage:** 1 of the development plan
**Status:** Implemented for the mock-defense build
**Source of truth:** `database-schema.md`, `erd.md`, `payroll-computation-design.md`

This document records how the 39-table design is implemented in the
`lgu_payroll_system_dev` MySQL database using Laravel migrations and Eloquent models.

---

## 1. Conventions

| Concern | Convention |
|---|---|
| Primary keys | Custom, named `{entity}_id` (e.g. `employee_id`, `payroll_run_id`) via `$table->id('...')` and `protected $primaryKey`. |
| Foreign keys | `$table->foreignId('...')->constrained('table', 'primary_key')` with explicit parent key. |
| Money | `DECIMAL(15,2)`, cast as `decimal:2` on models. |
| Rates / fractions | `DECIMAL(8,4)` / `DECIMAL(5,4)`, cast as `decimal:4`. |
| Statuses | Stored as `VARCHAR`, exposed as constants + PHP enums on the models (e.g. `PayrollRun::STATUS_DRAFT`). |
| Timestamps | Match the schema document exactly (see §3). |
| Factories | `HasFactory` on every model; factories provided for the core entities. |
| Soft deletes | `employees` only (`deleted_at`). |

### Timestamp handling

The schema document uses different timestamp shapes per table. Models mirror them:

| Shape | Columns | Model configuration |
|---|---|---|
| Both | `created_at`, `updated_at` | default `$timestamps` |
| Created only | `created_at` | `const UPDATED_AT = null;` |
| Updated only | `updated_at` | `const CREATED_AT = null;` |
| None | neither | `public $timestamps = false;` |

Tables with **neither** use their own domain timestamps instead
(`assigned_at`, `requested_at`, `acted_at`, `generated_at`, `anchored_at`,
`verified_at`, `transaction_date`).

---

## 2. Tables by module (39)

| Module | Table | Model | Primary key | Timestamps |
|---|---|---|---|---|
| User & Access | `users` | `User` | `user_id` | both |
| User & Access | `roles` | `Role` | `role_id` | both |
| User & Access | `permissions` | `Permission` | `permission_id` | both |
| User & Access | `user_roles` | `UserRole` | `user_role_id` | none |
| User & Access | `role_permissions` | `RolePermission` | `role_permission_id` | created |
| Employee | `employees` | `Employee` | `employee_id` | both (+ soft delete) |
| Organizational | `departments` | `Department` | `department_id` | both |
| Organizational | `positions` | `Position` | `position_id` | both |
| Organizational | `employment_types` | `EmploymentType` | `employment_type_id` | none |
| Employment | `employment_records` | `EmploymentRecord` | `employment_id` | both |
| Employment | `employment_history` | `EmploymentHistory` | `history_id` | created |
| Compensation | `salary_schedules` | `SalarySchedule` | `salary_schedule_id` | created |
| Compensation | `salary_grade_steps` | `SalaryGradeStep` | `grade_step_id` | created |
| Compensation | `compensation_profiles` | `CompensationProfile` | `compensation_id` | both |
| Compensation | `earning_types` | `EarningType` | `earning_type_id` | created |
| Compensation | `employee_earning_assignments` | `EmployeeEarningAssignment` | `assignment_id` | created |
| Attendance | `attendance_records` | `AttendanceRecord` | `attendance_id` | both |
| Attendance | `attendance_adjustments` | `AttendanceAdjustment` | `adjustment_id` | none |
| Leave | `leave_types` | `LeaveType` | `leave_type_id` | none |
| Leave | `leave_balances` | `LeaveBalance` | `leave_balance_id` | updated |
| Leave | `leave_applications` | `LeaveApplication` | `leave_application_id` | both |
| Leave | `leave_transactions` | `LeaveTransaction` | `leave_transaction_id` | none |
| Deduction | `deduction_types` | `DeductionType` | `deduction_type_id` | created |
| Deduction | `employee_deduction_assignments` | `EmployeeDeductionAssignment` | `assignment_id` | created |
| Deduction | `tax_brackets` | `TaxBracket` | `tax_bracket_id` | created |
| Deduction | `contribution_rates` | `ContributionRate` | `contribution_rate_id` | created |
| Payroll | `payroll_periods` | `PayrollPeriod` | `payroll_period_id` | both |
| Payroll | `payroll_runs` | `PayrollRun` | `payroll_run_id` | both |
| Payroll | `payroll_approvals` | `PayrollApproval` | `approval_id` | none |
| Payroll | `payroll_details` | `PayrollDetail` | `payroll_detail_id` | both |
| Payroll | `payroll_earnings` | `PayrollEarning` | `payroll_earning_id` | created |
| Payroll | `payroll_deductions` | `PayrollDeduction` | `payroll_deduction_id` | created |
| Payroll | `payroll_employer_contributions` | `PayrollEmployerContribution` | `employer_contribution_id` | created |
| Payroll | `payroll_adjustments` | `PayrollAdjustment` | `adjustment_id` | both |
| Payslip | `payslips` | `Payslip` | `payslip_id` | none |
| Reports | `generated_reports` | `GeneratedReport` | `report_id` | none |
| Audit | `audit_logs` | `AuditLog` | `audit_id` | created |
| Blockchain | `blockchain_anchors` | `BlockchainAnchor` | `anchor_id` | created |
| Blockchain | `blockchain_verification_logs` | `BlockchainVerificationLog` | `verification_id` | none |

---

## 3. Key relationships

- **Access:** `User` ↔ `Role` (many-to-many via `user_roles`); `Role` ↔ `Permission`
  (many-to-many via `role_permissions`). `User` optionally belongs to an `Employee`.
- **Organization:** `Department` has many `Position`; `Position` has many
  `EmploymentRecord`; `EmploymentRecord` belongs to employee, department, position,
  and employment type.
- **Compensation:** `EmploymentRecord` has many `CompensationProfile`;
  `CompensationProfile` belongs to a `SalaryGradeStep` (nullable for direct rates);
  `SalaryGradeStep` belongs to a `SalarySchedule`.
- **Payroll:** `PayrollPeriod` has many `PayrollRun`; `PayrollRun` has many
  `PayrollDetail`; each `PayrollDetail` has many earnings, deductions, employer
  contributions, and adjustments, plus one `Payslip`. `PayrollRun` has many
  `PayrollApproval` and one `BlockchainAnchor`.

### Cascade behavior

Deleting a **draft** payroll run removes its details and, transitively, the
detail-level rows (earnings, deductions, employer contributions, adjustments,
payslips) and its approvals/anchor. Finalized payroll must not be deleted; the
application enforces that separately.

---

## 4. Intentional differences from `database-schema.md`

| # | Difference | Reason |
|---|---|---|
| 1 | `users.password_hash` is retained, and the `User` model overrides `getAuthPassword()` and `getAuthPasswordName()` to map to it. | Laravel's authentication expects a hashed-password column; mapping keeps the documented column name while letting the framework work. |
| 2 | `users.remember_token` added. | Required for Laravel's "remember me" login option used by the existing auth controller. |
| 3 | `payroll_details.nthp_before_lbp` and `payroll_details.lbp_loan_deduction` added. | Required by `payroll-computation-design.md` §12.1 to preserve the client register totals (NTHP Before LBP and the separate LBP line). |
| 4 | The `users` table is created in `2026_01_01_000006` rather than the default `0001_01_01_000000` migration. | `users.employee_id` references `employees.employee_id`, so `employees` must be created first; the default migration now creates only `sessions` and `password_reset_tokens`. |
| 5 | The `salary_grade_steps` composite unique index is named `salary_grade_steps_grade_step_unique`. | The auto-generated name exceeds MySQL's 64-character identifier limit. |
| 6 | Added lookup indexes: `positions.salary_grade`, `compensation_profiles (employment_id, effective_from)`, `attendance_records.attendance_date`, `audit_logs (record_table, record_id)` and `(module, action)`. | Support the access patterns described in `database-schema.md` §7. |

No documented table or column was removed.

---

## 5. Seeders

`php artisan db:seed` runs `DatabaseSeeder`, which calls, in order:

| Seeder | Purpose |
|---|---|
| `RolePermissionSeeder` | 4 roles and the 17 documented permissions, with the role→permission map. |
| `EmploymentTypeSeeder` | Permanent, Coterminous, Part-time. |
| `LeaveTypeSeeder` | Common leave types (credit values are placeholders). |
| `EarningTypeSeeder` | The manually entered allowances (PERA, hazard, clothing, subsistence, laundry, medical). |
| `DeductionTypeSeeder` | Statutory contributions/tax plus the register's loan, GABE, PLI/dues, and LBP deduction labels. |
| `StatutoryConfigSeeder` | **Illustrative** tax brackets and GSIS/PhilHealth/Pag-IBIG rates. |
| `UserAccountSeeder` | One demo account per role. |
| `DemoDataSeeder` | **Illustrative** departments, positions, 8 employees, salary schedule, and compensation profiles. |

### Demonstration accounts (development only)

| Username | Role | Password |
|---|---|---|
| `admin` | Admin | `password` |
| `officer` | Payroll Officer | `password` |
| `approver` | Approver | `password` |
| `viewer` | Viewer | `password` |

> The Admin role deliberately does **not** hold `payroll.approve` / `payroll.reject`,
> reinforcing the separation-of-duties rule.

> Salary amounts, tax brackets, and contribution rates seeded here are placeholders
> for the mock defense. Replace them with the LGU's official, current figures before
> production use.

---

## 6. Commands

```bash
# Rebuild the schema and load demo data
php artisan migrate:fresh --seed

# Run the test suite (uses SQLite :memory:)
php artisan test --compact

# Format code
vendor/bin/pint app database tests --format agent
```

### Note on a stale seeder

`database/seeders/AdminUserSeeder.php` predates this work and references the old
`users` schema (`name`, `password`). It is superseded by `UserAccountSeeder` and is
**not** called by `DatabaseSeeder`. It is left in place pending a decision to remove it.
