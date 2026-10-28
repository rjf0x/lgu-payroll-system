# HYPERLEDGER BESU INTEGRATION — PHASED DEVELOPMENT PROMPT

## ROLE

Act as a Senior Blockchain Engineer, Full-Stack Developer, Solidity Smart Contract Developer, and Security Engineer.

Your task is to integrate **Hyperledger Besu** into an existing **LGU Accounting Office Payroll Management System**.

The system is being developed for an academic mock defense requiring approximately 70%–90% functional completion.

The main purpose of blockchain is to provide **tamper-evident integrity verification of finalized payroll batches**.

Prioritize working functionality, maintainability, security, and incremental integration with the existing application.

**IMPORTANT: Implement only one phase at a time. Never automatically continue to the next phase.**

---

# 1. PROJECT CONTEXT

The existing payroll application is expected to support:

- Employee management
- Salary grade and step management
- Monthly payroll preparation
- Earnings and allowance calculations
- Statutory contributions and withholding tax
- Loan deductions
- Payroll review and approval
- Payroll finalization
- Payroll registers and payslips
- Historical payroll records
- Role-based access control

The application's main objective is payroll automation.

Blockchain must enhance the integrity of finalized payroll without replacing the database or changing the established payroll calculation workflow.

### Existing Technology

Inspect the repository before implementation.

If the project uses Laravel, PHP, Blade, Tailwind CSS, and MySQL, retain that stack and follow its existing conventions.

Do not introduce a new frontend framework, database, or unnecessary backend service.

Use appropriate interoperability tools for Ethereum-compatible RPC and smart contracts.

---

# 2. BLOCKCHAIN ARCHITECTURE

Implement the following components:

**Blockchain Client:** Hyperledger Besu

**Network Type:** Private permissioned network

**Consensus:** QBFT

**Prototype Network:**
- Four validator nodes
- One non-validator RPC node

**Development Environment:** Docker-based network, subject to existing environment compatibility.

**Hashing Algorithm:** SHA-256

**Anchoring Strategy:** One hash for every finalized payroll batch version.

**Smart Contract:** Append-only payroll hash registry.

**Application Database:** Retains complete payroll records and historical data.

**On-Chain Data:** Batch commitment hash and minimal non-sensitive identifiers or metadata.

The blockchain must not contain employee names, salaries, loans, payslips, or complete payroll details.

A blockchain commitment proves consistency with the anchored snapshot, not the legal or computational correctness of the underlying payroll.

---

# 3. STRICT DEVELOPMENT RULES

1. Inspect the existing repository before changing anything.
2. Reuse working payroll functionality.
3. Do not rewrite unrelated modules.
4. Do not alter payroll calculation formulas.
5. Do not weaken existing access permissions.
6. Implement only the currently authorized integration phase.
7. Keep each phase small enough to review independently.
8. Split large phases into subphases when necessary.
9. Write meaningful automated tests for each phase.
10. Run tests and report the actual results.
11. Do not claim a blockchain transaction succeeded unless confirmed through a real Besu node.
12. Never substitute simulated blockchain verification for real integration.
13. Do not automatically create Git commits or push changes.
14. Do not proceed until I explicitly authorize the next phase.

---

# 4. BLOCKCHAIN WORKFLOW

Implement this workflow without changing the existing payroll-first process:

1. Payroll Officer prepares payroll.
2. System calculates payroll.
3. Payroll Officer reviews and submits payroll.
4. Approver reviews and approves payroll.
5. Authorized operation finalizes payroll.
6. System creates an immutable application-level payroll snapshot.
7. System generates the SHA-256 hash of the finalized batch.
8. Integration service submits the commitment to Hyperledger Besu.
9. System waits for a confirmed transaction receipt.
10. System independently retrieves the recorded commitment.
11. System compares it with the finalized snapshot's recomputed hash.
12. System displays the integrity verification result.

Never submit draft, returned, or unapproved payroll batches for anchoring.

Keep payroll lifecycle status separate from blockchain anchoring and verification status.

---

# 5. BLOCKCHAIN REQUIREMENTS

## BR-01 — Finalized Payroll Snapshot

Create a deterministic representation of every finalized payroll batch version.

The snapshot must include all fields required to detect meaningful changes to the finalized payroll record, including applicable employee payroll entries, earnings, deductions, totals, and calculation-rule references.

Use a stable format with:

- Explicit snapshot schema version
- Deterministic employee ordering
- Deterministic field ordering
- Consistent date representation
- Monetary values represented precisely, preferably as integer centavos
- Consistent null and optional-field handling
- Explicit finalized batch and version references

Hash the exact preserved snapshot bytes using SHA-256.

Preserve the original snapshot for future verification.

Do not generate hashes from mutable live employee records or current payroll configuration values.

## BR-02 — Batch-Level Hashing

Generate exactly one SHA-256 digest for each unique finalized payroll batch version.

Represent the digest as a 32-byte value suitable for a Solidity `bytes32` parameter.

Do not confuse SHA-256 with Ethereum's Keccak-256 hashing.

Do not generate individual blockchain transactions for every employee.

## BR-03 — Smart Contract Registry

Create a minimal Solidity smart contract named `PayrollIntegrityRegistry`.

The contract must:

- Record a unique finalized payroll batch-version reference.
- Store its corresponding `bytes32` SHA-256 commitment.
- Store minimal non-sensitive anchoring metadata.
- Emit an event when a new batch commitment is recorded.
- Permit only authorized writer accounts to create commitments.
- Reject attempts to overwrite an existing commitment with a different hash.
- Provide a read-only lookup function.
- Support controlled writer authorization changes.
- Avoid storing confidential payroll details.

Use an unambiguous, non-sensitive identifier for each finalized batch version.

For corrections, create a new version reference and commitment. Never overwrite the original.

## BR-04 — Blockchain Transaction Submission

Implement a backend blockchain integration service that:

- Connects to the configured Besu RPC endpoint.
- Verifies the expected chain ID.
- Identifies the deployed contract.
- Constructs a valid contract transaction.
- Signs transactions using a protected, authorized signing mechanism.
- Submits signed transactions through JSON-RPC.
- Records the transaction hash.
- Waits for and validates the transaction receipt.
- Confirms that the transaction succeeded.
- Checks the contract's recorded commitment.

Keep signing keys outside source code and frontend assets.

Use environment-based configuration and avoid exposing credentials in logs.

## BR-05 — Integrity Verification

Verification must:

1. Retrieve the preserved finalized payroll snapshot.
2. Recalculate the SHA-256 hash.
3. Retrieve the corresponding commitment directly from Besu.
4. Compare the two values.
5. Display the result.

Do not rely only on a hash stored in the application's database.

Possible outcomes:

- Anchoring pending
- Transaction submitted
- Blockchain confirmed
- Integrity verified
- Integrity mismatch
- Blockchain unavailable
- Anchoring failed

Do not mark a record verified merely because a transaction hash exists.

## BR-06 — Failure Handling

Handle:

- Blockchain RPC unavailable
- Transaction timeout
- Failed transaction receipt
- Incorrect chain ID
- Missing or incorrect contract address
- Duplicate submissions
- Application restart after transaction submission
- Interrupted anchoring processes
- Invalid transaction signature
- Integrity mismatch

Use a durable retry mechanism and idempotent operations.

Before retrying an uncertain transaction, check whether the commitment is already present on-chain.

Do not submit duplicate commitments for the same finalized batch version.

For prototype development, preserve approved snapshots as **Anchoring Pending** during an outage. Do not treat pending snapshots as blockchain-verified.

Whether such payroll documents may be officially released remains an unresolved client policy and must not be assumed.

## BR-07 — Privacy and Security

- Keep confidential payroll details off-chain.
- Restrict blockchain write operations.
- Prevent direct blockchain submissions from ordinary browser clients.
- Protect the RPC endpoint from public access.
- Do not expose validator keys or transaction-signing secrets.
- Preserve payroll approval separation of duties.
- Maintain appropriate audit logs.
- Use synthetic employee data during development and testing.

---

# 6. PHASED IMPLEMENTATION PLAN

## PHASE BESU-00 — REPOSITORY ASSESSMENT

**Goal:** Understand the existing application before blockchain integration.

Tasks:

1. Inspect the repository.
2. Identify the application framework and project structure.
3. Find payroll calculation, approval, and finalization functionality.
4. Determine whether finalized payroll snapshots already exist.
5. Inspect database schema and current test coverage.
6. Check Docker and other blockchain development prerequisites.
7. Identify reusable services and integration points.
8. Report missing prerequisites and dependencies.

Do not implement blockchain functionality during this phase.

Acceptance criteria:

- Current project structure identified.
- Payroll finalization integration point identified or documented as missing.
- Environment prerequisites checked.
- Minimal integration approach documented.

**STOP AFTER THIS PHASE.**

---

## PHASE BESU-01 — PRIVATE BLOCKCHAIN NETWORK

**Goal:** Establish a working local Hyperledger Besu network.

Tasks:

1. Set up an isolated private network.
2. Configure QBFT consensus.
3. Configure four validator nodes.
4. Configure one additional RPC node.
5. Assign a unique private chain ID.
6. Configure appropriate genesis parameters.
7. Configure local Docker networking and persistent development volumes.
8. Restrict RPC access to authorized local services.
9. Configure a zero-gas-price development network if compatible with the chosen genesis and client version.
10. Start the network and verify validator connectivity.

Prefer official, maintained Besu documentation and reproducible configuration.

Do not expose RPC administrative interfaces publicly.

Acceptance criteria:

- All required nodes start successfully.
- Validators participate in consensus.
- Blocks are produced.
- RPC endpoint responds.
- Chain ID is correct.
- Peer connectivity is confirmed.

Verify using appropriate JSON-RPC methods such as:

- `web3_clientVersion`
- `eth_chainId`
- `net_peerCount`
- `eth_blockNumber`

**STOP AFTER THIS PHASE.**

---

## PHASE BESU-02 — SMART CONTRACT DEVELOPMENT

**Goal:** Create a functional payroll integrity registry.

Tasks:

1. Create the Solidity contract.
2. Implement authorized writer access.
3. Implement append-only batch commitment storage.
4. Implement batch-version lookup.
5. Implement an anchoring event.
6. Prevent unauthorized or conflicting overwrites.
7. Add automated smart contract tests.
8. Compile and deploy to the private Besu network.
9. Record the deployed address and contract ABI.

Use a compatible, maintained Solidity toolchain.

Acceptance criteria:

- Contract compiles.
- Contract deploys successfully.
- Authorized writer records a commitment.
- Read-only lookup returns the expected hash.
- Unauthorized writes are rejected.
- Duplicate conflicting writes are rejected.
- Deployment transaction is confirmed on Besu.

**STOP AFTER THIS PHASE.**

---

## PHASE BESU-03 — PAYROLL SNAPSHOT AND HASHING

**Goal:** Integrate deterministic hashing into the existing application.

Tasks:

1. Inspect how finalized payroll data is stored.
2. Create or reuse a fixed finalized payroll snapshot.
3. Define a canonical snapshot schema.
4. Implement deterministic serialization.
5. Generate SHA-256 using the finalized snapshot bytes.
6. Store the snapshot hash and schema version.
7. Associate the hash with the unique payroll batch version.
8. Prevent silent modification of finalized snapshots.
9. Add unit and integration tests.

Do not submit transactions to Besu during this phase.

Acceptance criteria:

- Identical snapshots produce identical hashes.
- Changes to payroll amounts change the hash.
- Snapshot ordering is deterministic.
- Historical finalized snapshots remain reproducible.
- Test fixtures produce expected hashes.

**STOP AFTER THIS PHASE.**

---

## PHASE BESU-04 — BACKEND BLOCKCHAIN CONNECTION

**Goal:** Prepare secure, reusable backend connectivity.

Tasks:

1. Implement a blockchain RPC client or service.
2. Configure RPC URL and expected chain ID.
3. Configure contract address and ABI.
4. Integrate a protected transaction-signing mechanism.
5. Implement blockchain health checks.
6. Implement safe timeout and error handling.
7. Separate blockchain integration code from payroll controllers.
8. Add tests using controlled responses and integration tests where possible.

Do not automatically anchor finalized payroll during this phase.

Acceptance criteria:

- Application connects to Besu.
- Correct chain ID verified.
- Contract can be queried.
- Signing configuration works in the development environment.
- Connection failures are handled safely.
- No private keys or secrets appear in source control.

**STOP AFTER THIS PHASE.**

---

## PHASE BESU-05 — AUTOMATIC PAYROLL ANCHORING

**Goal:** Submit finalized payroll commitments to Besu.

Tasks:

1. Integrate anchoring with authorized payroll finalization.
2. Create a durable anchoring job or equivalent mechanism.
3. Submit finalized batch hashes to the deployed contract.
4. Record transaction identifiers.
5. Confirm transaction receipts.
6. Verify committed values on-chain.
7. Store blockchain confirmation metadata.
8. Prevent duplicate anchoring.
9. Preserve appropriate audit history.

Do not modify existing payroll calculation results.

Acceptance criteria:

- Approved finalized batch produces a hash.
- One valid contract commitment is recorded.
- Transaction is confirmed.
- The expected hash can be retrieved from Besu.
- Repeated requests do not create conflicting commitments.
- Failed submissions do not appear successful.

**STOP AFTER THIS PHASE.**

---

## PHASE BESU-06 — INTEGRITY VERIFICATION UI

**Goal:** Allow authorized users to verify finalized payroll integrity.

Tasks:

1. Add blockchain status to finalized payroll details.
2. Display the recorded transaction reference.
3. Implement a Verify Integrity action.
4. Recalculate the finalized snapshot's SHA-256 hash.
5. Retrieve the corresponding on-chain commitment.
6. Compare both hashes.
7. Display the verification result.
8. Record verification events.
9. Protect all verification interfaces with appropriate access controls.

Preserve the existing UI design system.

Do not redesign unrelated payroll screens.

Acceptance criteria:

- Valid finalized payroll shows Verified.
- Altered isolated test snapshot shows Integrity Mismatch.
- Unconfirmed transactions are not labeled Verified.
- Blockchain outages display a clear unavailable status.
- Unauthorized users cannot perform restricted operations.

**STOP AFTER THIS PHASE.**

---

## PHASE BESU-07 — OUTAGES, CORRECTIONS, AND HISTORY

**Goal:** Make the blockchain integration resilient and traceable.

Tasks:

1. Add safe retry processing for failed or interrupted submissions.
2. Reconcile uncertain transaction outcomes with on-chain state.
3. Preserve previous finalized payroll versions.
4. Anchor authorized corrected versions separately.
5. Maintain blockchain transaction and verification history.
6. Add appropriate administrative status visibility.
7. Test blockchain network unavailability and recovery.
8. Verify restoration and resumption where feasible.

Do not overwrite original blockchain commitments.

Acceptance criteria:

- Network outages do not corrupt payroll records.
- Anchoring status remains accurate.
- Successful retry creates or recovers the correct commitment.
- Corrected payroll has a new batch version and hash.
- Previous versions remain available.
- Duplicate transactions are handled safely.

**STOP AFTER THIS PHASE.**

---

## PHASE BESU-08 — INTEGRATION TESTING AND MOCK DEFENSE

**Goal:** Demonstrate complete blockchain integration with the payroll system.

Prepare a reproducible demonstration using fictional payroll data.

Test Scenario A — Successful Anchoring:
- Prepare payroll.
- Submit for approval.
- Approve and finalize.
- Generate hash.
- Submit to Besu.
- Confirm transaction.
- Verify recorded commitment.

Test Scenario B — Tamper Detection:
- Create an isolated test copy of the finalized snapshot.
- Modify one payroll value in the test copy.
- Recalculate the hash.
- Demonstrate a mismatch against the original blockchain commitment.

Test Scenario C — Blockchain Outage:
- Stop or disconnect the RPC service in an isolated development environment.
- Attempt anchoring.
- Confirm status reflects the failure or pending operation.
- Restore the connection.
- Verify safe recovery.

Test Scenario D — Corrected Payroll:
- Create an authorized corrected version.
- Generate a different snapshot hash.
- Anchor it separately.
- Confirm that historical versions remain independently verifiable.

Run relevant automated tests.

Produce an honest completion report identifying:
- Working blockchain features
- Tested integration points
- Known defects
- Pending client decisions
- Prototype-only security limitations
- Remaining production requirements

**STOP AFTER THIS PHASE.**

---

# 7. PROJECT ORGANIZATION

Preserve the existing repository organization.

Where appropriate, separate:

- Blockchain network configuration
- Smart contracts and contract tests
- Deployment scripts
- Backend blockchain services
- Payroll snapshot hashing
- Transaction submission and receipt processing
- Verification services
- Blockchain database records
- UI components
- Integration tests

Maintain minimal documentation of:

- Local network startup and shutdown
- Required configuration variables
- Contract deployment
- Blockchain transaction verification
- Testing commands
- Known limitations

Never commit secrets, live signing keys, generated private-key files, or confidential payroll records.

---

# 8. PHASE COMPLETION REPORT

After every phase, provide the following concise report.

### Phase
Current phase identifier and name.

### Implemented
Actual functionality completed.

### Files Changed
List important files created or modified.

### Blockchain Status
Network, contract, and application connectivity status as applicable.

### Tests Performed
Exact commands executed and results.

### Manual Verification
Short steps I can follow to test the implementation.

### Issues
Failures, unresolved requirements, or incomplete functionality.

### Next Phase
Identify the next phase but do not implement it.

### Final Status
**STOPPED — Awaiting authorization to continue.**

---

# 9. STRICT RESTRICTIONS

- No fake blockchain transactions.
- No simulated verification presented as real.
- No employee payroll details on-chain.
- No automatic implementation of future phases.
- No replacement of the existing payroll system.
- No unrelated framework migrations.
- No uncontrolled duplicate blockchain submissions.
- No exposure of signing keys.
- No automatic Git commits or pushes.
- No silent payroll modification after finalization.
- No hardcoded blockchain transaction hashes as proof of success.
- No claim of successful integration without checking a real blockchain transaction.
- No deployment to a public blockchain without explicit authorization.

Use the current official Hyperledger Besu documentation when determining version-dependent configuration.

---

# 10. STARTING INSTRUCTION

**BEGIN PHASE BESU-00 — REPOSITORY ASSESSMENT ONLY.**

Inspect the existing application and determine the safest integration strategy.

Identify all relevant existing payroll finalization features, database structures, application services, and environmental prerequisites.

Do not initialize a new application if one already exists.

Do not proceed to BESU-01.

Report findings using the required checkpoint format, then STOP.