# LGU Payroll Management System – Development Plan

**Status:** Working plan for mock-defense build
**Stack:** Laravel 13 / PHP 8.5 · MySQL · Blade + Tailwind 4 (Vite)
**Roles:** Admin, Payroll Officer, Approver, Viewer
**Source of truth:** `database-schema.md`, `erd.md`, `system-architecture-diagram.md`, `data-flow-diagram.md`, `use-case-diagram.md`, `payroll-computation-design.md`

---

## 0. Progress

| Stage | Status | Notes |
|---|---|---|
| 0. Foundations | ✅ Done | Prerequisites verified; `intl`/`gd` enabled; Laravel Boost installed; baseline commit `1f22762`. |
| 1. Schema & models | ✅ Done | 39 tables, 39 models, factories, seeders, schema tests. See `database-implementation.md`. |
| 2. Auth & RBAC | ✅ Done | Username login, account status, gates, role middleware, auth audit. See `authentication-authorization.md`. |
| 3. Master data | ✅ Done | Departments, positions, employees, employment records, salary schedules/grade steps, compensation profiles, user accounts. See `master-data-modules.md`. |
| 4. Attendance & leave | ✅ Done | Day-based attendance, adjustment approvals, leave types/balances/applications/transactions. See `attendance-leave-modules.md`. |
| 5. Earnings & deductions | ✅ Done | Earning/deduction types + assignments; effective-dated tax brackets and contribution rates. See `earnings-deductions-modules.md`. |
| 6. Payroll engine | ✅ Done | Periods, runs, details; computation service with snapshots; documented example verified. See `payroll-computation-engine.md`. |
| 7. Approval & SoD | ✅ Done | Submit/review/approve/reject/return/finalize with `payroll_approvals` history and backend-enforced separation of duties. See `payroll-approval-workflow.md`. |
| 8. Register, payslips, reports | ✅ Done | Client-column register + CSV export, printable payslips with publication status, reports hub and department summary. See `payroll-register-payslips-reports.md`. |
| 9. Blockchain | ✅ Done (local development) | SHA-256 anchoring with a real Hyperledger Besu QBFT Docker network, Web3Signer, and Solidity contract; simulated QA driver retained. Production operations remain in Stage 13. See `blockchain-anchoring.md`. |
| 10. UI | ✅ Done | Four role dashboards with live summaries, permission-aware actions, shared responsive navigation, and dashboard feature tests. |
| 11. Audit & hardening | Done | Audit viewer/export, permission-protected system settings, audited account/role changes, last-administrator protection, fresh-state checks under transaction locks, payroll input validation, and period-history constraints implemented. See Stage 11 notes below. |
| 12. Testing & QA | Done (mock-defense scope) | Payroll boundary regression tests, complete HTTP workflow, repeatable demo scenarios, and isolated MySQL concurrency checks. See Stage 12 notes below. |
| 13. Deployment & docs | Not started | Production deployment, HTTPS, backups, and operational/defense materials. |

---

## 1. Initial scaffold baseline (historical)

At the start of development, the repository was a bare Laravel scaffold. The following snapshot is historical; the progress table above tracks the implemented system:

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
| **Blockchain** | **Hyperledger Besu**, using a local private network with four QBFT validators and node permissioning. Laravel submits SHA-256 hashes to a Solidity contract through Web3Signer; pending receipts and on-chain verification are implemented. The simulated driver remains available for isolated QA. |
| **Authentication** | **Username-based** login per the documented schema; `email` becomes an optional contact field; `account_status` gates access. |
| **Scope target** | Working **end-to-end system for mock defense**: all modules functional, local Besu integration with simulated QA fallback, demo seed data, core payroll workflow tested. |

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
| **9. Blockchain** | Anchor + verify | Deterministic serialization of finalized payroll → SHA-256; `blockchain_anchors` and `blockchain_verification_logs`; Hyperledger Besu JSON-RPC driver and owner-authorized Solidity contract implemented with local Docker setup. Submitted hashes are PENDING until verified on-chain; only hash and transaction metadata leave the application. Simulated driver retained for QA. |
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

### Stage 11 implementation and verification

Completed November 7, 2026 for the mock-defense scope.

- **System settings:** Added persistent system name, organization name, and support email, protected by `settings.manage`. The application workspace uses the saved identity and support link. Currency, monthly frequency, timezone, audit logging, and security rules are displayed as enforced configuration. Settings cannot disable authorization or separation of duties, or alter historical reports.
- **Audit:** Retained the existing metadata viewer/export and inactive-session enforcement. Account creation, account/role updates, deletion, settings changes, payroll run creation/deletion, period updates, and manual detail changes now record their corresponding audit events. Credential fields are omitted from account snapshots, and the logger recursively redacts known secret keys. Viewer/export queries retrieve metadata columns only.
- **Account authorization:** Updates preserve at least one active account with user-management permission. Account and role changes commit together with their audit record. Referenced accounts cannot be deleted; the interface explains that they should be deactivated. Role IDs must be distinct.
- **Payroll boundaries:** Run numbering locks its parent period; closed periods reject new runs. Approval transitions, recomputation, manual editing, and deletion recheck fresh run state under a transaction lock. Manual type IDs are validated; inactive earnings and statutory, inactive, or LBP deductions cannot be injected through the manual detail endpoint. Existing separation of duties remains enforced.
- **Attendance/leave review:** Review decisions recheck current application/adjustment state under a lock. Leave approval also locks the balance before applying usage, and attendance approval locks the target attendance record.
- **History and database constraints:** Period dates, type, name, and pay date cannot change once runs exist; closing a period remains possible. The payroll-period foreign key now restricts deletion instead of cascading payroll history. Settings keys are unique, and audit dates are indexed. Existing attendance, leave-balance, run-number, detail, payslip, anchor, and role-membership uniqueness/foreign-key constraints were reviewed.
- **Migrations:** Both new migrations were applied to the development MySQL database. They add default settings and grant the new permission to the existing Admin role without resynchronizing or removing existing role grants. Fresh SQLite test migrations also pass.
- **Verification:** 177 PHPUnit tests passed with 769 assertions, including 28 settings/hardening cases. PHP formatting and frontend build passed. Browser checks confirmed settings navigation for Admin only, 44-pixel controls, no page overflow at 375/768/1024/1440 pixels, and focused, linked errors with preserved nested input. The browser validation submission failed validation and did not save settings.

Stage 12 follow-up results are recorded below. The Stage 11 stale-model tests exercise refreshed-state checks; Stage 12 adds independent-process MySQL contention checks. Stage 13 production deployment, HTTPS, backups, and operational configuration remain separate. Audit records are application records, not a claim of tamper-proof storage.

### Stage 12 implementation and verification

Completed November 7, 2026 for the mock-defense scope.

- **Payroll regression coverage:** Retained the documented PHP 45,000 computation example. Added contribution floor/ceiling cases, zero-tax and bracket boundaries, monthly rounding, version selection, inclusive period-end expiration, expired/future configuration, annual-versus-monthly table selection, separate LBP treatment, repeat-computation row counts, and daily employees with absence records.
- **Defects corrected:** Annual tax projection now chooses the newest effective annual table before matching an income bracket. Tax and contribution expiration use the period-end cutoff consistently. Daily employees with recorded absences no longer receive the full-month fallback reserved for missing attendance. The demo salary-step lookup retains Step 1 when Step 2 is also seeded. Existing configured percentages and the documented computation example are unchanged.
- **Workflow coverage:** The new HTTP walkthrough exercises the seeded Officer, Approver, and Viewer accounts through computation, submission, denied unauthorized actions, independent approval, finalization, blocked recomputation, the register, idempotent payslip generation, publication, and simulated hash verification. A test-only stored-amount change produces MISMATCH after an initial MATCH. Existing authentication, inactive-account, RBAC, approval, rejection/return, and mixed-role separation-of-duties tests remain in the full suite.
- **Repeatable demo fixtures:** The opt-in `DemoPayrollWorkflowSeeder` creates four named scenarios using the existing eight fictional employees and role accounts. It uses the computation/approval services, preserves existing runs on subsequent executions, and rejects production environments. It is deliberately separate from the default `DatabaseSeeder`; it does not anchor anything automatically.
- **MySQL contention:** Two separate PHP processes load the same pending run before approval; exactly one succeeds and the other receives 422, with one approval and audit event. Four parallel controller invocations create sequential run numbers without duplicates or lost audit events. A parent-held row lock and worker readiness barrier coordinate contention. These are bounded concurrency smoke checks, not HTTP load tests or production throughput measurements.
- **Database isolation:** Tests allow only in-memory SQLite or MySQL databases named with the `lgu_payroll_qa_` prefix. The guard checks the resolved connection, including connection-URL overrides, before test migrations run. Regression tests verify rejection of the development database, persistent SQLite, and an unsafe URL override. MySQL checks used the separate `lgu_payroll_qa_stage12` database; the development database was not migrated, reseeded, or changed.
- **Verification:** The final suite contains 200 tests. SQLite: 198 passed, 2 MySQL-only tests skipped, 832 assertions. Isolated MySQL: 200 passed, 850 assertions. Pint and the Vite production build passed. The existing optional `fontaine` build advisory remains; no dependencies were changed.

#### Reproducing QA

The normal command uses disposable in-memory SQLite:

```powershell
php artisan test --compact
```

For MySQL, provision an empty, disposable database named `lgu_payroll_qa_stage12` and use the existing local connection credentials. In a separate PowerShell session, with configuration caching disabled and no `DB_URL` overriding the connection:

```powershell
$env:DB_CONNECTION = 'mysql'
$env:DB_DATABASE = 'lgu_payroll_qa_stage12'
php artisan test --compact
```

The suite migrates this QA database, and the concurrency class runs `migrate:fresh` inside it. Its contents are disposable. To run only the MySQL contention checks in that same session:

```powershell
php artisan test --compact --group=mysql
```

#### Mock-defense walkthrough

Use a separate local demo/QA environment, not live payroll data. After fresh migrations and the default `DatabaseSeeder`, run the opt-in fixture seeder. Keep the app's blockchain driver set to `simulated`:

```powershell
php artisan db:seed --class=DemoPayrollWorkflowSeeder --no-interaction
```

| Scenario | Initial state | Demonstration |
|---|---|---|
| DEMO QA - Ready to prepare | DRAFT, January 2026 | Officer computes and submits; Approver reviews and finalizes. |
| DEMO QA - Pending review | PENDING_APPROVAL, February 2026 | Approver examines totals and approves, rejects, or returns with remarks. |
| DEMO QA - Returned for correction | RETURNED, March 2026 | Officer inspects the correction remark, adjusts inputs, recomputes, and resubmits. |
| DEMO QA - Finalized proof | FINALIZED, April 2026 | Show locked computation, register/export, payslips, and simulated anchoring/verification. |

1. Sign in as `viewer` and show read-only payroll/register access. Payroll preparation and approval requests must be denied.
2. Sign in as `officer`, open **Ready to prepare**, review employee inputs, compute all eight employees, inspect the register, and submit. The Officer cannot approve their own preparation. An account holding both preparation and approval roles is also blocked from reviewing its own run by the existing separation-of-duties tests.
3. Sign in as `approver`, review the pending run and its history, approve, then finalize. Return/reject demonstrations require remarks; use the separate pending scenario for those branches.
4. Open the finalized run. Recomputing must be blocked. Generate payslips twice and show that the second operation creates no duplicate payslips; publish one and open its printable view. Export the register and inspect the department summary.
5. As Officer or Approver, anchor the finalized run using the simulated driver and verify MATCH. Explain that only hash/metadata are anchored. The automated test demonstrates MISMATCH using isolated test data; do not edit finalized demo or live data to stage a mismatch.
6. As `admin`, inspect the audit trail and settings. Demo accounts use the existing development-only password `password`; they are not production credentials.

The HTTP walkthrough is automated; a human mock-defense rehearsal and the pending UI plan's mixed-role browser/screen-reader acceptance are not recorded as completed here. Demo attendance may be absent and therefore exercise the existing full-month fallback; it is not evidence of approved attendance. Current calculation code still uses rounded PHP floats, annual tax projection, and illustrative statutory data. Adopted rates, contribution coverage, withholding/annualization, allowance taxability, and paid-day policies need client confirmation before live payroll. Production-scale load testing and operational readiness remain outstanding; Stage 13 is not started.

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
