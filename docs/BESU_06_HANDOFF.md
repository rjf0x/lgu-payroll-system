# BESU-06 - Integrity Verification UI

Scope: BESU-06 only, following BESU-05 commit 2cf4fb8. Implementation is complete; full live end-to-end validation is blocked by the existing network clock condition described below. Stop before BESU-07.

## Behavior

The existing finalized payroll details and integrity-check page now display verification status and the recorded automatic Besu transaction reference. Authorized Payroll Officers and Approvers can use Verify Integrity; Viewers remain read-only and Administrators retain their existing denied payroll scope. Existing authentication, active-account/auth-version checks, CSRF protection, throttling, batch/finalization binding and actor-scoped idempotency keys are retained.

GET requests only inspect local records and render the most recent saved check, its timestamp and a warning that results are historical rather than continuous network monitoring. They never contact Besu, sign or send. A cached confirmed outbox entry alone does not label a snapshot Verified. Changed snapshot/hash/attempt/outbox/reference evidence makes an older saved result stale; the current mismatch replaces its summary badge while immutable history stays available.

POST verification recomputes canonical SHA-256 from the frozen finalized snapshot, checks the retained archive/local commitment and then uses the BESU-05 verifier to independently check the exact signed transaction, canonical receipt, contract event and registry value. A separate read-only BesuIntegrityReader validates frozen network identity and reference binding, decrypts the existing signed journal only for transaction inspection, and obtains the displayed on-chain hash through registry lookup. It never loads signing keys, reserves nonces, broadcasts, retries anchoring or updates the outbox. Current writer authorization remains unnecessary for historical reads; identity and transaction/receipt checks remain mandatory.

Matching confirmed evidence produces Verified. Altered isolated snapshot values or conflicting chain values produce Integrity Mismatch. Queued/pending, failed and unavailable results cannot become Verified. Outages and malformed/changed identity fail safely, without raw provider/process errors or signed bytes reaching pages or stored verification records. Earlier manual-reader behavior remains supported; its unavailable adapter is not silently activated.

Each new check remains immutable in payroll_verifications, now with an optional restricted foreign key to its Besu outbox record. Migration 2026_10_30_000003_link_besu_verification_history adds only that nullable link and refuses rollback when linked history exists. It was the only pending migration and was applied to configured MySQL. No historical hashes, payroll calculations, roles, environment values, contract or network configuration were changed.

## Use

From finalized payroll details, open Payroll integrity checks and history, then use Verify Integrity. Read the result with its last-checked timestamp, transaction, current/saved/on-chain hashes and retained check history. The existing demonstration-only payroll boundary still applies. BESU_ENABLED must be configured using the existing reviewed deployment identity; turning automatic submission off does not prevent historical verification.

## Validation and limits

- Full application regression: 679 cases / 8433 assertions, zero failures/errors, two opt-in live cases skipped, 03:50.933. The final local-message wording and its additional assertions were checked separately after that run began; see TESTING_STATUS.md.
- Focused UI/legacy verification/finalization checks pass. They cover fresh proof versus cached confirmation, matching hashes, mismatched local/on-chain values, stale badges, pending/failed states, outage redaction, immutable linked history/rollback, idempotency, viewer restrictions and cross-batch requests. Existing role/session/CSRF tests remain passing.
- Existing MySQL payroll workflow script passed with automatic anchoring disabled only for that legacy check process; all fictional writes rolled back.
- npm run build, Composer manifest/platform checks, Pint and whitespace checks passed. Generated assets remain ignored; no dependencies were added.
- A headless Edge rendering of the actual Blade response was visually inspected at desktop width using controlled synthetic confirmation evidence. Visual probe/HTML/screenshot/browser profile are under ignored .local, not source-controlled. This is controlled-response UI validation, not evidence of a new live confirmation.
- Initial tests exposed an inline Blade PHP compatibility issue; the template now uses the existing block syntax. Three old workflow assertions expecting the obsolete unconditional no-anchor message were updated to check accurate new status/verification wording. A stale compiled-view cache was cleared during visual-copy validation.

The explicit live UI integration attempt failed at its anchoring prerequisite: after polling, the submitted fictional transaction remained pending. Independent node inspection showed latest block 8819 while validators rejected block 8820 because proposed timestamps were approximately 53,815 seconds earlier than the parent. RPC and all five peers/services remained reachable, but consensus could not advance after the environment clock moved backwards. No clock, genesis, chain data, writer nonce or permission was reset; no successful live verification is claimed. The opt-in test remains available as BESU_LIVE_TEST=1 php vendor/bin/phpunit --filter test_live_besu_http_verification_shows_verified_then_isolated_integrity_mismatch. It also checks actual endpoint failure display and an unchanged writer nonce across verification once the network can mine again.

The pending fictional test transaction may be mined when the network clock catches up. It contains only an opaque reference and hash; application fixtures were in-memory SQLite. Do not treat it as a production payroll anchor. Clock/network recovery, broader retry/correction/history work and production custody remain outside BESU-06.

No commit, push or BESU-07 implementation.

## BESU-06 F1 staleness bug fix

Changed journal/configured network identity or batch reference now invalidates the saved Verified summary on both pages. New checks retain a SHA-256 fingerprint of the context actually checked: stored/configured public network identity, commitment/reference, transaction hash and nonce. JSON object keys are sorted so MySQL serialization order does not invalidate equivalent context. Queued entries with no signed identity yet still have a stable context fingerprint; unchanged pending/failed checks do not become spuriously stale.

Migration 2026_10_30_000004_preserve_besu_verification_context adds one nullable fingerprint column and refuses rollback once context is retained. It was the only pending migration and was applied to configured MySQL. No existing check was backfilled or rewritten. Older Besu-linked checks with no fingerprint require a new check before their Verified result can be presented as current; original history remains available.

Permanent regressions first reproduced F1 (three changed-identity scenarios failed before the fix). They now cover stored identity drift, configured identity drift, simultaneous identity/configuration changes, reference changes, equivalent JSON key ordering, legacy linked records, historical immutability and context retention. Final focused UI/legacy verification run passed 50 cases / 817 assertions, with one opt-in live test skipped. MySQL legacy workflow and Pint/whitespace checks passed. See TESTING_STATUS.md for the full regression result.

The live integration prerequisite remains externally blocked: read-only inspection found head 8819 unchanged and validator proposals for 8820 rejected at timestamps approximately 51831 seconds earlier than the parent. No additional live transaction was submitted in this bug-fix turn. No clock, chain, genesis, signer, environment or payroll-calculation changes, generalized recovery features, commit, push or BESU-07 work were performed.
