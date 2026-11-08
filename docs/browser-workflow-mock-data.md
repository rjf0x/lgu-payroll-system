# Browser Workflow and Mock Data

Use this walkthrough for the first browser test on a clean database. All names and amounts are **fictional demo data**.

Workflow: Admin setup → Employees → Employment → Compensation → Payroll preparation → Independent approval → Finalization → Reports and payslips.

## 1. Create the initial administrator

If an administrator has not been created yet, run from the project directory:

```powershell
php artisan app:create-admin
```

Enter a username, email, and password when prompted. The password requires at least 16 characters with uppercase, lowercase, numbers, and symbols.

Open the application's login page in the browser and sign in with those credentials. For the default local server, this is `http://localhost:8000/login`; use the configured address if different.

## 2. Create the payroll users

As Admin, click **Add User** on the dashboard and create these accounts:

| Field | Payroll preparer | Reviewer |
|---|---|---|
| Username | `demo.officer` | `demo.approver` |
| Email | `officer@example.test` | `approver@example.test` |
| Password | `DemoOfficer2026!Safe` | `DemoApprover2026!Safe` |
| Role | Payroll Officer | Approver |
| Account Status | Active | Active |
| Linked Employee | Leave blank | Leave blank |

Use these passwords only for a local demonstration.

The Admin role manages accounts and configuration. Use the Payroll Officer account to prepare payroll and a separate Approver account to approve it. The creator or submitter cannot review the same run.

## 3. Create a department and position

As Admin, open **Departments** and create:

| Field | Value |
|---|---|
| Code | `DEMO-ADMIN` |
| Name | Demo Administrative Office |
| Status | Active |
| Description | Fictional department for payroll testing |

Next, open **Positions** and create:

| Field | Value |
|---|---|
| Department | Demo Administrative Office |
| Code | `DEMO-CLERK` |
| Title | Administrative Clerk |
| Salary Grade | Leave blank |
| Status | Active |
| Description | Fictional position using a direct monthly rate |

This walkthrough uses **Direct rate**, so a salary schedule and grade steps are not required.

## 4. Create two employees

Open **Employees** and create:

| Field | Employee 1 | Employee 2 |
|---|---|---|
| Employee Number | `DEMO-001` | `DEMO-002` |
| First Name | Maria | Jose |
| Middle Name | Santos | Reyes |
| Last Name | Dela Cruz | Bautista |
| Birth Date | `1995-05-15` | `1993-08-20` |
| Sex | Female | Male |
| Civil Status | Single | Married |
| Email | `maria@example.test` | `jose@example.test` |
| Address | Demo Street, Sample Municipality | Sample Avenue, Sample Municipality |

Leave suffix and contact number blank.

## 5. Add employment records

Open each employee's employment section, or open **Employment Records**, and create:

| Field | Maria | Jose |
|---|---|---|
| Employee | DEMO-001 — Maria | DEMO-002 — Jose |
| Employment Type | Permanent | Permanent |
| Department | Demo Administrative Office | Demo Administrative Office |
| Position | Administrative Clerk | Administrative Clerk |
| Appointment Number | `DEMO-APPT-001` | `DEMO-APPT-002` |
| Employment Status | Active | Active |
| Start Date | `2026-11-01` | `2026-11-01` |
| End Date | Leave blank | Leave blank |

The initial administrator setup seeds the employment types.

## 6. Add compensation profiles

Open **Compensation Profiles** and create one for each employment record:

| Field | Maria | Jose |
|---|---|---|
| Employment Record | Maria's employment | Jose's employment |
| Compensation Basis | Direct rate | Direct rate |
| Basic Rate | `22000.00` | `30000.00` |
| Service Fraction | `1.0000` | `1.0000` |
| Pay Basis | Monthly | Monthly |
| Effective From | `2026-11-01` | `2026-11-01` |
| Effective To | Leave blank | Leave blank |

## 7. Add a sample earning and deduction

Open **Earning Types** and create:

| Field | Value |
|---|---|
| Name | Demo Allowance |
| Category | `ALLOWANCE` |
| Computation Type | Manual entry |
| Taxable | No |
| Recurring | No |
| Status | Active |

Open **Deduction Types** and create:

| Field | Value |
|---|---|
| Name | Demo Cooperative Loan |
| Category | `LOAN` |
| Computation Type | Manual entry |
| Statutory | No |
| Pre-tax | No |
| Status | Active |

For this first test, leave **Contribution Rates** and **Tax Brackets** empty. The expected amounts below exclude statutory contributions and withholding tax. This is a workflow demonstration; real payroll requires the approved configuration.

## 8. Create a payroll period and run

Log out and sign in as `demo.officer`.

Open **Payroll Periods** and create:

| Field | Value |
|---|---|
| Period Name | DEMO — November 2026 |
| Payroll Type | Monthly |
| Start Date | `2026-11-01` |
| End Date | `2026-11-30` |
| Pay Date | `2026-11-30` |
| Status | Open |

Next, open **Payroll Runs** and create:

| Field | Value |
|---|---|
| Payroll Period | DEMO — November 2026 |
| Run Type | Regular |

Click **Create run**, then **Compute payroll**.

## 9. Enter and confirm payroll inputs

In the employee breakdown, click **Edit** for each employee:

| Field | Maria | Jose |
|---|---|---|
| Approved payable days | `22` | `22` |
| Approved input reference / reason | `DEMO-NOV-001 — fictional full-month approval` | `DEMO-NOV-002 — fictional full-month approval` |
| Demo Allowance | `2000.00` | `1500.00` |
| Demo Cooperative Loan | `500.00` | `1000.00` |
| LBP Loan Deduction | `0.00` | `0.00` |
| Confirmation checkbox | Checked | Checked |

Click **Save & recompute** for each employee.

The system uses a **22-day equivalent for monthly pay**. The first computation may show zero basic pay until payable days are entered. Reflect unpaid absences in payable days; do not deduct the same absence again under Deductions.

With only the mock configuration above, expect:

| Result | Maria | Jose | Total |
|---|---:|---:|---:|
| Basic Pay | ₱22,000.00 | ₱30,000.00 | ₱52,000.00 |
| Earnings | ₱2,000.00 | ₱1,500.00 | ₱3,500.00 |
| Gross Pay | ₱24,000.00 | ₱31,500.00 | ₱55,500.00 |
| Deductions | ₱500.00 | ₱1,000.00 | ₱1,500.00 |
| Net Pay | **₱23,500.00** | **₱30,500.00** | **₱54,000.00** |

Return to the run and click **Submit for approval**.

## 10. Review, approve, and finalize

Log out and sign in as `demo.approver`.

Open the submitted payroll run, check both employees and the totals, and enter these review remarks:

> Reviewed fictional November payroll inputs and totals for demonstration.

Click **Approve**, then **Finalize**.

The normal status sequence is:

```text
DRAFT → PENDING_APPROVAL → APPROVED → FINALIZED
```

To test corrections before approval, enter a reason such as “Please confirm Maria's allowance” and click **Return**. The officer can correct the run and submit it again.

## 11. View reports and payslips

From the payroll run:

- **Register:** Review the complete payroll and export CSV.
- **Summary:** Review department totals.
- **Payslips:** Click **Generate payslips**, then **View** and **Publish** for each employee.
- **Print:** Use the available print controls for the register or payslip.

Check **Audit Logs** as Admin to review recorded actions.

If blockchain anchoring is configured, anchor the finalized run and verify it. A simulated driver provides a demonstration rather than a live Besu transaction.
