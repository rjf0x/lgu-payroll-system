# LGU Payroll Management System

Laravel 13 / PHP 8.5 application for employee records, approved manual payroll inputs, monthly payroll, independent approval, registers, payslips, audit records, and Hyperledger Besu hash verification. The interface uses Blade and Tailwind 4.

The current release is prepared for local review and mock defense. Production hosting, HTTPS validation, backup restore acceptance, payroll-policy approval, and human accessibility/usability acceptance remain open. See [development progress](docs/development-plan.md) and [UI/UX progress](docs/ui-ux-improvement-plan.md).

## Local setup

Requirements: PHP 8.5 with PDO MySQL/SQLite, intl, gd, mbstring and OpenSSL; Composer; Node.js/npm compatible with the locked Vite version; MySQL for development; Docker Desktop for the optional local Besu network.

```powershell
composer install
npm ci
Copy-Item .env.example .env
php artisan key:generate --no-interaction
```

Configure the local database in `.env` before migrating. On a new local demonstration database only:

```powershell
php artisan migrate --seed --no-interaction
php artisan db:seed --class=DemoPayrollWorkflowSeeder --no-interaction
npm run build
php artisan serve --no-interaction
```

Demo accounts are `admin`, `officer`, `approver`, and `viewer`, with development-only password `password`. Default seeding is blocked in production. The demo scenarios are opt-in, reuse fictional employees, and must never be used as adopted statutory or salary data.

For real local blockchain anchoring, follow [Besu setup](docs/blockchain-anchoring.md). The setup generates ignored local keys and deploys the contract; existing simulated anchors remain simulated.

## Verification

```powershell
php artisan test --compact
php vendor/bin/pint --dirty --format agent
npm run build
php artisan deployment:check --no-interaction
```

Normal tests require in-memory SQLite or a database with the `lgu_payroll_qa_` prefix. MySQL contention and live Besu tests are opt-in; see the development plan and blockchain guide. The preflight command is read-only. Its local mode checks the PHP runtime, configured database migrations, application key presence, writable directories, compiled assets, and absence of Vite's development marker. It does not prove production readiness.

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
5. For the first installation with an empty users table, temporarily provide INITIAL_ADMIN_USERNAME, INITIAL_ADMIN_EMAIL, and INITIAL_ADMIN_PASSWORD via the environment. The password must contain at least 16 characters, mixed case, digits and symbols. Run `php artisan db:seed --class=ProductionDatabaseSeeder --force --no-interaction`. Existing accounts are never overwritten. Only roles, permissions, system identity defaults, employment types, and one administrator are created; no employees, salary schedules or statutory amounts are seeded.
6. Remove the INITIAL_ADMIN_PASSWORD secret after bootstrap and rebuild configuration caches. Configure organization identity and authorized staff through the interface. Import only client-approved reference data and payroll policies.
7. Configure Besu's actual chain ID, contract and public sender addresses, node RPC and Web3Signer URLs. Keep signing keys in managed infrastructure. The local four-container stack and raw development key are not a production deployment design. Restrict and authenticate RPC/signing access; test hash anchoring and verification in staging.
8. Install the report worker below, then warm caches with `php artisan optimize --no-interaction`. Run `php artisan deployment:check --production --no-interaction`. Resolve every failure; the final message also identifies manual checks the command cannot prove.
9. Verify login for each role, inactive-account rejection, independent payroll approval, an authorized report export, printable payslips, worker health, HTTPS redirects/cookies, and Besu evidence. Approve the release only after the backup restore drill and client payroll-policy acceptance. No external deployment is performed by this repository's release builder.

The supplied preflight checks the repository's database-backed deployment baseline. A different session/cache/queue architecture needs a corresponding reviewed configuration rather than treating the command as a universal hosting validator.

## Reports and background workers

CSV registers stream on demand. Export history records the request and scope; it is not an immutable archive of the downloaded file. CSV text is neutralized against spreadsheet formulas. Department summaries and payslips have browser-print layouts; the wide register requests A3 landscape paper.

`QUEUE_PAYSLIP_GENERATION=true` queues payslip generation on `reports`; false retains synchronous behavior for local demonstrations. The endpoint confirms dispatch, and users refresh to see generated payslips. Jobs recheck the requester's active status/permission and the run state, and generation is idempotent under a run lock. Generation never publishes payslips automatically.

```bash
php artisan queue:work database --queue=reports,default --sleep=3 --tries=3 --timeout=60 --max-time=3600
php artisan queue:failed
```

The database retry reservation is 90 seconds, longer than the 60-second worker timeout. Review `config/deployment/reports-worker.conf` for a Linux Supervisor example and replace its user, paths and logging destinations for the selected host. Monitor failed jobs and worker backlog; reconcile failures before retrying. Restart workers after a release on a conventional server. Managed platforms may manage that restart themselves.

No periodic payroll mutations are scheduled. If the selected host enables Laravel scheduling, use a single coordinated scheduler and inspect `php artisan schedule:list --no-interaction` before enabling `schedule:run` every minute. Backups are a separate host/database responsibility; an idle Laravel scheduler does not mean a backup exists.

## Backups, restore and rollback acceptance

Before production, the operator must record an approved backup owner, encrypted destination, retention, recovery point objective and recovery time objective. Configure automated MySQL backups using a dedicated account or managed snapshots. Protect configuration/APP_KEY, private files and Besu/signing recovery material separately; never put plaintext passwords or signing keys in this source release or a public bucket.

A release is not operationally accepted until a backup has been restored to an isolated database, foreign-key integrity and expected row counts have been checked, staff can log in, historical registers/payslips are available, and a finalized payroll still verifies against its original chain. Record the backup identifier, restore time and reviewer. Never demonstrate tampering by changing live finalized payroll. No backup or restore drill is claimed by the local preflight command.

Keep the preceding application release and its lockfiles. Use backward-compatible migrations so application traffic can return to the earlier release when appropriate. Restore a database only under an approved recovery procedure; an application rollback does not undo database writes or blockchain transactions. Keep old chain, contract and signer references available for historical verification.

## Mock-defense rehearsal

Use a separate demonstration environment. Show the Viewer reports workspace, Officer computation/submission, independent Approver review and finalization, register/CSV, generated and published payslips, and Besu MATCH verification. Show the Admin audit/settings screens and explain the distinction between application audit records and independently operated blockchain evidence. The isolated tests demonstrate MISMATCH; real payroll remains unchanged.

Documented remaining decisions include adopted salary/contribution/tax tables, allowance taxability, coverage classifications, paid-day/leave rules, withholding/annualization, and the meaning/authority of LBP and GABE deductions. Participant task times, screen-reader acceptance, production-scale load, and production recovery acceptance remain outstanding.
