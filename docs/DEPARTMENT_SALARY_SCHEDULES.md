# Department salary schedule coverage

User-requested change, 2026-10-28. Salary schedule creation/edit now offers a
single **Covered department** dropdown: All departments, or one existing department.
It replaces the personnel-classification selector for new schedules.

Department names come from employees' Office / department values, including
inactive records, plus existing department-scoped schedules. No separate department
management feature or invented department list is added. Names are trimmed and
compared case-insensitively using the same normalization as the coverage key;
accent-distinct names remain separate regardless of database collation.

## Applicability

For an employee, effective date and data use, choose:

1. Latest published schedule for that employee's department, effective by the date.
2. Otherwise, latest published All departments schedule effective by the date.
3. Otherwise, the preserved legacy classification schedule, if one applies.

The user explicitly selected department-specific priority. A newer global schedule
therefore does not displace an older applicable department-specific one. Future
and unpublished schedules do not apply. Demonstration and externally authorized
data remain separate. Duplicate department/date/data-use schedules are rejected;
global and department-specific schedules may share an effective date.

Department coverage applies across personnel classifications. Employee salary
history still snapshots the employee's classification; contribution/tax review
rules remain category-specific and unchanged. Salary assignment rechecks the
actual employee department server-side; posting another department's rate fails.

Payroll source checks use the same priority and block actual within-month source
changes. An irrelevant other-department change or lower-priority global update
does not block a department-specific source. Changing an employee's department
can invalidate a department-specific salary source; an authorized applicable
assignment/update and current payroll coverage must be reviewed before use.

## Existing records and migration

Migration `2026_10_28_000001_add_department_coverage_to_salary_schedules` adds
nullable `department` and `coverage_key` plus a unique coverage/date/data-use index.
The old classification column becomes nullable, with existing values and old
uniqueness retained for legacy data. New saves clear classification and derive
a server-controlled scope key. No existing schedule is converted to All departments.

Published schedules remain immutable. Existing classification sources display
**Legacy classification: ...** and remain fallback sources. An unpublished legacy
draft can be explicitly edited to select new department coverage, with a notice
before saving/publishing. Existing employee salary amounts, finalized snapshots,
documents, commitments and verification history are not rewritten. Schema rollback
refuses to discard department schedules once they exist.

Run `php artisan migrate` in `application/` on other installations. The new
migration was applied on the current local MySQL; all 17 migrations are Ran.
Before/after hashes of existing columns across six source/history tables match.

## Verification

- Create Accounting and Human Resources employee records, then create/publish
  an All departments schedule and an Accounting-only schedule.
- Record salary for employees in both departments. Accounting must use its
  specific source; HR falls back to All departments. Repeat with different
  personnel classifications to confirm coverage is independent of classification.
- Publish a newer global schedule: Accounting must still resolve to its specific
  source. Attempt to assign an Accounting rate to HR; expect a validation error.
- Confirm old published classification schedules and employee salary history
  still display unchanged, and global/specific drafts show the selected coverage.

Fifteen new permanent department cases cover controls/validation, escaped and
accent-distinct names, effective-date/data-use filtering, specific priority,
cross-classification coverage, wrong-department rejection, department transfers,
case-only name edits without accidental global selection,
midmonth applicability, normalized duplicates, locked publication, legacy schema
round-trip preservation and retained-data rollback refusal. Focused department
coverage passes 15 cases / 158 assertions. Full results are in TESTING_STATUS.md.
The final full isolation run passes **591 tests / 7273 assertions**, including all
previous cases. Pint, JS syntax, production build and whitespace checks pass.

The MySQL salary verifier now exercises department-scoped schedules across all
three personnel classifications. Salary and payroll workflow verifiers pass with
fictional fixture rollback. Earlier classification fixtures remain in the suite
as legacy-regression cases; new header CRUD uses the department selector.

Fresh BrowserDemoSeeder setup now creates one shared All departments salary
schedule; existing seeded legacy sources/passwords/payroll history are preserved.
No real-payroll activation, statutory rule change, Phase 13, Besu integration,
commit or push is part of this change. Client authority/department conventions,
live browser/printing and real concurrent operations still need validation.
