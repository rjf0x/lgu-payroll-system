# Phase 12-B - Authorized Correction Versions

Date: 2026-10-27 (Asia/Manila). Implements only the next authorized subphase after
Phase 12-A commit `5ba1bd4`. The master prompt's Phase 12 correction task spans
preparation, approval, finalization, documents and integrity history; it was
explicitly deferred to this separately reviewable subphase. Phase 13 is untouched.

The user confirmed there is no authorized blockchain test network. The existing
unavailable submitter/reader remain configured. No genuine anchor, receipt or
successful blockchain verification is claimed.

## Implemented behavior

1. A Payroll Officer requests correction of the latest finalized demonstration
   version. A reason, authority reference, UUID retry key, reviewed source digest
   and explicit confirmation are required. A recalculated source must be valid
   and match any existing local commitment. One pending request is allowed.
2. An independent Approver records an authorization or return, with reason and
   confirmation; authorization also needs an authority reference. Requesters and
   original preparers/submitters cannot review, even after changing role. An
   inactive/non-Payroll-Officer requester cannot receive a new authorized draft;
   an independent reviewer can still return the request.
3. Authorization atomically creates an **empty** draft for the same month/data
   use, with the next consecutive payroll version and immutable source/root
   links. It never reopens the old batch or copies old coverage/inputs. A returned
   request creates no draft and permits a new retained request revision.
4. The Payroll Officer selects coverage and prepares reviewed replacement inputs,
   core calculation and statutory reviews. Existing submission, return/resubmit,
   independent approval and finalization gates apply. Authorization alone neither
   submits nor finalizes payroll. An independent Approver may authorize, approve
   and finalize; the requester remains a preparer of the replacement.
5. The corrected submission freezes source hash, source/root/version IDs, request
   revision, reasons, authority references, actors and application timestamps.
   Finalization uses version 2, 3, etc., and the canonical envelope hashes that
   authorization lineage with the payroll. Legacy version-1 snapshot shape and
   canonicalization remain unchanged. Missing/changed lineage or a source changed
   since authorization blocks preparation of a submittable correction snapshot.
6. The family history links each version's fixed review, documents, commitment/
   attempts and integrity checks. Prior finalized values, CSVs, hashes and check
   history remain retrievable and unchanged. Each correction gets separate local
   evidence; its parent's anchor/check is never reused as proof for the new version.

A correction is a **complete replacement**, not an additional payment or a
monetary difference to disburse. No payment, delta calculation, document upload,
loan management, old-version reopen, cancellation or automatic data copying is
implemented. Only the latest finalized head accepts another correction. An
authorized draft must be completed before another family version can be opened.

## Integrity, access and retention

- Active Payroll Officers request; independent active Approvers authorize/return;
  Viewers read history; Administrators retain their existing no-payroll-record
  access. Server-side actor/session/policy checks repeat inside the transaction.
- Family locks serialize request/decision checks; unique period/mode/version,
  parent finalization, request revision/key and decision/draft constraints reject
  duplicates/forks. Exact retries return the same evidence even after the family
  advances; conflicting content/actor/action/key reuse is rejected.
- An evidence-insert failure rolls back the new draft. Source hash changes fail
  closed; returning a changed-source request does not repair or overwrite evidence.
- Request/decision models reject update/delete; batch models reject lineage and
  correction month/data-use changes and deletion. FKs restrict parent/history
  deletion. Direct privileged database modification is outside those model guards.
- POST uses CSRF, throttling and UTF-8 sanitation. GET is read-only, escaped,
  private/no-store, no-sniff/no-referrer and paginated with overflow protection.
- Reasons, authority references and payroll details stay local. This phase adds
  no external submission path or adapter payload; only the existing SHA-256
  commitment interface can be used if a genuine adapter is later authorized.

## Files and schema

New: `PayrollCorrectionService`, `PayrollCorrectionController`, immutable
`PayrollCorrectionRequest`/`PayrollCorrectionDecision`, correction-history Blade
view, `PayrollCorrectionTest` and migration
`2026_10_27_000004_create_payroll_correction_versions`.

Updated: batch model/policy, preparation/workflow services, routes, monthly list/
batch/review views, rollback-only MySQL workflow verifier, README and four tracking
documents. No dependency, formula, statutory activation or canonical format change.

The migration adds `payroll_version` (existing rows default to 1), root/source
lineage FKs and two retained request/decision tables. The month/mode unique root
constraint becomes month/mode/version uniqueness, with unique source finalization
to prevent forks. Regular draft creation always uses version 1. Rollback refuses
retained correction requests or versions. Empty rollback/reapply is tested on
SQLite; MySQL forward migration succeeds with all **16 migrations Ran**.

The MySQL verifier runs a complete replacement through renewed coverage/input/
calculation/statutory review, independent approval, finalization, JSON/hash,
documents and local checks inside its outer transaction. All fictional fixtures,
including replacement records, are rolled back. The initially stopped MySQL
helper was stopped again after validation.

## Tests and acceptance

- `PayrollCorrectionTest`: **28 cases / 540 assertions pass**. Covers three
  finalized versions, empty drafts, changed amounts, source/root/authorization
  linkage, separate commitments, original CSV/hash/check preservation, all roles,
  source/requester role changes, input validation/UTF-8, stale/cross-bound routes,
  exact/conflicting retries, return/revision, ancestor-fork prevention, source
  tampering before/after authorization, missing/wrong context, storage-fault
  rollback, stale/inactive sessions, retained/FK/model history, empty migration
  rollback, application timestamps, pagination/escaping, CSRF and real/draft blocks.
- Existing preparation/workflow subset: **84 tests / 1,051 assertions pass**.
- Full hostile-environment isolation: **565 tests / 7,079 assertions pass**,
  203.311 seconds, 100 MB, including all 537 earlier cases. Detailed commands are
  in TESTING_STATUS.md. No coverage percentage was measured. Earlier Phase 12-A
  comparison/mismatch tests remain.
- MySQL replacement/original-preservation/JSON workflow passes with fixture
  rollback. Application/verifier Pint, JavaScript syntax, Composer strict/platform
  validation, production asset build (60 modules) and whitespace checks pass.

| Parent Phase 12 acceptance | Evidence / limit |
| --- | --- |
| Original finalized snapshot verifies successfully | Local hash consistency and explicit test-double confirmed comparison pass; genuine verification remains blocked without an authorized network |
| Modified isolated test snapshot produces mismatch | Existing isolated-copy/tampering verification tests remain; new correction tests reject changed sources and preserve evidence |
| Previous payroll versions remain available | New permanent three-version test retrieves old reviews/documents/anchoring/checks and compares original records/CSV/hash; MySQL full replacement preserves originals |
| No sensitive payroll details disclosed on-chain | No network operation added or performed; authorization/payroll stays local and the existing adapter contract accepts hash payloads only; real adapter deployment still needs validation |

## Manual verification

1. Apply migrations and finalize a fictional monthly payroll using normal reviews.
2. As Payroll Officer, open its correction/version history and submit a reason,
   authority reference and confirmation.
3. As independent Approver, review and authorize. Confirm a new empty version-2
   draft appears while the original is finalized; alternatively return a request
   and confirm its retained reason and new-request revision path.
4. As Payroll Officer, prepare complete replacement coverage/inputs and reviews;
   submit, then approve/finalize using an independent Approver.
5. Reopen both versions' fixed reviews, register/payslips and integrity history.
   Confirm changed replacement amounts, unchanged originals and separate hashes.

## Remaining limits and next phase

Authority references are operator attestations, not verified legal authorization.
Actual office correction/replacement policy and reviewer authority, real payroll
rules/examples/templates, genuine chain confirmation/retrieval/reorganizations,
actual concurrent writers and process-crash recovery, capacity, and live browser/
accessibility/printing acceptance remain unvalidated. Sequential retries and fault
injection are not proof of real concurrency or operational recovery. There is no
automatic draft cancellation or ability to bypass a blocked source's integrity.

The local correction workflow is implemented and tested; parent Phase 12 genuine
blockchain acceptance remains incomplete. Next: **Phase 13 - Integration Testing
and Mock Defense**, only with authorization. No commit, push, publication or
Phase 13 implementation in this turn. **STOPPED - Awaiting permission to continue.**

## Current-phase F1 follow-up - 2026-10-27

PHASE_12B_AUDIT.md found a stale-source display inconsistency without a saved
commitment. Under the user's subsequent fix instruction, pending request source
hashes now must match the fresh digest before showing authorization. Mismatches
show an integrity warning and only independent return remains available. GET
remains read-only, the locked POST still rejects changed sources with 409, and
source/request evidence is preserved. No new correction capability was added.

The exact failing-before/passing-after regression is now permanent. Correction
coverage is **29 cases / 576 assertions**; full isolation passes **566 tests / 7115
assertions**, plus Pint, JS syntax, production build and whitespace. Detailed
results are in TESTING_STATUS.md. F1 is resolved. No schema, service/controller, dependency,
network, real payroll or Phase 13 change; earlier validation limits remain.
