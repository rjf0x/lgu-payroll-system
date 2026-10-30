# BESU-08 - Integration testing and mock defense

Status: demonstration tooling implemented; audited retry and tamper-comparison defects corrected; live completion remains BLOCKED. Do not treat passing controlled tests as production readiness.

## Reproduce

From the repository root on Windows with PHP, Composer-installed application dependencies and Node dependencies already installed:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify-besu-integration.ps1
```

The default runner executes the Besu anchoring, journal recovery, receipt verification, integrity UI and correction tests, followed by offline Node bridge tests. It temporarily disables inherited live-test flags and restores them on exit. PHPUnit's existing bootstrap refuses any database other than testing/in-memory SQLite before migrations. No application environment files are written. Default mode uses controlled responses and never sends to Besu.

For a real development-chain demonstration after the existing network and audit blockers are resolved:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify-besu-integration.ps1 -Live
```

Live mode requires the existing reviewed local Besu deployment identity and protected writer configuration. It runs controlled checks first, then the new combined live A/B/C/D case. A clock/head preflight fails before creating payroll fixtures or signing if the chain is stale/future-dated. Live mode can permanently record two fictional commitments on the development chain; application fixtures remain isolated and are discarded. It does not reset chain data, deploy contracts, generate keys, stop containers, change clocks or write environment files. Preserve journals and investigate if a send is uncertain; do not repeatedly create new demonstrations to bypass it. Public transaction references and block proofs are printed only after the combined scenario succeeds; signed payloads and payroll values are not printed.

## Scenario coverage

| Scenario | Combined demonstration |
| --- | --- |
| A - Successful anchoring | Prepare fictional payroll through existing independent approval/finalization, retain canonical bytes/hash, process durable outbox, require confirmed receipt, verify via HTTP, and repeat processing without a new commitment. |
| B - Tamper detection | Change only the corrected snapshot in the isolated SQLite fixture. HTTP verification recomputes the hash and must retain a mismatch while the original snapshot/check/journal remain unchanged. |
| C - Outage | Disconnect only the test application's RPC endpoint using the closed loopback port 1. Processing must remain incomplete with no transaction and unchanged payroll. Restore the endpoint and require successful confirmation. Shared containers remain running. |
| D - Corrected payroll | Authorize and finalize a correction through existing HTTP workflow. Require version 2, a different hash/reference, separate confirmed transaction history and independent successful verification of both versions before isolated tampering. |

The controlled-response case exercises the same scenario assertions without real RPC/signing. It supplies synthetic already-mined proofs, so it does not establish real broadcast, mining or cryptographic receipt validity. Existing anchoring and Node tests exercise those boundaries separately. Live mode uses real adapters without those mocks.

## Results and limits

The final default runner passed 85 PHP cases / 1391 assertions, zero failures/errors, four opt-in live cases skipped (44.898 seconds), and all three offline Node tests. Pint and whitespace checks passed. After making the controlled scenario independent of local environment identity values by loading the existing public deployment fixture, its final focused rerun also passed 1 test / 50 assertions. The complete application suite was not rerun for this test/runner-only phase; prior full-suite results remain in TESTING_STATUS.md.

The controlled combined scenario passed 1 test / 50 assertions. Its initial fixture had captured an empty registry map by value, causing an unavailable verification; the callback now reads the populated map by reference. No production code was changed to satisfy that test.

The explicit live combined case failed at its preflight: 1 case / 2 assertions, one failure, message `Live demonstration blocked: chain clock/head is not healthy; no transaction submitted.` The existing chain is stalled at block 8819 after environment clock regression. Consequently none of the new combined live scenario steps after that preflight is claimed as validated. Earlier real historical recovery evidence is documented in BESU_07_HANDOFF.md; it does not prove new corrected-version mining.

The audit reproduced a high-priority retry defect: an older worker could apply a failed receipt to a newer retry and its history row. The authorized bug-fix follow-up now binds pending/terminal result writes to the checked transaction hash, updates the matching journal in the same transaction and suppresses stale-worker error/backoff changes. Stale workers return current recorded status. Three permanent interleaving regressions (failed, pending and exception results) failed before the fix and pass afterward, preserving the replacement journal and failed predecessor.

The audit also found that Scenario B proved only a local mismatch: the application deliberately rejects changed snapshots before a chain read. The demonstration now explicitly retrieves the registry commitment after tampering, confirms it still contains the pre-tamper digest, and compares the independently retrieved digest against the recalculated changed snapshot hash. Application verification keeps its fail-closed local mismatch behavior; no verification policy was weakened.

Bug-fix validation: full PHPUnit suite passed 706 cases / 8874 assertions, zero failures/errors, four opt-in live cases skipped (03:47.089). The focused three interleaving cases plus corrected tamper scenario passed 4 cases / 109 assertions after all three interleaving cases reproduced failures before the fix. Existing rollback-only MySQL payroll workflow, all three Node bridge tests, Pint and whitespace checks passed. No new live submission was attempted while the chain remains stalled; successful fresh on-chain confirmation is still not claimed. Older ignored audit probes retain pre-fix assertions; the permanent regressions now assert the corrected behavior.

## Working integration points

Controlled coverage exercises finalization/outbox atomicity, preserved canonical snapshots, nonces and exact-byte retries, canonical receipt/registry verification, read-only HTTP integrity checks, role restrictions, outage/backoff handling, corrected versions and retained history. The existing QBFT network, registry and backend adapters remain unchanged. Full fresh live demonstration is incomplete.

## Decisions and production requirements

Client decisions still needed before production include validator ownership/hosting, administrator and writer custody/rotation, approval to use an actual payroll commitment protocol, backup/retention policy and an operational recovery owner. These are requirements to settle, not assumptions about approved deployment.

Prototype limitations include local HTTP RPC, development signer custody and single-host infrastructure. Application authorization, encrypted journals and hash-only on-chain records do not protect against a privileged database/operator compromise. Hash commitments do not prove that payroll inputs were truthful, authorize payment or establish legal compliance. Production needs managed custody, access-controlled transport, independent validators, monitoring/alerting, tested backup restoration, concurrency fixes, recovery drills, load testing and a reviewed security/privacy model.

The initial BESU-08 implementation changed only tests, a validation runner and this report. The authorized follow-up fixes only the existing BesuAnchoringService race and scenario assertion gap. No payroll calculation, migration, contract, network, dependency, key, clock or environment changes. No commit or push. Stop after this phase; live acceptance remains blocked by the network prerequisite.
