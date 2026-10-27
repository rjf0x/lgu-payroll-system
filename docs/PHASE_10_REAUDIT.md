# Phase 10 re-audit - Payroll Document Generation

Date: 2026-10-27 (Asia/Manila). Scope: current Phase 10 after the pagination
correction, against `master-prompt.md:557`. Phase 9 is committed at `30023f9`;
Phase 10 remains uncommitted. This is an audit, with no application fix,
permanent-test change, migration, commit or Phase 11 implementation.

## Result and acceptance criteria

**Implemented but needs validation.** All three criteria pass for the tested
finalized demonstrations. F1 remains corrected, and no new confirmed Phase 10 bug
or missing supported task area was found. Client forms and operational acceptance
remain pending; passing these tests does not authorize real payroll.

| Criterion | Assessment | Evidence and limits |
| --- | --- | --- |
| Register totals match employee payroll results | Passed tested demonstration paths | Preserved lines, manual deductions, statutory components and batch sums reconcile with exact decimal arithmetic. Literal HTML/CSV tests verify earnings `30650.17`, deductions `4886.21`, net `25763.96`; three-category totals are `91950.51`, `14658.63`, `77291.88`. The full register/export includes employees beyond the index page. |
| Payslip values match the finalized snapshot | Passed tested demonstration paths | Saved identity, included earnings, replacement adjustments, deductions and net are used without live employee/rule recalculation. Individual scope and later profile/configuration changes are tested. PDF generation and separate extracted-value/layout probes pass; detailed PDF assertions are not in the normal permanent suite. |
| Documents can be retrieved | Passed tested authorized paths | All six endpoints retain existing payroll read roles, private responses and batch/finalization/employee scope. F1 now returns 404 for unavailable pages before arithmetic. New 20/40/41-row probes verify exact last pages, missing pages, extreme integers and malformed query fallbacks without mutation. Authorization and invalid-snapshot errors still take precedence over pagination rejection. |

## Bugs and missing functionality

- No new confirmed Phase 10 defect was found. The permanent F1 regression passes;
  its original ignored HTTP 500 reproducer intentionally asserts former behavior
  and was excluded, not rewritten to erase historical evidence.
- Register/payslip viewing, native print controls, PDF downloads, register CSV and
  frozen-value retrieval are implemented. Actual LGU template matching, headings,
  signatories and confidential distribution acceptance remain blocked by missing
  client material/decisions. Generic forms are not approved official forms.
- Real payroll remains blocked by unresolved client rules, category/statutory
  applicability, attendance, authority, minimum-net and refund treatment. Earlier
  Phase 2/3 findings and legacy coverage-history limitations are unchanged.
- Blockchain and finalized correction workflows are not missing Phase 10 tasks
  and were not added in this audit.

## Checks executed

| Check | Result |
| --- | --- |
| `scripts/verify-test-isolation.ps1` | Passed: **480 tests / 5586 assertions**, 151.140 seconds, 98 MB; hostile inherited production/MySQL settings remained pinned to in-memory SQLite |
| New ignored re-audit probes | Passed: **4 tests / 91 assertions**, 21.437 seconds, 68 MB; exact page boundaries, malformed queries, authorization and snapshot-error ordering |
| Applicable earlier ignored audit probes | Passed: **10 tests / 161 assertions**, 80.978 seconds, 90 MB; additional malformed snapshots and synthetic maximum-length document layout. Original F1 reproducer excluded |
| MySQL workflow/document verifier | Passed with fictional fixture rollback: exact three-category values, register/CSV/register PDF/payslip PDF and preserved documents after configuration activation. MySQL began stopped and was restored to stopped; no migration executed |
| PDF extraction/bounds checks | Passed: previously generated eight-page Unicode register/six-page payslip, plus freshly regenerated one-page boundary register/eleven-page boundary payslip. All maximum-length identity fields, 114 added long lines, net values, references, warnings and page numbers retained within page bounds. Boundary register and final payslip page visually reviewed |
| Application/workflow-verifier Pint, Composer strict validation, JavaScript syntax | Passed |
| Whitespace and audit boundary | `git diff --check` passed; ordinary verifier LF/CRLF notice only. SHA-256 comparisons confirmed all 13 reviewed Phase 10 application/dependency/view/test paths unchanged during this re-audit |

Fourteen applicable ignored diagnostic cases pass **252 assertions**, separately
from the permanent suite. Permanent Phase 10 coverage remains **37 cases / 818
assertions**, alongside all 443 prior cases. No line/branch coverage percentage
was measured. No new build or dependency installation was needed.

New probes and the full regression log are ignored under `.local/phase10-reaudit`.
The earlier boundary PDFs/scripts remain ignored under `.local/phase10-audit`;
Unicode QA and inspection packages remain under `.local/phase10-qa` and
`.local/pdf-tools`. All test business data was isolated or rolled back.

Reproduce additional checks from `application/`:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never --filter=test_reaudit_ ../.local/phase10-reaudit/Phase10ReauditTest.php
php vendor/phpunit/phpunit/phpunit --colors=never --filter='/test_audit_(?!extreme_page_number_failure_reproducer)/' ../.local/phase10-audit/Phase10AuditTest.php
```

## Coverage gaps and remaining validation

- Permanent tests cover amounts in HTML/CSV, roles/sessions, scope, snapshots,
  pagination, escaped markup, CSV quoting/formula text, private headers and PDF
  generation/signature/EOF/page count. PDF extracted amounts, continued identities,
  typography and clipping remain separate ignored QA/visual checks.
- No live browser print-dialog, physical-printer, keyboard, mobile or screen-reader
  acceptance was performed. The actual office spreadsheet import, precision and
  protective-apostrophe behavior remain unverified.
- Simultaneous rendering/font-cache initialization, disk/memory/time failures,
  interrupted downloads and recovery remain untested. All snapshot rows are still
  prepared in memory for an index or individual payslip; office-scale time/memory
  capacity and network deployment are unmeasured.
- Client-approved payroll examples, official forms and distribution practices are
  unavailable. Internal schema/arithmetic checks do not establish cryptographic
  integrity against coordinated privileged database rewriting.

Only this report, the four tracking documents and ignored diagnostics changed in
this re-audit. Stop at Phase 10; no new feature, fix, commit or Phase 11 work.
