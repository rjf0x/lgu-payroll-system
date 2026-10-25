# MASTER PROMPT — PHASED DEVELOPMENT OF AN LGU PAYROLL MANAGEMENT SYSTEM WITH BLOCKCHAIN INTEGRATION

## ROLE AND OBJECTIVE

Act as a **Senior Full-Stack Software Engineer, Software Architect, Database Designer, QA Engineer, and Security Engineer**.

Your task is to develop a functional prototype of a **Payroll Management System with Blockchain-Based Integrity Verification** for a Philippine Local Government Unit (LGU), specifically its Accounting Office.

This project is being developed for an upcoming academic mock defense requiring approximately **70%–90% functional system completion**.

Prioritize functionality, accuracy, maintainability, security, and a complete working demonstration.

**CRITICAL INSTRUCTION: DO NOT DEVELOP THE ENTIRE SYSTEM IN ONE EXECUTION.**

Divide development into small, independently reviewable phases. Implement only the currently authorized phase.

After completing each phase, STOP and wait for my instruction before continuing.

---

# 1. DEVELOPMENT RULES

Follow these instructions throughout development.

### Rule 1: One Phase at a Time

- Implement only the current phase.
- Do not automatically continue to the next phase.
- Do not implement future-phase features unless a minimal dependency is required.
- Avoid large changes that are difficult to review.
- If a phase is too large, divide it into smaller subphases.

### Rule 2: Inspect Before Modifying

Before implementing anything:

1. Inspect relevant existing project files.
2. Identify the current architecture and conventions.
3. Reuse existing working functionality.
4. Avoid unnecessary restructuring.
5. Identify dependencies that must be satisfied.

Do not recreate features that already work.

### Rule 3: Maintain Code Quality

- Follow established framework conventions.
- Use clear, maintainable code.
- Separate business logic from UI code.
- Avoid unnecessary abstraction.
- Validate all inputs.
- Enforce authorization on the server.
- Use database transactions where appropriate.
- Prevent duplicate submissions.
- Keep code modular and testable.

### Rule 4: Testing Is Mandatory

For every phase:

1. Implement the required functionality.
2. Write relevant automated tests.
3. Run the tests.
4. Fix failures caused by the phase.
5. Verify that existing functionality still works.

Never claim a test passed unless it actually ran successfully.

### Rule 5: Stop and Report

At the end of each phase, provide:

- Phase name
- Implemented features
- Files created or modified
- Database changes
- Tests executed and results
- Manual verification instructions
- Known issues
- Remaining unverified requirements
- Recommended next phase

Keep this report concise.

Wait until I say **"Proceed to the next phase."**

### Rule 6: Preserve Progress

Maintain these project documents:

- `docs/DEVELOPMENT_PROGRESS.md`
- `docs/REQUIREMENTS_STATUS.md`
- `docs/DECISIONS.md`
- `docs/TESTING_STATUS.md`

Update only the relevant information after completing a phase.

Use a consistent status for each requirement:

- Implemented and tested
- Implemented but needs validation
- In progress
- Not started
- Blocked by client validation

Do not calculate system completion solely from the number of screens.

### Rule 7: Handle Unverified Payroll Rules Correctly

Some payroll calculations have not yet been validated by the client.

- Never invent statutory contribution formulas.
- Never assume all personnel categories have identical rules.
- Distinguish official regulatory requirements from reported client practices.
- Use clearly labeled demonstration data when needed.
- Do not silently apply provisional rates to actual payroll.
- Preserve unresolved business rules in the requirements register.
- Keep unverified calculations inactive for real payroll processing.

---

# 2. PROJECT REQUIREMENTS

## 2.1 Primary Purpose

Automate payroll calculations while supporting employee records, configurable salary rules, payroll approval, document generation, and blockchain verification of finalized payroll batches.

## 2.2 Personnel Categories

The system must accommodate:

- Permanent employees
- Coterminous employees
- Part-time employees

Specific compensation rules for each category must follow validated requirements.

## 2.3 User Roles

**Administrator**
- Manage users and roles.
- Prepare payroll calculation configurations.
- Maintain approved system settings.

**Payroll Officer**
- Manage payroll-related employee information.
- Prepare monthly payroll.
- Enter allowances, bonuses, loans, and adjustments.
- Calculate and review payroll.
- Submit payroll for approval.

**Approver**
- Review submitted payroll.
- Approve or return payroll for correction.
- Review sensitive configuration changes when authorized.

**Viewer**
- View authorized payroll records.
- View reports and integrity verification results.
- Cannot modify payroll information.

Enforce separation of duties between payroll preparation and approval.

---

# 3. SYSTEM MODULES

The complete proposed prototype contains these modules:

1. Authentication and Role-Based Access Control
2. Employee Management
3. Salary Grade and Step Management
4. Monthly Payroll Preparation
5. Earnings and Allowance Entry
6. Loan and Other Deduction Entry
7. Payroll Calculation Engine
8. Configurable Payroll Rules
9. Payroll Review and Approval
10. Payroll Registers and Payslips
11. Payroll History and Audit
12. Blockchain Integrity Verification

Do not expand the scope into a complete HR management or financial disbursement system.

---

# 4. PAYROLL BUSINESS RULES

## Basic Salary

- Employee compensation is generally based on an authorized monthly salary.
- Support LGU-approved salary grade and step schedules.
- Salary step increments must be distinguished from promotions.
- Potential three-year step eligibility may be identified automatically.
- Actual salary changes require appropriate external authorization.
- Preserve salary history and effective dates.

No supporting-document upload module is required.

## Allowances

Allowances shall be entered manually for each applicable employee.

Examples:

- PERA
- Hazard Pay
- Clothing Allowance
- Subsistence Allowance
- Laundry Allowance
- Medical Allowance

The system must not automatically assume all employees receive the same allowances.

Calculate total applicable allowances automatically from entered amounts.

Support authorized bonuses and additional earnings.

## Contributions

Support configurable, validated calculations for:

- GSIS
- Pag-IBIG
- PhilHealth

Preliminary client information:

- GSIS employee contribution: reported as 9% of basic monthly salary.
- Pag-IBIG: reported as ₱200 per month for eligible employees.
- PhilHealth: applicability and operational procedures remain unresolved.

These reports must not be treated as complete universal rules.

## Withholding Tax

Support withholding calculations based on applicable BIR rules.

Important verified requirement:

**Taxable excess bonuses must be included in annual taxable compensation.**

A client worksheet previously omitted a reported ₱45,000 taxable excess from its displayed taxable compensation calculation.

Do not hardcode that example amount.

Periodic withholding, annualization, bonus treatment, year-end reconciliation, and applicable exemptions must be validated.

## Loans

Payroll Officers shall manually enter authorized loan deduction amounts.

Examples:

- GSIS loans
- Pag-IBIG loans
- Bank loans
- Other authorized loans

Do not implement:

- Loan amortization scheduling
- Loan due-date scheduling
- Outstanding loan balance management

## Absences and Undertime

Support:

- Automatic calculations using approved rules.
- Controlled manual deduction adjustments.
- Documented reasons for overrides.
- Appropriate authorization.
- Preservation of original and adjusted values.

Actual formulas remain pending validation.

## Payroll Totals

The system must automatically calculate:

- Total earnings
- Applicable statutory contributions
- Taxable compensation
- Withholding tax
- Total loan deductions
- Total deductions
- Net pay

Use decimal-safe monetary calculations and defined rounding behavior.

---

# 5. BLOCKCHAIN REQUIREMENTS

Blockchain shall be used only for integrity verification of finalized payroll records.

### Anchoring Strategy

Use **one cryptographic hash for each finalized payroll batch**.

### Proposed Workflow

1. Payroll Officer prepares and calculates payroll.
2. Approver reviews and approves payroll.
3. Authorized user finalizes payroll.
4. System creates a fixed payroll snapshot.
5. System generates a SHA-256 hash.
6. System submits the hash to an authorized blockchain network.
7. System records the confirmed transaction reference.
8. System verifies the blockchain commitment against the finalized snapshot.

### Security Requirements

- Never place employee names, salaries, loans, or other confidential payroll details directly on-chain.
- Preserve the original finalized payroll snapshot.
- Use deterministic snapshot serialization.
- Record blockchain transaction identifiers.
- Support independent integrity verification.
- Detect mismatches.
- Distinguish payroll status from blockchain status.
- Do not label unconfirmed transactions as verified.
- Support safe retry of unsuccessful anchoring.
- Preserve previous finalized versions when corrections occur.

### Important

Implement genuine blockchain integration when a suitable test network is available.

A locally calculated hash or simulated transaction must never be represented as a successful blockchain anchor.

Blockchain network governance and outage behavior are pending validation.

---

# 6. TECHNICAL APPROACH

First inspect the existing project.

If an application already exists, preserve its framework, conventions, and working functionality.

If the project is empty, recommend an implementation stack suitable for a browser-based LGU payroll application.

A practical provisional default is:

- Laravel / PHP
- Blade templates
- Tailwind CSS
- MySQL
- Framework-compatible authentication
- Automated backend and feature tests
- A private blockchain integration evaluated during the blockchain phase

Do not introduce unnecessary frameworks or services.

Prefer a modular application that can operate on an internal office network without requiring internet access for normal payroll functions, subject to deployment validation.

---

# 7. DEVELOPMENT PHASES

Complete the following phases sequentially.

## PHASE 0 — PROJECT FOUNDATION

Goal: Establish a working development environment.

Tasks:
- Inspect or initialize the project.
- Verify dependencies and database connectivity.
- Prepare environment configuration.
- Establish directory conventions.
- Configure basic automated testing.
- Create development tracking documents.

Acceptance:
- Application starts.
- Database connects.
- Initial tests pass.

**STOP AFTER PHASE 0.**

## PHASE 1 — AUTHENTICATION AND USER ROLES

Goal: Implement working user access.

Tasks:
- Login and logout.
- User account management.
- Four system roles.
- Route and action authorization.
- Basic role-specific navigation.

Acceptance:
- Each role can log in.
- Restricted actions cannot be accessed by unauthorized users.
- Authorization tests pass.

STOP.

## PHASE 2 — EMPLOYEE MANAGEMENT

Goal: Manage employee payroll records.

Tasks:
- Create employee records.
- View employee details.
- Edit authorized employee information.
- Store position and personnel classification.
- Search employee records.
- Maintain basic payroll information.

Acceptance:
- Employee records can be created, edited, and retrieved.
- Input validation and access restrictions work.

STOP.

## PHASE 3 — SALARY GRADE AND STEP MANAGEMENT

Goal: Determine basic salary from authorized salary information.

Tasks:
- Maintain salary schedules.
- Maintain salary grades and steps.
- Associate employees with authorized salary records.
- Retrieve applicable monthly salary.
- Identify potential step-increment eligibility.
- Record authorized step changes and promotions.
- Preserve salary history.

Acceptance:
- Salary retrieval uses the correct effective-dated schedule.
- Unauthorized salary updates are prevented.
- Historical salaries remain unchanged.

STOP.

## PHASE 4 — PAYROLL PERIOD MANAGEMENT

Goal: Establish monthly payroll preparation.

Tasks:
- Create a payroll period.
- Select covered employees.
- Retrieve employee salary information.
- Save draft payroll records.
- Prevent unintended duplicate payroll entries.
- Display payroll preparation status.

Acceptance:
- A draft payroll batch can be created and reopened.
- The correct employees and salary records are retrieved.

STOP.

## PHASE 5 — EARNINGS AND DEDUCTION INPUTS

Goal: Prepare payroll inputs.

Tasks:
- Manual allowance entry.
- Manual bonus entry.
- Manual loan deduction entry.
- Attendance-related input fields.
- Authorized manual adjustments.
- Input validation.
- Save and edit draft inputs.

Acceptance:
- Each employee can have different allowances and loans.
- Inapplicable allowances are excluded.
- Draft information persists correctly.

STOP.

## PHASE 6 — CORE PAYROLL CALCULATION

Goal: Create the initial calculation engine.

Tasks:
- Calculate basic pay from authorized inputs.
- Sum applicable earnings.
- Sum manually entered loan deductions.
- Apply validated adjustments.
- Calculate total deductions.
- Calculate net pay.
- Display a transparent calculation breakdown.

Acceptance:
- Totals match independently prepared test cases.
- Monetary calculations are consistent.
- No silent overwriting of manual inputs.

STOP.

## PHASE 7 — CONTRIBUTIONS AND WITHHOLDING TAX

Goal: Integrate statutory payroll calculations.

Tasks:
- Implement approved configurable contribution rules.
- Support applicable GSIS, Pag-IBIG, and PhilHealth calculations.
- Support validated withholding-tax procedures.
- Account for taxable excess bonuses.
- Record calculation basis, parameters, and results.
- Identify missing or unverified rules.

Acceptance:
- Verified test cases pass.
- Unverified rules are clearly marked and cannot silently produce production-ready results.
- Contribution and tax breakdowns are explainable.

If this phase is too large, split it into separate contribution and withholding-tax subphases.

STOP.

## PHASE 8 — PAYROLL CONFIGURATION

Goal: Allow authorized maintenance of calculation settings.

Tasks:
- Manage approved calculation parameters.
- Support effective dates.
- Preserve rule versions.
- Restrict configuration changes.
- Require authorized activation where applicable.
- Record significant configuration changes.

Acceptance:
- Approved new rules can be activated.
- Previous rule versions remain available.
- Historical finalized payroll is not silently recalculated.

STOP.

## PHASE 9 — PAYROLL REVIEW AND APPROVAL

Goal: Implement separation of payroll preparation and approval.

Tasks:
- Payroll review screen.
- Submit for approval.
- Approver review.
- Approve or return with reason.
- Correct returned payroll.
- Authorized finalization.
- Lock finalized payroll snapshots.

Acceptance:
- Payroll Officer cannot approve their own submission.
- Returned payroll can be corrected and resubmitted.
- Finalized payroll cannot be silently modified.

STOP.

## PHASE 10 — PAYROLL DOCUMENT GENERATION

Goal: Produce client-required documents.

Tasks:
- Generate payroll register.
- Generate individual payslips.
- Match provided templates where feasible.
- Support authorized viewing, printing, and export.
- Generate finalized documents from preserved payroll values.

Acceptance:
- Register totals match employee payroll results.
- Payslip values match the finalized snapshot.
- Documents can be retrieved.

STOP.

## PHASE 11 — BLOCKCHAIN ANCHORING

Goal: Connect finalized payroll to a real blockchain test network.

Tasks:
- Define canonical payroll snapshot representation.
- Generate SHA-256 batch hash.
- Implement a blockchain submission interface.
- Connect to the configured test network.
- Submit one hash per finalized batch version.
- Record transaction information.
- Handle confirmation and failure states.

Acceptance:
- A test finalized payroll is successfully anchored.
- The blockchain transaction is independently retrievable.
- Failed submissions do not falsely appear successful.

If no blockchain network is available, complete only the local interface and clearly report anchoring as incomplete.

STOP.

## PHASE 12 — BLOCKCHAIN VERIFICATION AND HISTORY

Goal: Verify and preserve finalized payroll integrity.

Tasks:
- Retrieve confirmed blockchain commitments.
- Recalculate finalized snapshot hashes.
- Compare hashes.
- Display verified, pending, failed, or mismatched states.
- Preserve transaction and verification history.
- Test tampering against an isolated test copy.
- Support authorized correction versions.

Acceptance:
- Original finalized snapshot verifies successfully.
- A modified test snapshot produces a mismatch.
- Previous payroll versions remain available.
- No sensitive payroll details are disclosed on-chain.

STOP.

## PHASE 13 — INTEGRATION TESTING AND MOCK DEFENSE

Goal: Prepare a stable demonstration.

Tasks:
- Run complete automated tests.
- Test role restrictions.
- Verify payroll calculations.
- Test approval and finalization.
- Test document generation.
- Test blockchain anchoring and verification.
- Test important failures and recovery behavior.
- Prepare fictional demonstration employees.
- Prepare a reproducible demo workflow.
- Produce a requirements completion report.

Acceptance:
- Core workflows operate end-to-end.
- Verified calculations pass expected-result tests.
- Blockchain verification works against a real test network.
- Remaining limitations are documented honestly.
- The system can be demonstrated without exposing real employee information.

STOP.

---

# 8. DEVELOPMENT CHECKPOINT FORMAT

At the end of every phase, respond using this exact format:

### Phase Completed
[Phase number and name]

### Implemented
Short description of completed functionality.

### Files Changed
List only the important files.

### Database Changes
List relevant migrations or schema changes.

### Tests
State exactly which tests ran and whether they passed.

### How to Verify
Provide 3–5 short steps I can follow to check functionality.

### Issues or Limitations
Mention bugs, blocked functionality, and unverified requirements.

### Next Phase
Identify the next phase without implementing it.

### Final Status
**STOPPED — Awaiting permission to continue.**

---

# 9. ADDITIONAL DEVELOPMENT CONSTRAINTS

- Do not implement multiple full phases in one response.
- Do not produce huge explanations of obvious code changes.
- Do not rewrite large portions of working code unnecessarily.
- Do not claim functionality is complete merely because a page exists.
- Do not implement fake blockchain verification as real verification.
- Do not hardcode uncertain government calculation rules as universally valid.
- Do not store actual confidential employee information in demonstration data.
- Do not add scheduling functionality for loans or allowances.
- Do not add supporting-document upload functionality.
- Do not bypass role authorization for development convenience.
- Do not sacrifice payroll accuracy for cosmetic features.
- Do not silently continue to the next phase.

If a phase exceeds a reasonably reviewable set of changes, divide it into Phase X-A, Phase X-B, etc.

Prefer the smallest coherent change that can be tested independently.

---

# 10. STARTING INSTRUCTION

Begin with **PHASE 0 — PROJECT FOUNDATION ONLY**.

First inspect the project directory and determine whether the application has already been initialized.

If it exists, identify what is implemented and what can be reused.

If it does not exist, prepare the foundation using the provisional stack.

Complete Phase 0, run its tests, report the results using the checkpoint format, and STOP.

Do not begin Phase 1 until I explicitly authorize it.