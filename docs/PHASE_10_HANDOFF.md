# Phase 10 - Payroll Document Generation

Date: 2026-10-27 (Asia/Manila). Scope: Phase 10 only, after Phase 9 commit `30023f9`.

## Implemented behavior

- Authorized document index on finalized payroll reviews, with paginated employee
  payslip links, full-batch register and exact preserved totals.
- Register and individual payslip HTML views with native browser printing; register
  PDF/CSV and individual payslip PDF attachments. Register includes all employees,
  regardless of which index page is open.
- Payslips display the saved basic pay, included earning lines, authorized adjustment
  replacement amounts, manual loan/deduction lines, contribution/withholding amounts
  and earnings/deduction/net totals. Excluded allowances and original adjustment
  references are not counted or misrepresented as payable amounts.
- Generic prototype layouts: A4 landscape register and A4 portrait payslip,
  repeatable table headings, page numbers and reference footers. Demonstration
  warning appears on every PDF page; payslip continuation pages identify the
  employee. Client templates are still unprovided and remain a validation item.

## Source, access and preservation

Every document uses the selected `PayrollFinalization.snapshot.payroll` values.
It does not query current employee/entry/input/rule/calculation sources to obtain
amounts or identity. Later profile deactivation/changes and actual configuration
activation leave retrieved values unchanged. Register/payslip columns and CSV totals
reconcile with stored line/component/item/batch amounts using exact decimal sums.
No contribution/tax formula is recalculated and no snapshot is repaired.

Scope/schema/data-use and missing/inconsistent/ambiguous values fail closed with
404/409. Only finalized demonstration data with `production_ready=false` is supported.
These internal consistency checks are not cryptographic verification and cannot
detect a coordinated privileged database rewrite. Blockchain remains Phase 11/12.

Payroll Officer, Approver and Viewer retain the existing whole-office payroll read
scope. Administrator has no payroll-record access. All six document routes require
authenticated active sessions and the existing batch view policy. A finalization
must belong to its URL batch; payslip IDs must exist within that frozen snapshot.
Draft/submitted/returned/approved records cannot issue documents. The read paths do
not create payroll/workflow events or change saved inputs/results.

HTML/CSV/PDF responses are private/no-store with no-sniff and no-referrer headers.
PDF attachments use period/batch/version/numeric employee IDs in filenames. PDFs
are rendered in memory, without publicly stored payroll copies or an additional
document table. Stable authenticated URLs regenerate the same preserved values;
PDF byte-for-byte stability is not promised because renderer metadata may vary.

CSV uses UTF-8 BOM and explicit quoting/escaping. Potential formula/control-prefixed
text gets an apostrophe in the export only; commas, quotes, newlines and Unicode
otherwise remain intact. Numeric values retain exact two-decimal strings.

## Dependencies and files

New runtime package: `dompdf/dompdf` (^3.1, locked v3.1.6), with four new transitive
packages: php-font-lib, php-svg-lib, masterminds/html5 and sabberworm/php-css-parser.
No existing dependency was upgraded. Normal application PDF generation uses PHP
and bundled DejaVu Unicode fonts, without an external renderer or network service.
Remote resources, embedded PHP and PDF JavaScript are disabled; local resource
access is confined to bundled fonts. PHP needs writable
`storage/framework/cache/dompdf` for its ignored renderer cache.

Created: `PayrollDocumentService`, `PayrollDocumentController`, four Blade document
views, `PayrollDocumentTest`, and this handoff.

Updated: Composer manifest/lock, routes, existing review link/shared navigation,
the workspace print handler, MySQL workflow verifier, README and all four tracking
documents. No model/table or migration change. Existing thirteen migrations remain.
No production activation, supporting-document upload or Phase 11 feature.

## Manual verification

1. Run `composer install`, build assets and start the application per README.
   Use fictional employees and a completed demonstration payroll approved/finalized
   by an independent Approver, following the Phase 9 handoff.
2. As Payroll Officer/Approver/Viewer, open its payroll review and select
   **Finalized payroll register and payslips**. Compare the displayed earnings,
   deductions and net totals with the preserved finalized review.
3. View the register; use **Print document**, download PDF and export CSV. Verify
   all covered employees, category/identity details, complete totals and conspicuous
   demonstration labeling. Check CSV text/Unicode in the office's chosen spreadsheet
   application; export-only protective apostrophes may be visible.
4. View/download one employee's payslip. Confirm only that employee appears and
   basic/earning/deduction/net values match the preserved snapshot. Test native
   printing or print the downloaded PDF in the office's PDF viewer.
5. Change a fictional live employee profile or activate an applicable fictional
   configuration, then retrieve the same documents. Saved document values should
   remain unchanged. Administrator/guest/inactive users and cross-batch IDs must
   not expose documents; unfinalized batches have no document link/output.

For the automated literal example, fictional basic salary `30000.00`, applicable
allowance `100.07`, supplementary bonus `500.01`, earning replacement `50.09`,
loan `200.02` and deduction replacement `111.11` yield earnings `30650.17`.
Reviewed contribution bases `30000.00` and a fictional cumulative tax scenario
with taxable regular `26350.00`, taxable supplementary `650.17`, elapsed periods
`1` and explicit zero prior tax/qualifying benefit pool preserve contribution
amounts `2700.00`, `200.00`, `750.00` and withholding `925.08`. Final deductions
are `4886.21` and net `25763.96`. The scenarios establish software behavior,
not actual entitlement, tax classification, membership or client approval.

## Verification

Original implementation verification: full isolation passed **479 tests / 5570 assertions**,
including 36 Phase 10 cases / 802 assertions and all 443 earlier cases. Extended
MySQL workflow/document checks and existing configuration/core/statutory verifiers
pass with rollback. Pint, JavaScript syntax, Composer strict/platform validation,
production build and whitespace checks pass. Thirteen migrations already Ran; none
executed. MySQL's original stopped state was restored.

Final fictional PDF layout QA covers an eight-page 28-employee register with long
Unicode identity fields and a six-page long-line payslip. Extracted values,
continued records/lines, repeated warnings/references/page numbering, correct batch
versus individual footer identity and all text page bounds pass. PNG pages/contact
sheets were visually inspected. A shared Blade loop-variable footer defect was
reproduced, corrected and permanently tested before the final passing run. Detailed
execution evidence and ignored QA reproduction paths are in TESTING_STATUS.md.

## Phase 10-only correction - 2026-10-27

The audit's low-severity F1 pagination overflow is corrected. Document-index pages
beyond the available preserved rows return HTTP 404 before offset multiplication,
including PHP_INT_MAX and the first page whose offset would overflow. Existing
valid page navigation, authorization, snapshot totals and export behavior remain.

The new permanent regression first reproduced HTTP 500 (1 failed / 9 assertions).
After correction, it and the existing multi-page snapshot/full-register test pass
2 cases / 36 assertions. The new case also verifies subsequent normal retrieval,
unchanged finalized batch/snapshot attributes and no additional workflow event.
Detailed full-regression evidence is recorded in TESTING_STATUS.md.

Current correction verification: full isolation passes **480 tests / 5586
assertions**, including 37 Phase 10 cases / 818 assertions and all 443 earlier
cases. Application Pint and whitespace checks pass. Earlier MySQL/build/dependency
and PDF visual QA results remain historical; they were not rerun for this index-only
guard. The full suite continues to cover document generation and authorization.

To verify the correction, open a finalized document index and append
`?page=9223372036854775807`: it should return 404 rather than 500. Remove the query
to retrieve the normal index. For a batch exceeding 20 covered employees, the
second page and full register/export should still include the expected employees.

Only the controller guard, permanent regression and phase/audit tracking documents
changed in this follow-up. No schema, dependency, new feature, commit or Phase 11.

## Commit review - 2026-10-27

The authorized Phase 10 commit review reran full isolation (480 tests / 5586
assertions), MySQL workflow/documents with rollback, Pint, Composer strict/platform
checks and locked security audit, JavaScript syntax and the production build.
All pass; Composer reported no vulnerability advisories. Twenty-two intended phase
files were reviewed, with no secret/debug scan findings or generated/sensitive paths.
No application behavior changed during commit review. See TESTING_STATUS.md for
execution details. No migration, push or Phase 11 implementation.

## Remaining validation

Actual LGU register/payslip templates, signatory/heading requirements, confidential
distribution policy and office acceptance remain unprovided/unverified. Real
payroll and the earlier category/statutory/attendance/minimum-net/refund blocks
remain unchanged. Earlier Phase 2/3 findings and legacy coverage-history limits
are not fixed by this phase.

Rendered PDF checks do not establish live browser/physical-printer acceptance,
mobile/keyboard/screen-reader behavior or CSV behavior in every spreadsheet viewer.
Large-office capacity, concurrent PDF requests/font cache behavior, memory/time
limits, storage/disk failures, interrupted downloads and office-network deployment
remain unverified. Downloaded files are not a payment-release record or blockchain
commitment. No finalized correction/reopen workflow added.

Recommended next phase: Phase 11 - Blockchain Anchoring, only after user
authorization. Stop after Phase 10; no Git commit or publication performed.
