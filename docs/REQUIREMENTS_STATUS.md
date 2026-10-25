# Requirements status

Updated: 2026-10-25. Requirements derive from `master-prompt.md`.
Statuses reflect behavior and validation, not screen counts.

Allowed statuses: Implemented and tested; Implemented but needs validation;
In progress; Not started; Blocked by client validation.

| Requirement | Phase | Status | Evidence or remaining work |
| --- | --- | --- | --- |
| Application starts, database connects, initial tests pass | 0 | Implemented and tested | HTTP 200; MySQL readiness/persistence; 6 tests, 14 assertions |
| Environment setup and directory conventions | 0 | Implemented and tested | Root README, application/, scripts/; build and DB restart checks passed |
| Progress, decisions, requirements, and testing records | 0 | Implemented and tested | Four tracking documents created and reviewed |
| Login/logout, account management, four roles, server authorization | 1 | Implemented and tested | Five audit findings fixed; post-correction audit confirms 45 tests / 611 assertions and MySQL role/concurrency checks; no new confirmed defects. Remaining verification gaps: PHASE_1_REAUDIT.md |
| Employee create, view, edit, search, classification | 2 | Implemented but needs validation | Existing 76 tests / 1040 assertions and normal MySQL checks pass; PHASE_2_AUDIT.md records missing suffix-search behavior, invalid-encoding HTTP 500 paths, and test coverage gaps |
| Effective-dated salary schedules, grades, steps, history | 3 | Not started | Salary changes require external authorization |
| Potential step eligibility separate from promotions | 3 | Not started | Eligibility must not change salary automatically |
| Monthly draft batches and duplicate prevention | 4 | Not started | No payroll tables created |
| Manual employee allowances, bonuses, loans, attendance, adjustments | 5 | Not started | Authorized reasons and original values must be preserved |
| Decimal-safe totals, rounding, transparent calculation breakdown | 6 | Not started | Expected-result test cases required |
| Configurable validated contributions and withholding | 7 | Not started | Unverified rules must remain inactive for real processing |
| Taxable excess bonuses included in annual taxable compensation | 7 | Not started | Confirmed requirement; never hardcode the worksheet example |
| Effective-dated versioned rules and controlled activation | 8 | Not started | Preserve historic payroll values |
| Submission, return, approval, finalization, separation of duties | 9 | Not started | Preparers cannot approve their own submissions |
| Payroll registers and payslips from finalized values | 10 | Not started | Obtain and validate client templates |
| Deterministic snapshot and real blockchain anchoring | 11 | Not started | One SHA-256 commitment per finalized batch version |
| Confirmed commitment comparison, mismatch detection, retries/history | 12 | Not started | Preserve original snapshots and correction versions |
| End-to-end demo, role checks, failure recovery, completion report | 13 | Not started | Fictional personnel only |

## Business and deployment rules requiring validation

| Unresolved requirement | Status | Required validation |
| --- | --- | --- |
| Category-specific compensation and applicability | Blocked by client validation | Approved rules for permanent, coterminous, and part-time personnel |
| Employee record visibility and local numbering conventions | Blocked by client validation | Prototype uses whole-office read access for Payroll Officer/Approver/Viewer and restricted-format unique employee numbers; confirm any office/record scoping and actual identifier format before deployment |
| Approved salary schedules and step eligibility practice | Blocked by client validation | Effective dates, authorization, and source schedules |
| GSIS reported employee contribution of 9% | Blocked by client validation | Applicability, basis, limits, rounding, and approved current rules |
| Pag-IBIG reported monthly employee contribution of PHP 200 | Blocked by client validation | Eligibility, applicability, and approved current rules |
| PhilHealth applicability and procedures | Blocked by client validation | Employee categories, bases, rates, and procedures |
| Periodic withholding, annualization, exemptions, bonus treatment, reconciliation | Blocked by client validation | Approved BIR procedure and independently verified examples |
| Absence/undertime formulas and override authorization | Blocked by client validation | Approved formulas and controlled adjustment policy |
| Client document layouts | Blocked by client validation | Payroll register and payslip examples |
| Finalization authority and configuration activation policy | Blocked by client validation | Role and separation-of-duties decisions |
| Blockchain network, governance, confirmations, outage policy | Blocked by client validation | Suitable genuine test network and operating policy |
| Office network deployment, backups, and recovery | Blocked by client validation | Target infrastructure and operational acceptance |

No statutory formulas or rates have been activated. No employee information is
stored on-chain. A local hash or simulated transaction cannot satisfy anchoring.

## Excluded scope

Full HR management; financial disbursement; loan amortization, due-date or balance
management; allowance/loan scheduling; supporting-document uploads. These remain
excluded unless the user explicitly changes the master requirements.
