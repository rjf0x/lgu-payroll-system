# LGU Payroll Management System: Three-Person Workflow Script

**Estimated duration:** 10–12 minutes, excluding background registration wait time.

*Instructions in brackets describe what to show on screen.*

| Presenter | Assigned role | Coverage |
|---|---|---|
| Person 1 | Administrator | Introduction, accounts, employee setup, audit trail |
| Person 2 | Payroll Officer | Payroll inputs, computation, submission, corrections |
| Person 3 | Approver | Review, finalization, reports, payslips, verification |

## Before the demonstration

- Prepare fictional employees, approved demonstration inputs, effective compensation and statutory settings, and an unused full monthly period for one **Regular** run. Rehearse the expected totals and use separate browser profiles for the three accounts.
- Rehearse blockchain registration and fresh verification with the required services and workers running. Keep an existing confirmed fictional run available as clearly identified historical evidence if live registration is delayed.
- Generate and view payslips from the finalized run. Keep publication outside this demonstration while the publication lifecycle issue remains unresolved; publication status does not establish that a returned or corrected payroll is current.

## Person 1 — Introduction and system setup

*[Show the login page, then sign in as Administrator.]*

“Good day, everyone. Today, we will demonstrate the workflow of our LGU Payroll Management System.

The system manages employee records, computes payroll using approved inputs, supports independent approval, produces reports and payslips, and provides blockchain verification of finalized payroll records.

For this demonstration, we will perform three roles: Administrator, Payroll Officer, and Approver.”

*[Open Users.]*

“The workflow begins with account setup. The Administrator creates user accounts and assigns their roles.

The Payroll Officer prepares and submits payroll, while a separate Approver reviews and finalizes it. The default Administrator role manages accounts and configuration but cannot prepare or approve payroll.”

*[Show Departments, Positions, and Employees.]*

“Next, we set up departments and positions, then register employees.

Each employee needs an employment record identifying their employment type, department, position, and employment dates.”

*[Open an employee’s compensation profile.]*

“We also configure the employee’s compensation profile. This identifies their salary basis, applicable rate, and effective dates. Compensation may use a direct rate or a salary grade and step.

The system also maintains earning types, deduction types, tax brackets, and contribution rates used during payroll computation.

With the employee records and reference data ready, I will turn over the demonstration to our Payroll Officer.”

## Person 2 — Payroll preparation and computation

*[Sign in as Payroll Officer and open Payroll Periods.]*

“As the Payroll Officer, I begin by creating the payroll period. This specifies the coverage dates and pay date.”

*[Create or open the unused demonstration period, then create a Regular payroll run.]*

“Next, I create a Regular payroll run for this period. The run starts in Draft status.

Supplemental and adjustment runs require explicit employees, entitlement evidence, and confirmed Municipal Accounting Office tax and contribution treatment. They remain blocked from submission until the supported accounting policy is configured.

I click **Compute payroll** to generate the employee breakdown using the applicable employment and compensation records.”

*[Point to the employee input status indicators.]*

“The row indicators distinguish **Needs Input**, **Needs Confirmation**, **Needs Recomputation**, and **Confirmed**. Copied inputs require fresh confirmation for this period.

I use **Edit** for missing payable days or unresolved eligibility. For reviewed existing inputs, **Confirm & Save** confirms and recomputes them.”

*[Open an employee’s payroll detail.]*

“Before submission, I must enter and confirm the approved inputs for every employee.

These include approved payable days and any applicable earnings or deductions. The input reference is optional; I will enter one to identify our supporting document.

The current workflow uses approved payroll inputs entered by the officer. Attendance and leave are handled through those approved inputs rather than separate attendance and leave screens.”

*[Enter payable days, an input reference, an earning, and a deduction.]*

“For monthly pay, the system uses a 22-day equivalent. Any unpaid absence must be reflected in the approved payable days.

I then confirm that these entries match the approved source and click **Save & recompute**.”

*[Return to the payroll breakdown and point to the totals.]*

“The system calculates basic pay, earnings, applicable statutory contributions, withholding tax, and deductions using the configured reference data.

Gross pay includes basic pay and earnings. Deductions reduce the amount payable, and any separately recorded LBP loan deduction is applied to arrive at the final take-home pay.

Employer contributions are recorded separately and do not reduce the employee’s net pay.”

*[Review employee amounts and overall totals.]*

“I check each employee’s breakdown and reconcile the payroll totals with our approved worksheet.

Submission checks eligibility, effective compensation and statutory configuration, confirmed inputs, calculation freshness, consistent totals, and nonnegative net pay. Duplicate compensation for the same employee, component, and overlapping coverage is also checked across runs.

If a check fails, I correct the reported issue and recompute before submitting. Once the checks pass, I click **Submit for approval**.

The payroll status changes to Pending Approval. It now requires review by another authorized person.”

## Person 3 — Independent review and correction

*[Sign in as Approver and open the submitted run.]*

“As the Approver, I open the submitted payroll and review its coverage, employee breakdown, earnings, deductions, and totals against the supporting records.

The system enforces separation of duties. A person who created or submitted this payroll cannot review it, even if that person also has an Approver role.”

*[Show the review actions.]*

“I can approve the payroll, return it for correction, or reject it.

To demonstrate the correction process, I will enter a remark asking the officer to confirm one employee’s allowance, then click **Return**.”

## Person 2 — Correcting and resubmitting

*[Switch to Payroll Officer and open the returned run.]*

“The payroll now shows Returned status and displays the reviewer’s remarks.

I check the supporting document, make the required correction, confirm the inputs, and save to recompute the affected employee’s payroll.

After checking the updated totals, I submit the run again for independent approval.”

## Person 3 — Approval and finalization

*[Switch to Approver and reopen the submitted run.]*

“I review the correction and updated totals. Once everything is acceptable, I enter my review remarks and click **Approve**.

The payroll is now Approved. I then click **Finalize**.

Finalization locks the payroll against editing and recomputation. The normal status sequence is Draft, Pending Approval, Approved, and Finalized.

Financial finalization and blockchain confirmation are separate states. A finalized payroll can still have a queued or pending registration request.”

## Person 3 — Reports and payslips

*[Open the finalized payroll register.]*

“We can now open the payroll register to review employee earnings, deductions, and take-home pay. The register can be printed or exported as an Excel **XLSX** spreadsheet.

Historical reports use saved component labels and amounts, so later reference-data changes preserve the finalized record. Identifiers distinguish components with the same name. Any reconciliation differences are displayed without changing saved totals.”

*[Open the department summary and deduction report.]*

“The department summary groups payroll totals by department. The deduction report provides totals and employee breakdowns for recorded deductions.”

*[Open Payslips for the finalized run and select Generate payslips. If generation is queued, refresh after the reports worker finishes. Select View for one generated payslip.]*

“Next, I generate payslips from this finalized run and open a printable statement. Each payslip shows the employee’s earnings, deductions, and net pay.

I compare these amounts with the same employee’s register entry. Generation and publication are separate actions; today we are demonstrating generation and viewing.”

## Person 3 — Payroll verification

*[Open Payroll Verification for the demonstrated finalized run and show its actual registration status. Continue to fresh verification once registration is confirmed.]*

“When blockchain registration is enabled and configured, finalization captures a registration request for background processing.

The system registers a cryptographic hash—a digital fingerprint of the payroll—with Hyperledger Besu. Employee names, salary details, and payslip contents are not placed on the blockchain.”

*[For a confirmed registration, select the available verification action and show its new result and transaction evidence.]*

“Verification compares a newly calculated payroll hash against the registered blockchain evidence.

A **MATCH** means the payroll data covered by that hash matches the registered record. A **MISMATCH** means those records differ and require investigation.

Pending registration gives no integrity conclusion. **UNAVAILABLE** means verification could not complete; we must retry when the service is available. A network health indicator alone does not establish a MATCH.

This checks record integrity. Payroll accuracy still depends on correct inputs, configuration, and review.”

*[If registration remains pending or verification is unavailable, state the observed condition. Show the prepared confirmed run only as historical evidence, and distinguish an old result from a fresh verification. Record the demonstrated run’s live verification as incomplete until its registration is confirmed and a new MATCH is obtained.]*

## Person 1 — Audit trail and closing

*[Sign in as Administrator and open Audit Logs.]*

“Finally, the Administrator can review the audit trail.

Here we can trace the demonstrated payroll submission, review decisions, finalization, and verification actions, including who performed them and when. Audit coverage for other changes is still being expanded.

This completes our demonstration: employee and compensation setup, payroll preparation, independent review, finalization, reports and payslips, and integrity verification.

Thank you.”
