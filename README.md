# LGU Payroll Management System

Browser-based payroll prototype with planned blockchain integrity verification.
Phase 0 establishes the application and development environment only.

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
The initial screen has no payroll data or user actions.

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

## Verification

Run inside `application/`:

```powershell
composer test
vendor/bin/pint --test
npm.cmd run build
php artisan migrate:status
php artisan app:check-foundation
```

PHPUnit defaults to in-memory SQLite, but inherited database environment variables
can override those defaults. Run tests in a shell without inherited DB_* settings;
see the unresolved isolation finding in [the Phase 0 audit](docs/PHASE_0_AUDIT.md).
The readiness command separately verifies the configured MySQL connection and
base tables. `/up` indicates application boot, not database readiness.

Use `php artisan serve` with built assets on Windows. For frontend editing, run
`npm.cmd run dev` in a second terminal. Authentication and role restrictions are
reserved for Phase 1; this foundation must not hold confidential employee data.
`composer dev` also runs the HTTP and Vite servers together; it excludes the
default Pail log process, which requires an extension unavailable on Windows.

See [development progress](docs/DEVELOPMENT_PROGRESS.md),
[requirements](docs/REQUIREMENTS_STATUS.md), [decisions](docs/DECISIONS.md), and
[testing status](docs/TESTING_STATUS.md).
