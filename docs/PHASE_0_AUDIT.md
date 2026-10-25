# Phase 0 audit

Date: 2026-10-25. Scope: Project Foundation only.

## Acceptance result

The existing local environment passes the three Phase 0 acceptance criteria:
application startup, database connectivity, and initial automated tests.
This does not establish reliable fresh setup or safe test isolation under every
inherited environment configuration.

## Findings and follow-up status

1. **High - Test database isolation was not enforced. Resolved in Phase 1.**
   `application/phpunit.xml` sets database variables without `force="true"`, and
   `FoundationTest` uses `RefreshDatabase`, which runs `migrate:fresh`.
   A configuration-only probe confirmed inherited DB_CONNECTION, DB_DATABASE,
   and DB_URL values survive PHPUnit configuration. Tests can therefore target
   a non-test database. The probe did not connect to or modify any database.
   Phase 1 pins both environment and server values and guards the resolved SQLite
   in-memory configuration before RefreshDatabase runs. The full suite passes
   with conflicting inherited MySQL configuration; see TESTING_STATUS.md.
2. **Medium - Repeated setup rotates the encryption key.**
   `application/composer.json` runs `key:generate` unconditionally in `setup`.
   Rerunning it can invalidate encrypted sessions and encrypted values.
   Generate the key only when missing. Identified by code review; the key was
   not rotated during the audit or commit review.
3. **Medium - Composer setup does not initialize a fresh MySQL database.**
   The `setup` script copies the environment template and migrates without
   creating the database or configuring credentials. The root README's manual
   sequence handles this prerequisite; the Composer entry point is incomplete.
4. **Medium - The local database helper preserves a conflicting DB_URL.**
   `scripts/configure-local-database.php` updates individual DB_* settings without
   clearing or rejecting DB_URL. A configuration-only parser probe confirmed a
   URL overrides those settings, potentially selecting another database.

No application fixes were made during the original audit or commit review. The
isolation fix was subsequently included as a prerequisite in authorized Phase 1.
Findings 2-4 remain unresolved; Phase 1 did not modify those setup paths.

## Coverage

Six PHPUnit tests cover homepage rendering, boot health, persistence, readiness
success, missing tables, and connection failure. They normally run on in-memory
SQLite; MySQL persistence and live HTTP/assets have separate smoke checks.

Missing automated coverage includes inherited environment variables, fresh and
repeated setup, conflicting DB_URL, database-helper failures and port conflicts,
MySQL integration, and live asset delivery. Browser layout/accessibility and
office-network offline operation remain unverified. No coverage percentage was
measured.
