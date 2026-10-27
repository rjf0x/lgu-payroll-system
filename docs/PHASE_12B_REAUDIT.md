# Phase 12-B re-audit - Authorized Correction Versions

Date: 2026-10-27 (Asia/Manila). Audit of the current uncommitted Phase 12-B
implementation after its F1 display fix. This is a review of the current phase,
not Phase 13 implementation. No application fix or permanent-test change.

Verdict: **Implemented but needs validation.** F1 remains resolved, and no new
confirmed defect was found in the reviewed and exercised local paths. Genuine
blockchain acceptance remains incomplete under the user's no-network fallback.

## Acceptance criteria

| Master Phase 12 criterion | Assessment | Current evidence and limit |
| --- | --- | --- |
| Original finalized snapshot verifies successfully | Partial | Local consistency and explicit test-double confirmed comparison pass for original and corrected payrolls. The default adapter returns unavailable without a fabricated transaction/receipt. No authorized network, genuine anchor or independent real receipt is available |
| Modified isolated test snapshot produces mismatch | Pass for tested local behavior | Existing permanent verification cases and the re-audit's cloned corrected-authorization change produce mismatch while preserving persisted originals; changed/malformed source authorization is blocked |
| Previous payroll versions remain available | Pass for tested demonstrations | Permanent three-version retrieval/record/CSV/hash checks, MySQL replacement preservation and extra original/corrected PDF checks pass. A new isolated corrected return/recalculate/resubmit probe retains both submission revisions, their lineage and the original finalized version |
| No sensitive payroll details disclosed on-chain | Pass for reviewed local/interface scope; live acceptance unverified | The corrected-version submitter test double receives exactly a SHA-256 digest and retry key, and the reader receives only network/transaction identity. No genuine external operation occurs; actual future adapter/deployment needs validation |

The deferred correction task is implemented: independent request/authorize/return,
linear empty replacement drafts, complete renewed preparation and review, normal
independent approval/finalization, frozen hashed authorization/source lineage,
separate per-version evidence and retained originals. No required local task area
was found entirely missing.

## Bugs and F1 verification

No new confirmed bug or authorization bypass was found in this re-audit. This
statement is limited to code review and executed scenarios, not proof against
untested concurrency, operational failure or live browser behavior.

The permanent F1 regression and an updated isolated probe confirm that a pending
request whose source changed without a local commitment now displays an integrity
warning and hides authorize. Valid source controls remain available, Viewers stay
read-only, independent Approvers can return the invalid request, and direct
authorization POST rejects with 409/no replacement. Source/request evidence is
preserved. Existing commitment mismatch and malformed-source checks still block.

The original audit's old F1 reproduction asserted the defect. Its re-audit copy
was updated to expect the corrected display, rather than treating the historical
defect-reproduction probe as a post-fix regression.

## Missing functionality and validation limits

- Genuine test-network submission, confirmed commitment retrieval and successful
  original/corrected live verification remain unavailable. This is the explicitly
  accepted local-interface boundary, not a newly discovered implementation bug.
- Office authority/reviewer policy, full replacement treatment and actual payroll
  rules/examples/templates remain client validation requirements. References are
  operator attestations; real payroll stays blocked.
- Actual simultaneous writers, authorization/role-revocation races, deadlock and
  process-crash recovery, office capacity, live browser/accessibility and printed
  layout remain unverified. Sequential retries and injected faults are insufficient
  evidence for these cases.
- Cancellation/abandonment, financial payment/delta handling and reopening old
  payrolls are explicit design exclusions, not newly missing acceptance tasks.
  Authorized drafts must be completed before another family version is opened.

## Test coverage and checks

- Permanent correction coverage remains **29 cases / 576 assertions**, including
  F1. Full hostile-environment isolation passes **566 tests / 7115 assertions**,
  214.552 seconds, 102 MB, including all previous cases. Commands are recorded in
  TESTING_STATUS.md. No coverage percentage or permanent test was added.
- Eight ignored isolated probes pass **8 cases / 221 assertions**, 14.176 seconds,
  88 MB. They cover F1's corrected display/409/return, original/corrected register
  and payslip PDF responses, frozen lineage timestamps, isolated authorization-hash
  mismatch, ignored actor/version/action/time overrides and actor-bound UUID retry,
  corrected hash-only submission/own test-double receipt, default unavailable
  adapter, invalid source shape, removed-context HTTP blocking and a dedicated
  corrected return/recalculate/resubmit/finalize cycle.
- The new returned-correction probe rejects stale submission input, changes a
  replacement loan, preserves first-submission values, retains identical frozen
  correction authority/source lineage in both submissions, finalizes revision 2
  as payroll version 2, and leaves original finalized records unchanged.
- MySQL workflow verifier passes full replacement/original JSON/hash/document/
  check preservation with all fictional fixtures rolled back. All **16 migrations
  already Ran**, none executed. The initially stopped helper was stopped again.
- Application/verifier Pint, JS syntax and whitespace checks pass. Build/Composer
  were not rerun because no source or dependency changed; their prior results
  remain historical. No genuine network calls or receipts were produced.
- A SHA-256 comparison across **190 source/config/route/view/migration/permanent-
  test/verifier files** confirms no changes during this re-audit. Only re-audit/
  tracking documents and ignored diagnostics changed; the index remains empty.

Remaining permanent coverage gaps: corrected-version PDF/receipt-specific cases
and dedicated corrected return/recalculate/resubmit. These were exercised by
ignored probes, not added to the repository's permanent suite. F1's exact case is
already permanent. PDF signatures/HTTP success do not establish visual/printed
layout or extracted text correctness. Real-network, concurrency/recovery/capacity
and live browser coverage remain absent.

Evidence is ignored under `.local/phase12b-reaudit`; no application, permanent test,
schema, dependency or business-rule change. **STOPPED - Re-audit complete. No
Phase 13, commit, push or publication.**
