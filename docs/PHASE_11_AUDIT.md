# Phase 11 audit - Blockchain Anchoring local interface

Date: 2026-10-27 (Asia/Manila). Scope: the existing uncommitted Phase 11 changes
after Phase 10 commit `b4e62fe`, against `master-prompt.md` Phase 11. The user
previously confirmed no test network is available and authorized the local-only
fallback. This audit changes documentation and ignored diagnostics only: no
application fix, permanent-test change, migration, commit or Phase 12 feature.

Subsequent user-authorized Phase 11 correction: **F1 and H1 are corrected**.
The original audit and execution evidence below are preserved; see the correction
note at the end and PHASE_11_HANDOFF.md for current behavior and validation.

## Result and acceptance criteria

**Implemented but needs validation. Live anchoring remains incomplete.** The
fallback implements canonical hashing, local preparation and honest failure
states. One low-severity local-interface bug is confirmed (F1). A separately
documented conditional transaction hazard was reproduced using a test double
(H1); it is not a live-network failure or an active broadcast risk with the
unavailable adapter.

| Phase 11 acceptance criterion | Assessment | Evidence and limits |
| --- | --- | --- |
| A finalized demonstration payroll is successfully anchored | Incomplete, authorized fallback | The only application binding is `UnavailableBlockchainSubmitter`; there is no transport, signer, test-network adapter or genuine broadcast. A saved local hash is never described as anchored. |
| Blockchain transaction is independently retrievable | Incomplete, authorized fallback | No real transaction or receipt exists. Pending transaction references in automated tests are expressly fictional test-double values. No independent network retrieval was performed. |
| Failed submissions do not falsely appear successful | Passed for tested local/interface paths | Default attempts retain `unavailable` with null network/reference. Definite rejection remains failed; unconfirmed references stay pending; ambiguous or interrupted acceptance blocks blind retry. No confirmed/verified state is implemented. F1 affects reading history, not conversion of failures into successes. |

Canonical representation and SHA-256 generation, one unique commitment per
finalization, authenticated status access, independent Approver actions,
transaction-time actor/session checks, scope/data-use guards and unchanged saved
payroll are implemented. Safe retry keeps the original hash/key and prior failed
attempts. Only a digest and opaque key cross the submission contract. Successful
status pages remain private/no-store. This is local-interface acceptance only,
not acceptance of a working blockchain deployment.

## Findings

### F1 - Low: extreme attempt-history page returns HTTP 500

Location: `application/app/Http/Controllers/PayrollAnchoringController.php:25`.

When at least one attempt exists, a request such as:

```text
/payroll-batches/{batch}/anchoring/{finalization}?page=9223372036854775807
```

passes the positive page number into `paginate(20)`. Its SQL offset calculation
overflows integer range. On the installed PHP 8.5.10/Laravel runtime, casting the
out-of-range float to an integer raises an exception and the endpoint returns
HTTP 500. The first overflowing offset boundary,
`intdiv(PHP_INT_MAX, 20) + 2`, reproduces the same failure.

This requires authenticated payroll read access and retained attempt history.
Ordinary retrieval continues working afterward. The isolated reproducer checks
that commitment, attempt and finalized snapshot attributes stay unchanged.
There is no demonstrated authorization bypass, false anchoring success or saved
payroll modification. The permanent suite has no extreme-history-page regression.

Recommended correction within Phase 11: validate/cap the requested page before
offset arithmetic, or reject pages beyond the retained history, then add a
permanent boundary regression. No correction was implemented in this audit.

### H1 - Medium before real integration: outer transaction can erase a send record

Locations: `application/app/Services/PayrollAnchoringService.php:53` and `:98`.

The interface is called after the service's own `DB::transaction` returns, but
that does not establish a durable commit if a caller already has an open
transaction. The service neither rejects that condition nor defers the send until
the outermost commit. An outer rollback can erase the attempt after the adapter
has already been called; a subsequent request can call the adapter again with
the same key. Stable keys alone do not prove network-specific deduplication.

An ignored isolated probe reproduces two test-double interface calls separated
by outer rollbacks, with no retained attempt. It performs no real broadcast.
The ordinary HTTP controller adds no outer transaction, and the production
binding cannot send anything, so this is a conditional integration hazard rather
than a second active fallback defect. The implementation handoff already notes
the durability requirement; the audit now supplies execution evidence.

Before binding a real transport, enforce durable reservation outside ambient
transactions or dispatch only after a durable commit; require and test actual
network idempotency/reconciliation. No change was implemented.

## Missing functionality and scope boundaries

- Genuine authorized network connection, signing/funding, hash broadcast, real
  transaction recording, receipt identity/confirmation handling and independent
  retrieval remain unimplemented. These are outstanding Phase 11 live-network
  criteria, explicitly permitted to remain incomplete by the fallback.
- Unknown/interrupted requests intentionally stay blocked. There is no live
  reconciliation or recovery command; that must be designed with the selected
  network and operating policy before actual broadcasting.
- Phase 12 on-chain hash comparison, verification history and correction-version
  workflow were not implemented or required by this audit. Their absence is not
  an additional Phase 11 local-interface defect.
- No real payroll processing is enabled. Earlier client/statutory/attendance,
  document-template, deployment and Phase 2/3 audit limitations are unchanged.

## Checks executed

| Check | Result |
| --- | --- |
| `scripts/verify-test-isolation.ps1` | Passed: **502 tests / 5949 assertions**, 167.302 seconds, 100 MB. All 480 earlier cases and 22 Phase 11 cases remain; inherited production/MySQL settings stayed pinned to in-memory SQLite |
| New ignored audit probes | **11 tests / 181 assertions passed**, 25.869 seconds, 66 MB. Includes a reproducer intentionally asserting F1's current HTTP 500 and H1's conditional test-double behavior; passing probes do not mean those findings are corrected |
| Existing MySQL workflow/document/local-commitment verifier | Passed, fictional fixtures rolled back. All 14 migrations already Ran; no migration executed. MySQL began stopped and was restored to stopped |
| Application Pint, JavaScript syntax, whitespace | Passed; existing verifier LF/CRLF normalization notice only |
| Application/permanent-test audit boundary | SHA-256 comparisons across **165 source/config/route/view/migration/test/verifier files** found no changes during this audit |

The first diagnostic run uncovered F1 and three audit-harness errors caused by
passing a session manager rather than the session store to a synthetic request.
The ignored harness now supplies the store. F1's initial expected-200 failure
remains in `probes.log`; the separate current reproducer intentionally asserts
500, without altering application code. Current probes and full-suite execution
logs are ignored under `.local/phase11-audit`.

Reproduce from `application/`:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never --filter=test_audit_ ../.local/phase11-audit/Phase11AuditTest.php
```

## Coverage and gaps

Permanent Phase 11 coverage remains **22 cases / 363 assertions**. It covers
canonical vectors/types/order/Unicode, all three personnel categories, idempotent
preparation, saved values, live-profile independence, role and session boundaries,
CSRF, scoped/data-use validation, stale hashes, local disagreements, safe retries,
pending/failed/unknown/interrupted states and model-level retention.

Additional ignored probes cover full-snapshot map-key reordering, hidden floats,
schema/metadata/amount rejection, actor changes after request construction,
malformed provider results, format/algorithm disagreement, a reentrant send seeing
the reservation, ordinary/malformed pagination, throttling, rollback protection,
F1 and H1. Reentrant same-connection execution is not a simultaneous multi-process
concurrency test. These probes are outside the permanent suite.

Still unverified: genuine chain submission/retrieval/confirmations/reorganizations,
real transport deduplication, concurrent database writers, lost-response/process-
crash recovery, persistence failure after a send, office-scale capacity and live
browser/accessibility acceptance. No line/branch coverage percentage was measured.
No new build/dependency installation was needed because application/assets were
unchanged; prior build evidence remains historical.

Only this report, relevant tracking documents and ignored diagnostics changed.
STOPPED at Phase 11 audit; no fix, commit, publication or Phase 12 work.

## Correction follow-up - 2026-10-27

The user subsequently authorized reviewing and fixing Phase 11 only. F1 now
returns HTTP 404 before any unrepresentable offset arithmetic. H1 now rejects
submissions inside outer transactions with 409 before reserving an attempt or
calling any interface. Every adapter uses this guard; no test-mode bypass was
introduced. A subsequent standalone call reserves/commits the attempt, then
invokes the interface with transaction level zero and the original digest/key.

Three permanent regressions were added. Before correction, the pagination case
reproduced the original 500 and the outer-transaction case permitted a call;
both now pass. Phase 11's disposable in-memory tests run without an ambient test
transaction to validate actual commit boundaries. Focused coverage is now
25 cases / 458 assertions. Nine applicable audit probes pass 161 assertions;
the original F1/H1 current-bug reproducers are intentionally excluded and retained
as historical diagnostics. Full isolation passes **505 tests / 6044 assertions**;
MySQL rollback-only verification, application/verifier Pint, JS syntax and whitespace
checks pass. Detailed execution evidence is in TESTING_STATUS.md.

Only controller pagination, the submission transaction guard, their permanent
tests, the existing MySQL verifier and documentation changed in this correction.
No new feature, schema, dependency, canonical format or Phase 12 work. Genuine
network anchoring and independent transaction retrieval remain incomplete under
the authorized fallback; other original operational coverage gaps remain.
