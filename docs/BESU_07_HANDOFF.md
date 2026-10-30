# BESU-07 — Outages, Corrections, and History

This phase adds recovery and retained history to the existing automatic outbox. Stop before BESU-08. No changes to payroll calculations, deployed contract, network identity, signing credentials or environment configuration.

## Recovery behavior

`besu:anchor` processes due queued/signed/pending entries with bounded exponential backoff (5–300 seconds on incomplete checks). An uncertain send keeps the exact encrypted signed bytes, hash and nonce. Reconciliation checks the original transaction before any identical rebroadcast; it never replaces an ambiguous transaction with a new nonce. A stale head (over 120 seconds) or future head (over 30 seconds) prevents new signing/broadcasting while allowing historical proof checks.

Only an operator's explicit `php artisan besu:anchor --retry-failed=ID` may replace a mined failure. It rechecks the source, frozen identity and canonical failed receipt, retains the failed transaction in history, then queues the same reference/digest with a new nonce. It refuses pending/uncertain records and does not discard their evidence. Normal scheduler processing does not reset terminal failures.

A restored pre-send outbox first looks up its existing opaque reference. A matching registry row is recovered by querying bounded log ranges, reconstructing the public signed transaction without signing files, and checking the canonical receipt, exact signed calldata, writer, event and registry value through the existing verifier. Conflicting/missing evidence fails closed. Recovery scans at most block 100000 in ranges of 2000; larger histories need explicit investigation. This supports restoration of an existing commitment/outbox from an earlier backup, not recreation of a missing payroll database, submission UUID or snapshot archive.

Migration `2026_10_30_000005_retain_besu_recovery_history` adds retry metadata and a retained transaction ledger. Existing signed journals are copied verbatim, including ciphertext. It was applied successfully to configured local MySQL. Rollback refuses to remove retained outbox/history records. Transaction payloads are service-managed and hidden from serialization and page queries.

## Versions and visibility

Authorized corrections continue through the existing independent approval/finalization workflow. Each corrected version has its own commitment/hash/reference/outbox and transaction journal; the original snapshot, archive, commitment and verification history remain unchanged. Saved verification results remain historical and existing fingerprint checks invalidate their current summary when anchoring evidence changes.

Payroll anchoring pages now show automatic status, safe incomplete-check reason, next eligible check and paginated transaction/event history. Existing verification pages retain check history. Active Administrators can open `/admin/blockchain` for recorded queue totals and transaction status without payroll values, ciphertext or signing paths. This GET does no RPC or submission and does not grant administrators payroll access. No browser retry/signing action was added.

## Validation

Controlled regressions cover network unavailability/backoff/resumption, lost send responses and service restart, explicit failed retries with retained old journals, rejection of ambiguous resets, restored pre-send recovery without signing, conflicting evidence, clock regression, migration backfill, admin role restrictions and separate corrected-version transaction journals. Node tests cover event filters and exact cryptographic reconstruction of signed RPC transactions, including altered-input rejection.

Live read-only recovery passed against the retained fictional development commitment at reference `0xe2ea2e561da8ad0df99d1e82203cf20fd2bf658dd7b16bde5fc46fe12d85972f`. It recovered transaction `0x6eb81d20d217e5c4a6efa2a8e584e41e3db1c79dadde7091d3d17b9b837fed88`, nonce 34, block 6786. The opt-in test uses nonexistent signing-file paths and asserts the writer pending nonce is unchanged. Run with process variable `BESU_LIVE_RECOVERY_REF` and PHPUnit filter `test_live_read_only_recovery`.

Fresh live mining and a new corrected-version confirmation remain unvalidated: the existing development chain is stalled at block 8819 after the environment clock moved backward. Validators reject timestamps preceding the parent. No chain reset, clock change, key generation or new live broadcast was performed. Controlled confirmation tests do not substitute for a live mining result. Preserve the chain and durable journals; resume processing when its clock/consensus is healthy.

Final automated results are recorded in TESTING_STATUS.md. No commit, push or BESU-08 demonstration work.
