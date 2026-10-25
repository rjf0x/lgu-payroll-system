# LGU Payroll Management System

Browser-based payroll prototype with planned blockchain integrity verification.
Completed through Phase 1: authentication, administrator-managed accounts, four
roles, server authorization, and role-specific navigation.

## Layout

- `application/`: Laravel 13 application (Blade, Tailwind CSS, PHPUnit).
- `docs/`: master prompt, requirements, decisions, and phase checkpoints.
- `scripts/`: local development helpers.
- `.local/`: ignored local MySQL data, configuration, and credentials.

## Requirements

- PHP 8.4.1+ for the locked dependencies, with PDO MySQL, PDO SQLite (tests), BCMath, DOM, Fileinfo, Mbstring,
  OpenSSL, Session, Tokenizer, XML, and the standard Laravel extensions.
- Composer 2, Node.js 22.12+ (or a supported newer release), npm.
- MySQL 8.4. The Windows helper can discover a Laragon installation; otherwise
  pass `-MySqlBin 'C:\path\to\mysql\bin'`.

## Windows development setup

Run from this project directory:

```powershell
cd application
composer install
Copy-Item .env.example .env # first setup only; preserve an existing .env
php artisan key:generate
cd ..
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/local-mysql.ps1
cd application
php artisan migrate
npm.cmd ci
npm.cmd run build
php artisan app:check-foundation
php ../scripts/verify-mysql.php
php artisan serve --host=127.0.0.1 --port=8000
```

Open <http://127.0.0.1:8000>. Built assets are served locally; the application
does not require a CDN or internet access after dependencies are installed.
Guests are redirected to sign in. No payroll processing is implemented yet.

The MySQL helper starts an isolated development server on `127.0.0.1:3307`,
creates `lgu_payroll`, generates local passwords, and configures `application/.env`.
It preserves an existing local database and never installs a Windows service.
Stop it when finished:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/local-mysql.ps1 -Action Stop
```

For an existing MySQL server instead, create an empty development database and a
user with access only to that database. Set the `DB_*` values in
`application/.env`, skip the local MySQL helper, and run the remaining commands.
Do not commit `.env`, database files, or local credentials.

## User access

Run inside `application/` after migrations:

```powershell
php artisan app:create-admin
```

Enter the first administrator's name, email, password, and password confirmation.
Password entry is hidden. Use at least 12 characters with uppercase, lowercase,
and numbers, at most 72 bytes, and no null characters. Some characters use more
than one byte. The command refuses reuse once an administrator exists. There is no
public registration or default account/password; `db:seed` creates no accounts.

Sign in at <http://127.0.0.1:8000/login>. Administrators can create/edit accounts,
assign Administrator, Payroll Officer, Approver, or Viewer, reset passwords, and
activate/deactivate access under **User accounts**. Leave an edit password blank
to preserve it. Administrators cannot deactivate or demote themselves.

If an account was created with a password longer than 72 bytes before the Phase 1
fix, an administrator should reset it to a supported password. Such full-length
passwords are now rejected; existing hashes cannot reveal their original length.

Each role has its own protected workspace and navigation. Account edits revoke
previous sessions; the current session survives a self-edit. Disabled users cannot
sign in. There is no remember-me login. Payroll modules remain for later phases.

## Verification

Run inside `application/`:

```powershell
composer test
php vendor/laravel/pint/builds/pint --test
npm.cmd run build
php artisan migrate:status
php artisan app:check-foundation
php ../scripts/verify-mysql.php
php ../scripts/verify-auth.php
php ../scripts/verify-auth-concurrency.php
```

PHPUnit pins in-memory SQLite in both server and environment variables. The base
test case refuses unsafe resolved configuration before destructive migrations.
Verify isolation from the project root:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify-test-isolation.ps1
```

This runs the full suite with conflicting inherited production/MySQL settings.
The ordinary MySQL smoke checks use transactions and roll back their fictional
accounts. The concurrency check temporarily commits four fictional accounts so
separate processes can access them, then deletes only the accounts it created.
It verifies reciprocal administrator deactivation/demotion without changing schema.
The readiness command separately verifies the configured MySQL connection and
base tables. `/up` indicates application boot, not database readiness.

Use `php artisan serve` with built assets on Windows. For frontend editing, run
`npm.cmd run dev` in a second terminal. `composer dev` also runs the HTTP and Vite
servers together; it excludes the default Pail log process, which requires an
extension unavailable on Windows.

The three remaining setup findings in [the Phase 0 audit](docs/PHASE_0_AUDIT.md)
are still open. Use the documented manual setup sequence; preserve existing keys
and ensure no conflicting DB_URL is configured when using the local MySQL helper.

See [development progress](docs/DEVELOPMENT_PROGRESS.md),
[requirements](docs/REQUIREMENTS_STATUS.md), [decisions](docs/DECISIONS.md), and
[testing status](docs/TESTING_STATUS.md).
