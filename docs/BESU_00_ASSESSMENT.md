# BESU-00 - Repository assessment

Assessed: 2026-10-28 (Asia/Manila).

## Phase

**BESU-00 - Repository Assessment.** This is the first phase of the separate
[Besu integration plan](HYPERLEGER_BESU_INTEGRATION.md). Earlier payroll Phases
11 and 12 implemented local interfaces, verification test doubles and correction
history; they did not establish a Besu network. No completed BESU checkpoint,
network configuration or contract was found. Reading the plan previously did not
complete BESU-00. The current authorization therefore covers this assessment only.

## Implemented

Inspected the application, retained workflow, schema, blockchain interfaces,
tests and local development prerequisites. Documented the reuse points and gaps
below. This phase changes documentation only. No network, contract, RPC adapter,
signing key, migration, payroll calculation or permission was implemented or changed.

### Existing project and integration points

| Area | Existing work and assessment |
| --- | --- |
| Application | `application/`: Laravel 13.38.0, PHP, Blade, Tailwind 4 and Vite 7; MySQL application storage. Retain this stack. |
| Development helpers | `scripts/`: local MySQL, payroll verification and test-isolation helpers. `.local/` is ignored local state. |
| Calculation | `PayrollCalculationService::calculate()` uses `PayrollCalculator`, frozen inputs and exact decimal strings/BCMath. `StatutoryPreviewService`, `StatutoryCalculator` and `PayrollRuleVersionService` supply retained reference calculations/configuration. These formulas are outside the Besu integration scope. |
| Review and finalization | `PayrollWorkflowService::transition()` locks the actor and batch, checks authorization, stale state, current source consistency and approval evidence, then saves workflow history and a finalization in one database transaction. Its `finalize` branch is the future automatic anchoring integration point. |
| Separation of duties | `PayrollBatchPolicy` restricts preparation to Payroll Officers, approval/finalization/anchoring to Approvers and integrity checks to Payroll Officers/Approvers. The workflow and anchoring services additionally reject preparers acting as their own reviewer after a role change. Retain both policy and service checks. |
| Finalized snapshot | `PayrollFinalization.snapshot` contains `finalized-payroll-v1`, the version, the full `payroll-review-v1` payload, submission metadata and workflow evidence. The payroll payload includes ordered employee entries, frozen earnings/deductions, totals, inputs, engine references and rule parameters. |
| Local commitment | `PayrollAnchoringService::bytes()` validates the frozen document projection and serializes the entire finalization envelope through `CanonicalPayrollSnapshot`. `digest()` calculates SHA-256. The `prepare` action creates one immutable commitment per finalization. It is currently a manual action after finalization. |
| Submission interface | `BlockchainSubmitter::submit($sha256, $idempotencyKey)` receives only a digest and a stable UUID. `PayrollAnchoringService::act()` commits an attempt reservation before transport; pending/unknown/interrupted attempts block blind resubmission. This is an interface, not a Besu transaction implementation. |
| Verification interface | `PayrollVerificationService` recomputes the snapshot digest, records local mismatch/invalid states and can compare an independently supplied confirmed receipt. `BlockchainReader::receipt()` has no real implementation. Saved references alone cannot verify a snapshot. |
| Corrections | `PayrollCorrectionService` creates separately authorized full replacement batches with increasing versions and frozen source-hash lineage. Prior batches/finalizations/commitments remain retained. Reuse this versioning; never reopen or overwrite an original commitment. |
| UI and routes | `routes/web.php`, anchoring/integrity/correction controllers and corresponding Blade views already expose protected local preparation, submission, verification and history. They still describe local-only/unavailable behavior. Reuse them in the appropriate later phase. |

Application finalization, documents, anchoring and verification currently support
**demonstration payroll only**. Existing real-payroll gates remain necessary;
blockchain consistency does not validate statutory rules or authorize release.

### Database structures

The application database readiness check passed. All **18 migrations** report
`Ran`, including the two payroll-configuration coverage migrations. The checks
were read-only; no migration, seeding or application-data modification was run.

| Tables | Relevance |
| --- | --- |
| `payroll_batches`, `payroll_entries`, `payroll_input_revisions` | Lifecycle, selected employees, saved payroll context and input history. `record_version` is a stale-state revision; it is distinct from the finalized `payroll_version`. |
| `payroll_calculation_runs`, `payroll_calculation_items`, `statutory_previews` | Retained calculation results, inputs and reference parameters used to build the submitted snapshot. |
| `payroll_submissions`, `payroll_workflow_events` | Frozen submission revisions and actor/action/reason evidence. |
| `payroll_finalizations` | One finalization per batch, unique submission, version and JSON snapshot. Corrections use new batch rows rather than additional finalizations on the original batch. |
| `payroll_commitments` | Unique finalization, canonicalization identifier, `sha256` algorithm, 64-character hex digest and unique submission UUID. |
| `payroll_anchor_attempts` | Retained attempts with `submitting`, `unavailable`, `failed`, `pending` or `unknown`; optional network/transaction references and completion time. No durable confirmed-anchor record exists yet. |
| `payroll_verifications` | Append-only local/network evidence and statuses, hashes, transaction/block references and confirmation count. A historical check is evidence at its check time. |
| `payroll_correction_requests`, `payroll_correction_decisions` | Independent authorization and replacement lineage. |
| `jobs`, `job_batches`, `failed_jobs` | Laravel database-queue infrastructure exists. No payroll anchoring job or reconciliation scheduler exists. |

Finalizations, commitments and verification history reject ordinary Eloquent
updates/deletes; restrictive foreign keys and rollback guards preserve history.
These are application protections, not database-level protection against a
privileged SQL rewrite or query-builder bypass. The blockchain comparison must
remain independent of the application database.

### Snapshot compatibility and work remaining for BESU-03

Reuse [Canonical payroll commitment v1](CANONICAL_PAYROLL_COMMITMENT_V1.md).
It defines sorted object keys, preserved list order, exact two-decimal monetary
strings, consistent JSON encoding and rejection of floats/objects. Employee order
is established by ascending employee ID during snapshot creation. Verification
must preserve the saved list order rather than silently sort a modified snapshot.
Dates in the snapshot are explicit period strings and ISO workflow timestamps.

The current database preserves snapshot JSON and its canonicalization identifier;
it does **not** separately store the exact canonical UTF-8 byte string. Existing
v1 intentionally hashes the decoded snapshot envelope, not raw database JSON
text. In BESU-03, address exact canonical-byte preservation and historical
compatibility explicitly. Do not change existing v1 digests, convert old amounts
to centavos, replace saved hashes or treat the stale-form review token as a
blockchain commitment. SHA-256 digest bytes must eventually be encoded as Solidity
`bytes32`; Ethereum ABI selectors/signatures require their own Ethereum tooling.

### Environment prerequisites checked

| Prerequisite | Observed result |
| --- | --- |
| Host | Windows 11 Pro, build 26200; approximately 15.87 GB usable RAM, 5.97 GB free at inspection. |
| Disk | C: has approximately **0.58 GB free** at inspection. Storage location/capacity must be resolved before pulling images and retaining five node data volumes. Other Docker storage locations were not inspected. No cleanup was performed. |
| PHP and Composer | PHP 8.5.10; Composer 2.10.3. Strict manifest validation and installed platform checks passed. BCMath, PDO MySQL, PDO SQLite, cURL and OpenSSL are enabled; sodium is absent and is not a current application dependency. |
| JavaScript tools | Node.js 24.19.0 and npm 11.17.0 installed. No Ethereum/Solidity toolchain or signing dependency is declared in the application manifests/locks. |
| Docker CLI | Docker 29.7.2 installed; selected context is `desktop-linux`. `docker version` cannot connect to `dockerDesktopLinuxEngine` (named pipe absent). The engine is not available; image/container/volume availability could not be checked. |
| Docker Compose | Compose v5.4.0 installed. Compose execution against the engine remains untested. |
| WSL | WSL 2.7.12.0, kernel 6.18.33.2-2. `Ubuntu` and `docker-desktop` are WSL2 distributions, both stopped. `wsl --status` also warns WSL1 is unsupported; the intended Docker path uses WSL2. |
| Host Java | OpenJDK 17.0.20.1 installed. A Docker image supplies its own Java runtime; host Java compatibility was not established for a native Besu installation. |
| Blockchain executables | No `besu`, `solc`, `forge` or `anvil` executable found on PATH. `curl.exe` is available. |
| Existing network artifacts | No project Compose/genesis, Solidity, Hardhat or Foundry files found outside ignored dependency/local-state directories. No Besu version/image is pinned. |
| Local candidate ports | No listening TCP endpoints on 8545, 8546 or 30303 at inspection. No configured authorized remote Besu endpoint was found in application configuration. This does not rule out a network on other ports/hosts. |
| Configuration and keys | No Besu RPC/chain/contract configuration, ABI, registry address or signing mechanism exists in application configuration or `.env.example`. Private `.env` values and local credentials were not printed. |

The official [Developer Quickstart](https://docs.besu-eth.org/private-networks/tutorials/quickstart/)
documents the requested four-validator plus one-RPC-node Docker topology, and
advises Windows 11/WSL2 kernel 6.6+ and up to 6 GB Docker memory for its network.
The observed Windows/WSL versions meet that documented baseline; actual Docker
resource allocation/startup remains unverified. Use the official
[QBFT tutorial](https://docs.besu-eth.org/private-networks/tutorials/qbft/) for
genesis/key-generation guidance and recheck version-specific settings in BESU-01.
No tutorial generator, image pull or network startup was run in this assessment.

### Minimal integration approach (future phases only)

1. **BESU-01:** Add isolated Docker network configuration in a dedicated directory
   (for example `blockchain/besu/`) with a pinned Besu image, four QBFT validators,
   one non-validator RPC node and persistent volumes. Resolve disk/engine readiness
   first. Use a distinct documented chain ID and restricted peers/permissioning;
   a private chain ID alone does not restrict access. Publish RPC on loopback for
   the host application; keep validator/admin endpoints internal. Generate node
   secrets outside tracked files. Confirm real blocks, peers, chain ID and all
   four validators before considering the phase complete.
2. **BESU-02:** Keep the append-only `PayrollIntegrityRegistry`, contract tests,
   ABI and deployment tooling separate from the frontend. Define an unambiguous
   opaque finalized-version reference and its future application mapping. Authorize
   writer accounts explicitly. Do not put payroll records or signing secrets on-chain.
3. **BESU-03:** Reuse the existing frozen snapshots and v1 canonicalization.
   Close the preserved-byte gap, validate deterministic creation and correction
   versions, and retain historical digest compatibility without submitting to Besu.
4. **BESU-04:** Add backend RPC/signing support behind existing interfaces. Choose
   and test maintained Ethereum ABI/signing tooling; PHP OpenSSL alone is not an
   implemented Ethereum signer. Configure expected chain/contract identity and
   protected credentials. Extend the reader contract if needed so verification
   validates successful receipt inclusion and independently reads the registry's
   exact batch-version commitment, rather than trusting a hash in a receipt DTO.
5. **BESU-05:** Reuse the finalization transaction to persist a durable pending
   anchoring intent atomically with the snapshot. Process it through the existing
   database queue/outbox infrastructure after commit. Never send a transaction
   inside the automatically retried payroll transaction. Current manual preparation
   and attempt reservations do not provide automatic anchoring or crash recovery.
   Add durable submission/confirmation metadata without conflating payroll status
   with anchoring status. Reconcile on-chain state before retrying an uncertain send.
6. **BESU-06 through BESU-08:** Adapt the current protected UI and history for real
   registry reads, receipt/contract identity checks and accurate status transitions.
   Complete recovery/correction and real Besu integration demonstrations in their
   authorized phases. Mock receipts remain unit-test evidence only.

No new frontend, database, general-purpose backend service or wallet/browser
submission path is needed. Do not assume document release is permitted during an
outage; that policy remains unresolved. Production governance, writer/key custody,
RPC controls, backup/restore and confirmation policy still need validation.

## Files changed

- `docs/BESU_00_ASSESSMENT.md` - assessment and phase checkpoint.
- `docs/DEVELOPMENT_PROGRESS.md` - current authorization and checkpoint.
- `docs/REQUIREMENTS_STATUS.md` - Besu assessment/network status.
- `docs/DECISIONS.md` - reuse and phase-boundary decisions.
- `docs/TESTING_STATUS.md` - current assessment validation evidence.

Pre-existing uncommitted documentation is preserved. No application source,
dependency, schema, environment or integration-plan file was changed.

## Blockchain status

**Not connected.** `AppServiceProvider` binds both blockchain interfaces to the
unavailable adapters. No node, chain ID, contract, transaction, receipt or on-chain
commitment was verified. Real anchoring and verification acceptance remain unmet.

## Tests performed

From `application/`:

- `php vendor/phpunit/phpunit/phpunit --colors=never`: **615 tests / 7568
  assertions passed**, 03:25.741, 102 MB.
- `composer validate --strict` and `composer check-platform-reqs`: passed.
- `php artisan app:check-foundation`: passed against local MySQL.
- `php artisan migrate:status --no-ansi`: all 18 migrations Ran.
- `php artisan route:list --path=payroll-batches --no-ansi`: 29 payroll routes.

Root prerequisite checks include `docker version` (engine connection failed),
`docker compose version` (installed), WSL status/version, host resources and local
port inspection. Full command evidence and limits are recorded in
[TESTING_STATUS.md](TESTING_STATUS.md) under the BESU-00 checkpoint. Existing tests
are reused because this phase introduces no executable behavior. Their controlled
blockchain doubles are not real Besu integration tests.

## Manual verification

From `application/`, run `php vendor/phpunit/phpunit/phpunit --colors=never`,
`composer validate --strict`, `composer check-platform-reqs`,
`php artisan app:check-foundation`, `php artisan migrate:status --no-ansi` and
`php artisan route:list --path=payroll-batches --no-ansi`.
The readiness/migration commands inspect the configured local MySQL database;
PHPUnit is pinned to disposable in-memory SQLite by `phpunit.xml` and `TestCase`.
From the repository root, run `docker version`, `docker compose version`,
`wsl --list --verbose`, `wsl --version` and `Get-PSDrive C` to reassess readiness.
These checks do not start a Besu network or authorize BESU-01.

## Issues

- Docker engine unavailable and C: storage severely constrained; actual five-node
  startup, image compatibility, memory allocation and persistence are unverified.
- No deployed registry, signing mechanism, real RPC adapter or durable anchoring
  worker/reconciliation path exists. These belong to later phases.
- Exact canonical bytes are not separately retained; existing v1 behavior must be
  preserved while BESU-03 addresses the stronger byte-preservation requirement.
- Existing real-payroll/client-policy and prior audit limitations remain. This
  assessment does not certify production readiness or all earlier audit findings.

## Next phase

**BESU-01 - Private Blockchain Network**, only after explicit authorization.

## Final status

**STOPPED - Awaiting authorization to continue.**
