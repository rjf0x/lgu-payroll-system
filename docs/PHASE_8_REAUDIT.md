# Phase 8 re-audit - Payroll Configuration

Date: 2026-10-26 (Asia/Manila). Scope: current uncommitted Phase 8 implementation
after F1-F3 corrections, against `master-prompt.md` tasks and acceptance criteria.
This review changes audit/tracking documents and ignored diagnostics only. No
application fix, permanent-test change, migration, commit or Phase 9 work.

## Result

**Implemented but needs validation. No new confirmed Phase 8 bug in the exercised
supported paths.** The original F1-F3 findings remain corrected. This is acceptance
of tested prototype behavior, not certification of actual payroll rules, browser
behavior, concurrency or finalized records.

| Acceptance criterion | Assessment | Evidence and limit |
| --- | --- | --- |
| Approved new rules can be activated | Passed for supported operator-recorded activation; actual client acceptance blocked | Administrator-only writes recheck active role/session under lock; draft parameters are validated again at activation. Current revision, evidence references, reason and authorization confirmation are required; external data use also requires client-validation confirmation. Approval references record operator assertions and are not independently authenticated. Actual approved rules/examples are unavailable. |
| Previous rule versions remain available | Passed for tested retention and retrieval | Activated versions and events reject ordinary model/HTTP mutation/deletion. Dated/category/data-use selection, future versions, historical retrieval, event pagination, canonical/legacy retries and retained prior results pass. Raw legacy history is preserved. This does not promise resistance to privileged direct database modification. |
| Historical finalized payroll is not silently recalculated | Partially verifiable; actual finalized lifecycle pending | Configuration activation does not write existing core/statutory snapshots or manual inputs. Explicit later statutory saves create separate revisions. These preservation paths pass. Finalized records do not exist yet; their complete lifecycle belongs to Phase 9 and cannot be claimed as tested here. |

All six current task areas are present: supported parameter maintenance, effective
dates, version preservation, restricted changes, authorized activation and atomic
change-event recording. No supported-path loss of history, unauthorized mutation,
wrong effective selection or silent recalculation was confirmed.

## Bug review and correction verification

- **Original F1, unsafe source links: remains corrected.** The rendering boundary
  at `resources/views/statutory-previews/show.blade.php:25` allows valid HTTP(S)
  links only. Other sources are escaped plain text. The twelve permanent stored-
  reference cases pass, including unsafe schemes, control characters, HTML input,
  ordinary document identifiers and three payroll-reading roles. No live browser
  script execution or rendering acceptance was performed.
- **Original F2, key-order-sensitive retries: remains corrected.** Recursive
  associative canonicalization retains list order/types. The service's original-
  creation matching at `app/Services/PayrollRuleVersionService.php:116` supports
  legacy fingerprints without rewriting drafts/hashes/events. Permanent and MySQL
  tests pass, including reordered retries after edits and changed-payload rejection.
  Additional isolated probes confirm that changing the reason or administrator
  cannot reuse another original creation; missing/invalid legacy history rejects
  a fallback instead of modifying a record or raising a server error.
- **Original F3, UTC calendar-date ambiguity: remains corrected.** New events save
  `Y-m-d` at `app/Services/PayrollRuleVersionService.php:83`; actual timestamps stay
  instants. `PayrollRuleEvent::effectiveDate()` reads legacy values without mutation.
  Creation/revision/activation cases pass in Asia/Manila, UTC and Pacific/Auckland.
  Legacy decoding assumes the configured application timezone remains appropriate
  for the original events; migrating legacy history between timezone conventions
  has not been tested.

No further confirmed bug is added by this audit. Earlier unrelated Phase 2/3
findings remain open and were not fixed or relabeled as Phase 8 defects.

## Missing or blocked functionality

- Actual client-approved contribution/withholding examples, category applicability,
  bases, rounding and activation policy remain unavailable. External activation
  confirmation cannot replace that evidence. Real payroll processing remains
  blocked server-side, and calculated references retain `production_ready=false`.
- Supported maintenance covers the current three contribution methods, six-row
  tax tables and two-decimal parameter fractions. Other formulas, more brackets,
  greater rate precision and mid-month changes are unsupported. Their necessity
  must be established from client rules; they are not silently approximated.
- First-day dates, per-calendar-year review, retrospective activation and the
  current Administrator activation policy still need client acceptance. A separate
  configuration approver is not defined by the current requirements or prototype.
- Submission/approval/finalization, finalized documents and blockchain behavior
  are later phases. Their absence limits end-to-end acceptance; it is not authority
  to add them during this audit.

## Checks executed in this re-audit

| Check | Result |
| --- | --- |
| `scripts/verify-test-isolation.ps1` | Passed: 396 tests / 4031 assertions, 54.873 seconds, 72 MB; conflicting inherited production/MySQL/DB_URL settings remained isolated in-memory |
| Additional ignored audit probes | Passed: 3 tests / 38 assertions; creation actor/reason identity, invalid/missing legacy history, forged nested server metadata and list-order rejection |
| `php ../scripts/verify-payroll-rule-versions.php` | Passed on MySQL: six category/data-use scopes, revisions/events, canonical/legacy retries, unchanged history, authorization/confirmation, activation, calendar boundaries, retained versions, duplicate prevention, arithmetic, production block and rollback |
| `php ../scripts/verify-statutory-previews.php` | Passed on MySQL: owned fictional configurations, identity binding, preserved snapshots/inputs, exact arithmetic, unavailable states, real block and rollback |
| `php ../scripts/verify-payroll-calculations.php` | Passed on MySQL: preserved inputs/runs, prepared item/aggregate results and rollback |
| `php artisan migrate:status` | All twelve migrations already Ran; no migration executed |
| Application Pint `--test` and configuration/statutory verifier Pint `--test` | Passed |
| `git diff --check` | Passed; ordinary LF/CRLF notices |

MySQL was initially stopped, temporarily started for the rollback-only checks and
stopped afterward. No operator configuration, actual employee fixture, payroll
snapshot, credential or database dump was retained or changed by diagnostics.
The asset build was not rerun in this read-only application review; its last
successful execution is recorded in the correction section of TESTING_STATUS.md.

Additional diagnostic source is ignored at
`.local/phase8-reaudit/Phase8ReauditTest.php`. From `application/`:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never --filter test_reaudit_ ../.local/phase8-reaudit/Phase8ReauditTest.php
```

## Coverage and remaining gaps

Permanent Phase 8 coverage remains **58 cases / 529 assertions**: 43 configuration
and 15 statutory integration cases, alongside all 338 prior cases. The additional
three audit cases are separate and do not inflate the permanent suite count. No
coverage percentage was measured.

Covered: four roles/six scopes, restricted draft visibility, guest/CSRF/current-
actor checks, validation and activation evidence, date selection, stale revisions,
ordinary/canonical/legacy retries, source rendering, date-only history, retained
snapshots, event pagination, model/HTTP immutability, three event-failure rollback
paths, configuration identity changes, rejected-input isolation and real blocking.

Still unverified:

- Simultaneous MySQL activations/edits/statutory saves, lock scheduling, deadlock
  retries, connection-loss recovery and source publication races. Sequential stale
  requests and injected persistence exceptions do not establish those properties.
- Live browser interactions, source navigation, keyboard/screen-reader behavior,
  responsive layout and visual acceptance. HTTP/DOM checks establish markup only.
- Office-scale data volume, capacity and response times.
- Client-approved expected payroll results and actual finalized-record retention
  across later configuration activation.

Stop at Phase 8. No next-phase implementation is authorized by this audit request.
