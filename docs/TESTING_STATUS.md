# Testing status

Updated: 2026-10-25. Phase 0 acceptance checks passed.

## Automated checks

`application/tests/Feature/FoundationTest.php` covers:

1. Home page renders the foundation screen.
2. Application boot health endpoint responds.
3. Base migrations support persistence of a fictional factory record.
4. Readiness succeeds with the base schema.
5. Readiness fails when required base tables are missing.
6. Readiness fails cleanly when the database is unavailable.

These checks use in-memory SQLite with RefreshDatabase. The home-page feature test
disables Vite lookup; a separate built-asset HTTP check must verify real assets.

## Execution log

| Check | Result |
| --- | --- |
| Composer dependency installation and platform compatibility | Passed: Laravel 13.38.0 installed; `composer check-platform-reqs` succeeded |
| Composer manifest and lock validation | Passed: `composer validate --strict` |
| PHPUnit suite (`composer test`) | Passed: 6 tests, 14 assertions; final run 1.43 seconds |
| PHP formatting (`php vendor/laravel/pint/builds/pint --test`) | Passed for application and, separately, `../scripts` |
| npm dependency installation and production asset build | Passed: 87 packages; `npm.cmd run build` with Vite 7.3.7 |
| MySQL migrations and migration status | Passed: users, cache, and jobs migrations all reported Ran |
| MySQL readiness command | Passed before and after database restart |
| MySQL persistence smoke check (`php ../scripts/verify-mysql.php`) | Passed: fictional account write/read and rollback; no account retained |
| HTTP startup, health, and compiled local assets | Passed: `/`, `/up`, compiled CSS and JavaScript all returned HTTP 200 |
| External/development asset references on live page | Passed: no Google/Bunny font CDN, Tailwind CDN, or Vite dev client references |
| Local MySQL helper repeat start and stop/restart | Passed: existing instance recognized, clean shutdown, restart, readiness preserved |

The initial sandboxed npm attempt was interrupted after failing to complete;
the installation succeeded outside the sandbox. npm reported an unapproved
optional esbuild install script; the production build succeeded without enabling
additional package lifecycle scripts.

The scaffold initially ran its automatic base migration step. A subsequent
explicit migration check found no pending migrations on the configured MySQL
database. The smoke script explicitly requires the MySQL driver, so SQLite
coverage is not being presented as MySQL validation.

No test is reported as passed before execution. There were no existing application
tests or features at initial inspection.

## Not yet verified

Production deployment, full offline office-network operation, visual browser review,
backups/recovery, and all Phase 1-13 workflows. Payroll formulas and regulatory
procedures require validation before their implementation and activation.

## Audit and commit review

See [PHASE_0_AUDIT.md](PHASE_0_AUDIT.md) for the unresolved findings and missing
coverage. A configuration-only probe confirmed inherited database variables can
override PHPUnit's defaults. For commit validation, the test command explicitly
pins APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, and an empty
DB_URL in its own process; this precaution does not fix the committed test setup.

The pre-commit run passed: 6 tests and 14 assertions (1.34 seconds), MySQL
readiness and transactional persistence smoke check, Pint for application and
scripts, Vite production build, strict Composer validation, and platform checks.
The candidate-file scan found no private-key/token/debugging patterns or matches
for the generated local database passwords and application key. Git ignore checks
exclude local credentials, database files, installed dependencies, and built assets.
The first staged whitespace check flagged three blank lines in the unused Laravel
starter view; trailing spaces were removed and the subsequent check passed.
