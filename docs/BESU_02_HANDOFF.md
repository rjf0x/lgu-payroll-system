# BESU-02 - Smart Contract Development

Date: 2026-10-30 (America/Los_Angeles). Implements only BESU-02 after inspection
of the committed BESU-01 network, its handoff/audit and the integration plan.

## Implemented

`PayrollIntegrityRegistry` is an append-only registry keyed by an opaque bytes32
batch-version reference. It retains the exact bytes32 SHA-256 commitment, original
writer, timestamp and existence flag. Authorized identical retries preserve all
metadata and emit no duplicate event; conflicting hashes revert. Corrections use
new references. There is no deletion/update path for saved commitments.

An immutable administrator grants/revokes writers and emits authorization events;
it has no implicit writing permission. All addresses/references/digests reject
zero where appropriate. Read-only lookup is unrestricted. The contract stores no
payroll content and cannot establish computational/legal payroll correctness.

The separate `blockchain/contracts/` workspace uses Solidity 0.8.38, ethers 6.17.0,
Node's test runner and a dependency lockfile. The compiler explicitly targets the
BESU-01 Shanghai EVM with optimization enabled. No additional runtime server or
frontend framework was introduced. `tmp` is overridden to patched 0.2.7; the
initial dependency audit exposed the compiler's vulnerable transitive 0.2.6.

Development signers are encrypted in Git-ignored, protected local storage, separate
from validator keys. Windows ACLs grant the current operator and SYSTEM only;
POSIX modes are 0700/0600. Password files are colocated for unattended development,
not production custody. Signing takes place locally, not by RPC account unlocking.
Only loopback HTTP endpoints and chain ID 20261030 are accepted by tooling.

Deployment journals a transaction hash/nonce/expected address before broadcasting,
waits for a successful canonical receipt, verifies constructor/source input,
runtime hash and registry authorization, then writes public deployment metadata.
Rerunning verifies the existing deployment without sending another transaction.
Uncertain pending outcomes stop rather than blindly redeploying.

## Files changed

- `blockchain/contracts/src/PayrollIntegrityRegistry.sol`.
- `blockchain/contracts/scripts/`: compilation, protected signer initialization,
  local Besu connection/receipt helpers, deployment and read-only verification.
- `blockchain/contracts/test/`: registry behavior on real Besu and tooling guards.
- `blockchain/contracts/abi/PayrollIntegrityRegistry.json`: required public ABI.
- `blockchain/contracts/deployments/local.json`: public deployment evidence.
- Workspace manifest/lock, `.gitignore`, operations README, this handoff and
  existing development/requirements/decisions/testing/root README documentation.

No Laravel source/configuration, application dependencies, payroll formulas,
permissions, snapshots or database data were changed. Existing BESU-01 network
files and prior uncommitted documentation were preserved. No Git commit/push.

## Blockchain status

**Deployed and independently queried on real Besu.**

| Field | Confirmed value |
| --- | --- |
| Chain ID | 20261030 |
| RPC | `http://127.0.0.1:18545` |
| Genesis block hash | `0x9f286a7e67e2c9d4e7fbf0cf8276bbfbeefe328b04a7f3934cc3b80525570bf5` |
| Registry | `0xDE556481A1DbF7a9bB1C152Ed574f122706dfFB6` |
| Deployment transaction | `0x433615052af8b0cae4e2cd76f6c50f1677c21ae68c833367a27c8caf13ec19a3` |
| Deployment block | 1062; receipt status 1, canonical block verified |
| Administrator | `0xFDAecD74240ec4360e0489c81DbD610Fb36847A0` |
| Initial writer | `0xa577895fBcD474CCd24355cD3D443Be22CF9959e` |
| ABI | `blockchain/contracts/abi/PayrollIntegrityRegistry.json` |

The main registry is deployed with initial authorization. Automated write tests
deploy separate disposable registry instances on this same real chain. Successful
and deliberately failed transactions in those fixtures are real Besu receipts,
not simulations. Only fictional digests/random references are used; no application
payroll snapshot was submitted. Network and existing unrelated lab remain running.

## Acceptance criteria

| Criterion | Evidence |
| --- | --- |
| Contract compiles | Warning-free pinned compiler, Shanghai target, published ABI. |
| Contract deploys | Real successful canonical receipt at block 1062. |
| Authorized writer records commitment | Live test confirms status 1, event, exact digest, original writer and timestamp. |
| Lookup returns expected hash | Independent unrestricted reads in real-node tests. |
| Unauthorized writes rejected | Custom-error call checks and actual status-0 transaction; no record created. |
| Conflicting duplicate rejected | Custom error and actual status-0 transaction; original hash retained. |
| Deployment confirmed on Besu | Deployment and separate verification commands query receipt, canonical block, bytecode/source input and authorization. |

## Tests and manual verification

From `blockchain/contracts/`:

```powershell
npm ci --ignore-scripts
npm run compile
# First setup only; current local signers already exist:
# npm run init:signers
npm test
npm run deploy
npm run verify
npm audit --audit-level=low
```

The test suite covers actual deployment, unknown lookup, authorized storage/event,
unauthorized writing/admin separation, real rejected receipt, identical retry from
a different authorized writer, conflicting overwrite, writer grant/revocation,
retained history, independent correction references, zero inputs/constructor
addresses, chain/runtime verification rejection, loopback endpoint restrictions
and ABI/compiler compatibility. Repeated test execution leaves fictional fixture
contracts and transactions on the development chain; it does not clear data.

An initial contract-test run passed 9/10: an ethers Result proxy comparison failed
despite identical values. Converting both results to plain arrays corrected the
test; the next 10/10 passed in 47.958 seconds. Initial compiler diagnostics for a
reserved identifier and built-in shadowing were corrected before deployment.
One early npm command was issued from the repository root and failed ENOENT; all
successful workspace commands above run from `blockchain/contracts/`.

The final expanded suite and clean-lock installation are recorded in
[TESTING_STATUS.md](TESTING_STATUS.md). Also rerun successfully:

- `powershell -NoProfile -File blockchain/besu/Test-Configuration.ps1`.
- `powershell -NoProfile -File blockchain/besu/Test-Network.ps1`: all five nodes,
  four peers each, zero fees; height advanced 1072 -> 1079.
- `npm run deploy`, `npm run verify`, then `npm run deploy`: successful confirmed
  deployment, verification and reuse with no second broadcast.
- Host `Get-Acl` confirms new signer secrets are limited to operator/SYSTEM;
  `git check-ignore` confirms keystores/passwords/artifacts/dependencies excluded.

## Limits and stopping point

- No application connection, snapshot-byte change, automatic anchoring or UI
  integration. Existing unavailable Laravel blockchain adapters remain unchanged.
- Administrator replacement, multisig governance, HSM/key vault custody, formal
  contract audit and restoration are not implemented. An immutable administrator
  is intentionally minimal; losing its key loses writer-management capability.
- The contract trusts authorized writers to use the correct SHA-256 digest and
  opaque reference. Finalized-version reference mapping belongs to later phases.
- Deployment metadata is specific to this local genesis. The deployment verifier
  checks initial writer authorization; intentional future revocation requires an
  explicit verification-policy update. Do not blindly delete deployment/pending
  records or signer identities to bypass a failed check.
- The existing BESU-01 F1 test omission and H1 validator-key ACL limitation remain;
  protected contract signers do not silently repair historical node keys.
- Local network security/client-policy/real-payroll limitations remain. Receipt
  success proves this contract action, not payroll legality or release permission.

Next: **BESU-03 - Payroll Snapshot and Hashing**, not implemented.

**STOPPED - Awaiting authorization to continue.**
