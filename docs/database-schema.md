# LGU Payroll Management System – Updated Database Schema

**Version:** Updated working design for mock-defense preparation  
**Database:** MySQL  
**Application backend:** Laravel  
**Total tables:** 39

## 1. Confirmed design requirements

- Four system roles: **Admin, Payroll Officer, Approver, and Viewer**.
- Payroll preparation/submission and payroll approval are separate duties. The user who prepares or submits a payroll run must not approve that same run.
- Attendance is recorded by **days**, not by time-in/time-out or hours.
- Salary grade and step determine the monthly basic salary using the applicable LGU-adopted salary schedule.
- Example only: if the adopted schedule stores **SG 15, Step 1 = ₱45,000**, the employee’s monthly basic salary is ₱45,000 before applicable earnings and deductions.
- Permanent, coterminous, and part-time appointment arrangements are supported, subject to their applicable compensation basis.
- The following tables are intentionally excluded: `employee_documents`, `employee_emergency_contacts`, and `employee_government_ids`.
- MySQL stores payroll and employee records. Blockchain stores cryptographic proof and transaction references, not confidential payroll details.

## 2. Salary grade and step design

### How the salary is determined
1. `positions.salary_grade` stores the salary grade assigned to the position (NULL when the position is not salary-grade based).
2. `salary_schedules` stores each adopted schedule and its effective period/reference.
3. `salary_grade_steps` stores the monthly peso amount for each schedule, grade, and step.
4. `compensation_profiles.grade_step_id` identifies the employee’s applicable grade-and-step row. The selected row’s `monthly_salary` supplies the basic monthly rate.
5. Laravel must validate that the selected `salary_grade_steps.grade_number` matches the `positions.salary_grade` for the employee’s employment record and that the schedule is effective for the payroll date.
6. For a direct-rate arrangement without a grade-step record, `grade_step_id` is NULL and `basic_rate` stores the approved rate. `pay_basis` determines whether that rate is monthly, daily, hourly, or another approved basis.
7. For an authorized proportional monthly appointment, `service_fraction` stores the approved fraction of full-time service. Apply it only where the appointment/payroll rules require it.

### Compensation fields and rules
- `salary_grade_steps.monthly_salary` is the source of the salary amount for grade-and-step appointments.
- `compensation_profiles.basic_rate` is used for direct-rate arrangements; it is NULL when the rate is retrieved through `grade_step_id`.
- `payroll_details` stores salary grade, step, rate, and pay-basis snapshots so historical payroll can be understood even after a schedule changes.
- Illustrative amounts must never be inserted as official LGU salary data unless they match the adopted schedule supplied or approved by the client.

## 3. Table definitions

**Notation:** PK = Primary Key; FK = Foreign Key; UQ = Unique; NULL = Nullable. Unless stated otherwise, columns are NOT NULL.

### 1. `users`

| Column | Data type | Key / constraint |
|---|---|---|
| `user_id` | `BIGINT UNSIGNED` | PK |
| `employee_id` | `BIGINT UNSIGNED` | FK → employees.employee_id, NULL, UNIQUE |
| `username` | `VARCHAR(100)` | NOT NULL, UNIQUE |
| `email` | `VARCHAR(150)` | NULL |
| `password_hash` | `VARCHAR(255)` | NOT NULL |
| `account_status` | `VARCHAR(20)` | NOT NULL, DEFAULT ACTIVE |
| `last_login_at` | `DATETIME` | NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |
| `updated_at` | `TIMESTAMP` | NOT NULL |

### 2. `roles`

| Column | Data type | Key / constraint |
|---|---|---|
| `role_id` | `BIGINT UNSIGNED` | PK |
| `role_name` | `VARCHAR(100)` | NOT NULL, UNIQUE |
| `description` | `TEXT` | NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |
| `updated_at` | `TIMESTAMP` | NOT NULL |

### 3. `permissions`

| Column | Data type | Key / constraint |
|---|---|---|
| `permission_id` | `BIGINT UNSIGNED` | PK |
| `permission_name` | `VARCHAR(100)` | NOT NULL, UNIQUE |
| `description` | `TEXT` | NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |
| `updated_at` | `TIMESTAMP` | NOT NULL |

### 4. `user_roles`

| Column | Data type | Key / constraint |
|---|---|---|
| `user_role_id` | `BIGINT UNSIGNED` | PK |
| `user_id` | `BIGINT UNSIGNED` | FK → users.user_id |
| `role_id` | `BIGINT UNSIGNED` | FK → roles.role_id |
| `assigned_by` | `BIGINT UNSIGNED` | FK → users.user_id, NULL |
| `assigned_at` | `DATETIME` | NOT NULL |

### 5. `role_permissions`

| Column | Data type | Key / constraint |
|---|---|---|
| `role_permission_id` | `BIGINT UNSIGNED` | PK |
| `role_id` | `BIGINT UNSIGNED` | FK → roles.role_id |
| `permission_id` | `BIGINT UNSIGNED` | FK → permissions.permission_id |
| `created_at` | `TIMESTAMP` | NOT NULL |

### 6. `employees`

| Column | Data type | Key / constraint |
|---|---|---|
| `employee_id` | `BIGINT UNSIGNED` | PK |
| `employee_number` | `VARCHAR(50)` | NOT NULL, UNIQUE |
| `first_name` | `VARCHAR(100)` | NOT NULL |
| `middle_name` | `VARCHAR(100)` | NULL |
| `last_name` | `VARCHAR(100)` | NOT NULL |
| `suffix` | `VARCHAR(20)` | NULL |
| `birth_date` | `DATE` | NOT NULL |
| `sex` | `VARCHAR(20)` | NOT NULL |
| `civil_status` | `VARCHAR(30)` | NULL |
| `email` | `VARCHAR(150)` | NULL |
| `contact_number` | `VARCHAR(30)` | NULL |
| `address` | `TEXT` | NOT NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |
| `updated_at` | `TIMESTAMP` | NOT NULL |
| `deleted_at` | `TIMESTAMP` | NULL |

### 7. `departments`

| Column | Data type | Key / constraint |
|---|---|---|
| `department_id` | `BIGINT UNSIGNED` | PK |
| `department_code` | `VARCHAR(50)` | NOT NULL, UNIQUE |
| `department_name` | `VARCHAR(150)` | NOT NULL |
| `description` | `TEXT` | NULL |
| `status` | `VARCHAR(20)` | NOT NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |
| `updated_at` | `TIMESTAMP` | NOT NULL |

### 8. `positions`

| Column | Data type | Key / constraint |
|---|---|---|
| `position_id` | `BIGINT UNSIGNED` | PK |
| `department_id` | `BIGINT UNSIGNED` | FK → departments.department_id |
| `position_code` | `VARCHAR(50)` | NOT NULL, UNIQUE |
| `position_title` | `VARCHAR(150)` | NOT NULL |
| `salary_grade` | `INT UNSIGNED` | NULL for positions without a salary grade |
| `description` | `TEXT` | NULL |
| `status` | `VARCHAR(20)` | NOT NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |
| `updated_at` | `TIMESTAMP` | NOT NULL |

### 9. `employment_types`

| Column | Data type | Key / constraint |
|---|---|---|
| `employment_type_id` | `BIGINT UNSIGNED` | PK |
| `employment_type_name` | `VARCHAR(100)` | NOT NULL, UNIQUE |
| `description` | `TEXT` | NULL |
| `status` | `VARCHAR(20)` | NOT NULL |

### 10. `employment_records`

| Column | Data type | Key / constraint |
|---|---|---|
| `employment_id` | `BIGINT UNSIGNED` | PK |
| `employee_id` | `BIGINT UNSIGNED` | FK → employees.employee_id |
| `department_id` | `BIGINT UNSIGNED` | FK → departments.department_id |
| `position_id` | `BIGINT UNSIGNED` | FK → positions.position_id |
| `employment_type_id` | `BIGINT UNSIGNED` | FK → employment_types.employment_type_id |
| `appointment_number` | `VARCHAR(100)` | NULL |
| `start_date` | `DATE` | NOT NULL |
| `end_date` | `DATE` | NULL |
| `employment_status` | `VARCHAR(30)` | NOT NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |
| `updated_at` | `TIMESTAMP` | NOT NULL |

### 11. `employment_history`

| Column | Data type | Key / constraint |
|---|---|---|
| `history_id` | `BIGINT UNSIGNED` | PK |
| `employment_id` | `BIGINT UNSIGNED` | FK → employment_records.employment_id |
| `from_department_id` | `BIGINT UNSIGNED` | FK → departments.department_id, NULL |
| `to_department_id` | `BIGINT UNSIGNED` | FK → departments.department_id, NULL |
| `from_position_id` | `BIGINT UNSIGNED` | FK → positions.position_id, NULL |
| `to_position_id` | `BIGINT UNSIGNED` | FK → positions.position_id, NULL |
| `change_type` | `VARCHAR(50)` | NOT NULL |
| `effective_date` | `DATE` | NOT NULL |
| `remarks` | `TEXT` | NULL |
| `recorded_by` | `BIGINT UNSIGNED` | FK → users.user_id |
| `created_at` | `TIMESTAMP` | NOT NULL |

### 12. `salary_schedules`

| Column | Data type | Key / constraint |
|---|---|---|
| `salary_schedule_id` | `BIGINT UNSIGNED` | PK |
| `schedule_name` | `VARCHAR(150)` | NOT NULL |
| `effective_from` | `DATE` | NOT NULL |
| `effective_to` | `DATE` | NULL |
| `reference_document` | `VARCHAR(255)` | NULL |
| `status` | `VARCHAR(20)` | NOT NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |

### 13. `salary_grade_steps`

| Column | Data type | Key / constraint |
|---|---|---|
| `grade_step_id` | `BIGINT UNSIGNED` | PK |
| `salary_schedule_id` | `BIGINT UNSIGNED` | FK → salary_schedules.salary_schedule_id |
| `grade_number` | `INT UNSIGNED` | NOT NULL |
| `step_number` | `INT UNSIGNED` | NOT NULL |
| `monthly_salary` | `DECIMAL(15,2)` | NOT NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |

### 14. `compensation_profiles`

| Column | Data type | Key / constraint |
|---|---|---|
| `compensation_id` | `BIGINT UNSIGNED` | PK |
| `employment_id` | `BIGINT UNSIGNED` | FK → employment_records.employment_id |
| `grade_step_id` | `BIGINT UNSIGNED` | FK → salary_grade_steps.grade_step_id, NULL for a direct-rate arrangement |
| `pay_basis` | `VARCHAR(30)` | NOT NULL; e.g., MONTHLY, DAILY, HOURLY, PROPORTIONAL_MONTHLY |
| `basic_rate` | `DECIMAL(15,2)` | NULL when rate is retrieved from salary_grade_steps; required for direct-rate arrangements |
| `service_fraction` | `DECIMAL(5,4)` | NOT NULL, DEFAULT 1.0000; used for approved proportional appointments |
| `effective_from` | `DATE` | NOT NULL |
| `effective_to` | `DATE` | NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |
| `updated_at` | `TIMESTAMP` | NOT NULL |

### 15. `earning_types`

| Column | Data type | Key / constraint |
|---|---|---|
| `earning_type_id` | `BIGINT UNSIGNED` | PK |
| `earning_name` | `VARCHAR(150)` | NOT NULL |
| `earning_category` | `VARCHAR(100)` | NOT NULL |
| `computation_type` | `VARCHAR(50)` | NOT NULL |
| `is_taxable` | `BOOLEAN` | NOT NULL |
| `is_recurring` | `BOOLEAN` | NOT NULL |
| `status` | `VARCHAR(20)` | NOT NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |

### 16. `employee_earning_assignments`

| Column | Data type | Key / constraint |
|---|---|---|
| `assignment_id` | `BIGINT UNSIGNED` | PK |
| `employment_id` | `BIGINT UNSIGNED` | FK → employment_records.employment_id |
| `earning_type_id` | `BIGINT UNSIGNED` | FK → earning_types.earning_type_id |
| `amount` | `DECIMAL(15,2)` | NULL |
| `percentage` | `DECIMAL(8,4)` | NULL |
| `frequency` | `VARCHAR(30)` | NOT NULL |
| `effective_from` | `DATE` | NOT NULL |
| `effective_to` | `DATE` | NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |

### 17. `attendance_records`

| Column | Data type | Key / constraint |
|---|---|---|
| `attendance_id` | `BIGINT UNSIGNED` | PK |
| `employee_id` | `BIGINT UNSIGNED` | FK → employees.employee_id |
| `attendance_date` | `DATE` | NOT NULL |
| `attendance_status` | `VARCHAR(30)` | NOT NULL |
| `credited_days` | `DECIMAL(4,2)` | NOT NULL, DEFAULT 0.00 |
| `source` | `VARCHAR(30)` | NULL |
| `remarks` | `TEXT` | NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |
| `updated_at` | `TIMESTAMP` | NOT NULL |

### 18. `attendance_adjustments`

| Column | Data type | Key / constraint |
|---|---|---|
| `adjustment_id` | `BIGINT UNSIGNED` | PK |
| `attendance_id` | `BIGINT UNSIGNED` | FK → attendance_records.attendance_id |
| `requested_by` | `BIGINT UNSIGNED` | FK → users.user_id |
| `reviewed_by` | `BIGINT UNSIGNED` | FK → users.user_id, NULL |
| `old_attendance_status` | `VARCHAR(30)` | NOT NULL |
| `requested_attendance_status` | `VARCHAR(30)` | NOT NULL |
| `old_credited_days` | `DECIMAL(4,2)` | NOT NULL |
| `requested_credited_days` | `DECIMAL(4,2)` | NOT NULL |
| `adjustment_reason` | `TEXT` | NOT NULL |
| `approval_status` | `VARCHAR(30)` | NOT NULL |
| `requested_at` | `DATETIME` | NOT NULL |
| `reviewed_at` | `DATETIME` | NULL |

### 19. `leave_types`

| Column | Data type | Key / constraint |
|---|---|---|
| `leave_type_id` | `BIGINT UNSIGNED` | PK |
| `leave_name` | `VARCHAR(100)` | NOT NULL |
| `description` | `TEXT` | NULL |
| `default_credit` | `DECIMAL(8,2)` | NOT NULL |
| `is_paid` | `BOOLEAN` | NOT NULL |
| `status` | `VARCHAR(20)` | NOT NULL |

### 20. `leave_balances`

| Column | Data type | Key / constraint |
|---|---|---|
| `leave_balance_id` | `BIGINT UNSIGNED` | PK |
| `employee_id` | `BIGINT UNSIGNED` | FK → employees.employee_id |
| `leave_type_id` | `BIGINT UNSIGNED` | FK → leave_types.leave_type_id |
| `leave_year` | `YEAR` | NOT NULL |
| `opening_balance` | `DECIMAL(8,2)` | NOT NULL, DEFAULT 0.00 |
| `total_credits` | `DECIMAL(8,2)` | NOT NULL, DEFAULT 0.00 |
| `total_used` | `DECIMAL(8,2)` | NOT NULL, DEFAULT 0.00 |
| `available_balance` | `DECIMAL(8,2)` | NOT NULL, DEFAULT 0.00 |
| `updated_at` | `TIMESTAMP` | NOT NULL |

### 21. `leave_applications`

| Column | Data type | Key / constraint |
|---|---|---|
| `leave_application_id` | `BIGINT UNSIGNED` | PK |
| `employee_id` | `BIGINT UNSIGNED` | FK → employees.employee_id |
| `leave_type_id` | `BIGINT UNSIGNED` | FK → leave_types.leave_type_id |
| `start_date` | `DATE` | NOT NULL |
| `end_date` | `DATE` | NOT NULL |
| `total_days` | `DECIMAL(8,2)` | NOT NULL |
| `reason` | `TEXT` | NULL |
| `approval_status` | `VARCHAR(30)` | NOT NULL |
| `approved_by` | `BIGINT UNSIGNED` | FK → users.user_id, NULL |
| `approved_at` | `DATETIME` | NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |
| `updated_at` | `TIMESTAMP` | NOT NULL |

### 22. `leave_transactions`

| Column | Data type | Key / constraint |
|---|---|---|
| `leave_transaction_id` | `BIGINT UNSIGNED` | PK |
| `leave_balance_id` | `BIGINT UNSIGNED` | FK → leave_balances.leave_balance_id |
| `leave_application_id` | `BIGINT UNSIGNED` | FK → leave_applications.leave_application_id, NULL |
| `transaction_type` | `VARCHAR(30)` | NOT NULL |
| `days` | `DECIMAL(8,2)` | NOT NULL |
| `balance_after` | `DECIMAL(8,2)` | NOT NULL |
| `transaction_date` | `DATE` | NOT NULL |
| `created_by` | `BIGINT UNSIGNED` | FK → users.user_id |
| `remarks` | `TEXT` | NULL |

### 23. `deduction_types`

| Column | Data type | Key / constraint |
|---|---|---|
| `deduction_type_id` | `BIGINT UNSIGNED` | PK |
| `deduction_name` | `VARCHAR(150)` | NOT NULL |
| `deduction_category` | `VARCHAR(100)` | NOT NULL |
| `computation_type` | `VARCHAR(50)` | NOT NULL |
| `is_statutory` | `BOOLEAN` | NOT NULL |
| `is_pre_tax` | `BOOLEAN` | NOT NULL |
| `status` | `VARCHAR(20)` | NOT NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |

### 24. `employee_deduction_assignments`

| Column | Data type | Key / constraint |
|---|---|---|
| `assignment_id` | `BIGINT UNSIGNED` | PK |
| `employment_id` | `BIGINT UNSIGNED` | FK → employment_records.employment_id |
| `deduction_type_id` | `BIGINT UNSIGNED` | FK → deduction_types.deduction_type_id |
| `amount` | `DECIMAL(15,2)` | NULL |
| `percentage` | `DECIMAL(8,4)` | NULL |
| `reference_number` | `VARCHAR(100)` | NULL |
| `effective_from` | `DATE` | NOT NULL |
| `effective_to` | `DATE` | NULL |
| `status` | `VARCHAR(20)` | NOT NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |

### 25. `tax_brackets`

| Column | Data type | Key / constraint |
|---|---|---|
| `tax_bracket_id` | `BIGINT UNSIGNED` | PK |
| `tax_type` | `VARCHAR(50)` | NOT NULL |
| `pay_frequency` | `VARCHAR(30)` | NOT NULL |
| `minimum_income` | `DECIMAL(15,2)` | NOT NULL |
| `maximum_income` | `DECIMAL(15,2)` | NULL |
| `base_tax` | `DECIMAL(15,2)` | NOT NULL |
| `marginal_tax_rate` | `DECIMAL(8,4)` | NOT NULL |
| `effective_from` | `DATE` | NOT NULL |
| `effective_to` | `DATE` | NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |

### 26. `contribution_rates`

| Column | Data type | Key / constraint |
|---|---|---|
| `contribution_rate_id` | `BIGINT UNSIGNED` | PK |
| `contribution_type` | `VARCHAR(100)` | NOT NULL |
| `minimum_compensation` | `DECIMAL(15,2)` | NOT NULL |
| `maximum_compensation` | `DECIMAL(15,2)` | NULL |
| `employee_rate` | `DECIMAL(8,4)` | NOT NULL |
| `employer_rate` | `DECIMAL(8,4)` | NOT NULL |
| `employee_fixed_amount` | `DECIMAL(15,2)` | NULL |
| `employer_fixed_amount` | `DECIMAL(15,2)` | NULL |
| `effective_from` | `DATE` | NOT NULL |
| `effective_to` | `DATE` | NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |

### 27. `payroll_periods`

| Column | Data type | Key / constraint |
|---|---|---|
| `payroll_period_id` | `BIGINT UNSIGNED` | PK |
| `period_name` | `VARCHAR(100)` | NOT NULL |
| `payroll_type` | `VARCHAR(30)` | NOT NULL |
| `start_date` | `DATE` | NOT NULL |
| `end_date` | `DATE` | NOT NULL |
| `pay_date` | `DATE` | NOT NULL |
| `status` | `VARCHAR(30)` | NOT NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |
| `updated_at` | `TIMESTAMP` | NOT NULL |

### 28. `payroll_runs`

| Column | Data type | Key / constraint |
|---|---|---|
| `payroll_run_id` | `BIGINT UNSIGNED` | PK |
| `payroll_period_id` | `BIGINT UNSIGNED` | FK → payroll_periods.payroll_period_id |
| `run_number` | `INT UNSIGNED` | NOT NULL |
| `run_type` | `VARCHAR(30)` | NOT NULL |
| `status` | `VARCHAR(30)` | NOT NULL |
| `created_by` | `BIGINT UNSIGNED` | FK → users.user_id |
| `processed_at` | `DATETIME` | NULL |
| `submitted_by` | `BIGINT UNSIGNED` | FK → users.user_id, NULL |
| `submitted_at` | `DATETIME` | NULL |
| `finalized_by` | `BIGINT UNSIGNED` | FK → users.user_id, NULL |
| `finalized_at` | `DATETIME` | NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |
| `updated_at` | `TIMESTAMP` | NOT NULL |

### 29. `payroll_approvals`

| Column | Data type | Key / constraint |
|---|---|---|
| `approval_id` | `BIGINT UNSIGNED` | PK |
| `payroll_run_id` | `BIGINT UNSIGNED` | FK → payroll_runs.payroll_run_id |
| `approval_step` | `VARCHAR(50)` | NOT NULL |
| `action` | `VARCHAR(30)` | NOT NULL |
| `acted_by` | `BIGINT UNSIGNED` | FK → users.user_id |
| `remarks` | `TEXT` | NULL |
| `acted_at` | `DATETIME` | NOT NULL |

### 30. `payroll_details`

| Column | Data type | Key / constraint |
|---|---|---|
| `payroll_detail_id` | `BIGINT UNSIGNED` | PK |
| `payroll_run_id` | `BIGINT UNSIGNED` | FK → payroll_runs.payroll_run_id |
| `employee_id` | `BIGINT UNSIGNED` | FK → employees.employee_id |
| `employment_id` | `BIGINT UNSIGNED` | FK → employment_records.employment_id |
| `employee_number_snapshot` | `VARCHAR(50)` | NOT NULL |
| `employee_name_snapshot` | `VARCHAR(255)` | NOT NULL |
| `department_snapshot` | `VARCHAR(150)` | NULL |
| `position_snapshot` | `VARCHAR(150)` | NULL |
| `salary_grade_snapshot` | `INT UNSIGNED` | NULL |
| `salary_step_snapshot` | `INT UNSIGNED` | NULL |
| `salary_rate_snapshot` | `DECIMAL(15,2)` | NULL |
| `pay_basis_snapshot` | `VARCHAR(30)` | NULL |
| `basic_pay` | `DECIMAL(15,2)` | NOT NULL |
| `total_earnings` | `DECIMAL(15,2)` | NOT NULL |
| `gross_pay` | `DECIMAL(15,2)` | NOT NULL |
| `total_deductions` | `DECIMAL(15,2)` | NOT NULL |
| `net_pay` | `DECIMAL(15,2)` | NOT NULL |
| `days_worked` | `DECIMAL(8,2)` | NOT NULL |
| `days_present` | `DECIMAL(8,2)` | NOT NULL |
| `days_absent` | `DECIMAL(8,2)` | NOT NULL |
| `days_on_leave` | `DECIMAL(8,2)` | NOT NULL |
| `payroll_status` | `VARCHAR(30)` | NOT NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |
| `updated_at` | `TIMESTAMP` | NOT NULL |

### 31. `payroll_earnings`

| Column | Data type | Key / constraint |
|---|---|---|
| `payroll_earning_id` | `BIGINT UNSIGNED` | PK |
| `payroll_detail_id` | `BIGINT UNSIGNED` | FK → payroll_details.payroll_detail_id |
| `earning_type_id` | `BIGINT UNSIGNED` | FK → earning_types.earning_type_id |
| `earning_name_snapshot` | `VARCHAR(150)` | NOT NULL |
| `quantity` | `DECIMAL(10,2)` | NULL |
| `rate` | `DECIMAL(15,2)` | NULL |
| `amount` | `DECIMAL(15,2)` | NOT NULL |
| `is_taxable` | `BOOLEAN` | NOT NULL |
| `remarks` | `TEXT` | NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |

### 32. `payroll_deductions`

| Column | Data type | Key / constraint |
|---|---|---|
| `payroll_deduction_id` | `BIGINT UNSIGNED` | PK |
| `payroll_detail_id` | `BIGINT UNSIGNED` | FK → payroll_details.payroll_detail_id |
| `deduction_type_id` | `BIGINT UNSIGNED` | FK → deduction_types.deduction_type_id |
| `deduction_name_snapshot` | `VARCHAR(150)` | NOT NULL |
| `amount` | `DECIMAL(15,2)` | NOT NULL |
| `remarks` | `TEXT` | NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |

### 33. `payroll_employer_contributions`

| Column | Data type | Key / constraint |
|---|---|---|
| `employer_contribution_id` | `BIGINT UNSIGNED` | PK |
| `payroll_detail_id` | `BIGINT UNSIGNED` | FK → payroll_details.payroll_detail_id |
| `contribution_rate_id` | `BIGINT UNSIGNED` | FK → contribution_rates.contribution_rate_id |
| `contribution_type_snapshot` | `VARCHAR(100)` | NOT NULL |
| `amount` | `DECIMAL(15,2)` | NOT NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |

### 34. `payroll_adjustments`

| Column | Data type | Key / constraint |
|---|---|---|
| `adjustment_id` | `BIGINT UNSIGNED` | PK |
| `payroll_detail_id` | `BIGINT UNSIGNED` | FK → payroll_details.payroll_detail_id |
| `adjustment_type` | `VARCHAR(30)` | NOT NULL |
| `amount` | `DECIMAL(15,2)` | NOT NULL |
| `reason` | `TEXT` | NOT NULL |
| `requested_by` | `BIGINT UNSIGNED` | FK → users.user_id |
| `approved_by` | `BIGINT UNSIGNED` | FK → users.user_id, NULL |
| `approval_status` | `VARCHAR(30)` | NOT NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |
| `updated_at` | `TIMESTAMP` | NOT NULL |

### 35. `payslips`

| Column | Data type | Key / constraint |
|---|---|---|
| `payslip_id` | `BIGINT UNSIGNED` | PK |
| `payroll_detail_id` | `BIGINT UNSIGNED` | FK → payroll_details.payroll_detail_id, UNIQUE |
| `payslip_number` | `VARCHAR(100)` | NOT NULL, UNIQUE |
| `file_path` | `VARCHAR(500)` | NULL |
| `generated_at` | `DATETIME` | NOT NULL |
| `published_at` | `DATETIME` | NULL |
| `publication_status` | `VARCHAR(30)` | NOT NULL |

### 36. `generated_reports`

| Column | Data type | Key / constraint |
|---|---|---|
| `report_id` | `BIGINT UNSIGNED` | PK |
| `report_type` | `VARCHAR(100)` | NOT NULL |
| `generated_by` | `BIGINT UNSIGNED` | FK → users.user_id |
| `filters` | `JSON` | NULL |
| `file_path` | `VARCHAR(500)` | NULL |
| `generated_at` | `DATETIME` | NOT NULL |

### 37. `audit_logs`

| Column | Data type | Key / constraint |
|---|---|---|
| `audit_id` | `BIGINT UNSIGNED` | PK |
| `user_id` | `BIGINT UNSIGNED` | FK → users.user_id, NULL |
| `action` | `VARCHAR(100)` | NOT NULL |
| `module` | `VARCHAR(100)` | NOT NULL |
| `record_table` | `VARCHAR(100)` | NULL |
| `record_id` | `VARCHAR(100)` | NULL |
| `old_values` | `JSON` | NULL |
| `new_values` | `JSON` | NULL |
| `ip_address` | `VARCHAR(45)` | NULL |
| `user_agent` | `TEXT` | NULL |
| `created_at` | `DATETIME` | NOT NULL |

### 38. `blockchain_anchors`

| Column | Data type | Key / constraint |
|---|---|---|
| `anchor_id` | `BIGINT UNSIGNED` | PK |
| `payroll_run_id` | `BIGINT UNSIGNED` | FK → payroll_runs.payroll_run_id, UNIQUE |
| `payload_hash` | `CHAR(64)` | NOT NULL |
| `hash_algorithm` | `VARCHAR(30)` | NOT NULL |
| `transaction_id` | `VARCHAR(255)` | NULL |
| `block_number` | `BIGINT UNSIGNED` | NULL |
| `network_name` | `VARCHAR(100)` | NOT NULL |
| `contract_address` | `VARCHAR(255)` | NULL |
| `anchor_status` | `VARCHAR(30)` | NOT NULL |
| `anchored_by` | `BIGINT UNSIGNED` | FK → users.user_id, NULL |
| `anchored_at` | `DATETIME` | NULL |
| `created_at` | `TIMESTAMP` | NOT NULL |

### 39. `blockchain_verification_logs`

| Column | Data type | Key / constraint |
|---|---|---|
| `verification_id` | `BIGINT UNSIGNED` | PK |
| `anchor_id` | `BIGINT UNSIGNED` | FK → blockchain_anchors.anchor_id |
| `verified_by` | `BIGINT UNSIGNED` | FK → users.user_id |
| `stored_hash` | `CHAR(64)` | NOT NULL |
| `calculated_hash` | `CHAR(64)` | NOT NULL |
| `verification_result` | `VARCHAR(30)` | NOT NULL |
| `remarks` | `TEXT` | NULL |
| `verified_at` | `DATETIME` | NOT NULL |

## 4. Initial role records

Insert these four records into `roles` through a database seeder. These are role names, not four separate tables.

| role_id | role_name | Description |
|---:|---|---|
| 1 | Admin | Manages system configuration, user accounts, and access settings. |
| 2 | Payroll Officer | Maintains payroll inputs, prepares payroll computations, and submits payroll for approval. |
| 3 | Approver | Independently reviews, approves, rejects, or returns payroll. |
| 4 | Viewer | Has read-only access to authorized records and reports. |

### Suggested permission names

`users.manage`, `roles.manage`, `employees.view`, `employees.manage`, `attendance.manage`, `leave.manage`, `compensation.manage`, `payroll.prepare`, `payroll.submit`, `payroll.review`, `payroll.approve`, `payroll.reject`, `payroll.finalize`, `payroll.view`, `reports.generate`, `audit.view`, and `blockchain.verify`.

Assign permissions through `role_permissions` and users through `user_roles`. Enforce authorization in Laravel policies/middleware as well as the interface.

## 5. Payroll processing and separation of duties

### Suggested payroll run statuses

| Status | Meaning |
|---|---|
| `DRAFT` | Payroll run is being prepared. |
| `PROCESSING` | Payroll computations are being processed. |
| `PENDING_APPROVAL` | Payroll was submitted for independent review. |
| `RETURNED` | Payroll was returned for correction. |
| `REJECTED` | Payroll was rejected. |
| `APPROVED` | Payroll was approved by an authorized Approver. |
| `FINALIZED` | Approved payroll was finalized. |
| `CANCELLED` | Payroll run was cancelled through an authorized process. |

### Required workflow rules

1. The Payroll Officer prepares and submits the payroll run.
2. Only a user assigned the Approver role and the required permission may approve or reject it.
3. The approving user must be different from both `payroll_runs.created_by` and `payroll_runs.submitted_by` for that payroll run.
4. Admin access must not bypass this separation-of-duties rule.
5. Returned or rejected payroll must be corrected and resubmitted; prior approval actions remain in `payroll_approvals` as history.
6. Only an approved payroll run may be finalized.
7. Record submissions, decisions, and finalization in `payroll_approvals` and/or `audit_logs` as appropriate.
8. Create the blockchain anchor only after the payroll reaches the client-approved finalization stage.

**Implementation note:** The different-user rule is a cross-table business rule and must be enforced in Laravel (and optionally reinforced with database triggers). A conventional foreign key alone cannot enforce it.

## 6. Attendance design

- `attendance_records` stores one day-based attendance record per employee per date, if that matches the client’s practice.
- Suggested statuses include `PRESENT`, `HALF_DAY`, `ABSENT`, `ON_LEAVE`, `HOLIDAY`, and `REST_DAY`; actual categories and credited-day treatment must be confirmed by the client.
- `credited_days` stores the attendance credit for the date (for example, 1.00 for a full day and 0.50 for a half day when applicable). Do not infer that leave, holiday, or rest-day statuses always produce a particular credit without the client’s rules.
- No time-in, time-out, total-hours, overtime-hours, or `hours_worked` fields are included in this day-based design.
- `payroll_details` includes `days_worked`, `days_present`, `days_absent`, and `days_on_leave` as payroll-period summary values.

## 7. Key constraints and indexes

| Table | Recommended constraint/index |
|---|---|
| `users` | UNIQUE `username`; UNIQUE nullable `employee_id` if one employee may have at most one account |
| `employees` | UNIQUE `employee_number` |
| `departments` | UNIQUE `department_code` |
| `positions` | UNIQUE `position_code`; index `department_id` and `salary_grade` |
| `employment_records` | Index `employee_id`, `department_id`, `position_id`, and `employment_type_id` |
| `salary_grade_steps` | UNIQUE (`salary_schedule_id`, `grade_number`, `step_number`) |
| `compensation_profiles` | Index `employment_id`, `grade_step_id`, and effective dates; prevent overlapping active compensation periods per employment |
| `attendance_records` | UNIQUE (`employee_id`, `attendance_date`) if one record per employee per day; index `attendance_date` |
| `leave_balances` | UNIQUE (`employee_id`, `leave_type_id`, `leave_year`) |
| `payroll_runs` | UNIQUE (`payroll_period_id`, `run_number`) |
| `payroll_details` | UNIQUE (`payroll_run_id`, `employment_id`) |
| `payslips` | UNIQUE `payroll_detail_id`; UNIQUE `payslip_number` |
| `blockchain_anchors` | UNIQUE `payroll_run_id` for one anchor per payroll run in this design |
| `user_roles` | UNIQUE (`user_id`, `role_id`) |
| `role_permissions` | UNIQUE (`role_id`, `permission_id`) |

Use `DECIMAL(15,2)` for monetary fields. Use restrictive deletion rules for finalized payroll and other historical records. Consider cascading deletes only for appropriate junction/child records. Validate date ranges, non-negative amounts, and credited-day limits. If the client requires more than one attendance entry per employee per date, revise the unique attendance constraint.

## 8. Module and table count

| Module | Tables |
|---|---:|
| User and Access Management | 5 |
| Employee Management | 1 |
| Organizational Management | 3 |
| Employment Management | 2 |
| Compensation Management | 5 |
| Attendance Management | 2 |
| Leave Management | 4 |
| Deduction Management | 4 |
| Payroll Processing | 8 |
| Payslip Management | 1 |
| Reports Management | 1 |
| Audit Trail Management | 1 |
| Blockchain Integration | 2 |
| **TOTAL** | **39** |

## 9. Implementation caveats

- This is a proposed working schema for the capstone and mock-defense preparation, not a certified statement of the client’s official payroll rules.
- Load actual salary amounts only from the LGU’s approved/adopted salary schedule. A value such as SG 15, Step 1 = ₱45,000 is an example unless confirmed in that schedule.
- Configure tax and contribution rates by effective date and applicable compensation/pay frequency. Confirm eligibility and bases for each employment category before production use.
- Preserve payroll snapshots and finalized results. Changes to salary schedules, tax tables, or contribution rates must not silently recalculate historical payroll.
- Keep confidential employee and payroll data off-chain. Anchor a deterministic hash of the finalized payroll data and retain transaction evidence in `blockchain_anchors`.
- Validate the schema, payroll formulas, contribution treatment, appointment categories, approval routing, and reporting requirements with the Municipal Accounting Office before production implementation.

## 10. Companion ERD

See the separate file `LGU_Payroll_Management_System_ERD_UPDATED.md` for the three Mermaid ERD diagrams and the relationship notes.
