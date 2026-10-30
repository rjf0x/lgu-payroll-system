# BESU-04 � Backend Blockchain Connection

Implemented 2026-10-30. Scope ends before BESU-05.

## Implementation

Laravel now has an opt-in Besu RPC client, registry service, offline ethers ABI/signing bridge and `php artisan besu:check [--sign]`. Configuration is in `application/config/besu.php`; safe variable names/defaults are in `.env.example`. Existing application reader/submitter bindings remain unavailable. Controllers, payroll calculations, finalization, database schema and jobs are unchanged.

The client permits read-only RPC methods and HTTP loopback endpoints only, disables redirects and proxies, pins localhost to IPv4 loopback, validates JSON-RPC IDs/envelopes, and applies connection/request timeouts. Registry health verifies chain ID 20261030, Besu client identity, genesis, deployed runtime hash, administrator and writer authorization at a captured block, then rechecks that block hash. Lookups are contract reads, not payroll integrity verdicts.

The bridge uses pinned existing ethers, has no RPC provider or broadcast operation, loads encrypted keystore/password files on the server, verifies the recovered signer and fixes chain, destination, zero gas price/value and record calldata. Secrets do not appear in argv. PHP subprocess execution has a 15-second timeout. Safe errors omit provider bodies, stderr and credentials. `besu:check --sign` signs a synthetic commitment offline and prints only public health information and transaction hash; raw signed bytes are neither printed nor persisted.

Development `.env` was appended with public deployment identity and protected credential file paths, preserving existing values. It and credential files remain ignored. File permissions must restrict keystore/password access to the service operator (plus SYSTEM on Windows); POSIX permissions are checked by the bridge. Windows ACL protection was provisioned in BESU-02. This is development file-based signing, not a production custody solution. No keys were generated or replaced by the implementation.

## Validation

- Live `php artisan besu:check --sign` passed against Besu 26.10.2: chain 20261030, registry `0xDE556481A1DbF7a9bB1C152Ed574f122706dfFB6`, matching runtime/genesis/admin/writer, absent synthetic lookup and valid offline signature. Broadcast false.
- PHP connection feature tests: 11 passed, 40 assertions. Cover envelope matching, endpoint/method restrictions, redirects/errors, timeout redaction, chain/contract/authorization failures, pinned reads, signing input validation and safe CLI output.
- `node --test test/backend-bridge.test.mjs`: 3 passed. Checks ABI encoding/decoding, unsupported operations, an ephemeral encrypted test signer and recovered transaction fields, and wrong-writer rejection. Temporary test credentials are removed.
- Full application regression suite: 641 tests / 7983 assertions passed (including the initial seven connection cases). The final expanded 11-case connection file also passed independently; see TESTING_STATUS.md.
- Pint and Git whitespace checks performed. Existing dependencies reused; no package or database changes.

No on-chain transaction was submitted. Offline signing does not establish receipt confirmation, nonce reservation/retries or production signer suitability; those are outside this phase. Existing BESU-01/02 audit findings remain outside this change. No Git commit or push.

## Operator use

Install the existing contract tool dependencies with `npm ci --include=dev` under `blockchain/contracts` and ensure Node >=22.13 is on the PHP worker's PATH. Set BESU_ENABLED=true, BESU_RPC_URL=http://127.0.0.1:18545 and BESU_CHAIN_ID=20261030 in the ignored application environment. Set BESU_GENESIS_HASH, BESU_CONTRACT_ADDRESS, BESU_RUNTIME_CODE_HASH, BESU_ADMINISTRATOR and BESU_WRITER from a separately reviewed deployment manifest (`genesisHash`, `address`, `runtimeCodeHash`, `administrator`, `writer`). Configure BESU_KEYSTORE_PATH and BESU_PASSWORD_PATH with absolute protected writer-file paths. Never copy credentials into tracked configuration. Clear an existing Laravel config cache when changing configuration.

Run `php artisan besu:check` from application for health/contract reads. Add `--sign` for a synthetic offline signature. Any identity, RPC, signing or configuration failure exits nonzero. The command never broadcasts and does not enable existing payroll submission routes.
