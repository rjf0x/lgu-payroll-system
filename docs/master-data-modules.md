# Master Data Modules (Stage 3)

**Stage:** 3 of the development plan
**Status:** Implemented for the mock-defense build
**Related:** `database-implementation.md`, `authentication-authorization.md`

---

## 1. Scope

Admin and Payroll Officer management of the organizational, employee, and compensation
master data:

| Resource | Route base | Model |
|---|---|---|
| Departments | `/departments` | `Department` |
| Positions | `/positions` | `Position` |
| Employees | `/employees` | `Employee` |
| Employment records | `/employment-records` | `EmploymentRecord` |
| Salary schedules | `/salary-schedules` | `SalarySchedule` |
| Salary grade steps | `/salary-grade-steps` | `SalaryGradeStep` |
| Compensation profiles | `/compensation-profiles` | `CompensationProfile` |
| User accounts | `/users` | `User` |

---

## 2. Authorization

Route protection uses the RBAC gates from Stage 2 via the `can:` middleware:

| Access | Permission |
|---|---|
| Read (index/show) | `employees.view` |
| Write — organization & employment | `employees.manage` |
| Write — salary schedules, grade steps, compensation profiles | `compensation.manage` |
| User accounts (all actions) | `users.manage` |

So a **Viewer** can browse read-only pages but receives `403` on any create/edit route,
and user management is restricted to **Admin**.

---

## 3. Validation

Each resource has a `FormRequest` under `app/Http/Requests/`:

- Unique business keys: `department_code`, `position_code`, `employee_number`, `username`.
- Foreign keys validated with `exists:` (`department_id`, `position_id`, `employment_type_id`, `salary_schedule_id`, …).
- Date ranges: `effective_to` / `end_date` must not precede their start.
- `salary_grade` bounded `1–33`; grade steps enforce a composite unique key of
  `(salary_schedule_id, grade_number, step_number)`.

### Compensation profile rules (the important ones)

`CompensationProfileRequest` adds post-validation checks so a profile always maps to a
valid salary:

1. **Basis:** either `GRADE_STEP` (requires `grade_step_id`) or `DIRECT_RATE`
   (requires `basic_rate`).
2. **Grade match:** when a grade step is chosen, its `grade_number` must equal the
   position's `salary_grade` on the selected employment record.
3. **Schedule effectiveness:** the grade step's salary schedule must be `ACTIVE` and
   cover the profile's `effective_from`.
4. **No overlap:** an employment record may not have two compensation profiles whose
   effective periods overlap (open-ended when `effective_to` is null).

### Appointment consistency (F10)

`EmploymentRecordRequest` checks related fields after basic field/date validation. `EmploymentRecordValidationService` repeats the same checks inside the audited save transaction:

- A position must belong to the selected department.
- An existing appointment keeps its employee identity, preserving compensation, employment history and saved payroll references. Create a separate appointment rather than reassigning one to another employee.
- New appointments cannot select archived employees, including when the proposed appointment is inactive. An existing appointment for an archived employee can be made inactive and corrected for history; it cannot remain active or be reactivated through an appointment update. Its archived employee stays visible and labeled on edit, while new forms exclude archived employees.
- ACTIVE appointments for the same employee cannot overlap, across departments and employment types. Start/end dates are inclusive, and a missing end date is open-ended. A succeeding appointment starts after the earlier appointment ends. Updates exclude the appointment being edited and also check reactivation/date extensions.
- Inactive historical records may overlap. Separate nonoverlapping appointments and consistent transfers remain supported and audited. Concurrent dual appointments have no authorization policy or bypass in this build and are rejected.

Saves lock the employee before validating/writing the appointment, including the first appointment where no conflicting rows exist yet. Updates use the same employee-first lock order, reload the current appointment, and recheck position membership and overlap with current locked reads. This also coordinates with employee archival. History and source audit events remain in the same transaction; rejected saves create neither.

These rules govern the exposed appointment create/update paths and the shared mutation service. They do not backfill or repair legacy inconsistent records, direct database/model writes, or downstream organization changes. F04's independent employee/coverage compensation guard remains enforced; sequential source appointments do not authorize duplicate regular pay within a payroll period. No dual-appointment compensation or retrospective entitlement policy is introduced.

---

## 4. Controllers & routes

Resource controllers live in `app/Http/Controllers/`. Routes are registered in
`routes/web.php`; each resource applies the read permission to `index`/`show` and the
write permission to the mutating actions using `middlewareFor()`:

```php
Route::resource('departments', DepartmentController::class)
    ->middlewareFor(['index', 'show'], 'can:employees.view')
    ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:employees.manage');
```

Implicit route model binding resolves custom primary keys automatically (e.g.
`/employees/{employee}` → `employees.employee_id`).

---

## 5. UI

- **Layout:** `resources/views/layouts/app.blade.php` — a Tailwind sidebar shell with
  role-aware navigation (`@can`), a flash/error area, and `@vite` asset loading.
- **Components:**
  - `x-nav-link` — active-aware sidebar links.
  - `x-status` — ACTIVE/INACTIVE pill.
  - `x-form.input`, `x-form.select`, `x-form.textarea` — labeled fields that show
    `old()` values and inline validation errors.
- **Per resource:** `index`, `create`, `edit`, `show`, and a shared `_form` partial.
- The dashboard now lists the signed-in user's roles and permissions.

### Frontend build

The authenticated layout loads `resources/css/app.css` and `resources/js/app.js`
through Vite. Run one of:

```bash
npm install          # first time
npm run build        # production assets (required if not running the Vite dev server)
npm run dev          # or the Vite dev server during development
```

The login screen is standalone HTML and does not require the build.

---

## 6. Tests

| Test | Coverage |
|---|---|
| `tests/Feature/MasterDataTest.php` | Department CRUD + unique code, Viewer read-only vs `403` on create, employee create, employment record create, grade-step composite uniqueness, employee soft delete. |
| `tests/Feature/CompensationProfileValidationTest.php` | Grade match accept/reject, direct-rate requirement, schedule effective-date rule, overlap reject, non-overlapping accept. |
| `tests/Feature/EmploymentRecordValidationTest.php` | Department/position consistency, inclusive overlap boundaries, sequential/inactive history, archival, identity preservation, transaction rechecks, permission denial, retained form input and audit/history effects. |
| F10 cases in `tests/Feature/PayrollConcurrencyTest.php` | MySQL contention for first appointment creation, overlapping reactivation and archival before a waiting save; requires a dedicated `lgu_payroll_qa_` database. |
| `tests/Feature/UserManagementTest.php` | Admin creates a user with a role, non-admin forbidden, self-delete guard, at-least-one-role rule. |

Run with `php artisan test --compact`.
