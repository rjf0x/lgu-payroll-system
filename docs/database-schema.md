# LGU Payroll Management System - Database Schema

Verified on November 11, 2026 against the connected MySQL database and the applied Laravel migrations.

**Database:** MySQL. **Application tables:** 36. **Framework tables:** 8. **Total physical tables:** 44.

This document describes the implemented schema. [The ERD](erd.md) illustrates the same tables and foreign-key relationships. The table count includes framework infrastructure separately from payroll business records.

## Current design and changes

- Employment types are **Permanent**, **Coterminous** and **Elective**. The Part-time row was renamed in place, preserving its ID and existing employment relationships.
- Separate attendance and leave tables were removed by the manual-input migration. Payroll uses `payable_days`, `input_reference`, `input_recorded_by`, `input_confirmed_at` and `manual_adjustments` on `payroll_details`.
- Positions optionally hold a salary grade. Compensation profiles reference a salary-grade/step row or store an approved direct rate. Grade, step, rate and pay basis are copied into payroll snapshots.
- Versioned Payroll Verification uses `payroll_registrations` and `payroll_registration_verifications`. Legacy anchors and their logs remain separate for existing proofs.
- Deduction reports read `payroll_deductions` joined through `payroll_details` to the selected payroll run. They require no additional report table.
- System display and organization settings are stored in `system_settings`.

## Table inventory

| Area | Tables |
|---|---|
| Access, organization and employment | `users`, `roles`, `permissions`, `user_roles`, `role_permissions`, `employees`, `departments`, `positions`, `employment_types`, `employment_records`, `employment_history` |
| Compensation, earnings, deductions and statutory configuration | `salary_schedules`, `salary_grade_steps`, `compensation_profiles`, `earning_types`, `employee_earning_assignments`, `deduction_types`, `employee_deduction_assignments`, `tax_brackets`, `contribution_rates` |
| Payroll processing and outputs | `payroll_periods`, `payroll_runs`, `payroll_approvals`, `payroll_details`, `payroll_earnings`, `payroll_deductions`, `payroll_employer_contributions`, `payroll_adjustments`, `payslips`, `generated_reports` |
| Payroll Verification, auditing and settings | `payroll_registrations`, `payroll_registration_verifications`, `blockchain_anchors`, `blockchain_verification_logs`, `audit_logs`, `system_settings` |
| Laravel framework infrastructure | `sessions`, `password_reset_tokens`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations` |

## Column and constraint reference

Types, nullability, defaults, indexes and referential actions below are taken from the inspected schema. `NULL` means nullable; a dash in the default column means no non-null default was reported. Timestamp columns follow the actual database definition, including nullable Laravel timestamps. Composite uniqueness is documented in each table's index list, not as independent uniqueness of each member column.

## Access, organization and employment

### `users`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `user_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `employee_id` | `bigint unsigned` | Yes | — | UNIQUE; FK → `employees.employee_id` |
| `username` | `varchar(100)` | No | — | UNIQUE |
| `email` | `varchar(150)` | Yes | — | — |
| `password_hash` | `varchar(255)` | No | — | — |
| `account_status` | `varchar(20)` | No | `ACTIVE` | — |
| `last_login_at` | `datetime` | Yes | — | — |
| `remember_token` | `varchar(100)` | Yes | — | — |
| `created_at` | `timestamp` | Yes | — | — |
| `updated_at` | `timestamp` | Yes | — | — |

**Indexes:**

- `primary`: PRIMARY KEY (`user_id`).
- `users_employee_id_unique`: UNIQUE (`employee_id`).
- `users_username_unique`: UNIQUE (`username`).

**Foreign-key actions:**

- `employee_id` → `employees(employee_id)`: ON DELETE SET NULL; ON UPDATE NO ACTION.

### `roles`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `role_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `role_name` | `varchar(100)` | No | — | UNIQUE |
| `description` | `text` | Yes | — | — |
| `created_at` | `timestamp` | Yes | — | — |
| `updated_at` | `timestamp` | Yes | — | — |

**Indexes:**

- `primary`: PRIMARY KEY (`role_id`).
- `roles_role_name_unique`: UNIQUE (`role_name`).

### `permissions`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `permission_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `permission_name` | `varchar(100)` | No | — | UNIQUE |
| `description` | `text` | Yes | — | — |
| `created_at` | `timestamp` | Yes | — | — |
| `updated_at` | `timestamp` | Yes | — | — |

**Indexes:**

- `permissions_permission_name_unique`: UNIQUE (`permission_name`).
- `primary`: PRIMARY KEY (`permission_id`).

### `user_roles`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `user_role_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `user_id` | `bigint unsigned` | No | — | FK → `users.user_id` |
| `role_id` | `bigint unsigned` | No | — | FK → `roles.role_id` |
| `assigned_by` | `bigint unsigned` | Yes | — | FK → `users.user_id` |
| `assigned_at` | `datetime` | No | — | — |

**Indexes:**

- `primary`: PRIMARY KEY (`user_role_id`).
- `user_roles_assigned_by_foreign`: INDEX (`assigned_by`).
- `user_roles_role_id_foreign`: INDEX (`role_id`).
- `user_roles_user_id_role_id_unique`: UNIQUE (`user_id`, `role_id`).

**Foreign-key actions:**

- `assigned_by` → `users(user_id)`: ON DELETE SET NULL; ON UPDATE NO ACTION.
- `role_id` → `roles(role_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.
- `user_id` → `users(user_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.

### `role_permissions`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `role_permission_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `role_id` | `bigint unsigned` | No | — | FK → `roles.role_id` |
| `permission_id` | `bigint unsigned` | No | — | FK → `permissions.permission_id` |
| `created_at` | `timestamp` | No | `CURRENT_TIMESTAMP` | — |

**Indexes:**

- `primary`: PRIMARY KEY (`role_permission_id`).
- `role_permissions_permission_id_foreign`: INDEX (`permission_id`).
- `role_permissions_role_id_permission_id_unique`: UNIQUE (`role_id`, `permission_id`).

**Foreign-key actions:**

- `permission_id` → `permissions(permission_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.
- `role_id` → `roles(role_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.

### `employees`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `employee_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `employee_number` | `varchar(50)` | No | — | UNIQUE |
| `first_name` | `varchar(100)` | No | — | — |
| `middle_name` | `varchar(100)` | Yes | — | — |
| `last_name` | `varchar(100)` | No | — | — |
| `suffix` | `varchar(20)` | Yes | — | — |
| `birth_date` | `date` | No | — | — |
| `sex` | `varchar(20)` | No | — | — |
| `civil_status` | `varchar(30)` | Yes | — | — |
| `email` | `varchar(150)` | Yes | — | — |
| `contact_number` | `varchar(30)` | Yes | — | — |
| `address` | `text` | No | — | — |
| `created_at` | `timestamp` | Yes | — | — |
| `updated_at` | `timestamp` | Yes | — | — |
| `deleted_at` | `timestamp` | Yes | — | — |

**Indexes:**

- `employees_employee_number_unique`: UNIQUE (`employee_number`).
- `primary`: PRIMARY KEY (`employee_id`).

### `departments`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `department_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `department_code` | `varchar(50)` | No | — | UNIQUE |
| `department_name` | `varchar(150)` | No | — | — |
| `description` | `text` | Yes | — | — |
| `status` | `varchar(20)` | No | — | — |
| `created_at` | `timestamp` | Yes | — | — |
| `updated_at` | `timestamp` | Yes | — | — |

**Indexes:**

- `departments_department_code_unique`: UNIQUE (`department_code`).
- `primary`: PRIMARY KEY (`department_id`).

### `positions`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `position_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `department_id` | `bigint unsigned` | No | — | FK → `departments.department_id` |
| `position_code` | `varchar(50)` | No | — | UNIQUE |
| `position_title` | `varchar(150)` | No | — | — |
| `salary_grade` | `int unsigned` | Yes | — | — |
| `description` | `text` | Yes | — | — |
| `status` | `varchar(20)` | No | — | — |
| `created_at` | `timestamp` | Yes | — | — |
| `updated_at` | `timestamp` | Yes | — | — |

**Indexes:**

- `positions_department_id_foreign`: INDEX (`department_id`).
- `positions_position_code_unique`: UNIQUE (`position_code`).
- `positions_salary_grade_index`: INDEX (`salary_grade`).
- `primary`: PRIMARY KEY (`position_id`).

**Foreign-key actions:**

- `department_id` → `departments(department_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.

### `employment_types`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `employment_type_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `employment_type_name` | `varchar(100)` | No | — | UNIQUE |
| `description` | `text` | Yes | — | — |
| `status` | `varchar(20)` | No | — | — |

**Indexes:**

- `employment_types_employment_type_name_unique`: UNIQUE (`employment_type_name`).
- `primary`: PRIMARY KEY (`employment_type_id`).

### `employment_records`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `employment_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `employee_id` | `bigint unsigned` | No | — | FK → `employees.employee_id` |
| `department_id` | `bigint unsigned` | No | — | FK → `departments.department_id` |
| `position_id` | `bigint unsigned` | No | — | FK → `positions.position_id` |
| `employment_type_id` | `bigint unsigned` | No | — | FK → `employment_types.employment_type_id` |
| `appointment_number` | `varchar(100)` | Yes | — | — |
| `start_date` | `date` | No | — | — |
| `end_date` | `date` | Yes | — | — |
| `employment_status` | `varchar(30)` | No | — | — |
| `created_at` | `timestamp` | Yes | — | — |
| `updated_at` | `timestamp` | Yes | — | — |

**Indexes:**

- `employment_records_department_id_foreign`: INDEX (`department_id`).
- `employment_records_employee_id_foreign`: INDEX (`employee_id`).
- `employment_records_employment_type_id_foreign`: INDEX (`employment_type_id`).
- `employment_records_position_id_foreign`: INDEX (`position_id`).
- `primary`: PRIMARY KEY (`employment_id`).

**Foreign-key actions:**

- `department_id` → `departments(department_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.
- `employee_id` → `employees(employee_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.
- `employment_type_id` → `employment_types(employment_type_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.
- `position_id` → `positions(position_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.

### `employment_history`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `history_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `employment_id` | `bigint unsigned` | No | — | FK → `employment_records.employment_id` |
| `from_department_id` | `bigint unsigned` | Yes | — | FK → `departments.department_id` |
| `to_department_id` | `bigint unsigned` | Yes | — | FK → `departments.department_id` |
| `from_position_id` | `bigint unsigned` | Yes | — | FK → `positions.position_id` |
| `to_position_id` | `bigint unsigned` | Yes | — | FK → `positions.position_id` |
| `change_type` | `varchar(50)` | No | — | — |
| `effective_date` | `date` | No | — | — |
| `remarks` | `text` | Yes | — | — |
| `recorded_by` | `bigint unsigned` | No | — | FK → `users.user_id` |
| `created_at` | `timestamp` | No | `CURRENT_TIMESTAMP` | — |

**Indexes:**

- `employment_history_employment_id_foreign`: INDEX (`employment_id`).
- `employment_history_from_department_id_foreign`: INDEX (`from_department_id`).
- `employment_history_from_position_id_foreign`: INDEX (`from_position_id`).
- `employment_history_recorded_by_foreign`: INDEX (`recorded_by`).
- `employment_history_to_department_id_foreign`: INDEX (`to_department_id`).
- `employment_history_to_position_id_foreign`: INDEX (`to_position_id`).
- `primary`: PRIMARY KEY (`history_id`).

**Foreign-key actions:**

- `employment_id` → `employment_records(employment_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.
- `from_department_id` → `departments(department_id)`: ON DELETE SET NULL; ON UPDATE NO ACTION.
- `from_position_id` → `positions(position_id)`: ON DELETE SET NULL; ON UPDATE NO ACTION.
- `recorded_by` → `users(user_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.
- `to_department_id` → `departments(department_id)`: ON DELETE SET NULL; ON UPDATE NO ACTION.
- `to_position_id` → `positions(position_id)`: ON DELETE SET NULL; ON UPDATE NO ACTION.

## Compensation, earnings, deductions and statutory configuration

### `salary_schedules`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `salary_schedule_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `schedule_name` | `varchar(150)` | No | — | — |
| `effective_from` | `date` | No | — | — |
| `effective_to` | `date` | Yes | — | — |
| `reference_document` | `varchar(255)` | Yes | — | — |
| `status` | `varchar(20)` | No | — | — |
| `created_at` | `timestamp` | No | `CURRENT_TIMESTAMP` | — |

**Indexes:**

- `primary`: PRIMARY KEY (`salary_schedule_id`).

### `salary_grade_steps`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `grade_step_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `salary_schedule_id` | `bigint unsigned` | No | — | FK → `salary_schedules.salary_schedule_id` |
| `grade_number` | `int unsigned` | No | — | — |
| `step_number` | `int unsigned` | No | — | — |
| `monthly_salary` | `decimal(15,2)` | No | — | — |
| `created_at` | `timestamp` | No | `CURRENT_TIMESTAMP` | — |

**Indexes:**

- `primary`: PRIMARY KEY (`grade_step_id`).
- `salary_grade_steps_grade_step_unique`: UNIQUE (`salary_schedule_id`, `grade_number`, `step_number`).

**Foreign-key actions:**

- `salary_schedule_id` → `salary_schedules(salary_schedule_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.

### `compensation_profiles`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `compensation_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `employment_id` | `bigint unsigned` | No | — | FK → `employment_records.employment_id` |
| `grade_step_id` | `bigint unsigned` | Yes | — | FK → `salary_grade_steps.grade_step_id` |
| `pay_basis` | `varchar(30)` | No | — | — |
| `basic_rate` | `decimal(15,2)` | Yes | — | — |
| `service_fraction` | `decimal(5,4)` | No | `1.0000` | — |
| `effective_from` | `date` | No | — | — |
| `effective_to` | `date` | Yes | — | — |
| `created_at` | `timestamp` | Yes | — | — |
| `updated_at` | `timestamp` | Yes | — | — |

**Indexes:**

- `compensation_profiles_employment_id_effective_from_index`: INDEX (`employment_id`, `effective_from`).
- `compensation_profiles_grade_step_id_foreign`: INDEX (`grade_step_id`).
- `primary`: PRIMARY KEY (`compensation_id`).

**Foreign-key actions:**

- `employment_id` → `employment_records(employment_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.
- `grade_step_id` → `salary_grade_steps(grade_step_id)`: ON DELETE SET NULL; ON UPDATE NO ACTION.

### `earning_types`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `earning_type_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `earning_name` | `varchar(150)` | No | — | — |
| `earning_category` | `varchar(100)` | No | — | — |
| `computation_type` | `varchar(50)` | No | — | — |
| `is_taxable` | `tinyint(1)` | No | — | — |
| `is_recurring` | `tinyint(1)` | No | — | — |
| `status` | `varchar(20)` | No | — | — |
| `created_at` | `timestamp` | No | `CURRENT_TIMESTAMP` | — |

**Indexes:**

- `primary`: PRIMARY KEY (`earning_type_id`).

### `employee_earning_assignments`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `assignment_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `employment_id` | `bigint unsigned` | No | — | FK → `employment_records.employment_id` |
| `earning_type_id` | `bigint unsigned` | No | — | FK → `earning_types.earning_type_id` |
| `amount` | `decimal(15,2)` | Yes | — | — |
| `percentage` | `decimal(8,4)` | Yes | — | — |
| `frequency` | `varchar(30)` | No | — | — |
| `effective_from` | `date` | No | — | — |
| `effective_to` | `date` | Yes | — | — |
| `created_at` | `timestamp` | No | `CURRENT_TIMESTAMP` | — |

**Indexes:**

- `employee_earning_assignments_earning_type_id_foreign`: INDEX (`earning_type_id`).
- `employee_earning_assignments_employment_id_foreign`: INDEX (`employment_id`).
- `primary`: PRIMARY KEY (`assignment_id`).

**Foreign-key actions:**

- `earning_type_id` → `earning_types(earning_type_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.
- `employment_id` → `employment_records(employment_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.

### `deduction_types`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `deduction_type_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `deduction_name` | `varchar(150)` | No | — | — |
| `deduction_category` | `varchar(100)` | No | — | — |
| `computation_type` | `varchar(50)` | No | — | — |
| `is_statutory` | `tinyint(1)` | No | — | — |
| `is_pre_tax` | `tinyint(1)` | No | — | — |
| `status` | `varchar(20)` | No | — | — |
| `created_at` | `timestamp` | No | `CURRENT_TIMESTAMP` | — |

**Indexes:**

- `primary`: PRIMARY KEY (`deduction_type_id`).

### `employee_deduction_assignments`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `assignment_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `employment_id` | `bigint unsigned` | No | — | FK → `employment_records.employment_id` |
| `deduction_type_id` | `bigint unsigned` | No | — | FK → `deduction_types.deduction_type_id` |
| `amount` | `decimal(15,2)` | Yes | — | — |
| `percentage` | `decimal(8,4)` | Yes | — | — |
| `reference_number` | `varchar(100)` | Yes | — | — |
| `effective_from` | `date` | No | — | — |
| `effective_to` | `date` | Yes | — | — |
| `status` | `varchar(20)` | No | — | — |
| `created_at` | `timestamp` | No | `CURRENT_TIMESTAMP` | — |

**Indexes:**

- `employee_deduction_assignments_deduction_type_id_foreign`: INDEX (`deduction_type_id`).
- `employee_deduction_assignments_employment_id_foreign`: INDEX (`employment_id`).
- `primary`: PRIMARY KEY (`assignment_id`).

**Foreign-key actions:**

- `deduction_type_id` → `deduction_types(deduction_type_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.
- `employment_id` → `employment_records(employment_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.

### `tax_brackets`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `tax_bracket_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `tax_type` | `varchar(50)` | No | — | — |
| `pay_frequency` | `varchar(30)` | No | — | — |
| `minimum_income` | `decimal(15,2)` | No | — | — |
| `maximum_income` | `decimal(15,2)` | Yes | — | — |
| `base_tax` | `decimal(15,2)` | No | — | — |
| `marginal_tax_rate` | `decimal(8,4)` | No | — | — |
| `effective_from` | `date` | No | — | — |
| `effective_to` | `date` | Yes | — | — |
| `created_at` | `timestamp` | No | `CURRENT_TIMESTAMP` | — |

**Indexes:**

- `primary`: PRIMARY KEY (`tax_bracket_id`).

### `contribution_rates`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `contribution_rate_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `contribution_type` | `varchar(100)` | No | — | — |
| `minimum_compensation` | `decimal(15,2)` | No | — | — |
| `maximum_compensation` | `decimal(15,2)` | Yes | — | — |
| `employee_rate` | `decimal(8,4)` | No | — | — |
| `employer_rate` | `decimal(8,4)` | No | — | — |
| `employee_fixed_amount` | `decimal(15,2)` | Yes | — | — |
| `employer_fixed_amount` | `decimal(15,2)` | Yes | — | — |
| `effective_from` | `date` | No | — | — |
| `effective_to` | `date` | Yes | — | — |
| `created_at` | `timestamp` | No | `CURRENT_TIMESTAMP` | — |

**Indexes:**

- `primary`: PRIMARY KEY (`contribution_rate_id`).

## Payroll processing and outputs

### `payroll_periods`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `payroll_period_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `period_name` | `varchar(100)` | No | — | — |
| `payroll_type` | `varchar(30)` | No | — | — |
| `start_date` | `date` | No | — | — |
| `end_date` | `date` | No | — | — |
| `pay_date` | `date` | No | — | — |
| `status` | `varchar(30)` | No | — | — |
| `created_at` | `timestamp` | Yes | — | — |
| `updated_at` | `timestamp` | Yes | — | — |

**Indexes:**

- `payroll_periods_coverage_unique`: UNIQUE (`payroll_type`, `start_date`, `end_date`).
- `primary`: PRIMARY KEY (`payroll_period_id`).

### `payroll_runs`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `payroll_run_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `payroll_period_id` | `bigint unsigned` | No | — | FK → `payroll_periods.payroll_period_id` |
| `run_number` | `int unsigned` | No | — | — |
| `run_type` | `varchar(30)` | No | — | — |
| `status` | `varchar(30)` | No | — | — |
| `created_by` | `bigint unsigned` | No | — | FK → `users.user_id` |
| `processed_at` | `datetime` | Yes | — | — |
| `submitted_by` | `bigint unsigned` | Yes | — | FK → `users.user_id` |
| `submitted_at` | `datetime` | Yes | — | — |
| `finalized_by` | `bigint unsigned` | Yes | — | FK → `users.user_id` |
| `finalized_at` | `datetime` | Yes | — | — |
| `created_at` | `timestamp` | Yes | — | — |
| `updated_at` | `timestamp` | Yes | — | — |

**Indexes:**

- `payroll_runs_created_by_foreign`: INDEX (`created_by`).
- `payroll_runs_finalized_by_foreign`: INDEX (`finalized_by`).
- `payroll_runs_payroll_period_id_run_number_unique`: UNIQUE (`payroll_period_id`, `run_number`).
- `payroll_runs_submitted_by_foreign`: INDEX (`submitted_by`).
- `primary`: PRIMARY KEY (`payroll_run_id`).

**Foreign-key actions:**

- `created_by` → `users(user_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.
- `finalized_by` → `users(user_id)`: ON DELETE SET NULL; ON UPDATE NO ACTION.
- `payroll_period_id` → `payroll_periods(payroll_period_id)`: ON DELETE RESTRICT; ON UPDATE NO ACTION.
- `submitted_by` → `users(user_id)`: ON DELETE SET NULL; ON UPDATE NO ACTION.

### `payroll_approvals`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `approval_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `payroll_run_id` | `bigint unsigned` | No | — | FK → `payroll_runs.payroll_run_id` |
| `approval_step` | `varchar(50)` | No | — | — |
| `action` | `varchar(30)` | No | — | — |
| `acted_by` | `bigint unsigned` | No | — | FK → `users.user_id` |
| `remarks` | `text` | Yes | — | — |
| `acted_at` | `datetime` | No | — | — |

**Indexes:**

- `payroll_approvals_acted_by_foreign`: INDEX (`acted_by`).
- `payroll_approvals_payroll_run_id_foreign`: INDEX (`payroll_run_id`).
- `primary`: PRIMARY KEY (`approval_id`).

**Foreign-key actions:**

- `acted_by` → `users(user_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.
- `payroll_run_id` → `payroll_runs(payroll_run_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.

### `payroll_details`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `payroll_detail_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `payroll_run_id` | `bigint unsigned` | No | — | FK → `payroll_runs.payroll_run_id` |
| `employee_id` | `bigint unsigned` | No | — | FK → `employees.employee_id` |
| `employment_id` | `bigint unsigned` | No | — | FK → `employment_records.employment_id` |
| `employee_number_snapshot` | `varchar(50)` | No | — | — |
| `employee_name_snapshot` | `varchar(255)` | No | — | — |
| `department_snapshot` | `varchar(150)` | Yes | — | — |
| `position_snapshot` | `varchar(150)` | Yes | — | — |
| `salary_grade_snapshot` | `int unsigned` | Yes | — | — |
| `salary_step_snapshot` | `int unsigned` | Yes | — | — |
| `salary_rate_snapshot` | `decimal(15,2)` | Yes | — | — |
| `pay_basis_snapshot` | `varchar(30)` | Yes | — | — |
| `basic_pay` | `decimal(15,2)` | No | — | — |
| `total_earnings` | `decimal(15,2)` | No | — | — |
| `gross_pay` | `decimal(15,2)` | No | — | — |
| `total_deductions` | `decimal(15,2)` | No | — | — |
| `nthp_before_lbp` | `decimal(15,2)` | No | — | — |
| `lbp_loan_deduction` | `decimal(15,2)` | No | `0.00` | — |
| `net_pay` | `decimal(15,2)` | No | — | — |
| `payroll_status` | `varchar(30)` | No | — | — |
| `created_at` | `timestamp` | Yes | — | — |
| `updated_at` | `timestamp` | Yes | — | — |
| `payable_days` | `decimal(8,2)` | Yes | — | — |
| `input_reference` | `varchar(255)` | Yes | — | — |
| `input_recorded_by` | `bigint unsigned` | Yes | — | FK → `users.user_id` |
| `input_confirmed_at` | `timestamp` | Yes | — | — |
| `manual_adjustments` | `tinyint(1)` | No | `0` | — |

**Indexes:**

- `payroll_details_employee_id_foreign`: INDEX (`employee_id`).
- `payroll_details_employment_id_foreign`: INDEX (`employment_id`).
- `payroll_details_input_recorded_by_foreign`: INDEX (`input_recorded_by`).
- `payroll_details_payroll_run_id_employment_id_unique`: UNIQUE (`payroll_run_id`, `employment_id`).
- `primary`: PRIMARY KEY (`payroll_detail_id`).

**Foreign-key actions:**

- `employee_id` → `employees(employee_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.
- `employment_id` → `employment_records(employment_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.
- `input_recorded_by` → `users(user_id)`: ON DELETE SET NULL; ON UPDATE NO ACTION.
- `payroll_run_id` → `payroll_runs(payroll_run_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.

### `payroll_earnings`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `payroll_earning_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `payroll_detail_id` | `bigint unsigned` | No | — | FK → `payroll_details.payroll_detail_id` |
| `earning_type_id` | `bigint unsigned` | No | — | FK → `earning_types.earning_type_id` |
| `earning_name_snapshot` | `varchar(150)` | No | — | — |
| `quantity` | `decimal(10,2)` | Yes | — | — |
| `rate` | `decimal(15,2)` | Yes | — | — |
| `amount` | `decimal(15,2)` | No | — | — |
| `is_taxable` | `tinyint(1)` | No | — | — |
| `remarks` | `text` | Yes | — | — |
| `created_at` | `timestamp` | No | `CURRENT_TIMESTAMP` | — |

**Indexes:**

- `payroll_earnings_earning_type_id_foreign`: INDEX (`earning_type_id`).
- `payroll_earnings_payroll_detail_id_foreign`: INDEX (`payroll_detail_id`).
- `primary`: PRIMARY KEY (`payroll_earning_id`).

**Foreign-key actions:**

- `earning_type_id` → `earning_types(earning_type_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.
- `payroll_detail_id` → `payroll_details(payroll_detail_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.

### `payroll_deductions`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `payroll_deduction_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `payroll_detail_id` | `bigint unsigned` | No | — | FK → `payroll_details.payroll_detail_id` |
| `deduction_type_id` | `bigint unsigned` | No | — | FK → `deduction_types.deduction_type_id` |
| `deduction_name_snapshot` | `varchar(150)` | No | — | — |
| `amount` | `decimal(15,2)` | No | — | — |
| `remarks` | `text` | Yes | — | — |
| `created_at` | `timestamp` | No | `CURRENT_TIMESTAMP` | — |

**Indexes:**

- `payroll_deductions_deduction_type_id_foreign`: INDEX (`deduction_type_id`).
- `payroll_deductions_payroll_detail_id_foreign`: INDEX (`payroll_detail_id`).
- `primary`: PRIMARY KEY (`payroll_deduction_id`).

**Foreign-key actions:**

- `deduction_type_id` → `deduction_types(deduction_type_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.
- `payroll_detail_id` → `payroll_details(payroll_detail_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.

### `payroll_employer_contributions`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `employer_contribution_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `payroll_detail_id` | `bigint unsigned` | No | — | FK → `payroll_details.payroll_detail_id` |
| `contribution_rate_id` | `bigint unsigned` | No | — | FK → `contribution_rates.contribution_rate_id` |
| `contribution_type_snapshot` | `varchar(100)` | No | — | — |
| `amount` | `decimal(15,2)` | No | — | — |
| `created_at` | `timestamp` | No | `CURRENT_TIMESTAMP` | — |

**Indexes:**

- `payroll_employer_contributions_contribution_rate_id_foreign`: INDEX (`contribution_rate_id`).
- `payroll_employer_contributions_payroll_detail_id_foreign`: INDEX (`payroll_detail_id`).
- `primary`: PRIMARY KEY (`employer_contribution_id`).

**Foreign-key actions:**

- `contribution_rate_id` → `contribution_rates(contribution_rate_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.
- `payroll_detail_id` → `payroll_details(payroll_detail_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.

### `payroll_adjustments`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `adjustment_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `payroll_detail_id` | `bigint unsigned` | No | — | FK → `payroll_details.payroll_detail_id` |
| `adjustment_type` | `varchar(30)` | No | — | — |
| `amount` | `decimal(15,2)` | No | — | — |
| `reason` | `text` | No | — | — |
| `requested_by` | `bigint unsigned` | No | — | FK → `users.user_id` |
| `approved_by` | `bigint unsigned` | Yes | — | FK → `users.user_id` |
| `approval_status` | `varchar(30)` | No | — | — |
| `created_at` | `timestamp` | Yes | — | — |
| `updated_at` | `timestamp` | Yes | — | — |

**Indexes:**

- `payroll_adjustments_approved_by_foreign`: INDEX (`approved_by`).
- `payroll_adjustments_payroll_detail_id_foreign`: INDEX (`payroll_detail_id`).
- `payroll_adjustments_requested_by_foreign`: INDEX (`requested_by`).
- `primary`: PRIMARY KEY (`adjustment_id`).

**Foreign-key actions:**

- `approved_by` → `users(user_id)`: ON DELETE SET NULL; ON UPDATE NO ACTION.
- `payroll_detail_id` → `payroll_details(payroll_detail_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.
- `requested_by` → `users(user_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.

### `payslips`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `payslip_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `payroll_detail_id` | `bigint unsigned` | No | — | UNIQUE; FK → `payroll_details.payroll_detail_id` |
| `payslip_number` | `varchar(100)` | No | — | UNIQUE |
| `file_path` | `varchar(500)` | Yes | — | — |
| `generated_at` | `datetime` | No | — | — |
| `published_at` | `datetime` | Yes | — | — |
| `publication_status` | `varchar(30)` | No | — | — |

**Indexes:**

- `payslips_payroll_detail_id_unique`: UNIQUE (`payroll_detail_id`).
- `payslips_payslip_number_unique`: UNIQUE (`payslip_number`).
- `primary`: PRIMARY KEY (`payslip_id`).

**Foreign-key actions:**

- `payroll_detail_id` → `payroll_details(payroll_detail_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.

### `generated_reports`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `report_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `report_type` | `varchar(100)` | No | — | — |
| `generated_by` | `bigint unsigned` | No | — | FK → `users.user_id` |
| `filters` | `json` | Yes | — | — |
| `file_path` | `varchar(500)` | Yes | — | — |
| `generated_at` | `datetime` | No | — | — |

**Indexes:**

- `generated_reports_generated_by_foreign`: INDEX (`generated_by`).
- `primary`: PRIMARY KEY (`report_id`).

**Foreign-key actions:**

- `generated_by` → `users(user_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.

## Payroll Verification, auditing and settings

### `payroll_registrations`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `registration_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `payroll_run_id` | `bigint unsigned` | No | — | UNIQUE; FK → `payroll_runs.payroll_run_id` |
| `organization_id` | `varchar(63)` | No | — | — |
| `payroll_reference` | `char(36)` | No | — | — |
| `revision` | `int unsigned` | No | `1` | — |
| `hash_schema_version` | `int unsigned` | No | — | — |
| `payload_hash` | `char(64)` | No | — | — |
| `idempotency_key` | `char(64)` | No | — | UNIQUE |
| `canonical_payload` | `longtext` | No | — | — |
| `period_snapshot` | `json` | No | — | — |
| `approval_snapshot` | `json` | No | — | — |
| `target_network` | `json` | No | — | — |
| `status` | `varchar(30)` | No | `QUEUED` | — |
| `transaction_hash` | `varchar(66)` | Yes | — | — |
| `evidence` | `json` | Yes | — | — |
| `attempts` | `int unsigned` | No | `0` | — |
| `last_error` | `varchar(100)` | Yes | — | — |
| `next_attempt_at` | `timestamp` | Yes | — | — |
| `lease_token` | `char(36)` | Yes | — | — |
| `created_at` | `timestamp` | Yes | — | — |
| `updated_at` | `timestamp` | Yes | — | — |

**Indexes:**

- `payroll_registration_identity_unique`: UNIQUE (`organization_id`, `payroll_reference`, `revision`).
- `payroll_registrations_idempotency_key_unique`: UNIQUE (`idempotency_key`).
- `payroll_registrations_payroll_run_id_unique`: UNIQUE (`payroll_run_id`).
- `payroll_registrations_status_next_attempt_at_index`: INDEX (`status`, `next_attempt_at`).
- `primary`: PRIMARY KEY (`registration_id`).

**Foreign-key actions:**

- `payroll_run_id` → `payroll_runs(payroll_run_id)`: ON DELETE RESTRICT; ON UPDATE NO ACTION.

### `payroll_registration_verifications`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `verification_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `registration_id` | `bigint unsigned` | No | — | FK → `payroll_registrations.registration_id` |
| `verified_by` | `bigint unsigned` | Yes | — | FK → `users.user_id` |
| `stored_hash` | `char(64)` | Yes | — | — |
| `calculated_hash` | `char(64)` | Yes | — | — |
| `captured_hash` | `char(64)` | No | — | — |
| `verification_result` | `varchar(30)` | No | — | — |
| `remarks` | `varchar(255)` | No | — | — |
| `evidence` | `json` | Yes | — | — |
| `verified_at` | `timestamp` | No | — | — |

**Indexes:**

- `payroll_registration_verifications_verified_by_foreign`: INDEX (`verified_by`).
- `payroll_registry_verification_time_index`: INDEX (`registration_id`, `verified_at`).
- `primary`: PRIMARY KEY (`verification_id`).

**Foreign-key actions:**

- `registration_id` → `payroll_registrations(registration_id)`: ON DELETE RESTRICT; ON UPDATE NO ACTION.
- `verified_by` → `users(user_id)`: ON DELETE SET NULL; ON UPDATE NO ACTION.

### `blockchain_anchors`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `anchor_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `payroll_run_id` | `bigint unsigned` | No | — | UNIQUE; FK → `payroll_runs.payroll_run_id` |
| `payload_hash` | `char(64)` | No | — | — |
| `hash_algorithm` | `varchar(30)` | No | — | — |
| `transaction_id` | `varchar(255)` | Yes | — | — |
| `block_number` | `bigint unsigned` | Yes | — | — |
| `network_name` | `varchar(100)` | No | — | — |
| `contract_address` | `varchar(255)` | Yes | — | — |
| `anchor_status` | `varchar(30)` | No | — | — |
| `anchored_by` | `bigint unsigned` | Yes | — | FK → `users.user_id` |
| `anchored_at` | `datetime` | Yes | — | — |
| `created_at` | `timestamp` | No | `CURRENT_TIMESTAMP` | — |

**Indexes:**

- `blockchain_anchors_anchored_by_foreign`: INDEX (`anchored_by`).
- `blockchain_anchors_payroll_run_id_unique`: UNIQUE (`payroll_run_id`).
- `primary`: PRIMARY KEY (`anchor_id`).

**Foreign-key actions:**

- `anchored_by` → `users(user_id)`: ON DELETE SET NULL; ON UPDATE NO ACTION.
- `payroll_run_id` → `payroll_runs(payroll_run_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.

### `blockchain_verification_logs`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `verification_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `anchor_id` | `bigint unsigned` | No | — | FK → `blockchain_anchors.anchor_id` |
| `verified_by` | `bigint unsigned` | No | — | FK → `users.user_id` |
| `stored_hash` | `char(64)` | No | — | — |
| `calculated_hash` | `char(64)` | No | — | — |
| `verification_result` | `varchar(30)` | No | — | — |
| `remarks` | `text` | Yes | — | — |
| `verified_at` | `datetime` | No | — | — |

**Indexes:**

- `blockchain_verification_logs_anchor_id_foreign`: INDEX (`anchor_id`).
- `blockchain_verification_logs_verified_by_foreign`: INDEX (`verified_by`).
- `primary`: PRIMARY KEY (`verification_id`).

**Foreign-key actions:**

- `anchor_id` → `blockchain_anchors(anchor_id)`: ON DELETE CASCADE; ON UPDATE NO ACTION.
- `verified_by` → `users(user_id)`: ON DELETE NO ACTION; ON UPDATE NO ACTION.

### `audit_logs`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `audit_id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `user_id` | `bigint unsigned` | Yes | — | FK → `users.user_id` |
| `action` | `varchar(100)` | No | — | — |
| `module` | `varchar(100)` | No | — | — |
| `record_table` | `varchar(100)` | Yes | — | — |
| `record_id` | `varchar(100)` | Yes | — | — |
| `old_values` | `json` | Yes | — | — |
| `new_values` | `json` | Yes | — | — |
| `ip_address` | `varchar(45)` | Yes | — | — |
| `user_agent` | `text` | Yes | — | — |
| `created_at` | `timestamp` | No | `CURRENT_TIMESTAMP` | — |

**Indexes:**

- `audit_logs_created_at_index`: INDEX (`created_at`).
- `audit_logs_module_action_index`: INDEX (`module`, `action`).
- `audit_logs_record_table_record_id_index`: INDEX (`record_table`, `record_id`).
- `audit_logs_user_id_foreign`: INDEX (`user_id`).
- `primary`: PRIMARY KEY (`audit_id`).

**Foreign-key actions:**

- `user_id` → `users(user_id)`: ON DELETE SET NULL; ON UPDATE NO ACTION.

### `system_settings`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `setting_key` | `varchar(64)` | No | — | UNIQUE |
| `value` | `varchar(255)` | Yes | — | — |
| `created_at` | `timestamp` | Yes | — | — |
| `updated_at` | `timestamp` | Yes | — | — |

**Indexes:**

- `primary`: PRIMARY KEY (`id`).
- `system_settings_setting_key_unique`: UNIQUE (`setting_key`).

## Laravel framework infrastructure

### `sessions`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `id` | `varchar(255)` | No | — | PK |
| `user_id` | `bigint unsigned` | Yes | — | — |
| `ip_address` | `varchar(45)` | Yes | — | — |
| `user_agent` | `text` | Yes | — | — |
| `payload` | `longtext` | No | — | — |
| `last_activity` | `int` | No | — | — |

**Indexes:**

- `primary`: PRIMARY KEY (`id`).
- `sessions_last_activity_index`: INDEX (`last_activity`).
- `sessions_user_id_index`: INDEX (`user_id`).

### `password_reset_tokens`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `email` | `varchar(255)` | No | — | PK |
| `token` | `varchar(255)` | No | — | — |
| `created_at` | `timestamp` | Yes | — | — |

**Indexes:**

- `primary`: PRIMARY KEY (`email`).

### `cache`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `key` | `varchar(255)` | No | — | PK |
| `value` | `mediumtext` | No | — | — |
| `expiration` | `bigint` | No | — | — |

**Indexes:**

- `cache_expiration_index`: INDEX (`expiration`).
- `primary`: PRIMARY KEY (`key`).

### `cache_locks`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `key` | `varchar(255)` | No | — | PK |
| `owner` | `varchar(255)` | No | — | — |
| `expiration` | `bigint` | No | — | — |

**Indexes:**

- `cache_locks_expiration_index`: INDEX (`expiration`).
- `primary`: PRIMARY KEY (`key`).

### `jobs`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `queue` | `varchar(255)` | No | — | — |
| `payload` | `longtext` | No | — | — |
| `attempts` | `smallint unsigned` | No | — | — |
| `reserved_at` | `int unsigned` | Yes | — | — |
| `available_at` | `int unsigned` | No | — | — |
| `created_at` | `int unsigned` | No | — | — |

**Indexes:**

- `jobs_queue_index`: INDEX (`queue`).
- `primary`: PRIMARY KEY (`id`).

### `job_batches`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `id` | `varchar(255)` | No | — | PK |
| `name` | `varchar(255)` | No | — | — |
| `total_jobs` | `int` | No | — | — |
| `pending_jobs` | `int` | No | — | — |
| `failed_jobs` | `int` | No | — | — |
| `failed_job_ids` | `longtext` | No | — | — |
| `options` | `mediumtext` | Yes | — | — |
| `cancelled_at` | `int` | Yes | — | — |
| `created_at` | `int` | No | — | — |
| `finished_at` | `int` | Yes | — | — |

**Indexes:**

- `primary`: PRIMARY KEY (`id`).

### `failed_jobs`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `id` | `bigint unsigned` | No | — | PK; AUTO_INCREMENT |
| `uuid` | `varchar(255)` | No | — | UNIQUE |
| `connection` | `varchar(255)` | No | — | — |
| `queue` | `varchar(255)` | No | — | — |
| `payload` | `longtext` | No | — | — |
| `exception` | `longtext` | No | — | — |
| `failed_at` | `timestamp` | No | `CURRENT_TIMESTAMP` | — |

**Indexes:**

- `failed_jobs_connection_queue_failed_at_index`: INDEX (`connection`, `queue`, `failed_at`).
- `failed_jobs_uuid_unique`: UNIQUE (`uuid`).
- `primary`: PRIMARY KEY (`id`).

### `migrations`

| Column | MySQL type | Nullable | Default | Key / attribute |
|---|---|---|---|---|
| `id` | `int unsigned` | No | — | PK; AUTO_INCREMENT |
| `migration` | `varchar(255)` | No | — | — |
| `batch` | `int` | No | — | — |

**Indexes:**

- `primary`: PRIMARY KEY (`id`).

## Workflow and integrity semantics

- Default roles are Admin, Payroll Officer, Approver and Viewer; permissions are assigned through `role_permissions` and `user_roles`. The current permission list is maintained in `database/seeders/RolePermissionSeeder.php`.
- Payroll statuses are DRAFT, PROCESSING, PENDING_APPROVAL, RETURNED, REJECTED, APPROVED, FINALIZED and CANCELLED. CANCELLED is defined in the model but has no dedicated cancellation action in the current interface. Their workflow and editability rules are enforced by Laravel, not SQL enum columns.
- A preparer or submitter cannot approve, reject or return their own run. Only an approved run can be finalized. Foreign keys alone do not enforce these business rules.
- When registry capture is enabled, finalization saves a canonical payroll snapshot and a registration request atomically. Queue workers submit and reconcile the blockchain transaction asynchronously.
- Registration statuses are QUEUED, SUBMITTED, RECONCILING, CONFIRMED, FAILED and CONFLICT. The unique `payroll_run_id` currently permits at most one registration row per run, even though identity includes a revision.
- Verification outcomes are MATCH, MISMATCH, PENDING, UNAVAILABLE, NOT_REGISTERED and INVALID_EVIDENCE. Each check appends a verification-history row. Confirmation and an integrity match are separate conclusions.
- Confidential canonical payroll data remains in MySQL. The blockchain receives an opaque payroll reference, revision, hash and supporting metadata. A Node SQLite transaction journal exists outside this MySQL schema.
- `period_snapshot`, `approval_snapshot`, `target_network` and `evidence` are JSON documents, not additional foreign-key relationships.
- `generated_reports.filters` may identify a payroll run, but it is JSON and has no SQL foreign key to `payroll_runs`. Deduction report viewing/printing does not create an archived export file.
- `sessions.user_id` has an index but no declared foreign key. Audit `record_table`/`record_id` identify heterogeneous records and are not SQL foreign keys.

## Maintenance

Apply schema changes through new Laravel migrations and update both this document and [the ERD](erd.md). Do not recreate removed attendance/leave tables or alter a live database merely to match an older design document.

Sources: Laravel Boost database schema inspection; `database/migrations`; employment-type and role-permission seeders; payroll approval, registration and verification services; report controller.
