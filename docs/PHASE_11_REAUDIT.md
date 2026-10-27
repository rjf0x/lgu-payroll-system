# Phase 11 re-audit - Blockchain Anchoring local interface

Date: 2026-10-27 (Asia/Manila). Scope: current Phase 11 after the F1/H1
corrections, against the master prompt's Phase 11 tasks and acceptance criteria.
Phase 10 remains committed at `b4e62fe`; Phase 11 remains uncommitted. The user
confirmed no authorized test network is available and selected the local-only
fallback. This is an audit, with no application fix, permanent-test change,
migration, dependency change, commit or Phase 12 implementation.

## Result and acceptance

**Implemented but needs validation. Live anchoring remains incomplete.** F1 and
H1 remain corrected; no new confirmed bug was found in the supported local
interface paths examined. The unavailable binding is not a blockchain adapter
capable of satisfying the two live-network criteria.

| Acceptance criterion | Assessment | Evidence and limits |
| --- | --- | --- |
| Finalized demonstration payroll successfully anchored | Incomplete under the authorized fallback | Only local canonical SHA-256 preparation and the unavailable interface exist. No real transport, signing, broadcast or confirmed network transaction. |
| Transaction independently retrievable | Incomplete under the authorized fallback | No genuine transaction/receipt exists. All non-null references in tests are explicitly fictional doubles; no independent chain retrieval was performed. |
| Failed submissions never falsely appear successful | Passed for tested local/interface paths | Default attempts remain unavailable without a reference; definite rejection stays failed; returned references remain pending; unknown/interrupted outcomes block blind resubmission. No confirmed/verified status exists in this fallback. |

Versioned full-snapshot canonicalization, exact SHA-256 generation, one unique
local commitment per finalization, independent Approver authorization, retained
attempts and unchanged finalized payroll remain implemented. The interface
accepts only the digest and opaque retry key; it never receives payroll details.
Success in these local tests does not establish genuine blockchain anchoring.

## Bugs and missing functionality

- **F1 remains corrected.** Unsafe page offsets return 404 before multiplication;
  normal and largest-safe pages still load. New checks include an empty history,
  exactly 40 attempts, malformed/overlong page input and original authorization/
  snapshot-error ordering. No replacement overflow or mutation was found.
- **H1 remains corrected.** The outer transaction guard applies to both injected
  and default unavailable adapters. Local preparation may commit inside a caller
  transaction, but submission is rejected with 409 and no attempt. A later
  standalone default call correctly records unavailable without replacing the
  saved commitment. Permanent tests additionally cover outer commit/rollback and
  transaction level zero at the interface boundary.
- No new confirmed local-interface defect or missing supported fallback task was
  identified. The unavailable adapter is intentional and remains plainly labeled.
- Actual authorized network configuration, signing/funding, real hash submission,
  transaction/receipt recording, confirmation policy and independent retrieval
  remain unimplemented. These are outstanding Phase 11 network tasks permitted
  to remain incomplete under the explicit fallback.
- Network reconciliation/recovery is unavailable. A newly injected failure while
  saving an interface result leaves the already committed `submitting` attempt
  and blocks another send. This establishes a safe local failure state, not
  recovery of a real network reference or completion of confirmation handling.
- Phase 12 on-chain comparison, verification history and correction-version
  workflow remain outside this audit. Existing client/statutory/attendance,
  document/deployment and earlier Phase 2/3 audit limitations remain unchanged.

## Executed checks

| Check | Result |
| --- | --- |
| `scripts/verify-test-isolation.ps1` | **505 tests / 6044 assertions passed**, 180.758 seconds, 100 MB; hostile inherited production/MySQL configuration stayed pinned to in-memory SQLite |
| New ignored re-audit probes | **5 tests / 92 assertions passed**, 5.751 seconds, 64 MB; exact/empty history bounds, error ordering, default-adapter transaction guard, injected post-interface persistence failure and database commitment uniqueness |
| Applicable earlier ignored audit probes | **9 tests / 161 assertions passed**, 33.066 seconds, 64 MB; original F1/H1 bug reproducers intentionally excluded and retained as historical evidence |
| MySQL workflow/document/commitment verifier | Passed with fictional fixture rollback, preserved JSON digest/local idempotency and nested-submission rejection with no attempt. All 14 migrations already Ran; none executed. MySQL began stopped and was restored to stopped |
| Application/verifier Pint, JavaScript syntax and whitespace | Passed; ordinary verifier LF/CRLF notice only |
| Audit boundary | SHA-256 comparison across **165 source/config/route/view/migration/permanent-test/verifier files** found no changes during this re-audit |

Fourteen applicable ignored diagnostic cases pass **253 assertions**, separately
from permanent coverage. Detailed execution evidence is also recorded in
TESTING_STATUS.md. No new asset build or dependency installation was needed;
earlier build evidence remains historical.

Reproduce new checks from `application/`:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never --filter=test_reaudit_ ../.local/phase11-reaudit/Phase11ReauditTest.php
```

Ignored probes, execution logs and the audit hash manifest are under
`.local/phase11-reaudit`. Test business data was isolated in in-memory SQLite or
rolled back on MySQL; no network transaction was sent.

## Coverage and remaining validation

Permanent Phase 11 coverage remains **25 cases / 458 assertions**, covering
canonical bytes/types/order/Unicode, all three personnel categories, saved-value
preservation, local idempotency, roles/sessions/CSRF, scope and data-use validation,
stale hashes, provider-result/failure states, safe retry and pending/uncertain
blocking, pagination boundaries and outer-transaction durability guards. All
480 pre-Phase-11 cases remain in the regression suite.

The five new probes are not permanent regressions. They supplement permanent
tests with empty/exact-40 pagination, provider-result storage failure and explicit
database uniqueness enforcement. Actual parallel database writers, process kills,
lost real network responses, durable transport idempotency, chain identity,
confirmation/reorganization handling, independent retrieval, reconciliation and
recovery remain unverified. Injected persistence failure does not simulate all
disk/connection failures or establish full crash recovery.

No live browser/accessibility or office-scale capacity acceptance was performed;
no line/branch coverage percentage was measured. No real payroll processing is
enabled, and a coordinated privileged rewrite of a snapshot and local commitment
is not detectable through these local guards alone.

Only this report, the four tracking documents and ignored diagnostics changed.
STOPPED at Phase 11 re-audit; no fix, permanent-test change, migration, commit,
publication or Phase 12 work.
