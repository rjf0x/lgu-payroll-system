# Phase 8 audit - Payroll Configuration

Date: 2026-10-26 (Asia/Manila). Original audit scope: uncommitted Phase 8 implementation,
its parameter maintenance/activation paths, statutory integration, history and tests.
No application fixes, permanent-test changes, migrations or Phase 9 work during
the original audit. Its evidence below is historical; see the correction follow-up.

Post-correction review: [PHASE_8_REAUDIT.md](PHASE_8_REAUDIT.md) confirms no new
Phase 8 defect in the exercised paths and records the current acceptance limits.

## Correction follow-up - 2026-10-26

F1-F3 are corrected in the current working tree under the user's instruction to
review and fix only Phase 8. Client acceptance remains pending; real payroll
remains disabled. No Phase 9 work, schema change, dependency or commit was added.

- **F1:** At consumption, contribution references become links only when they are
  valid HTTP(S) URLs. All other references render as escaped plain text, including
  existing saved values. Arbitrary document references remain valid input. Twelve
  HTTP rendering cases exercise unsafe schemes, control characters, HTML input,
  plain references, HTTP(S) and all three payroll-reading roles. No browser script
  execution or visual acceptance is claimed.
- **F2:** Fingerprints recursively sort associative keys while preserving list
  order and scalar types. Existing fingerprints are supported by comparing the
  immutable creation snapshot/reason, including legacy UTC dates, rather than
  edited draft values. Original hashes, events and drafts are not rewritten by
  retries. Tests cover reordered fields, changed-payload rejection and retries
  against legacy records after draft edits. MySQL JSON key ordering is exercised
  in all six category/data-use scopes using rollback-only fixtures.
- **F3:** New creation/revision/activation events store `effective_from` as `Y-m-d`.
  Actual activation timestamps remain UTC instants. History shows a readable
  effective date for new and legacy events. Legacy UTC values are interpreted
  using the configured application timezone (Asia/Manila); their raw preserved
  JSON remains available and unchanged. New-date tests cover Asia/Manila, UTC
  and Pacific/Auckland.

The regression selection first reproduced the defects (15 failed, 2 passed),
then passed all 17 cases / 165 assertions after correction. The full suite passed
396 tests / 4031 assertions. Configuration, statutory, core and manual-input MySQL
checks passed with rollback; application/verifier Pint checks, asset build,
Composer validation, database readiness and twelve-migration status passed.
See TESTING_STATUS.md for the final isolation check and environment restoration.
Permanent Phase 8 coverage is now 58 cases / 529 assertions, preserving all 338
prior cases. The original ignored diagnostics assert former defects and are no
longer acceptance tests. Browser/concurrency/recovery/performance and actual
client/finalized-payroll acceptance gaps below remain.

## Original acceptance assessment

Status: **Implemented but needs validation**. The configuration workflow is present
and its existing tests pass, but one medium security finding and two low findings
remain open. Do not treat the passing suite as full acceptance.

| Master-prompt criterion | Assessment | Evidence and limit |
| --- | --- | --- |
| Approved new rules can be activated | Passed for the supported operator-recorded workflow; client acceptance pending | Administrators can activate validated drafts with references, reason and confirmation; external data use additionally requires client-validation confirmation. Schema/bounds are checked. Actual external approval is not independently verified. F1 allows an unsafe source reference through the same workflow. |
| Previous rule versions remain available | Passed for retention, with audit representation/retry issues | Activated rows and event snapshots remain available and ordinary edits/deletion are blocked. Dated selection and old result preservation tests pass. F2 rejects a semantically unchanged creation retry; F3 makes a date-only snapshot appear as the previous UTC calendar date. |
| Historical finalized payroll is not silently recalculated | Partially verifiable at this phase | Activation does not rewrite existing core/statutory snapshots; explicit new reviews retain their selected parameters. Actual finalized payroll does not exist until Phase 9, so the complete finalized lifecycle cannot yet be accepted or tested. |

All six Phase 8 task areas are implemented within the documented current formula,
monthly-date and operator-authorization conventions. There is no new confirmed
effective-date selection error, unauthorized direct mutation, lost version or
silent recalculation in the exercised supported paths. This does not establish
concurrent/recovery behavior or production payroll readiness.

## Original confirmed findings (F1-F3 now corrected)

### F1 - Medium: maintained source references can become executable links

Locations:

- `application/app/Http/Requests/SavePayrollRuleVersionRequest.php:21` accepts
  source references as arbitrary required UTF-8 strings.
- `application/resources/views/statutory-previews/show.blade.php:23` places that
  value directly into an anchor's `href`.

Reproduction through HTTP:

1. As Administrator, create a valid Permanent / Demonstration configuration with
   GSIS source `javascript:alert(document.domain)` and otherwise valid parameters.
2. Activate it through the normal confirmation/reference workflow.
3. As Payroll Officer, open a matching employee's statutory reference review.
4. The response contains `href="javascript:alert(document.domain)"`. The tested
   response has no Content-Security-Policy header blocking that navigation.

The stored-input-to-executable-URL rendering is confirmed. Script execution on
navigation follows the [HTML Standard's javascript-URL processing](https://html.spec.whatwg.org/multipage/browsing-the-web.html#the-javascript:-url-special-case).
HTML attribute escaping does not turn that scheme into inert text. A live browser
click was not executed in this audit, so browser exploitation is inferred from
the rendered markup and specified behavior, not claimed as a measured browser run.

This requires configuration-authoring privilege and a payroll user's click;
severity is medium. It is more than cosmetic because Administrators intentionally
lack direct employee/payroll-record access, while injected script would run in the
payroll user's page/session context. The real-payroll calculation block does not
make the link safe. The harmless diagnostic only uses `alert`; it does not read
payroll data or send information elsewhere.

Recommended correction: treat document references as plain text unless they are
validated safe HTTP(S) links, and enforce the same boundary at consumption of
existing stored references. Add a stored-reference rendering regression. No fix
was made during this audit.

### F2 - Low: creation fingerprint depends on associative parameter key order

Location: `application/app/Services/PayrollRuleVersionService.php:111`.

Create a draft, then repeat the same `submission_key`, operator and parameter
values while reversing the associative order of `parameters.contributions`.
The request is rejected with a `submission_key` validation error rather than
reopening the existing draft. The fingerprint directly JSON-encodes the normalized
array without canonicalizing object keys.

The probe confirms one version and one event remain, with attributes unchanged;
there is no overwrite or duplicate persistence. Severity is low because ordinary
same-order browser retries still work. JSON objects/form parameter maps with the
same key/value pairs should not become different creation requests merely due to
member order. The existing retry test only repeats the identical array order.

Recommended correction: canonicalize associative keys before hashing while
preserving list order and scalar types, then add an equivalent-payload retry case.

### F3 - Low: date-only audit snapshots are serialized as UTC instants

Locations: `application/app/Services/PayrollRuleVersionService.php:80` and
`application/resources/views/payroll-rule-versions/show.blade.php:50`.

With the application's Asia/Manila timezone, a version effective `2026-10-01`
stores/displays this event snapshot value:

```text
effective_from: 2026-09-30T16:00:00.000000Z
```

The version's own date and selection remain `2026-10-01`. The event serializer
receives a Carbon date object from `Model::only()` and encodes its UTC instant;
the history disclosure displays the JSON unchanged. The timestamp represents the
same local midnight, but `effective_from` is a calendar date rather than an
instant. Readers treating the leading UTC date as the configuration date can
misread the audit history as September 30. This is a low audit representation
issue, not evidence of wrong activation/selection or altered payroll amounts.

Recommended correction: serialize this field as `Y-m-d` while retaining timezone-
qualified timestamps for actual event/activation times. Add a non-UTC snapshot
date assertion. No record was changed by the audit.

## Missing or blocked functionality

These are declared limits and client-validation dependencies, not additional bugs:

- Actual approved client rules/examples, category bases and rounding/activation
  policies remain unavailable. External references/confirmation record an operator
  assertion; they do not independently authenticate an approval document. No real
  processing is enabled by configuration activation.
- Maintenance is limited to the existing contribution methods, six-row tax tables
  and two-decimal parameter fractions. Different formula families/structures or
  higher-precision rules are unsupported rather than inferred.
- First-day, calendar review-year and retrospective activation conventions need
  client acceptance. A second-person configuration approval policy is not defined;
  the current prototype permits an authorized Administrator to activate a draft.
  Payroll preparer/approver separation and finalization belong to Phase 9 and
  were not added or counted as Phase 8 defects.
- The actual historical-finalized-payroll criterion cannot be fully verified until
  finalized records exist. Current tests establish no silent rewrite of the
  existing immutable core/statutory results and manual inputs.

## Checks executed

| Check | Result |
| --- | --- |
| `scripts/verify-test-isolation.ps1` | Passed: 379 tests / 3866 assertions, 49.735 seconds, 74 MB; conflicting inherited production/MySQL/DB_URL settings stayed isolated in-memory |
| Ignored HTTP audit diagnostics | Passed as reproductions: 3 tests / 21 assertions. They assert the current defects, not desired corrected behavior |
| `php ../scripts/verify-payroll-rule-versions.php` | Passed on MySQL: six scopes, draft revisions/events, retries, authorization/external confirmation, dated selection, duplicates, preserved versions and rollback |
| `php ../scripts/verify-statutory-previews.php` | Passed on MySQL with transaction-owned fictional configurations, identity binding, exact arithmetic/history, unavailable fault states and rollback |
| `php ../scripts/verify-payroll-calculations.php` | Passed on MySQL with fixture rollback |
| `php artisan migrate:status` | All twelve migrations already Ran; no migration executed |
| Application and configuration/statutory verifier Pint `--test` | Passed |
| Whitespace check | Passed; ordinary LF/CRLF conversion notices |

MySQL was initially stopped, temporarily started for rollback-only verification
and restored afterward. No actual employee/configuration fixture, credential or
database dump was retained. The unsafe-URL probe ran only in isolated SQLite.

Diagnostics are ignored local audit material at
`.local/phase8-audit/Phase8AuditTest.php`. Run from `application/`:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never --filter test_audit_ ../.local/phase8-audit/Phase8AuditTest.php
```

## Test coverage assessment

Permanent Phase 8 coverage remains 41 cases / 364 assertions: 38 configuration
cases and three statutory integration cases, plus all 338 prior tests. It covers
four roles/six scopes, draft visibility, parameter/source requirements, date
boundaries/future/reverse-insertion selection, expired years, required evidence/
confirmation, forged activation fields, stale revisions, ordinary retries,
HTTP/model immutability, history pagination, all three event-persistence rollback
paths, encoding/flash isolation, guest/CSRF/role/session/inactivity checks, selected-
configuration identity changes, unchanged prior snapshots and real-mode blocking.
No coverage percentage is claimed.

Material gaps:

- No scheme-safety regression for administrator-controlled source references or
  browser test of persisted links. Escaping ordinary text is not a URL-safety test.
- No creation retry with reordered associative fields, unlike the existing
  statutory JSON-key-order regression.
- No date-only event snapshot assertion under a non-UTC timezone; current tests
  inspect parameter values and event counts rather than serialized date semantics.
- No simultaneous MySQL activations/edits/statutory saves, lock scheduling,
  source publication, deadlock or connection-loss recovery probes. Sequential
  stale-token checks and injected exceptions do not establish those properties.
- No live browser visual/accessibility/responsive acceptance or office-scale
  capacity measurement. No client-approved actual payroll examples or finalized
  lifecycle acceptance can be claimed.

Recommended order: address F1 before accepting this phase, then correct F2/F3 with
permanent regressions and rerun the workflow/MySQL checks. Keep real processing
blocked until client rules are accepted. Only audit/tracking documents and ignored
diagnostics were added in this audit; no application fix, commit or Phase 9 work.
