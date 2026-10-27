# Phase 12-A audit - Integrity Verification and Check History

Date: 2026-10-27 (Asia/Manila). Scope: the current uncommitted Phase 12-A following
Phase 11 commit `f448daf`, against the master prompt's Phase 12 criteria and the
documented A/B split. The user previously confirmed no authorized test network.
This audit changes documentation and ignored diagnostic probes only: no
application fix, permanent-test change, migration, commit or Phase 12-B/13 feature.

## Result and acceptance criteria

**Implemented but needs validation.** No new confirmed defect was found in the
tested Phase 12-A scope. Genuine blockchain verification remains incomplete.
Phase 12 as a whole is not complete: correction versions remain deferred to
the separately scoped Phase 12-B.

| Master prompt acceptance | Assessment | Evidence and limits |
| --- | --- | --- |
| Original finalized snapshot verifies successfully | Passed comparison logic with confirmed-receipt test doubles; genuine acceptance incomplete | Verification requires a valid snapshot/local digest and identity-matched confirmed receipt with hash/block/positive confirmations. The actual container binds an unavailable reader, which cannot retrieve or confirm a real commitment. No genuine verified payroll or independently retrieved transaction exists. |
| Modified isolated test snapshot produces a mismatch | Passed tested supported paths | Permanent cases detect an inconsistent changed salary amount and a differing confirmed test-receipt hash. Additional workflow-reason tampering also mismatches. Unserializable or malformed snapshot shapes safely produce invalid-snapshot results instead of claiming a match. |
| Previous payroll versions remain available | Partial for full Phase 12; passed existing preservation scope | Existing finalized version 1, its documents, commitment, attempts and earlier check rows remain retrievable and unchanged. The schema still has one finalization per batch; authorized correction-version creation and old-versus-new finalized-version retrieval are not implemented and belong to Phase 12-B. |
| No sensitive payroll details disclosed on-chain | Passed local/interface boundary; no real chain exercised | Verification makes no blockchain write. Reader calls receive only saved network/transaction references. Check rows retain hashes and receipt metadata, not employee/payroll snapshots. Full snapshots remain local. No real on-chain privacy/transport inspection was possible. |

## Bugs and missing functionality

- **No new confirmed Phase 12-A bug** was found in reviewed code or executed
  supported-path probes. The application-clock correction still passes, including
  the early invalid-reference save path and unchanged timestamp on UUID retries.
- Actual trusted-chain connection, successful anchoring, real receipt retrieval,
  confirmation/reorganization policy and independent network validation remain
  unavailable. An interface-shaped test receipt is not independent blockchain
  evidence; live acceptance cannot be inferred from passing tests.
- Authorized correction versions are missing from full Phase 12, intentionally
  deferred in the documented subphase split. This audit does not implement them
  or claim that version 1 retention demonstrates a correction lifecycle.
- There is no background confirmation polling, transaction reconciliation or
  operational recovery workflow. These absences remain documented. A simulated
  history-insert failure propagates without storing a check or modifying source
  records; retry repeats only a read-only test-receipt lookup. That demonstrates
  local transaction rollback, not recovery against a real failed network/disk.
- Receipt lookups occur while actor/batch/finalization locks are held. Actual
  network timeouts, lock latency, multi-process conflicts and office-scale capacity
  remain unmeasured. A future transport must enforce trusted chain/payload and
  approved confirmation policy, not just fill the receipt DTO.
- Existing real-payroll blocks and earlier client/statutory/attendance/document/
  deployment and Phase 2/3 audit limitations are unchanged. Phase 13 remains out
  of scope.

## Checks executed

| Check | Result |
| --- | --- |
| Full `scripts/verify-test-isolation.ps1` | **537 tests / 6539 assertions passed**, 172.426 seconds, 100 MB. Includes all 505 earlier cases; hostile inherited production/MySQL configuration stayed pinned to in-memory SQLite |
| New ignored audit probes | **9 cases / 155 assertions passed**, 3.092 seconds, 62 MB. No permanent tests added/changed |
| MySQL workflow/document/anchoring/check-history verifier | Passed with fictional fixture rollback: JSON digest/history preservation, UUID idempotency and isolated snapshot-copy mismatch. All 15 migrations already Ran; none executed. MySQL began stopped and was restored to stopped |
| Application/verifier Pint, JS syntax and whitespace | Passed; ordinary verifier LF/CRLF notice only |
| Audit boundary | SHA-256 comparisons across **175 source/config/route/view/migration/test/verifier files** confirm no change during this audit |

No build or dependency installation was necessary; prior build/Composer evidence
remains historical and was not rerun for unchanged application sources. Audit
probes, source hash manifest and execution logs are ignored under
`.local/phase12a-audit`.

Reproduce diagnostics from `application/`:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never --filter=test_audit_ ../.local/phase12a-audit/Phase12AuditTest.php
```

## Test coverage and gaps

Permanent Phase 12-A coverage remains **32 cases / 495 assertions**. It covers
all three personnel categories; exact Phase 11 canonical bytes; no GET writes;
unanchored/unavailable defaults; explicit confirmed receipt comparison; local
and chain mismatch; pending/failed/unknown states; invalid receipt/snapshot/format;
provider exception suppression; UUID idempotency/history; role/session/CSRF/scope
and actor revocation; pagination overflow; model/rollback retention; and stored/
displayed application time. All 505 earlier cases pass alongside these tests.

Nine additional ignored cases cover:

1. Wrong network and wrong transaction independently fail identity validation.
2. Invalid-reference results use application time; a repeated UUID preserves it.
3. Workflow tampering mismatches, while null/float snapshot shapes fail closed.
4. Receipt block metadata is escaped and history rows contain no payroll payload.
5. An injected unique-key race exception becomes HTTP 409 without a history row.
6. Injected history storage failure rolls back cleanly; retry repeats a read-only
   receipt lookup and stores exactly one new result.
7. Database foreign keys prevent deletion of a referenced finalized source.
8. Viewer GET does not fetch/rewrite evidence; altered local data shows a stale-
   check warning while the earlier verified test-double result remains historical.
9. Client-supplied success/hash/confirmation/actor/time fields cannot manufacture
   a verified result; only the request UUID is accepted by the action.

These probes are outside the permanent suite. Fault injection is not an actual
parallel uniqueness race, full process crash or physical storage/network failure.
No real chain identity, confirmation/reorganization, transport privacy, independent
retrieval, concurrent writer, capacity or live browser/accessibility acceptance was
performed; no line/branch coverage percentage was measured.

Only this report, the four tracking documents and ignored diagnostics changed.
STOPPED - Phase 12-A audit only. No fix, permanent-test change, migration, commit,
push or Phase 12-B/13 implementation.
