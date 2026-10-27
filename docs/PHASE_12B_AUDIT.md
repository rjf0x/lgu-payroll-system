# Phase 12-B audit - Authorized Correction Versions

Follow-up: F1 was fixed under the user's subsequent current-phase fix instruction.
The audit below preserves its original findings; see the resolution at the end.

Date: 2026-10-27 (Asia/Manila). Audit only of the uncommitted Phase 12-B scope
following Phase 12-A commit `5ba1bd4`. No application fix, permanent-test change,
migration, dependency, commit, publication or Phase 13 implementation.

Verdict: **Implemented but needs validation**, with one confirmed low-severity
display defect. No critical/high-severity defect or authorization bypass was
found in the exercised paths. The local correction lifecycle and preservation
checks pass; parent Phase 12 genuine blockchain acceptance remains incomplete.

## Acceptance criteria

The master prompt lists four criteria for parent Phase 12; the current subphase
delivers its deferred authorized-correction task and preserves earlier verification.

| Criterion | Assessment | Evidence / missing acceptance |
| --- | --- | --- |
| Original finalized snapshot verifies successfully | Partial under the authorized fallback | Local consistency and explicit test-double confirmed receipt comparisons pass. An additional audit probe verifies a finalized correction using its own hash/reference and an explicit test-double receipt. Default corrected-version submit/check remains unavailable with null real transaction/network hash. No authorized network exists; no genuine successful anchoring/retrieval/verification performed |
| Modified isolated test snapshot produces mismatch | Pass for tested local behavior | Full suite retains Phase 12-A tampering/mismatch cases. An audit clone changing corrected authorization lineage returns `mismatched`/`snapshot_changed`, while original persisted records/hash remain unchanged. Requests and authorization reject changed sources; F1 concerns the display of an unavailable action |
| Previous payroll versions remain available | Pass for tested demonstrations | Permanent three-version test retrieves prior reviews, documents, CSVs, anchoring/check pages and compares old records/hash/CSV. MySQL completes a replacement and checks original preservation. Audit probes additionally generate original/corrected register and payslip PDFs and check retained lineage timestamps |
| No sensitive payroll details disclosed on-chain | Pass for reviewed local/interface scope; live acceptance unverified | No genuine network operation occurs. The corrected-version test-double submitter receives exactly hash and retry key; receipt reader receives network/transaction identity only. Payroll, reasons and authority references stay local. A future real adapter/deployment remains unvalidated |

Correction-specific behavior is present: independent request/authorize/return,
immutable evidence, exact retry semantics, linear version family, empty replacement
draft, renewed preparation/core/statutory review, normal independent approval/
finalization, hashed authorization/source lineage, distinct version evidence and
retained originals. Real payroll remains blocked. No required local task area
was found entirely missing.

## F1 - Low: stale source can still display an authorization form

Location: `application/resources/views/payroll-corrections/index.blade.php:52`;
supporting digest handling in `PayrollCorrectionController.php:27-34` and server
rejection in `PayrollCorrectionService.php:137`.

Reproduction, using only isolated SQLite audit fixtures:

1. Finalize a demonstration payroll without preparing a local commitment.
2. Request correction as Payroll Officer; the request captures the source hash.
3. Modify the frozen source's workflow reason directly in the isolated test DB,
   leaving its payroll arithmetic valid. This simulates source tampering; normal
   application models do not permit editing a finalized source.
4. Open correction history as an independent Approver. GET returns 200, still
   shows **Authorize correction draft**, and shows no **Integrity review required**
   notice. The current digest is valid, but differs from the pending request hash.
5. Submit authorization: POST returns **409**, creates no replacement or decision,
   and retains the changed source. Returning the request still succeeds.

Cause: the page tests only whether a fresh digest exists before offering authorize;
it does not compare that digest with the pending request's captured source hash.
With an existing commitment the service throws a consistency error and the UI
already offers only return. Without one, the controller obtains a new valid hash
and the view incorrectly treats the pending request as authorizable.

Impact: misleading control/absent explanation and a rejected review action during
an integrity incident. **No authorization bypass, source repair, duplicate version
or loss of retained history was reproduced.**

Recommended current-phase fix, not implemented: compare each pending request's
captured hash with the current valid digest, explain a stale-source mismatch and
offer return only; retain the locked server-side comparison. Add a permanent
regression for the no-commitment source-change case.

## Missing functionality and limitations

- Genuine blockchain submission, independently retrieved confirmed receipt and
  original/corrected live verification remain missing because the user confirmed
  no authorized test network. This is the documented fallback boundary, not a
  newly discovered correction implementation defect.
- Actual office correction authority, full replacement treatment and reviewer
  policy remain client-validation requirements. References are operator attestations.
  Real payroll rules/examples/templates remain unavailable and processing blocked.
- Cancellation/abandonment of an authorized draft, delta/payment handling and old
  payroll reopening are explicitly excluded from the implemented design, not
  omitted acceptance tasks. A draft must be completed before another family
  version; no recovery bypass for a changed source is provided.
- Actual multi-process request/authorization/role-revocation races, deadlock/crash
  recovery, office-scale capacity and live browser/accessibility/printing acceptance
  remain unverified. Sequential retries and injected failures do not prove these.

## Executed tests and checks

- Full `scripts/verify-test-isolation.ps1`: **565 tests / 7,079 assertions pass**,
  209.217 seconds, 100 MB, under conflicting inherited production/MySQL settings.
  Includes **28 correction cases / 540 assertions**, all 537 earlier cases and
  the existing Phase 12-A comparison/mismatch suite. No coverage percentage measured.
- Additional ignored audit probes: **7 cases / 177 assertions pass**, 11.653
  seconds, 88 MB. They reproduce F1 and check corrected original/PDF/lineage/hash
  preservation, request/decision overposting and actor-bound/uppercase UUID retries,
  corrected-version hash-only/test-double receipt behavior, unavailable default
  fallback, invalid source shape and HTTP submission/approval blocking when
  authorization context is removed. F1's probe asserts the observed defect;
  its passing result is not acceptance of that display behavior.
- MySQL `scripts/verify-payroll-workflow.php` passes with full replacement
  preparation/approval/finalization, JSON/hash/document/check preservation and
  fixture rollback. All **16 migrations already Ran**; none executed during audit.
  Initial stopped MySQL state restored.
- Application and verifier Pint `--test`, JavaScript syntax and whitespace checks
  pass. No source/dependency change required a new build or dependency validation;
  previous build/Composer evidence remains historical in TESTING_STATUS.md.
- SHA-256 comparison across **190 source/config/route/view/migration/permanent-test/
  verifier files** confirms zero changes during audit. No staged diff or commit.

The additional probes/PDFs/logs/manifests are ignored under `.local/phase12b-audit`.
They are not permanent regression coverage. PDF checks establish successful PDF
responses/signatures, not visual layout, printed output or text extraction.

Permanent coverage gaps include F1's exact no-commitment case, corrected-version
PDF/receipt-specific regressions and a dedicated corrected return/recalculate/
resubmit scenario (the shared workflow's original-batch return/resubmit is tested).
No actual concurrency, live chain, operational recovery, capacity or live browser
coverage was added. F1 remains unfixed in this audit-only turn.

**STOPPED - Audit complete. Phase 13 not started; no commit, push or publication.**

## Subsequent F1 resolution - 2026-10-27

The user authorized review/fixes to the current phase only. The correction view
now compares each request's captured source hash with the fresh valid digest.
A pending mismatch shows an integrity warning and hides authorize; independent
Approvers retain return, and Viewers see the warning without review controls.
No server-side authorization guard or persisted source/commitment/history changes.

A new permanent regression reproduces the exact no-commitment case, fails against
the old view, and passes with the fix. It also verifies valid-source controls,
read-only Approver/Viewer display, direct authorization POST 409/no draft, return
and unchanged source/request evidence. Focused correction coverage is now
**29 cases / 576 assertions**. Full isolation passes **566 tests / 7115 assertions**;
Pint, JS syntax, production build (60 modules) and whitespace checks pass. Detailed
execution evidence and unchanged limitations: TESTING_STATUS.md.

F1 is **resolved**, and its precise regression is now permanent. The original
ignored F1 reproduction probe asserts the old defective behavior and is retained
only as historical audit evidence, not a passing post-fix regression. Other
reported validation/coverage limits remain. No new feature or next-phase work.
