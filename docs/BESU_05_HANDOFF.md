# BESU-05 - Automatic Payroll Anchoring

Implemented 2026-10-30 after BESU-04 commit 0dd090f. Scope ends before BESU-06.

## Behavior and boundaries

When BESU_ENABLED and BESU_AUTOMATIC_ANCHORING are true, the existing independently authorized, approved finalization transaction creates its immutable canonical archive, commitment and unique durable outbox entry together. No RPC or signing occurs in the request. Any archive/outbox failure rolls back finalization. Disabled mode preserves the previous workflow; older finalizations are not silently backfilled. Existing demonstration-only and separation-of-duties guards remain unchanged.

The scheduler runs `besu:anchor --limit=20` each minute when enabled. This database outbox is the durable job mechanism; it does not depend on the application's sync queue setting. The command may also be run explicitly. It processes queued, signed and pending entries, and excludes terminal confirmed/failed entries. It returns failure for incomplete/failed processing and never prints payroll content, raw signatures, provider errors or credential paths.

Before signing, the processor validates the retained source/archive/hash and version, verifies the existing Besu chain/genesis/runtime/admin/writer identity, locks the outbox row and the chain/writer nonce cursor, and reserves a unique nonce. Reads and offline signing occur in this transaction, but broadcasts never do. The signed bytes (encrypted using Laravel's application key), expected hash, nonce, frozen public network identity and signed audit event commit before any network send. Multiple workers serialize reservations through database row locks. The writer must be dedicated to this application; external use can consume reserved nonces and requires operator reconciliation.

An interrupted/uncertain send retains exactly that transaction and nonce. Later processing checks for its receipt and known transaction before rebroadcasting identical bytes, so duplicate sends have the same transaction hash and cannot create a conflicting second commitment. No replacement transaction, blind nonce reuse, automatic fee bump or automatic repair is implemented. Persistent errors leave an incomplete journal for operator investigation. Preserve the application encryption key and journal during recovery.

Confirmation requires a successful canonical receipt for the expected hash, exact recovered signed transaction/chain/writer/contract/record calldata, the matching CommitmentRecorded event, and the same digest/writer/event timestamp in independent registry lookup. The receipt block is checked again after lookup. QBFT's committed block provides the one-block finality policy on this development network. Status-0 receipts are terminal failed; pending/unknown/error states are never marked confirmed. Confirmation block number/hash/time and append-only service audit events are retained. Terminal confirmation describes a historical check, not continuous reorg monitoring or a current payroll integrity verdict.

No payroll calculation, permission, contract, network configuration or existing manual verification UI changes. Legacy reader/submitter bindings remain unavailable. BESU-06 must integrate the new outbox/confirmation records with the UI; existing manual screens do not display these automatic confirmations yet. Production custody/governance, generalized retries and broader recovery policy remain outside this phase.

## Operator setup

1. Install existing PHP/Node dependencies and start the retained Besu QBFT network.
2. Configure the reviewed BESU-04 chain/contract identity and protected encrypted writer files. Keep the writer exclusive to this application.
3. Run `php artisan migrate` from application. The new migration is `2026_10_30_000002_create_besu_anchor_outbox`; rollback refuses to destroy retained journals.
4. Set BESU_ENABLED=true and BESU_AUTOMATIC_ANCHORING=true in the protected, ignored environment. Clear any existing config cache after changing settings. The tracked example defaults anchoring to false.
5. Run Laravel's scheduler (`php artisan schedule:work` locally, or the standard once-per-minute schedule:run task). `php artisan besu:anchor --limit=20` processes a bounded batch immediately. Limit range is 1-100.
6. Inspect besu_anchor_outbox and besu_anchor_events for durable status. Do not treat pending/signed/incomplete results as confirmed. Database snapshots, key backups and journal access need the same operator protection as the signer.

The existing Docker Desktop/Besu services and configured Laragon MySQL were initially stopped. They were started using their existing configuration. Only the new outbox migration was pending and applied to the configured MySQL; no database credentials, payroll records or calculation settings were changed. The new automatic flag was appended to the ignored local environment. Scheduler registration and an empty outbox command were verified; a persistent scheduler process was not installed or launched.

## Validation

- Focused final PHP tests: 29 cases, 209 assertions, one default opt-in live case skipped. Cases cover atomic enqueue/rollback, disabled behavior, encrypted journal before broadcast, ambiguous-send recovery, terminal failure, source tampering, no send inside an outer transaction, distinct nonces and MySQL-style JSON key reordering/identity drift. Seven receipt-verifier cases cover pending/failed/confirmed, wrong hash, noncanonical block, wrong digest and event rejection.
- Opt-in live case: `BESU_LIVE_TEST=1 php vendor/bin/phpunit --filter test_live_besu_finalized_payroll_is_confirmed_once` passed, 1 test / 18 assertions. Fictional payroll runs through real HTTP workflow and finalization, durable signing/broadcast, actual canonical receipt and contract read. Application fixture DB is isolated in-memory SQLite; only an opaque reference and digest reach Besu. The writer nonce increases by exactly one, repeated processing does not sign/send again, and the finalized snapshot stays unchanged.
- Example live proof: transaction 0x8668ae90aa3afeb097a2ba3c17cae180feed73d7160cf571bf18ca3b5b9980ed; reference 0xc6ea18630eb10de27b1e2f13409ad2561774d561663cfced43689ad02cf6db04; block 4881 / 0x89a7dad2b82a3c87c0569c76b55b8619090426ae89a324500adf13d06b9ce4e0.
- Node bridge: 3 tests passed, including real encrypted ephemeral signer recovery plus exact mined transaction/event verification and invalid chain/status/hash/event/digest rejection.
- Existing MySQL workflow script passed with automatic anchoring explicitly disabled for the legacy check, including all three classifications, archive/documents/correction retention and complete fictional fixture rollback.
- Full regression suite: 661 cases / 8122 assertions passed, with the live case skipped. This run began before the final two nonce/identity tests were added; the final 29-case focused run separately passed all final Besu cases. See TESTING_STATUS.md.
- Pint, Composer manifest/platform checks and whitespace validation passed. npm audit reported zero vulnerabilities. No new dependencies.

The initial live attempt failed while Docker/Besu was stopped; rerun after startup passed. An initial Mockery expectation conflict in the ambiguous-send test was removed; durable single-signature assertions remain. A source review caught MySQL JSON object key-order differences; comparison now normalizes key order while preserving typed identity values, with a regression case.

No commit, push, future-phase implementation, personal payroll data on-chain or generated signing credentials in Git. Prior BESU-01/02/04 audit findings remain outside this change except confirmation now explicitly rechecks the receipt block after lookup. Stop after BESU-05.

## BESU-05 audit bug fixes - 2026-10-30

Current-phase fixes only. F1: the bounded processor now selects the least recently attempted eligible entries (updated_at, then id), and stamps attempts before processing, including incomplete attempts. Persistent pending/incomplete entries therefore rotate instead of monopolizing every scheduled run. A three-entry regression advances successive scheduler minutes and proves later queued work is reached and pending work is revisited.

F2: registry identity checks now have a read-only mode that retains chain/genesis/runtime/administrator validation without requiring current writer authorization. Historical lookup and receipt reconciliation use this mode, so revocation does not hide previously mined records. New signatures, writer-health checks and broadcasts still require current writer permission. Canonical inclusion, exact transaction/event checks and registry digest/writer/timestamp comparison remain mandatory. Regressions cover historical lookup/confirmation, wrong-chain rejection, blocked signing and blocked rebroadcast while revoked.

Before implementation, the new starvation/revocation regressions failed as expected. Final validation: full regression run 666 cases / 8209 assertions, one opt-in live case skipped; final expanded focused run 33 cases / 276 assertions, one opt-in live case skipped. The final rebroadcast-authorization regression was added after the full run started and passed in the expanded focused run. Explicit real Besu integration passed separately; Node bridge 3 tests passed; existing MySQL workflow passed with fictional writes rolled back; Pint and whitespace checks passed. See TESTING_STATUS.md for final live result.

No migration, environment, dependency, payroll calculation, UI, commit, push or future-phase change was made during these bug fixes. The old ignored audit probes retain pre-fix expectations and are historical evidence, not the permanent regression suite. Stop before BESU-06.
