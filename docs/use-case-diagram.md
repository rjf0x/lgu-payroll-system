# LGU Payroll Management System

## Use case diagram

This diagram reflects the implemented routes and default role permissions. Ovals represent use cases; lines connect each role to its available actions. All protected actions require login and an active account. Users with multiple roles receive their combined permissions, subject to independent-review rules.

```mermaid
flowchart LR
    Admin["Admin"]
    Officer["Payroll Officer"]
    Approver["Approver"]
    Viewer["Viewer"]

    subgraph System["LGU Payroll Management System"]
        Accounts([Manage users and assign roles])
        Settings([Manage system settings])
        Audit([View and export audit trail])
        Organization([Manage departments and positions])
        Employees([Manage employees and employment records])
        Compensation([Manage salary schedules, grade steps and compensation profiles])
        Configuration([Manage earnings, deductions, tax brackets and contribution rates])
        Periods([Manage payroll periods])
        Prepare([Confirm approved inputs and compute payroll])
        Submit([Submit payroll for approval])
        Review([Review, approve, reject or return payroll])
        Finalize([Finalize approved payroll])
        Read([View dashboard, employee records and payroll])
        Reports([View registers, existing payslips, summaries and deduction reports])
        Documents([Export payroll spreadsheet])
        Payslips([Generate or publish payslips])
        Verify([Payroll Verification])
    end

    Admin --- Accounts
    Admin --- Settings
    Admin --- Audit
    Admin --- Organization
    Admin --- Employees
    Admin --- Compensation
    Admin --- Configuration
    Admin --- Read
    Admin --- Reports
    Admin --- Documents
    Admin --- Payslips
    Admin --- Verify

    Officer --- Organization
    Officer --- Employees
    Officer --- Compensation
    Officer --- Configuration
    Officer --- Periods
    Officer --- Prepare
    Officer --- Submit
    Officer --- Read
    Officer --- Reports
    Officer --- Documents
    Officer --- Payslips
    Officer --- Verify

    Approver --- Review
    Approver --- Finalize
    Approver --- Read
    Approver --- Reports
    Approver --- Documents
    Approver --- Payslips
    Approver --- Verify

    Viewer --- Read
    Viewer --- Reports
    Viewer --- Documents
```

## Role responsibilities

| Role | Implemented responsibilities |
|---|---|
| **Admin** | Manages users, role assignments, settings, organization records, employees, compensation and payroll reference data. Views and exports the audit trail, accesses reports and payslips, and verifies payroll. The default Admin role cannot prepare, approve or finalize payroll. |
| **Payroll Officer** | Maintains organization and employee records, compensation, earnings, deductions and statutory configuration. Manages payroll periods, confirms approved inputs, computes payroll and submits it for independent approval. Accesses reports, payslips and Payroll Verification. |
| **Approver** | Reviews submitted payroll; approves, rejects or returns it for correction; and finalizes approved payroll. Views records, accesses reports and payslips, and verifies payroll. |
| **Viewer** | Views employee and payroll records, existing payslips, registers, department summaries and deduction reports. Exports spreadsheets through `reports.export`. Cannot generate/publish payslips, edit payroll inputs, approve or finalize payroll, or use Payroll Verification. |

## Payroll and verification workflow

```mermaid
flowchart TD
    A["Officer creates a payroll period and run"] --> B["Record and confirm approved inputs for every employee"]
    B --> C["Compute and check payroll"]
    C --> D["Submit for independent approval"]
    D --> E{"Approver decision"}
    E -->|"Return or reject"| F["Officer corrects and recomputes payroll"]
    F --> D
    E -->|"Approve"| G["Approver finalizes approved payroll"]
    G --> H["Save finalized payroll and capture registration request atomically"]
    G --> R["View payroll register, department summary and deduction reports"]
    R --> P["Print reports, export spreadsheet, generate and publish payslips"]
    H --> I["Scheduler dispatches registration to queue worker"]
    I --> J["Node bridge requests signing through Web3Signer"]
    J --> K["Submit payroll hash to Hyperledger Besu"]
    K --> L["Worker reconciles blockchain evidence and confirms registration"]
    L --> M["Authorized user selects Verify current payroll"]
    M --> N["Compare current payroll hash with blockchain evidence"]
    N --> O["Record verification result and audit history"]
```

## Included report and configuration features

- **Employment records:** Permanent, Coterminous and Elective employment types.
- **Compensation:** salary schedules, salary grade steps and employee compensation profiles.
- **Payroll inputs:** approved payable days and supporting references, earnings and deductions. Attendance and leave management are not separate screens in the current interface.
- **Deduction reports:** totals by deduction type, employee breakdowns, selection of an individual deduction and printing. Reports use saved payroll names and amounts, including inactive deduction types recorded in that payroll.
- **Payroll reports:** register, department summary, spreadsheet export and payslips. Reports for payroll that is not finalized display a working-copy notice.
- **Payroll Verification:** displays registration status and records a fresh integrity check when an authorized user requests verification.

## Business and integrity rules

- The preparer or submitter cannot approve, reject or return that same payroll run, even when assigned multiple roles.
- Only approved payroll can be finalized. Finalized payroll is locked against normal input editing and recomputation.
- With registry capture enabled, finalization saves the payroll and registration request in one database transaction. Blockchain submission and confirmation occur asynchronously.
- A confirmed registration and a verification result are separate states. Verification returns **MATCH**, **MISMATCH**, **PENDING**, **UNAVAILABLE**, **NOT REGISTERED** or **INVALID EVIDENCE**.
- Verification compares saved payroll values with the registered hash. Changes to current compensation profiles or salary schedules do not rewrite the compensation snapshots in an existing finalized payroll.
- Confidential employee and payroll details remain in MySQL. Besu stores an opaque payroll reference, revision, SHA-256 hash and supporting metadata.
- A match establishes consistency with the registered payroll version; it does not certify calculation accuracy or release salary payments.

Implementation references: `routes/web.php`, `database/seeders/RolePermissionSeeder.php`, `app/Services/PayrollApprovalService.php`, `app/Services/PayrollRegistrationService.php` and `app/Services/PayrollVerificationService.php`.
