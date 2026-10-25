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
