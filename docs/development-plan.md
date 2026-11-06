# LGU Payroll Management System – Development Plan

**Status:** Working plan for mock-defense build
**Stack:** Laravel 13 / PHP 8.3 · MySQL · Blade + Tailwind 4 (Vite)
**Roles:** Admin, Payroll Officer, Approver, Viewer
**Source of truth:** `database-schema.md`, `erd.md`, `system-architecture-diagram.md`, `data-flow-diagram.md`, `use-case-diagram.md`, `payroll-computation-design.md`

---

## 0. Progress

| Stage | Status | Notes |
|---|---|---|
| 0. Foundations | ✅ Done | Prerequisites verified; `intl`/`gd` enabled; Laravel Boost installed; baseline commit `1f22762`. |
| 1. Schema & models | ✅ Done | 39 tables, 39 models, factories, seeders, schema tests. See `database-implementation.md`. |
| 2. Auth & RBAC | ✅ Done | Username login, account status, gates, role middleware, auth audit. See `authentication-authorization.md`. |
| 3–13 | ⬜ Not started | — |

---

## 1. Current state of the codebase

The repository is currently a bare Laravel scaffold, not yet the LGU system:

- **Auth only:** `routes/web.php` has login/logout and a placeholder `/dashboard`. Login authenticates by **email**.
- **One migration set:** Laravel defaults (`users` with `name`/`email`/`password`, `cache`, `jobs`). **None** of the 39 documented tables exist.
- **One model:** default `App\Models\User` (name/email/password), which does **not** match the design's `users` (username, `employee_id`, `account_status`, `password_hash`).
- **Views:** plain `auth/login.blade.php`, `welcome`, and a bare `dashboard.blade.php` with no layout.
- **No RBAC, no domain code, no tests, no git commits** (`main` has zero commits).
- **Environment:** `.env` points at MySQL `lgu_payroll_system_dev`. Tailwind 4 + Vite are present.

---

## 2. Confirmed decisions

| Decision | Choice |
|---|---|
| **Frontend** | Blade + Tailwind 4; port the four prototypes into one shared layout with role-based navigation. |
| **Blockchain** | Real SHA-256 hashing behind a `BlockchainAnchor` driver interface; default **simulated/mock** driver plus a testnet stub. |
| **Authentication** | **Username-based** login per the documented schema; `email` becomes an optional contact field; `account_status` gates access. |
| **Scope target** | Working **end-to-end system for mock defense**: all modules functional, simulated chain, demo seed data, core payroll workflow tested. |

---

## 3. Stage plan

| Stage | Deliverable | Key notes |
|---|---|---|
| **0. Foundations** | Working baseline | Verify PHP/Node/MySQL (`lgu_payroll_system_dev`); install Laravel Boost (`composer require laravel/boost --dev`, `php artisan boost:install`) and re-read generated guidelines; first git commit; keep `.env` out of VCS. |
| **1. Schema & models** | All 39 tables + Eloquent models + seeders | Rebuild `users` to username/`employee_id`/`account_status`/`last_login_at`; migrations in FK-safe order grouped by module; relationships, casts, enums, unique constraints/indexes; seed 4 roles, permissions, `role_permissions`, `employment_types`, an initial Admin, and clearly-marked illustrative reference data; factories + SQLite test config. |
| **2. Auth & RBAC** | Username login + Gates/Policies | Enforce `account_status`; record `last_login_at`; permissions via `user_roles`/`role_permissions` exposed as Gates/Policies + middleware (`users.manage`, `payroll.prepare`, `payroll.approve`, …); auth events → `audit_logs`. |
| **3. Master data** | Departments, Positions, Employees, Employment, Salary schedules, Compensation profiles | Compensation validation: grade must match `positions.salary_grade` and the schedule must be effective for the payroll date; employee soft-deletes; employment records/history; Admin user-management UI. |
| **4. Attendance & Leave** | Day-based attendance + adjustments; leave lifecycle | `attendance_records` unique per employee/date; `attendance_adjustments` approval flow; leave types, balances, applications, transactions. |
| **5. Earn/Deduct config** | Earning/deduction types & assignments; tax & contribution tables | PERA, hazard, clothing, subsistence, laundry, medical; GSIS/Pag-IBIG/GABE/PLI/dues/LBP deduction types; `tax_brackets` and `contribution_rates` all effective-dated. |
| **6. Payroll engine** | Payroll periods, runs, detailed result tables | DRAFT→PROCESSING→PENDING_APPROVAL→…→FINALIZED status lifecycle; `PayrollComputationService` with decimal math: gross salary (CSC leave-without-pay formula; daily-rate part-timers) → Total Compensation → GSIS/PhilHealth/Pag-IBIG employee + employer shares → withholding tax → manual deductions → Total Deductions → NTHP-before-LBP → LBP → Net Take-Home Pay; persist `payroll_details` (with snapshots), `payroll_earnings`, `payroll_deductions`, `payroll_employer_contributions`, `payroll_adjustments`. |
| **7. Approval & separation of duties** | Submit/review/approve/reject/return/finalize | Approver must differ from `payroll_runs.created_by` and `submitted_by`; Admin cannot bypass; full history in `payroll_approvals`; optional DB trigger reinforcing the rule. |
| **8. Register, payslips, reports** | Client-column register, CSV/Excel export, payslip PDFs | Register matches the documented column layout (Office → … → Net Take-Home Pay); `payslips` with `publication_status`; `generated_reports`. |
| **9. Blockchain** | Anchor + verify | Deterministic serialization of finalized payroll → SHA-256; `blockchain_anchors` and `blockchain_verification_logs`; simulated driver (testnet stub); hash + metadata only on-chain. |
| **10. UI** | Four role dashboards in Blade + Tailwind | Port `admin-`, `payroll-officer-`, `approver-`, `viewer-` prototypes into shared layout/components; role-based nav and dashboards. |
| **11. Audit & hardening** | Audit viewer/export, settings, authorization review | Audit log viewer/export; system settings; validation and authorization pass; DB constraints. |
| **12. Testing & QA** | Unit + feature tests, demo seeders | Verify the documented ₱45,000 example and contribution/tax edge cases; feature tests for auth, RBAC, payroll lifecycle, and the separation-of-duties rule; demo seeders + mock-defense walkthrough. |
| **13. Deployment & docs** | MySQL production, HTTPS, backups, defense materials | Production MySQL config; HTTPS; backups; scheduler/queue for reports; update README and docs. |

**Critical path:** 0 → 1 → 2 → 3/4/5 → 6 → 7 → 8/9 → 10 → 11/12 → 13.

---

## 4. Implementation notes

- **`users` reconciliation:** the design uses `password_hash`, but Laravel's built-in auth expects a `password` column. Keep Laravel's column names/semantics so the framework's authentication works, and document the mapping rather than fighting the framework.
- **Deterministic hashing:** the finalized-payroll hash must use a stable, sorted serialization (fixed field order, fixed rounding) so re-computation reproduces the same digest during verification.
- **Money handling:** use `DECIMAL(15,2)` and a single documented rounding rule throughout the computation engine; never rely on floats.
- **Effective-dated configuration:** tax brackets, contribution rates, and salary schedules must be time-versioned so historical payroll never silently recalculates.
- **Separation of duties:** enforce in the Laravel backend (policy/middleware), not only in the interface.

---

## 5. Open items still requiring client confirmation

These do not block development but must be resolved before live payroll (from `payroll-computation-design.md` §14):

1. Exact meaning/arrangement of `LBP`.
2. Full meaning of `GABE` and the authority for each GABE deduction.
3. Whether `SEPT. 2026 DUES` should be a period-neutral field.
4. The LGU's formally adopted salary schedule, effective date, and authorized amounts.
5. Latest contribution rates, bases, floors, ceilings, and coverage rules (especially PhilHealth).
6. Tax treatment of each allowance/benefit.
7. BIR-compliant monthly withholding method and year-end/termination annualization.
8. Which classifications are covered by GSIS, PhilHealth, and Pag-IBIG.
9. The attendance/leave rules used to determine paid days and leave without pay.
