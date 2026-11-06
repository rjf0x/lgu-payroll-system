# LGU Payroll Management System
## Simplified Use Case Diagram

This simplified diagram shows the main activities of the four system roles. Detailed sub-tasks are grouped so the diagram is easier to understand during the mock defense.

```mermaid
flowchart LR
    Admin([Admin])
    Officer([Payroll Officer])
    Approver([Approver])
    Viewer([Viewer])

    subgraph System[LGU Payroll Management System]
        Login([Log in])
        AdminTasks([Manage users, roles, and system settings])
        MasterData([Manage employee and employment records])
        PayrollInputs([Manage compensation, attendance, leave, earnings, and deductions])
        Prepare([Prepare and calculate monthly payroll])
        Register([Generate monthly payroll register])
        Submit([Submit payroll for approval])
        Review([Review submitted payroll])
        Decision([Approve, reject, or return payroll])
        Reports([View authorized records and reports])
        Verify([Verify finalized payroll integrity])
    end

    Admin --> Login
    Officer --> Login
    Approver --> Login
    Viewer --> Login

    Admin --> AdminTasks
    Admin --> MasterData
    Admin --> Reports
    Admin --> Verify

    Officer --> MasterData
    Officer --> PayrollInputs
    Officer --> Prepare
    Officer --> Register
    Officer --> Submit
    Officer --> Reports
    Officer --> Verify

    Approver --> Review
    Approver --> Decision
    Approver --> Reports
    Approver --> Verify

    Viewer --> Reports
```

## Role responsibilities

| Role | Main responsibilities |
|---|---|
| **Admin** | Manages users, roles, system settings, and authorized master records; can view reports and audit-related information. |
| **Payroll Officer** | Manages employee and payroll information, enters monthly allowances and manual deductions, records attendance and leave, prepares payroll, generates the register, and submits it for approval. |
| **Approver** | Independently reviews submitted payroll and approves, rejects, or returns it for correction. |
| **Viewer** | Views only the employee, payroll, and report information permitted by assigned access. |

## Main payroll approval flow

```mermaid
flowchart TD
    A[Payroll Officer prepares monthly payroll] --> B[System calculates payroll totals]
    B --> C[Payroll Officer submits payroll]
    C --> D[Approver reviews payroll]
    D --> E{Decision}
    E -->|Return or reject| F[Payroll sent back for correction]
    F --> A
    E -->|Approve| G[Payroll is approved]
    G --> H[Authorized finalization and payslip generation]
    H --> I[Blockchain hash anchoring and verification]
```

## Important business rule

The Payroll Officer who prepares and submits a payroll run must not approve that same payroll run. Enforce this separation of duties in the Laravel backend, not only by hiding or showing interface buttons. Only approved payroll may proceed to finalization.

## Notes

- All four roles must authenticate before accessing protected features.
- The Viewer role is read-only and must only see authorized information.
- Allowances and the client's specified manual deductions are entered by the Payroll Officer during monthly payroll preparation.
- Attendance is recorded by days, not hours.
- Salary grade and step determine the basic salary through the LGU-adopted salary schedule.
- Blockchain verification applies to finalized payroll data; confidential employee and payroll details remain in MySQL.
- This is a simplified, proposed use case model. Confirm the exact Admin permissions, report access, finalization responsibility, and blockchain verification access with the client before implementation.
