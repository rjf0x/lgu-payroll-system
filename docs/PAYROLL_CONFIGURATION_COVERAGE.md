# Payroll configuration coverage

As Administrator, open **Payroll configuration → Create configuration draft**.
Select All departments or one recorded Office / department. Independently select
All classifications, Permanent, Coterminous or Part-time. Both All selections
create one shared configuration for everyone. Review the parameter values,
source references, data use and first-of-month effective date before saving.

Drafts can be revised. Activation records the existing approval/review references
and locks the version. Copy an activated version to create a separately dated
change. One activated version is allowed per exact department/classification
pair, effective date and data use; different coverage pairs may share a date.

The user-selected order for new reference reviews is:

1. Employee department and classification.
2. Employee department and All classifications.
3. All departments and employee classification.
4. All departments and All classifications.

The latest effective activated version within the first matching level wins.
For example, Accounting / All classifications takes priority over All departments
/ Permanent for a Permanent employee in Accounting, even if the global version
has a later effective date. Drafts, future versions and the other data-use mode
are excluded. Without a maintained match, the existing unverified built-in
reference profile remains subject to its review-year restriction.

Departments come from saved calculation inputs. A department transfer requires
the existing source-refresh and recalculation workflow; posted scope fields do
not change an employee's coverage. Existing saved reviews retain their original
values. If activation changes the selected profile, reopen and explicitly save
each affected contribution/withholding review before submission or approval.
Finalized payroll and its documents retain the approved snapshot.

Apply `php artisan migrate` from `application/` before using the forms. The new
migration preserves existing configuration coverage as All departments / its
original classification and adds scope keys without rewriting old parameters,
events or creation fingerprints. Rollback refuses to discard department or
All-classification configurations.

All-classification coverage leaves each classification's statutory applicability
unverified. Existing contribution methods, rounding, approvals and real-payroll
restrictions remain in force. This change does not advance the development phase
or add blockchain connectivity.
