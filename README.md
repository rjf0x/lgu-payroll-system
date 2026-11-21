# LGU Payroll Management System

Laravel 13 / PHP 8.5 application for employee records, approved manual payroll inputs, monthly payroll, independent approval, registers, payslips, audit records, and Hyperledger Besu hash verification. The interface uses Blade and Tailwind 4.

The current release is prepared for local review and mock defense. Production hosting, HTTPS validation, backup restore acceptance, payroll-policy approval, and human accessibility/usability acceptance remain open. See [development progress](docs/development-plan.md) and [UI/UX progress](docs/ui-ux-improvement-plan.md).

## Local setup

Requirements: PHP 8.5 with PDO MySQL/SQLite, intl, gd, mbstring and OpenSSL; Composer; Node.js/npm compatible with the locked Vite version; a configured local database; and Docker Desktop for the local Besu network.

For a fresh local development environment, configure MySQL in `.env` first if you are not using the default SQLite connection, then run the single setup command:

```powershell
composer setup
```

`composer setup` installs PHP and frontend dependencies, creates `.env` when needed, preserves an existing application key, creates the SQLite file when configured, runs migrations and local seeders, builds the frontend assets, starts the four-validator Besu network, deploys the local anchor contract, merges the generated Besu settings into `.env`, and clears Laravel's configuration cache. It is for local development only and refuses production environments.

The legacy anchor contract supports historical verification; new legacy network submissions are disabled. For new blockchain proofs, configure the versioned payroll registry and its bridge, worker and scheduler using the [blockchain guide](docs/blockchain-anchoring.md). A simulation-only demonstration uses `BLOCKCHAIN_DRIVER=simulated` with registry disabled and provides local comparison rather than independent blockchain evidence.

For the optional demo payroll workflow after setup:

```powershell
php artisan db:seed --class=DemoPayrollWorkflowSeeder --no-interaction
```

Demo accounts are `admin`, `officer`, `approver`, and `viewer`, with development-only password `password`. Default seeding is blocked in production. The demo scenarios are opt-in, reuse fictional employees, and must never be used as adopted statutory or salary data.

For real local blockchain anchoring, follow [Besu setup](docs/blockchain-anchoring.md). The setup generates ignored local keys and deploys the contract; existing simulated anchors remain simulated.

### Start the blockchain worker

When the versioned payroll registry is enabled and configured (`PAYROLL_REGISTRY_ENABLED=true`), start the long-running processes in separate terminals from the project root:

```powershell
php artisan queue:work database --queue=blockchain --timeout=60 --tries=3 --no-interaction
```

Start the scheduler in another terminal so pending registrations are dispatched every minute:

```powershell
php artisan schedule:work --no-interaction
```

If the registry bridge is configured, start it in a third terminal:

```powershell
node --env-file=storage/app/private/besu/bridge.env config/besu/bridge.mjs
```

To dispatch pending registrations immediately instead of waiting for the scheduler:

```powershell
php artisan blockchain:dispatch-registrations --limit=100 --no-interaction
```

### Create your own initial administrator

As an alternative to the demo-account setup above, migrate a new database without `--seed`, then run the interactive administrator command:

```powershell
php artisan migrate --no-interaction
php artisan app:create-admin
```

Enter your username, email, password, and password confirmation when prompted. The password requires at least 16 characters with uppercase, lowercase, numbers, and symbols. The command requires an empty users table and refuses to change existing accounts.

With `APP_ENV=local`, this also seeds 4 departments, 6 positions, an illustrative salary schedule with 6 grade/step entries, 8 fictional employees with employment records and compensation profiles, 6 earning types, 24 deduction types, 6 annual tax brackets, and 3 contribution rates (GSIS, PhilHealth, and Pag-IBIG). Roles, permissions, employment types, and system defaults are included. Create Payroll Officer and Approver accounts through **Add User** after signing in; this setup does not create the demo user accounts required by `DemoPayrollWorkflowSeeder`.

### Seed payroll setup data for an existing administrator

To add the same payroll setup data when your administrator already exists, run:

```powershell
php artisan db:seed --class=PayrollMasterDataSeeder --no-interaction
```

This seeder preserves existing matching records and their edited values. It does not create user accounts or payroll runs. It is restricted to local and testing environments because its employees, salary amounts, tax brackets, and contribution rates are illustrative. Replace these with approved reference data before real payroll use. The earning and deduction labels are based on the supplied payroll workbook, with normalized names.

See the [browser workflow and mock data](docs/browser-workflow-mock-data.md) for a manual walkthrough.

## Verification

```powershell
php artisan test --compact
php vendor/bin/pint --dirty --format agent
npm run build
php artisan deployment:check --no-interaction
```

Normal tests require in-memory SQLite or a database with the `lgu_payroll_qa_` prefix. MySQL contention and registry pilot/restore tests are opt-in; see the development plan and blockchain guide. The preflight command is read-only. Its local mode checks the PHP runtime, configured database migrations, application key presence, writable directories, compiled assets, and absence of Vite's development marker. It does not prove production readiness.

## Build a source release locally

After verification, with the Vite development server stopped:

```powershell
python config/deployment/build_release.py
```

The ZIP and SHA-256 sidecar are written to ignored `storage/app/private/releases/`. Each ZIP includes application source, lockfiles, built frontend assets, deployment examples, and a manifest containing per-file hashes, source commit, and whether the working tree was dirty. Review and commit the source before a final production release. The archive excludes `.env`, vendor packages, node_modules, runtime bootstrap caches, local databases, Besu keys, browser profiles, and other private storage. Composer dependencies must be installed on the target using `composer.lock`.

## Initial production installation

The production template is `.env.production.example`. It is an example, not a configured environment. Prepare a separate staging environment and database first. Do not copy development credentials, APP_KEY, fictional salaries, or sample statutory tables into production.

1. Select hosting, domain, database region, backup policy, and independent Besu operators. Provision a supported PHP 8.5 runtime, MySQL, persistent database sessions/cache/queue, and protected signing infrastructure.
2. Extract the release into a new release directory. Set only `public/` as the web root. Run `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`. Provide the production environment through the host's secret manager or a protected `.env` outside the web root.
3. Generate APP_KEY once for a new installation. Preserve that key through upgrades; changing it invalidates encrypted sessions and other encrypted values. Set APP_DEBUG=false, a verified HTTPS APP_URL, secure session cookies, and only the actual proxy IP addresses/CIDRs in TRUSTED_PROXIES. Do not trust arbitrary forwarded headers.
4. Apply migrations with `php artisan migrate --force --no-interaction` on the selected database after taking a restorable backup. Never run `migrate:fresh` on staging history or production.
5. For the first installation with an empty users table, temporarily provide INITIAL_ADMIN_USERNAME, INITIAL_ADMIN_EMAIL, and INITIAL_ADMIN_PASSWORD via the environment. The password must contain at least 16 characters, mixed case, digits and symbols. Run `php artisan db:seed --class=ProductionDatabaseSeeder --force --no-interaction`. Existing accounts are never overwritten. Roles, permissions, system identity defaults, employment types, earning types, deduction types, and one administrator are created; no employees, salary schedules or statutory amounts are seeded in production.
6. Remove the INITIAL_ADMIN_PASSWORD secret after bootstrap and rebuild configuration caches. Configure organization identity and authorized staff through the interface. Import only client-approved reference data and payroll policies.
7. Enable and configure the versioned payroll registry, organization namespace, pinned chain/genesis/contract/code/signer identity, authenticated bridge, worker and scheduler as described in the blockchain guide. Keep signing keys in managed infrastructure. The legacy Besu driver verifies historical proofs and cannot submit new ones; production preflight requires the supported registry transport. The local four-container stack and raw development key are not a production deployment design. Restrict RPC/signing access and verify registration/confirmation/integrity in staging.
8. Install the report worker below, then warm caches with `php artisan optimize --no-interaction`. Run `php artisan deployment:check --production --no-interaction`. Resolve every failure; the final message also identifies manual checks the command cannot prove.
9. Verify login for each role, inactive-account rejection, independent payroll approval, an authorized report export, printable payslips, worker health, HTTPS redirects/cookies, and Besu evidence. Approve the release only after the backup restore drill and client payroll-policy acceptance. No external deployment is performed by this repository's release builder.

The supplied preflight checks the repository's database-backed deployment baseline. A different session/cache/queue architecture needs a corresponding reviewed configuration rather than treating the command as a universal hosting validator.

## Reports and background workers

Payroll registers export XLSX using the bundled spreadsheet template. User-entered labels are written as text cells. Historical registers use saved component identities, labels and amounts, with reconciliation information for legacy differences. Export history records the request and scope; it is not an immutable archive of the downloaded file. Department summaries and payslips have browser-print layouts; the wide register requests A3 landscape paper.

`QUEUE_PAYSLIP_GENERATION=true` queues payslip generation on `reports`; false retains synchronous behavior for local demonstrations. The endpoint confirms dispatch, and users refresh to see generated payslips. Jobs recheck the requester's active status/permission and the run state, and generation is idempotent under a run lock. Generation never publishes payslips automatically.

Approved-run payslips are previews. Publication requires a finalized run; returning or rejecting payroll withdraws existing outputs. After correction and fresh approval, regenerate payslips before publishing. Viewer can read/export authorized outputs; generation/publication require the separate writer grant.

```bash
php artisan queue:work database --queue=reports,default --sleep=3 --tries=3 --timeout=60 --max-time=3600
php artisan queue:failed
```

The database retry reservation is 90 seconds, longer than the 60-second worker timeout. Review `config/deployment/reports-worker.conf` for a Linux Supervisor example and replace its user, paths and logging destinations for the selected host. Monitor failed jobs and worker backlog; reconcile failures before retrying. Restart workers after a release on a conventional server. Managed platforms may manage that restart themselves.

Payroll computation, approval and finalization are explicit user actions. Enabled registry dispatch uses the configured scheduler and blockchain worker. Use a single coordinated scheduler and inspect `php artisan schedule:list --no-interaction` before enabling `schedule:run` every minute. Backups are a separate host/database responsibility; an idle Laravel scheduler does not mean a backup exists.

## Backups, restore and rollback acceptance

Before production, the operator must record an approved backup owner, encrypted destination, retention, recovery point objective and recovery time objective. Configure automated MySQL backups using a dedicated account or managed snapshots. Protect configuration/APP_KEY, private files and Besu/signing recovery material separately; never put plaintext passwords or signing keys in this source release or a public bucket.

A release is not operationally accepted until a backup has been restored to an isolated database, foreign-key integrity and expected row counts have been checked, staff can log in, historical registers/payslips are available, and a finalized payroll still verifies against its original chain. Record the backup identifier, restore time and reviewer. Never demonstrate tampering by changing live finalized payroll. No backup or restore drill is claimed by the local preflight command.

Keep the preceding application release and its lockfiles. Use backward-compatible migrations so application traffic can return to the earlier release when appropriate. Restore a database only under an approved recovery procedure; an application rollback does not undo database writes or blockchain transactions. Keep old chain, contract and signer references available for historical verification.

## Mock-defense rehearsal

Use a separate environment with fictional records and prechecked individual role logins. Before projecting or sharing it, set `APP_DEBUG=false` in that environment and keep session cookies HTTP-only with `SESSION_HTTP_ONLY=true` and `SESSION_SAME_SITE=lax`. For local HTTP, leave `SESSION_SECURE_COOKIE` unset or false; an HTTPS demo requires `SESSION_SECURE_COOKIE=true` and the correct HTTPS `APP_URL`. Local HTTP is not production HTTPS acceptance. Keep the existing APP_KEY, credentials, registry identities and saved histories.

If configuration is cached, clear/rebuild it after reviewing the intended environment settings; restart long-running application processes as needed. Then run the read-only demo preflight:

```powershell
php artisan deployment:check --demo --no-interaction
```

The demo checks enforce debug suppression and compatible session settings in addition to the existing local checks. They do not certify a browser session, operational readiness or a payroll proof. Production checks remain separate and cannot be bypassed with `--demo`. Rehearse a benign validation error, forbidden/missing-page response and session behavior on fictional data before sharing the screen; detailed failures remain in server-side logs rather than the shared response.

Show the Viewer reports workspace, Officer computation/submission, independent Approver review/finalization, register/XLSX and the generation/publication lifecycle. Show Admin audit/settings and user assignment to predefined roles. Role definitions/grants are maintained in repository code; the seeded `roles.manage` permission has no role/permission editor.

For network integrity, show the versioned registration's actual status and a fresh verification result. Claim live proof only with actual CONFIRMED registration and fresh MATCH. QUEUED/RECONCILING, an unavailable check, local simulation and the **Node connected** header badge describe different states and do not establish that proof. Preserve the local journal and original target identity when recovering an uncertain submission. The isolated tests demonstrate MISMATCH without changing real payroll; live-network and human browser acceptance remain separately recorded gates.

Documented remaining decisions include adopted salary/contribution/tax tables, allowance taxability, coverage classifications, paid-day/leave rules, withholding/annualization, and the meaning/authority of LBP and GABE deductions. Participant task times, screen-reader acceptance, production-scale load, and production recovery acceptance remain outstanding.
