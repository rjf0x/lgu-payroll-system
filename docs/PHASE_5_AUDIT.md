# Phase 5 audit - Earnings and Deduction Inputs

Date: 2026-10-25. Scope: Phase 5 only. The original audit and repeat audit were
read-only; the subsequently authorized correction is recorded below. No Phase 6 work.

## Acceptance assessment

Current status: Implemented and tested after correction. The confirmed form-state
isolation defect is fixed with permanent regression coverage. Client-rule and
browser/concurrency acceptance remain unverified as documented below.

| Master-prompt acceptance criterion | Assessment | Evidence |
| --- | --- | --- |
| Each employee can have different allowances and loans | Passed for tested workflows after correction | Independent persistence and rejected-state isolation are now covered for other employees, empty recipient forms and another month for the same employee. |
| Inapplicable allowances are excluded | Passed for entered applicability flags | Filtered applicable allowance list and exact subtotal exclude false flags while retaining excluded values for review. Real category entitlements are not inferred or validated. |
| Draft information persists correctly | Passed tested save/edit/history/rollback and retry workflows | Exact amounts, preserved revisions and matching-form retry state are tested; the former wrong-form restoration defect is corrected. |

All seven Phase 5 task areas are implemented: allowances, bonuses/additional
earnings, manual loan deductions, attendance fields, authorized manual adjustments,
validation and draft input editing. No other explicitly required Phase 5 workflow
was found absent from the supported prototype.

## Original finding - corrected

**Medium - Rejected input was not scoped to the employee/monthly batch form.**

The following records the pre-correction behavior and source locations; it is not
a description of the current implementation. See correction verification below.

`application/resources/views/payroll-inputs/show.blade.php:48` reads global session
`old()` groups; line 63 does the same for attendance. The `_input_form` marker
identifies only the form type. Neither the old groups nor the marker identifies
the submitted employee, draft entry or batch. The form uses the currently opened
batch's fresh version at line 42.

Reproduced through the HTTP feature pipeline:

1. Save employee B's allowance as `777.77` and loan as `888.88`.
2. Submit employee A's inputs (`100.07` and `200.02`) with a missing change reason.
   Validation rejects the request and flashes A's submitted values.
3. Before following A's validation redirect, open B's input page in the same session.
   B's saved summary remains correct, but B's editable allowance/loan fields contain
   A's rejected `100.07` and `200.02`, alongside B's current batch version.
4. An authorized corrected resubmission to B can save those copied values as B's
   next revision. B's previous revision remains intact.

This is a conditional cross-form restoration defect relevant to multi-tab or
redirect-interleaving requests. An ordinary same-tab redirect may consume the flash
on A's page first. The HTTP sequencing is reproduced; a live browser race was not
tested. No unauthorized write, automatic database overwrite, cross-user session
leak or revision-history mutation was found. B's database values remain unchanged
until an authorized resubmission with confirmation.

Missing behavior: bind rejected edit state to the submitted employee/batch/entry
and restore it only on the matching form. Opening B must use B's own saved/default
fields rather than A's old groups, attendance or metadata. The permanent suite
covers clearing rows after a validation failure on the same form, but not this
different-form sequence.

## Functionality and client-validation limits

- Applicability is manually authorized, not established from verified category
  entitlement rules. Actual allowance amounts/eligibility and external-authority
  practices require client validation. A reference/confirmation is not independent
  proof that the external authority is valid.
- Absence days and undertime minutes have calendar bounds, not validated workday,
  paid-hour, overlap or deduction rules. Adjustment originals are manually entered
  reference values and replacement amounts, not automatically computed originals
  or deltas. Those semantics must be confirmed before calculation.
- Add/remove-line controls depend on JavaScript; existing values can be edited
  without it. Functional browser interaction and visual acceptance remain unverified.
- Current input history for removed coverage is retained and restored after
  re-selection; removed employees are not active covered inputs. Future processing
  must use selected employees and current matching revisions, not every historical
  payload. No calculation consumer is claimed in Phase 5.
- No Phase 6 basic pay/net-pay engine, statutory deduction, bonus tax treatment,
  loan scheduling, supporting upload or internal approval is counted as missing
  Phase 5 functionality. Those are later phases or explicitly excluded scope.

## Checks executed in the original audit

| Check | Result |
| --- | --- |
| Full PHPUnit suite | Passed: 182 tests / 2387 assertions |
| `scripts/verify-test-isolation.ps1` | Passed: 182 tests / 2387 assertions with conflicting inherited production/MySQL settings |
| Application and script Pint `--test` | Passed |
| `node --check resources/js/payroll-inputs.js` | Passed syntax check; no browser behavior claim |
| `php ../scripts/verify-payroll-inputs.php` | Passed against MySQL: categories/modes, independent values, exclusions, exact amounts, edited revisions/originals, coverage refresh and rollback |
| MySQL `migrate:status` | All eight migrations Ran; no migration run by this audit |
| Additional ignored audit probes | 3 cases / 38 assertions: reproduced cross-form restoration; verified rollback after injected batch-update failure and historical revision pagination |
| `git diff --check` | Passed; ordinary Git LF/CRLF notices |

The extra probes are at `.local/phase5-audit/Phase5AuditTest.php`. The original
first probe asserted the defect; after correction it asserts the isolated form
values instead. The permanent regressions are the primary fix evidence. Run from
`application/`:

```powershell
php vendor/phpunit/phpunit/phpunit --colors=never --filter test_audit_ ../.local/phase5-audit/Phase5AuditTest.php
```

## Test coverage assessment

The 34 permanent Phase 5 cases cover employee/category independence, applicability
and exact subtotaling, monetary formats/bounds, attendance month bounds, adjustment
metadata/directions, immutable history, safe rejected input/XSS rendering, clearing
groups, duplicate/stale saves, foreign-entry/revision scoping, role/guest/CSRF and
revoked-actor checks, source revalidation and coverage refresh. All 148 previous
cases remain. No coverage percentage is claimed.

Missing permanent or independent evidence:

- Employee/batch-bound validation retry state, including another employee form
  receiving pending flashed input and cross-month form interleavings.
- Live JavaScript add/remove/max-row behavior and browser form serialization.
- Simultaneous input saves, concurrent source/coverage changes, MySQL deadlock/
  uniqueness races and connection-loss/recovery behavior.
- The injected rollback and 11-revision pagination checks are diagnostics only,
  not permanent regressions; the rollback injection used SQLite rather than a
  real MySQL failure.
- Approved eligibility/authorization/attendance/adjustment examples, representative
  office-scale performance, deployment and backup/recovery.

The project database was initially stopped, started for the rollback-only check
and stopped afterward. All fictional fixtures rolled back. Only audit/tracking
documents and ignored probes were added; application code and permanent tests
were not changed. No commit or Phase 6 implementation was performed. Earlier
Phase 2/3 findings and Phase 4 policy/verification limits remain open.

## Repeat audit before correction - 2026-10-25

Rechecked Phase 5 against the same acceptance criteria. The medium cross-form
rejected-input defect remains reproducible; no additional defect was confirmed.
The phase remains Implemented but needs validation.

- Full PHPUnit rerun: passed, 182 tests / 2387 assertions.
- Existing audit probes: 3 cases / 38 assertions; reproduced the defect and
  reconfirmed atomic rollback and historical pagination.
- Application/script Pint, JavaScript syntax and whitespace checks: passed.

The MySQL smoke and isolation results earlier in this report are from the preceding
audit and were not rerun in this repeat check. The database remained stopped.
Only this audit record was updated; no application, permanent test or Phase 6 work.

## Authorized Phase 5 correction - 2026-10-25

The reported defect is resolved. `ScopePayrollInputErrors` records the actual bound
batch, entry and employee on validation redirect responses, including encoding
failures. No client-supplied identity field is trusted. The input controller/view
restore old values and the error bag only when all three identities match the
current form. Other employee/month forms show their own saved/default inputs.
The generic hidden form-type marker was removed. Same-form empty groups remain
empty after unrelated validation errors. JSON errors do not create restore state.

Seven new permanent cases extend the input suite to 41 cases. They cover another
employee, an unsaved recipient, the same employee in another month, spoofed posted
context, matching-owner values/errors, pre-FormRequest UTF-8 errors, JSON validation
and service-level stale-version validation. All original Phase 5 checks remain.

Verification executed:

- `composer test`: passed, 189 tests / 2472 assertions.
- Updated ignored probes: passed, 3 cases / 38 assertions; B retains its own
  editable values and prior revisions, rollback and history pagination still pass.
- MySQL input smoke check: passed with fixture rollback; eight migrations Ran.
- Application/script Pint, strict Composer validation, Vite production build,
  JavaScript syntax and whitespace checks: passed.

The three boundary regressions failed against the old behavior before the fix.
An initial exception-catching middleware attempt failed matching-owner tests
because Laravel converts exceptions into responses inside the routing pipeline;
the final middleware handles those validation redirect responses correctly.

No schema or salary/input persistence rules changed, and no new feature or Phase 6
work was introduced. Session flash still has its ordinary lifetime; this is not
per-tab caching or indefinite rejected-input retention. Live browser timing and
client-policy/concurrency/deployment limitations remain unverified. The temporary
project database was stopped after verification to restore its initial state.

## Post-correction re-audit - 2026-10-25

Rechecked the corrected implementation against all three Phase 5 acceptance
criteria. Per-employee allowance/loan persistence, manually flagged allowance
exclusion and draft/revision persistence pass the exercised prototype workflows.
The reported cross-form retry defect no longer reproduces. No additional Phase 5
application defect or missing required task was confirmed.

- Full PHPUnit suite: passed, 189 tests / 2472 assertions, including 41 Phase 5
  cases and all 148 earlier cases.
- Corrected audit probes: passed, 3 cases / 38 assertions, reconfirming recipient
  form values, preserved revisions, rollback and historical pagination.
- Application/script Pint, JavaScript syntax and whitespace checks: passed.

The seven permanent correction regressions cover recipient employee/month
boundaries, empty recipient defaults, forged posted identity, owner retries,
UTF-8 validation, JSON errors and service validation. They exercise HTTP sequences,
not live browser scheduling. The MySQL/build/isolation results recorded earlier
are from implementation, original audit or correction checks, not reruns during
this post-correction audit. The project database remained stopped.

Remaining gaps are the documented actual entitlement/attendance/authorization
rules, JavaScript add/remove interaction and visual browser acceptance, simultaneous
MySQL writers and failure/recovery, and office-scale/deployment validation. Later
calculation/approval modules are not missing Phase 5 functionality. Earlier-phase
findings remain open. Only audit/testing records were updated in this re-audit;
no application fixes, permanent tests, schema, commits or Phase 6 work were added.
