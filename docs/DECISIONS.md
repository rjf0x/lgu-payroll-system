# Decisions

## 2026-10-25 - Phase 0 architecture

- Adopt the master prompt's provisional Laravel / Blade / Tailwind CSS / MySQL
  stack because no application exists. Laravel 13 supports the installed PHP;
  exact resolved dependencies are captured in Composer and npm lockfiles.
- Keep the conventional Laravel tree in `application/`, requirements/checkpoints
  in `docs/`, and development helpers in `scripts/`. Preserve the master prompt.
- Use Asia/Manila as the application timezone. Currency calculations and rounding
  are deferred to the authorized payroll calculation phase.
- Compile Tailwind/Vite assets locally and use system fonts. Normal operation
  must not depend on a public CDN; dependency installation requires network access.
- Use an isolated MySQL 8.4 instance at 127.0.0.1:3307 for this Windows development
  environment. Keep its data and generated credentials in ignored `.local/`.
  The application database account has privileges only on `lgu_payroll`.
  No global database or Windows service is changed. This is a development helper,
  not the production deployment strategy.
- Retain Laravel's base users/session, cache, and queue migrations. The user model
  is scaffolding; authentication and roles are not Phase 0 features.
- Use PHPUnit with RefreshDatabase and in-memory SQLite for isolated automated
  checks, then separately validate migrations/readiness and persistence on MySQL.
- Keep `composer dev` compatible with Windows by starting only HTTP and Vite
  processes. Exclude the default Pail process, whose PCNTL requirement is not
  supported by this PHP installation, and defer the unused queue worker.
- Laravel's minimum PHP requirement is 8.3, but this resolved dependency lock uses
  Symfony 8 and requires PHP 8.4+. The verified runtime is PHP 8.5.10.
- Keep database readiness in the CLI `app:check-foundation` command; Laravel's
  `/up` endpoint indicates application boot only. Failure output must not disclose
  connection credentials or internal database exception details.
- Keep `.env`, build output, installed dependencies, database files, and local
  runtime state out of version control. `.env.example` contains no credentials.
- Do not infer approved payroll formulas from preliminary reports. Track the
  unresolved business and deployment rules explicitly in REQUIREMENTS_STATUS.md.

References: [Laravel 13 upgrade guide](https://laravel.com/framework/docs/13.x/upgrade),
[Laravel database testing](https://laravel.com/framework/docs/13.x/database-testing),
[MySQL data-directory initialization](https://dev.mysql.com/doc/refman/8.4/en/windows-initialize-data-directory.html).

## 2026-10-25 - Phase 1 access control

- Use Laravel's existing session guard, password hashing, validation, CSRF middleware,
  and gates; no authentication framework or public registration is needed.
- Assign one backed-enum role per user, constrained by the database. Existing and
  newly constructed accounts default to Viewer. Administrator does not implicitly
  inherit payroll preparation or approval privileges; detailed payroll actions
  remain for their authorized phases.
- Restrict account management to active administrators. Retain accounts through
  deactivation rather than deletion. Prevent changing one's own administrator role
  or active status. Other administrators can reset passwords and manage access.
- Increment an authentication version atomically on every account edit. Protected
  requests reject inactive accounts or stale versions. A self-edit keeps the
  current, rotated session; all other sessions require a new login. Do not enable
  persistent remember-me login in this phase.
- Normalize emails to lowercase; enforce database uniqueness and input validation.
  Require 12-character passwords with uppercase, lowercase, and numbers for new
  accounts/resets. Preserve an existing password when the edit field is blank.
- Apply five failed login attempts per email/IP per minute, plus a route limit of
  20 submissions per IP per minute. Use generic credential errors for missing,
  incorrect, or inactive accounts.
- Provision the first administrator interactively with hidden password entry.
  The command refuses reuse when any administrator exists. Remove Laravel's
  scaffold account seeding to avoid enabling known credentials.
- Resolve the audited test-isolation issue before adding authentication database
  tests: pin both `$_SERVER` and environment values and reject unsafe resolved
  configuration before RefreshDatabase. MySQL smoke tests use rollback rather than
  destructive migrations. Other Phase 0 setup findings remain outside this phase.

## 2026-10-25 - Phase 1 audit

- Reclassify Phase 1 as Implemented but needs validation: passing normal-path tests
  does not resolve the five defects recorded in PHASE_1_AUDIT.md.
- Keep the audit separate from implementation. Reproduction probes stay in ignored
  local storage; no authentication fixes or Phase 2 features were applied.
- Preserve the existing phase sequence; recommend resolving Phase 1 findings and
  adding permanent regression coverage before employee-management work.

## 2026-10-25 - Phase 1 corrections

- Resolve only Phase 1 findings. Keep schema, roles, modules, and phase sequence
  unchanged; no employee-management features are authorized.
- Lock the actor and target user rows in ID order for writes, then authorize the
  fresh actor and check its authentication version inside the transaction. An
  actor cannot revoke itself under the existing validated rules; reciprocal edits
  serialize and preserve an active administrator. Account creation also locks
  and rechecks the actor. Retry database deadlocks up to three attempts.
- Catch unique-constraint conflicts outside the transaction and report an email
  validation error. Failed edits roll back passwords and authentication versions;
  session rotation happens only after a successful commit.
- Keep bcrypt and apply a shared input rule to login, account edits/creation, and
  initial provisioning: at most 72 bytes and no null characters. Do not truncate.
  Hash literal user input explicitly at account-write boundaries, including text
  that resembles an encoded hash. Existing overlong credentials need an admin
  reset; stored hashes do not expose their original length.
- Render only scalar/string rejected inputs in form attributes, with safe role
  and status fallbacks. Preserve validation errors across real session cookies.
- Serialize first-administrator provisioning using the configured shared cache
  lock and recheck existence after interactive input. The normal database cache
  is shared across local processes; array cache remains test-only.
- Add permanent HTTP regression coverage and a MySQL two-process concurrency
  verification script. No destructive migrations are used for MySQL checks; only
  randomly named fictional accounts created by the script are removed.
