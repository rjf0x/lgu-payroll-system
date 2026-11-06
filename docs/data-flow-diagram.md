# LGU Payroll Management System – Simplified Data Flow Diagram

This simplified DFD is intended for capstone documentation and mock-defense presentation. It focuses on the four system roles, the main system processes, the MySQL database, and blockchain verification.

## 1. DFD Level 0 – Context Diagram

The whole LGU Payroll Management System is represented as one process. The external entities exchange information with the system.

```mermaid
flowchart LR
    Admin[Admin]
    PayrollOfficer[Payroll Officer]
    Approver[Approver]
    Viewer[Viewer]
    System((LGU Payroll Management System))
    Blockchain[Blockchain Network]

    Admin -->|User accounts, roles, system settings| System
    System -->|Account and system information| Admin

    PayrollOfficer -->|Employee data, attendance, leave, allowances, deductions, payroll inputs| System
    System -->|Payroll register, computation results, payslips| PayrollOfficer

    Approver -->|Approval, rejection, or return decision| System
    System -->|Payroll for review and approval| Approver

    Viewer -->|View request| System
    System -->|Authorized records and reports| Viewer

    System -->|Finalized payroll hash| Blockchain
    Blockchain -->|Transaction reference and verification result| System
```

## 2. DFD Level 1 – Main System Processes

This diagram shows the main processes and the central MySQL data store.

```mermaid
flowchart TB
    Admin[Admin]
    PO[Payroll Officer]
    Approver[Approver]
    Viewer[Viewer]
    DB[(MySQL Database)]
    BC[Blockchain Network]

    P1([1. Manage Users and Employee Records])
    P2([2. Manage Compensation, Attendance and Leave])
    P3([3. Prepare and Calculate Monthly Payroll])
    P4([4. Review and Approve Payroll])
    P5([5. Generate Payroll Register, Payslips and Reports])
    P6([6. Verify Finalized Payroll])

    Admin -->|User and system settings| P1
    PO -->|Employee information| P1
    P1 <--> |User and employee records| DB

    PO -->|Salary, attendance and leave updates| P2
    P2 <--> |Compensation, attendance and leave records| DB

    PO -->|Monthly allowance and deduction inputs| P3
    DB -->|Employee, salary, attendance, leave and deduction data| P3
    P3 -->|Payroll computation and register data| DB
    P3 -->|Payroll for approval| P4

    P4 -->|Approval decision and remarks| DB
    Approver -->|Approve, reject or return| P4
    P4 -->|Approved or returned status| PO

    DB -->|Approved payroll and employee payroll details| P5
    P5 -->|Payroll register, payslips and reports| PO
    P5 -->|Authorized reports| Viewer
    P5 -->|Authorized reports| Approver

    DB -->|Finalized payroll data| P6
    P6 -->|Payroll hash| BC
    BC -->|Transaction reference / verification result| P6
    P6 -->|Verification record| DB
```

## 3. Simplified Monthly Payroll Process

This diagram focuses only on how a monthly payroll is prepared, approved, and finalized.

```mermaid
flowchart TD
    A[Retrieve employee and employment information] --> B[Retrieve salary grade, step and applicable salary]
    B --> C[Retrieve attendance and leave information]
    C --> D[Enter allowances manually]
    D --> E[Calculate total compensation]
    E --> F[Calculate tax and statutory contributions]
    F --> G[Enter loan, GABE and other deductions manually]
    G --> H[Calculate total deductions]
    H --> I[Calculate NTHP before LBP]
    I --> J[Enter LBP deduction manually]
    J --> K[Calculate net take-home pay]
    K --> L[Generate monthly payroll register]
    L --> M[Payroll Officer submits payroll]
    M --> N[Approver reviews payroll]
    N --> O{Decision}
    O -->|Return for correction| P[Payroll Officer corrects payroll]
    P --> E
    O -->|Approve| Q[Finalize payroll]
    Q --> R[Generate payslips and reports]
    Q --> S[Create and anchor payroll hash]
```

## 4. Main process descriptions

| No. | Process | Description |
|---:|---|---|
| 1 | Manage Users and Employee Records | Admin manages accounts and access; authorized staff maintain employee information. |
| 2 | Manage Compensation, Attendance and Leave | Maintains salary grade and step assignments, compensation profiles, day-based attendance, and leave information. |
| 3 | Prepare and Calculate Monthly Payroll | Retrieves employee and payroll data, accepts manual allowances and deductions, and calculates compensation and take-home pay. |
| 4 | Review and Approve Payroll | Approver independently reviews submitted payroll and approves, rejects, or returns it. |
| 5 | Generate Payroll Register, Payslips and Reports | Produces the monthly payroll register, employee payslips, and authorized reports. |
| 6 | Verify Finalized Payroll | Generates or checks a cryptographic hash and records blockchain transaction and verification information. |

## 5. Important business rules

1. Payroll is processed monthly.
2. The salary grade and step determine the monthly basic salary using the LGU-adopted salary schedule.
3. PERA, hazard pay, clothing, subsistence, laundry, and medical allowances are manually entered.
4. GABE deductions and loan deductions are manually entered by the Payroll Officer.
5. PhilHealth appears in the same monthly payroll register as the other deductions.
6. LBP is entered separately after calculating NTHP Before LBP; its exact meaning and arrangement should follow the client's confirmation.
7. The Payroll Officer who prepares and submits a payroll run cannot approve that same payroll run.
8. Only approved payroll can be finalized and proceed to payslip generation and blockchain anchoring.
9. Employee and payroll details remain in MySQL. Only the required cryptographic proof and transaction metadata are sent to the blockchain.

## 6. Short explanation for the mock defense

> The Data Flow Diagram illustrates how information moves through the LGU Payroll Management System. The Admin manages system access and employee records, while the Payroll Officer maintains payroll information and prepares the monthly payroll. The system retrieves compensation, attendance, and leave data, accepts manually entered allowances and deductions, and calculates the employee's total compensation and net take-home pay. The submitted payroll is independently reviewed by the Approver. Once approved and finalized, the system generates payslips and reports and may anchor a cryptographic hash to the blockchain for payroll integrity verification. The MySQL database stores the system's operational records.

## 7. Scope note

This is a simplified logical DFD for presentation. The detailed database design remains in the separate 39-table schema and ERD. Exact payroll rates, tax handling, contribution coverage, and the meaning of LBP should follow current official rules and the client's final instructions.
