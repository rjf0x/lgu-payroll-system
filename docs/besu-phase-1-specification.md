# Besu Phase 1: Payroll integrity specification

Date: 2026-11-09
Status: Phase 1 specification complete; business policy defaults remain proposals until adoption. No runtime changes are included.
Parent plan: [Besu integration plan](besu-integration-plan.md).

## Scope and assurance

One registration commits to one finalized payroll run and revision. Laravel computes SHA-256 over the canonical UTF-8 JSON bytes defined below. MySQL retains the full payload; the bridge receives only the opaque reference, revision, digest, schema version, and idempotency key. A match means the covered current data matches the registered version. It does not prove calculation correctness or cover excluded records.

The existing application has no canonical schema version, organization namespace, persisted opaque reference, revision linkage, period snapshot, or approval snapshot for this protocol. These are Phase 4 implementation requirements, not existing capabilities. The existing contract must be extended in Phase 2.

## Protocol identity

- `hash_schema_version`: integer `1` for this new protocol. Existing delimiter-based anchors are designated `legacy-delimited-v0`; an absent version on a historical anchor means legacy, never version 1.
- `organization_id`: immutable ASCII namespace assigned at deployment and persisted with each run. Never derive it from a mutable display name or current environment configuration during historical verification.
- `payroll_reference`: lowercase UUID v4 generated once and persisted before registration. Do not derive it from a local database ID or employee information. The organization and reference together identify a payroll family.
- `revision`: positive JSON integer, initially `1`, limited to `4294967295`. Hash schema version is a separate concept from payroll revision.
- Contract registration key: the tuple `(organization_id, payroll_reference, revision)`. Phase 2 must specify its ABI encoding explicitly; do not concatenate variable-length values without boundaries.
- Bridge idempotency key: lowercase SHA-256 hex of UTF-8 `organization_id + LF + payroll_reference + LF + revision-as-base-10`. Organization namespaces must match `[a-z0-9][a-z0-9-]{0,62}`; UUID and integer validation prevent LF ambiguity.
- Digest representation: 64 lowercase hex characters in Laravel and the service API; `0x` plus that digest when encoded as Solidity `bytes32`. The bridge never substitutes Ethereum Keccak-256 for SHA-256.

## Exact payload shape and field coverage

All keys below are mandatory. Null is allowed only where specified. No additional keys are permitted in version 1. JSON object order is defined by canonicalization, not by the order in this table.

| Object | Exact keys and source |
| --- | --- |
| Root | `hash_schema_version`, `organization_id`, `payroll_reference`, `revision`, `currency` (fixed `PHP`), `period`, `run`, `details` |
| `period` | Snapshot of `PayrollPeriod.period_name`, `payroll_type`, `start_date`, `end_date`, `pay_date` |
| `run` | `run_number`, `run_type`, `status` (must be `FINALIZED`), `created_by`, `submitted_by`, `submitted_at`, `finalized_by`, `finalized_at`, `approval` |
| `run.approval` | Snapshot of the latest successful `APPROVED` action before this finalization: `acted_by`, `acted_at`, `remarks` (nullable) |
| Each `details` item | `employment_id`, `employee_number_snapshot`, `employee_name_snapshot`, `department_snapshot`, `position_snapshot`, `salary_grade_snapshot`, `salary_step_snapshot`, `salary_rate_snapshot`, `pay_basis_snapshot`, `basic_pay`, `total_earnings`, `gross_pay`, `total_deductions`, `nthp_before_lbp`, `lbp_loan_deduction`, `net_pay`, `payable_days`, `input_reference`, `input_recorded_by`, `input_confirmed_at`, `manual_adjustments`, `payroll_status`, `earnings`, `deductions`, `employer_contributions` |
| Each `earnings` item | `earning_type_id`, `earning_name_snapshot`, `quantity`, `rate`, `amount`, `is_taxable`, `remarks` |
| Each `deductions` item | `deduction_type_id`, `deduction_name_snapshot`, `amount`, `remarks` |
| Each `employer_contributions` item | `contribution_rate_id`, `contribution_type_snapshot`, `amount` |

Field types:

- Monetary amounts, rates, quantities, and payable days: exact decimal strings with two fractional digits, e.g. `"0.00"`, `"45000.00"`. Normalize using decimal string operations; never pass through floats or scientific notation. Reject extra nonzero precision rather than silently rounding. Normalize negative zero to `"0.00"`.
- Identifiers (including user, employment, and type IDs), `run_number`, salary grade, and salary step: canonical base-10 integer strings without leading zeroes; zero is allowed only if the domain permits it. Preserve null salary grade/step, never convert null to zero. These identifiers refer to original persisted identity; database restore must preserve them.
- `manual_adjustments` and `is_taxable`: JSON booleans.
- `hash_schema_version` and `revision`: JSON integers. No other JSON numbers are used.
- Period dates: `YYYY-MM-DD`. Timestamps: UTC `YYYY-MM-DDTHH:mm:ssZ`, with second precision. Explicitly interpret existing database timestamps using the application's storage timezone before converting to UTC.
- Nullable values: salary grade/step, period pay date, earning quantity/rate, and remarks. Other fields must be present and non-null for a registrable finalized run. Empty earnings, deductions, or contributions use `[]`; payroll details must be nonempty.
- Text: valid UTF-8, preserved exactly without trimming, case folding, or Unicode normalization. Whitespace or Unicode representation changes therefore change the digest. Empty text remains distinct from null. Reject invalid UTF-8 instead of replacing bytes. IDs must meet their domain validity requirements.

This specification covers approved payroll outputs and confirmed input metadata. It excludes mutable employee/department/type master records, compensation configuration, bank accounts, attachments, attendance source records, generated PDFs/payslips, audit history, rejected approval attempts, operational timestamps (`created_at`, `updated_at`, `processed_at`), and blockchain/verification status.

Adjustment request records are excluded; their financial effects must be reflected in the covered earnings/deductions and totals before finalization. Thus a match does not attest to the separate adjustment approval history or source-document contents. Expanding that assurance requires another schema version.

Snapshot period and successful approval data during finalization. Verification must reconstruct the covered payload from the current persisted payroll records and these historical snapshots, never from today's mutable master records and never solely from the archived original payload. Ordinary historical master edits must not cause mismatches; unauthorized changes to covered payroll snapshots must.

## Canonical serialization

1. Validate the exact shape and types, normalize decimals and timestamps, and reject missing/unknown fields or duplicate object keys.
2. Sort `details` by numeric `employment_id`; reject duplicate employment IDs within a run.
3. Canonicalize each earnings, deduction, and contribution object, then sort each of those arrays lexicographically by the unsigned UTF-8 bytes of its canonical JSON. Preserve duplicates and multiplicity. This resolves ties without relying on row IDs or database collation.
4. Sort every object's keys in ascending ASCII byte order recursively. Keep arrays as arrays, including empty lists; never convert them into objects.
5. Encode compact JSON with no insignificant spaces, UTF-8 BOM, or trailing newline. Leave Unicode, `/`, U+2028, and U+2029 unescaped; escape quotation marks, backslashes, and JSON control characters. PHP implementation should use `JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS` after recursively ordering keys.
6. Compute `SHA-256(canonical_bytes)`. Store schema version alongside the digest. Never change these rules in place after registration.

The companion [hash vectors](besu-phase-1-hash-vectors.json) contain synthetic finalized payroll examples and fixed expected digests. Their payload objects are already normalized and sorted by the array rules; key order and pretty-printing in the fixture file are not hash input. The fixture file itself must not be hashed as a payroll payload.

The one-cent tamper vector deliberately changes only net pay, leaving totals inconsistent. It demonstrates integrity detection, not a valid recalculated payroll. The vectors are protocol examples, not production records. Python and PHP independently produced the same digest and byte length for all three vectors during Phase 1 verification.

## Revision and historical compatibility rules

- Draft, returned, and rejected runs may be changed using the existing workflow; they cannot be registered.
- Once finalized, covered records and snapshots become immutable through ordinary application operations, including child-line endpoints and background computations.
- A correction creates a separately approved run linked to its predecessor, reusing the family reference with the next revision. Preserve all previous runs and evidence. Allocate revisions atomically; no parallel run may claim the same family revision.
- Creating a new hash or retrying registration never authorizes overwriting an existing revision. Different data under the same key is a conflict requiring investigation.
- An adjustment paid separately may be a new payroll family linked to the original instead of a replacement; the eventual UI must distinguish the two operations.
- Legacy anchors retain their exact existing hashing behavior. Never relabel simulated evidence as blockchain evidence. New registrations for historical records must state their actual registration date and cannot claim proof existed at historical approval time.
- Version 1 stays disabled until schema-version persistence and version-based verification are implemented. The current anchor service remains on its legacy algorithm during Phase 1.

## Registration failures and payment policy

Capture the finalized payload/hash, schema version, target network/contract identity, and outbox request in one database transaction. Submit only after commit. If payload validation or outbox persistence fails, finalization rolls back. The worker submits the captured hash, never a freshly recalculated and silently changed hash.

| Condition | Required behavior |
| --- | --- |
| Node/Besu unavailable before a known broadcast | Keep request queued; retry with bounded backoff and the same idempotency key |
| Broadcast may have succeeded, response lost, or service restarted | Mark `RECONCILING`; recover durable transaction identity and query chain evidence before another submission |
| Known transaction not yet included | Keep `SUBMITTED`; poll, retain its hash, and alert on age; elapsed time alone is not proof of failure |
| Confirmed successful transaction and matching contract registration | Mark `CONFIRMED`; record receipt, block identity, registration timestamp, and signer |
| Definitively reverted transaction | Preserve failed attempt evidence; diagnose; an authorized retry uses the same key/hash and a separately tracked attempt |
| Same registration key with a different digest | Stop retries; report conflict; do not overwrite or regenerate approved data |
| Verification node unavailable or evidence invalid | Report `UNAVAILABLE` or `INVALID_EVIDENCE`; do not report a match or mismatch |
| Hash differs from authoritative registration | Record `MISMATCH`, preserve evidence, notify authorized reviewers; do not automatically re-anchor |

Proposed default: blockchain outages do not reverse payroll finalization. Payment release remains subject to the existing approved process during the pilot. Whether production payment release must wait for `CONFIRMED` is an explicit business decision, still unresolved; Phase 1 adds no payment gate. Alert thresholds, retry budgets, and incident ownership must be set before production rollout.

## Acceptance evidence and next-phase boundary

Phase 1 supplies an exact field contract, serialization rules, fixed synthetic vectors, revision rules, and failure policy. The vectors cover a baseline payroll, a one-cent alteration, and Unicode/delimiter-containing text with multiple earning lines.

Implementation tests in later phases must prove row-order independence, duplicate-line preservation, field mutation detection, excluded-master-edit stability, decimal edge cases, invalid UTF-8 rejection, missing-field rejection, timezone conversion, legacy dispatch, and ambiguous-submission recovery. Fixed vectors must remain unchanged once schema version 1 is released.

Phase 2 may implement the reference-bound contract against this protocol. Runtime canonicalization, persistence, finalization locking, outbox processing, and verification dispatch belong to Phase 4/5. Production validator ownership, organization namespace assignment, and payment-release policy remain deployment/business decisions.

## Phase 2 contract implementation

Implemented in `app/Services/Blockchain/PayrollIntegrityRegistry.sol`, compiled with Solidity **0.8.30**, optimization enabled, targeting the **London** EVM used by the local genesis. The existing `PayrollAnchor.sol`, deployment script, driver, and configured addresses remain unchanged. The new registry is a separate deployment; it is not a replacement at an existing address and has not been deployed by this phase.

Exact ABI surface:

```text
administrator() -> address
setRegistrar(string organizationId, address registrar, bool authorized)
isRegistrar(string organizationId, address registrar) -> bool
registrationKey(string organizationId, string payrollReference, uint32 revision) -> bytes32
registerPayroll(string organizationId, string payrollReference, uint32 revision,
                bytes32 payloadHash, uint32 schemaVersion) -> bool created
getPayroll(string organizationId, string payrollReference, uint32 revision)
    -> (bytes32 payloadHash, uint32 schemaVersion, uint256 registeredAt, address registrar)
```

The registration key is `keccak256(abi.encode(organizationId, payrollReference, uint32(revision)))`. This uses standard ABI tuple encoding, not packed encoding. The key's Keccak hash is independent of the SHA-256 payroll digest. A future ethers client must use ABI types `string,string,uint32` for key reproduction. See [Solidity 0.8.30 ABI specification](https://docs.soliditylang.org/en/v0.8.30/abi-spec.html).

The deployer becomes the immutable administrator. No account, including the administrator, is automatically a registrar. The administrator explicitly grants/revokes registration permissions per organization. This supports registrar rotation while preserving historical signer evidence. The administrator cannot update or delete registrations. Administrator transfer, upgrade proxies, and governance recovery are outside this implementation: choose a durable managed administrator or governance contract at deployment. Loss of its authority prevents future permission changes; it does not prevent reads.

The contract validates organization namespaces, lowercase UUID v4 references (including the RFC variant), positive uint32 revisions, nonzero digests, and schema version `1`. Future hash schemas require an explicitly reviewed contract change/deployment. Revision sequencing and successful payroll approval are enforced by Laravel in later phases; the registry accepts any positive uint32 revision and cannot validate off-chain approval or digest contents.

First registration returns `true` and emits:

```text
PayrollRegistered(bytes32 indexed registrationKey, string organizationId,
                  string payrollReference, uint32 revision, bytes32 payloadHash,
                  uint32 schemaVersion, uint256 registeredAt, address indexed registrar)
```

An identical authorized retry returns `false`, emits no second registration event, and preserves the original signer and timestamp. Different hashes for an existing key revert with `RegistrationConflict`. Invalid schema versions revert before lookup. Unauthorized retries are rejected even if the original registration exists. Missing reads return a zero registrar/hash tuple; callers must check the registrar rather than treating the zero hash as a registration. Permission changes emit `RegistrarUpdated`.

Transaction/block hashes and block numbers remain receipt metadata. `registeredAt` is the block timestamp, not the payroll finalization time. All network participants can inspect registration metadata; neither Solidity visibility nor permissioned networking makes submitted data confidential.

### Repeatable contract tests

Run with Docker and a local Besu RPC node supporting contract-creation `eth_call`:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File config/besu/test-registry.ps1
# For the repository setup.ps1 network instead of the default local RPC:
powershell -NoProfile -ExecutionPolicy Bypass -File config/besu/test-registry.ps1 -RpcUrl http://127.0.0.1:28545
```

The runner compiles the registry and `tests/PayrollIntegrityRegistryTest.sol` using the existing pinned compiler container. Seven isolated constructor scenarios execute registry calls in the EVM and revert on failed assertions. Each call uses simulated state only: no signing keys, transactions, application configuration changes, or persisted chain writes. ABI and bytecode are generated under ignored `storage/app/private/besu/registry-tests/`.

Covered behavior: stored evidence and absent reads; unauthorized writes/grants and organization isolation; idempotency and hash conflicts; reference/revision separation and ABI encoding; signer rotation and historical signer preservation; invalid revision/hash/schema/registrar and maximum revision; organization/UUID boundaries. Event receipt decoding and actual persisted transaction confirmation remain integration tests for the Node bridge phase.
