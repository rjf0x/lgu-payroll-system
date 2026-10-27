# Phase 10 audit - Payroll Document Generation

Correction follow-up: F1 was subsequently fixed under the user's Phase 10-only
authorization. The index now rejects unavailable pages before offset arithmetic;
a permanent regression covers extreme page numbers and preserved payroll data.
The original audit below is retained as pre-fix evidence. Current correction
verification is in PHASE_10_HANDOFF.md and TESTING_STATUS.md. The old ignored F1
reproducer intentionally asserts the former HTTP 500 and is superseded by the
permanent corrected-behavior test; exclude it from current acceptance checks.

Date: 2026-10-27 (Asia/Manila). Scope: the current uncommitted Phase 10 changes
after Phase 9 commit `30023f9`, against `master-prompt.md:557` tasks and acceptance
criteria. Audit only; no application fix, permanent-test change, migration, commit
or Phase 11 implementation.

## Result

**Implemented but needs validation.** Register and payslip value criteria pass
for the tested finalized demonstrations. Ordinary retrieval passes, but one
low-severity pagination error is confirmed (F1). No missing supported task area
was found. Actual client forms and operational acceptance remain pending; this
does not approve real payroll processing.

| Acceptance criterion | Assessment | Evidence and limits |
| --- | --- | --- |
| Register totals match employee payroll results | Passed for exercised workflows | Each preserved earning/manual deduction line and statutory component is reconciled using BCMath. Employee sums must match saved batch totals. Literal one-employee values are earnings `30650.17`, deductions `4886.21`, net `25763.96`; three categories sum to `91950.51`, `14658.63`, `77291.88`. HTML and CSV compare exact strings, while PDF extraction/layout checks cover fictional fixtures. The paginated index does not truncate the full register/export. |
| Payslip values match the finalized snapshot | Passed for exercised workflows | Identity, basic pay, included earnings, replacement adjustments, deductions and net come from the finalization snapshot. Excluded allowances and original adjustment references are omitted from payable amounts. Tests verify individual scope, frozen values after live profile/configuration changes, and no saved-history mutation. PDF byte stability is not promised. |
| Documents can be retrieved | Passed ordinary authorized paths; F1 pagination exception | Index, register HTML/PDF/CSV and individual payslip HTML/PDF return private authenticated responses. All four roles are exercised; Administrator is denied under the existing policy. Guests, cross-batch and uncovered IDs, unfinished records and malformed snapshots are rejected. An extreme index page number causes HTTP 500 (F1). These are on-demand retained-value projections, not publicly stored files. Concurrent rendering and deployment recovery remain unverified. |

The phase tasks for register/payslip generation, authorized viewing/printing/export
and preserved finalized sources are implemented. Native print markup and the print
button exist; PDF rendering was exercised. Actual browser/printer behavior still
needs office validation. Template matching remains blocked because no client
register/payslip templates were provided; the generic layouts say so explicitly.

## Confirmed bug

### F1 - Low: extreme document-index page number returns HTTP 500

Location: `application/app/Http/Controllers/PayrollDocumentController.php:47`.

As an authorized payroll reader, request a finalized batch's document index with
`?page=9223372036854775807` (PHP_INT_MAX on the tested 64-bit runtime). Laravel
accepts the positive integer page. Multiplying `($page - 1) * 20` overflows to a
float, and `array_slice()` throws a TypeError for its integer `$offset` argument.
The route returns HTTP 500 instead of a controlled empty/out-of-range response.

The ignored reproducer verifies both HTTP 500 and the underlying array_slice
offset TypeError. Normal pagination remains functional, document values are not
modified and no authorization bypass was found. Severity is low because this
requires a crafted extreme page value on an authenticated read request.

Recommended correction: validate/bound the page before offset multiplication or
short-circuit pages beyond the available rows, and add a permanent regression
expecting a controlled response. No correction was made during this audit.

## Missing, blocked and deferred functionality

- No other confirmed Phase 10 bug or missing supported task area was found in
  the reviewed/executed paths. This scoped audit does not establish exhaustive
  renderer, input, deployment or failure-condition correctness.
- The previously corrected register-footer loop-variable leak remains covered by
  its permanent regression. The register uses a batch reference; individual
  payslip continuation pages retain the employee reference.
- Client form headings, signatories, official template matching and confidential
  distribution acceptance are pending client material/decisions. Generic forms
  cannot be represented as approved LGU documents.
- Real payroll remains deliberately blocked by the earlier unvalidated rules,
  category applicability, attendance, authority, minimum-net and refund policies.
  All issued documents remain conspicuously demonstration-only.
- Earlier Phase 2/3 audit findings and legacy overwritten coverage-history limits
  are unchanged. They are not new Phase 10 regressions or fixes in this audit.
- Blockchain anchoring/verification and finalized correction workflows were not
  introduced. Later-phase or excluded work is not counted as a Phase 10 defect.

## Checks executed

| Check | Result |
| --- | --- |
| Full `scripts/verify-test-isolation.ps1` rerun | Passed: **479 tests / 5570 assertions**, 148.538 seconds, 98 MB, under hostile inherited production/MySQL settings pinned to in-memory SQLite |
| Additional ignored audit probes | Passed: **10 tests / 161 assertions**, 80.149 seconds, 90 MB; nine invalid snapshot/data-use cases across all six routes, plus a synthetic document-layout boundary case |
| F1 ignored fault reproducer | Reproduced: **1 test / 12 assertions**, 2.815 seconds, 64 MB; expected HTTP 500 and array_slice offset TypeError observed. A passing diagnostic means the bug is present, not fixed |
| MySQL `scripts/verify-payroll-workflow.php` | Passed: three-category workflow, exact totals, register/CSV/register PDF/payslip PDF, frozen documents after configuration activation, immutable history and fixture rollback. MySQL began stopped and was stopped again afterward |
| Existing fictional PDF extraction/layout inspection rerun | Passed: eight-page register and six-page payslip, Unicode, exact net values, last records/lines, repeated warning/reference/page numbers and physical page bounds. Contact sheets visually reviewed |
| Additional boundary PDF extraction/render inspection | Passed: one-page register and eleven-page payslip; full 350-character name, 50-character employee number, 150-character office/position, all 114 added 100-character zero-value lines, preserved net, repeated warnings/references/page numbers and page bounds. First/last pages visually reviewed |
| Application and workflow-verifier Pint `--test` | Passed |
| `composer validate --strict` and `composer check-platform-reqs` | Passed |
| `node --check resources/js/workspace.js` | Passed |
| `git diff --check` | Passed; ordinary verifier LF/CRLF notice only |

The additional fault cases cover an empty employee list, non-list employee map,
unknown category, integer alias for an included boolean, missing lines, 121 lines,
statutory component mismatch, core net mismatch and a non-demonstration batch.
All six routes return 409 and retain the injected fixture unchanged. The boundary
case injects a synthetic snapshot with 120 total lines to stress the document
projection; it does not certify new input-entry combinations or client entitlements.

Audit diagnostics are ignored under `.local/phase10-audit`; PDF inspection packages
remain under `.local/pdf-tools`. No persistent business fixture was retained.
The visible regression log is `.local/phase10-audit/regression.log`.
No new production build was needed for this audit;
the implementation's earlier passing build is historical evidence in TESTING_STATUS.

Reproduce the extra probes from `application/`:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never --filter=test_audit_ ../.local/phase10-audit/Phase10AuditTest.php
```

Run `python .local/phase10-audit/inspect_boundaries.py` from the repository root
afterward for extraction/bounds assertions and first/last page PNGs. The existing
`.local/phase10-qa/inspect_pdfs.py` checks the earlier long Unicode fixtures.

## Test coverage and remaining gaps

Permanent Phase 10 coverage remains **36 cases / 802 assertions**, alongside all
443 earlier cases. Eleven extra diagnostic cases / 173 assertions ran separately:
ten acceptance probes and one confirmed-bug reproducer. They are not permanent
tests. No line/branch coverage percentage was measured.

Permanent cases cover three classifications, all four roles and all six endpoints,
literal line/component/batch values, employee isolation, pagination, frozen source
values, unfinished/cross-batch/uncovered records, inactive/revoked sessions, eleven
malformed snapshots, eight CSV text cases, escaped markup, private download headers
and the corrected footer behavior.

Remaining gaps:

- No permanent extreme-page regression exists; ordinary pagination coverage did
  not catch F1. The ignored reproducer intentionally asserts the current failure.
- Permanent PDF assertions check generation/signature/EOF, private headers,
  absence of PDF JavaScript and multi-page output. Detailed extracted amounts,
  continuation identities, typography and clipping are checked by ignored QA
  scripts and visual review, not by the normal permanent regression suite.
- No live browser print-dialog/action, physical-printer, mobile, keyboard or
  screen-reader acceptance was performed. JavaScript syntax and HTTP markup do
  not establish that acceptance.
- CSV quoting/formula safeguards are tested as parsed text; the office's actual
  Excel/LibreOffice import, protective apostrophe display, precision handling and
  confidential file-distribution practices remain unverified.
- Concurrent requests/font-cache initialization, read-only/full storage,
  memory/time exhaustion, interrupted downloads and failure recovery are not
  tested. All snapshots are prepared in memory even for the paginated index and
  an individual payslip; office-scale time/memory limits were not measured.
- No client-approved expected forms/payroll examples or office-network deployment
  acceptance is available. Internal arithmetic/schema checks do not detect a
  coordinated privileged database rewrite or provide cryptographic verification.

Stop at the Phase 10 audit. Application behavior and permanent tests are unchanged;
only this report, tracking documents and ignored diagnostics were added/updated.
