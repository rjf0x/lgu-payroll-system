# BESU-03 audit - Payroll Snapshot and Hashing

Date: 2026-10-30 (America/Los_Angeles). The findings below describe the original
audit. F1/F2 were subsequently corrected under explicit bug-fix authorization;
see the correction record at the end. Current phase only. No implementation
fixes, permanent-test changes or BESU-04 work.

## Verdict

**Implemented but needs validation.** All five explicit BESU-03 hashing/retention
criteria pass for the exercised fictional workflows. Two integration findings
remain: medium F1 (document generation does not consult the archived baseline)
and low F2 (anchoring-page action state ignores that baseline). No incorrect
SHA-256 computation, v1 compatibility break, archive overwrite through an exposed
application action or unauthorized transport call was confirmed.

## Findings

### F1 - Medium: document export ignores an intact archived-byte mismatch

Location: `application/app/Services/PayrollDocumentService.php:32` (prepare),
reading the current finalization JSON at line 35 and returning its projection at
line 131. This existing document path was not integrated with the new archive.

Reproduction in disposable in-memory SQLite using a complete fictional finalized
workflow: retain the original archive, then modify only the saved JSON's first
employee_name to an isolated fictional marker through the query builder. Local
verification returns invalid_snapshot and commitment preparation rejects the
same source with HTTP 409. However, document prepare returns the changed name,
and the authorized register-CSV route responds HTTP 200 containing that name.
The archived bytes/hash remain unchanged, and no commitment is created.

Impact: document exports can disagree with the newly preserved finalization bytes
while local verification correctly detects the mismatch. This requires a saved-JSON
change outside the protected application model; it does not establish a normal
web-user editing bypass or corruption of real payroll. The original archive was
not deleted or rewritten, so the issue is broader than the documented inability
to defend against a privileged rewrite of **all** evidence. The existing
demonstration-only/real-payroll restrictions remain in force.

Recommended correction, not applied: make document issuance honor archive
consistency when an archive exists, retaining explicit legacy handling. Avoid a
circular call: currentBytes already invokes PayrollDocumentService::prepare, so
calling assertPreserved directly from that same prepare method would recurse.
Add a regression covering an intact archive plus a structurally valid changed
document field. No document or snapshot service was changed during this audit.

### F2 - Low: anchoring page offers preparation despite archive corruption

Location: `application/app/Http/Controllers/PayrollAnchoringController.php:31`-33.
The matches/canAct computation compares current JSON with a manual commitment
only. With no manual commitment it treats the source as matching without checking
the new archive.

Reproduction: after a valid fictional finalization, replace only its archived
canonical_bytes with `{}` using isolated query-builder test tampering. Verification
returns invalid_snapshot, but the anchoring GET still responds 200, displays
Prepare local commitment and its confirmation input, and omits the blocked-source
warning. Following that form correctly returns HTTP 409; no commitment or attempt
is created.

Impact: stale/misleading action availability and an avoidable failed user action.
The backend integrity check is effective; this is not a successful submission or
authorization bypass. Suggested correction, not applied: include archived-source
consistency in the existing page's action/warning state and test GET/POST agreement.
This concerns the existing preparation screen, not implementation of BESU-06 UI.

## Acceptance criteria

| BESU-03 criterion | Result | Evidence |
| --- | --- | --- |
| Identical snapshots produce identical hashes | Pass | Exact persisted bytes/hash and idempotent archives; database JSON map order and later employee-profile changes leave digests unchanged. |
| Payroll amount changes change the hash | Pass | Independent v1 fixture changes a two-decimal amount; existing correction flows produce distinct retained version hashes. |
| Snapshot ordering is deterministic | Pass | Reversed selection of three employees finalizes in employee-ID order; map keys are canonical; reversing the stored employee list changes the digest and fails preservation checks. |
| Historical finalized snapshots remain reproducible | Pass | Legacy dry-run/apply/rerun preserves digest, UUID, JSON and version; mismatches block capture; correction archives retain earlier versions. |
| Test fixtures produce expected hashes | Pass | Fictional encoder vector matches b59b444791271872119426f8ffa81071b2d4c41cf047b765d779f2a1f9bde575, independently calculated with Node crypto. |

The vector tests encoding rather than full financial-document validity. Complete
workflow fixtures cover valid finalizations across all three classifications.

## Functionality and security review

- The serializer implementation/version is unchanged from the earlier local
  interface. Monetary strings, date/null handling, Unicode escaping and map/list
  conventions retain v1 digest compatibility. Floats/unsupported values fail
  closed; current JSON is recomputed rather than replaced with archive content
  to conceal mismatches. SHA-256 hex decodes to exactly 32 bytes.
- Archive creation runs inside the existing authorization-checked finalization
  transaction. An injected failure rolls back finalization/status/event. No
  commitment, job, attempt or network operation is created by finalization.
- The unique finalization foreign key, restrictive parent deletion, immutable
  Eloquent update/delete hooks and populated-table rollback refusal protect
  ordinary retention paths. Query-builder/privileged SQL bypass remains a
  documented limit. Missing archives intentionally retain legacy compatibility;
  deletion of a new archive cannot be distinguished from an unarchived legacy
  record without additional trusted evidence. This was not relabeled as a new
  ordinary-user bypass.
- Preparation checks existing commitment compatibility and archive content/hash
  before creating a commitment. Verification/correction compare the independent
  local archive when present. Existing role, CSRF, independent approval and
  correction-history tests continue to pass.
- The archival command defaults to dry-run, supports one ID and applies valid
  records individually. Unsupported/mismatching records return failure without
  repair. Generic per-record errors avoid printing confidential bytes/provider
  exceptions. A partial apply retains its valid records as documented.
- Canonical archive content remains confidential off-chain application data. No
  new route exports archive bytes, no signing dependency was added, and transport
  bindings remain unavailable. The probes use only fictional in-memory records.
- Initial legacy capture without an older digest cannot authenticate prior
  contents; neither local archive checks nor model immutability provide an
  independent blockchain proof. Existing BESU-01/02 findings remain open.

## Tests performed

From `application/`:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never --filter 'Payroll(Snapshot|Anchoring|Verification|Correction|Workflow)Test'
php vendor/phpunit/phpunit/phpunit --colors=never --filter PayrollDocumentTest
php vendor/phpunit/phpunit/phpunit --colors=never --filter test_audit ../.local/Besu03AuditTest.php
composer validate --strict
composer check-platform-reqs
php artisan payroll:archive-snapshots
php artisan migrate:status --no-ansi
```

- Focused phase/integration suite: **147 tests / 2507 assertions passed**,
  01:13.036, 74 MB.
- The two ignored audit probes: **2 tests / 37 assertions passed**, 00:04.825,
  66 MB. They assert the reproduced undesired behavior and effective POST
  rejection, rather than claiming those findings are fixed. The probe reuses
  fictional fixtures, rejects submitter/reader calls and stray HTTP, and is
  selected separately from inherited permanent test cases.
- Document suite: **37 tests / 818 assertions passed**, 01:01.509, 102 MB. Its
  current normal-path cases do not cover F1's archived-baseline mismatch.
- Scoped Pint validation of changed/new PHP sources/tests/migration: pass.
- Composer strict validation and installed platform requirements: pass.
- Actual local archival dry-run: Validated: 0; blocked: 0; no records written.
- Migration status: all 19 migrations Ran; no migration was applied in this audit.

From root: `php scripts/verify-payroll-workflow.php` passes actual MySQL
approval/finalization/documents/correction checks plus exact archive equality,
independent correction archives, >64 KB UTF-8/escaped-line-ending round-trip,
archive-tamper rejection/restoration and complete fictional fixture rollback.
`git diff --check` passes with existing LF/CRLF notices only. The ignored audit
scratch file is excluded by Git. No contract, ABI, deployment, network, signer,
dependency manifest, canonical serializer or provider binding change was made.

The earlier full 629-test / 7809-assertion run remains implementation evidence;
the full suite was not repeated for this documentation-only audit. The focused
and document suites plus independent probes supply current checks of affected
paths. No real concurrency/crash, backup restoration, production capacity or
browser visual acceptance test was performed.

## Integration compatibility and stopping point

Snapshot schema/canonicalization references and retained correction versions remain
compatible with the existing application. Public digest representation is suitable
for later bytes32 encoding, but Laravel is not connected to Besu. No blockchain
submission or claimed on-chain verification was performed during this audit.

Documentation only: this audit plus progress/requirements/testing updates. An
ignored scratch probe was added; application source and permanent tests were not
changed, and no fixtures were retained. No commit/push.

**STOPPED - F1/F2 reported without fixes; BESU-04 not implemented.**

## Subsequent BESU-03 corrections - 2026-10-30

The user authorized fixes to current-phase bugs only. Both reproduced findings
are corrected without backend RPC, signing, anchoring or other future-phase work.

- **F1:** PayrollDocumentService::prepare now requires the independent archive
  comparison. Frozen schema/arithmetic projection is separated into
  validatedProjection for diagnostic hashing; currentBytes calls that validation
  path rather than archive-aware issuance, avoiding a recursive service loop.
  Register/payslip HTML, CSV, PDFs and the document index all use guarded issuance.
- **F2:** Anchoring GET includes archive consistency in matches/canAct. A mismatch
  keeps the history page readable, displays the existing blocked-action warning
  and hides the confirmation/action form. The warning now accurately covers
  archived-byte mismatches as well as manual commitments. POST remains guarded.
- Four permanent regression cases exercise changed source and corrupt archive,
  each before/after manual preparation, across all six document endpoints and
  direct service issuance. They also require GET/POST agreement and retention of
  snapshots, archives, commitments and zero attempts. A fifth case explicitly
  preserves read-only document/preparation access for valid unarchived legacy data.
- The older document pagination fixture now explicitly simulates an unarchived
  legacy record before changing its synthetic employee coverage. It does not
  rewrite an existing archive or weaken the issuance guard to pass the test.
- Actual MySQL rollback-only probes reject document generation with corrupted
  archived bytes and with changed source JSON while the original archive remains
  intact; restoring the synthetic data restores the exact original CSV.

The four regression cases initially failed because the old routes returned 200.
After the guard fix, all returned 409, but a new no-marker assertion encountered
Laravel's debug renderer including test source/arguments. That test now explicitly
uses production-style error rendering; no application debug setting was changed.
All **19 snapshot tests / 382 assertions pass** (00:08.886, 68 MB). The full suite
passes **634 tests / 7953 assertions** (03:16.533, 106 MB). MySQL, scoped Pint,
Composer strict/platform and whitespace checks pass. Final current-phase status:
**Implemented and tested** for the documented prototype boundary. Details are
recorded in TESTING_STATUS.md and the handoff correction addendum.

The ignored original audit probes assert pre-fix behavior and remain historical
reproduction artifacts; the new permanent tests assert the corrected behavior.
Canonical bytes, digests, monetary rules, roles, schemas, archived data and transport
bindings are unchanged by these fixes. Earlier-phase findings remain separate.
